<?php

declare(strict_types=1);

namespace Ladecadanse\Utils;

use Closure;

/**
 * La date du contenu vient de l'appelant et non de filemtime() : le cache est réécrit à chaque
 * expiration même sans changement, et un Last-Modified qui avance ferait répondre 200.
 */
final readonly class HttpFileCache
{
    /**
     * @param Closure(string): ?int $contentDate
     */
    public function __construct(
        private string $file,
        private int $ttl,
        private string $contentType,
        private Closure $contentDate,
        private ?string $filename = null,
    ) {
    }

    // à appeler avant bootstrap.php, pour répondre sans session ni connexion à la base
    public function serveIfFresh(): void
    {
        $content = $this->read();

        if ($content === null)
        {
            return;
        }

        $this->serve($content);
    }

    // un cache non inscriptible n'empêche pas de répondre
    public function storeAndServe(string $content): never
    {
        $this->write($content);
        $this->serve($content);
    }

    public function read(): ?string
    {
        $mtime = @filemtime($this->file);

        if ($mtime === false || $mtime + $this->ttl <= time())
        {
            return null;
        }

        $content = @file_get_contents($this->file);

        return $content === false ? null : $content;
    }

    public function write(string $content): bool
    {
        $directory = dirname($this->file);

        if (!is_dir($directory) && !@mkdir($directory, 0o775, true) && !is_dir($directory))
        {
            return false;
        }

        $temporary = $this->file . '.' . getmypid() . '.tmp';

        if (@file_put_contents($temporary, $content) === false)
        {
            return false;
        }

        if (@rename($temporary, $this->file))
        {
            return true;
        }

        @unlink($temporary);

        return false;
    }

    private function serve(string $content): never
    {
        $etag = '"' . hash('xxh128', $content) . '"';
        $lastModified = ($this->contentDate)($content);

        // bootstrap.php ouvre une session et impose no-store : rien de cela ne doit partir avec
        // une réponse publique, qu'un cache partagé redonnerait à d'autres visiteurs
        header_remove('Set-Cookie');
        header_remove('Expires');
        header_remove('Pragma');
        header('Cache-Control: public, max-age=' . $this->ttl);
        header('ETag: ' . $etag);

        if ($lastModified !== null)
        {
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
        }

        if (ConditionalRequest::isNotModified($etag, $lastModified, (string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''), (string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '')))
        {
            http_response_code(304);
            exit;
        }

        header('Content-Type: ' . $this->contentType);

        if ($this->filename !== null)
        {
            header('Content-Disposition: inline; filename=' . $this->filename);
        }

        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }
}
