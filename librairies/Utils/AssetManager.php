<?php

namespace Ladecadanse\Utils;

use Psr\Log\LoggerInterface;

class AssetManager
{
    private string $baseSystemPath;
    private string $baseUrl;
    private ?LoggerInterface $logger;
    /**
     *
     * @var array cache interne par requête
     */
    private array $cache = [];

    /**
     * @param string $baseSystemPath chemin système vers les fichiers
     * @param string $baseUrl chemin URL (optionnel)
     * @param LoggerInterface|null $logger journal applicatif (optionnel)
     */
    public function __construct(string $baseSystemPath, string $baseUrl = '', ?LoggerInterface $logger = null)
    {
        $this->baseSystemPath = rtrim($baseSystemPath, '/');
        $this->baseUrl  = rtrim($baseUrl, '/');
        $this->logger = $logger;
    }

    /**
     * @param array $relativePaths
     * @param string|null $nonce nonce CSP : sans lui la balise inline est bloquée en prod
     * @return string base base url + relative path + hash
     */
    public function getImportMap(array $relativePaths, ?string $nonce = null): string
    {
        $imports = [];
        foreach ($relativePaths as $path) {
            $path = '/' . ltrim($path, '/');
            $originalUrl = $this->baseUrl . $path;
            $imports[$originalUrl] = $this->get($path);
        }
        $nonceAttr = $nonce !== null ? ' nonce="' . htmlspecialchars($nonce, ENT_QUOTES) . '"' : '';

        return '<script type="importmap"' . $nonceAttr . '>' . json_encode(['imports' => $imports], JSON_UNESCAPED_SLASHES) . '</script>';
    }

    public function get(string $relativePath): string
    {
        // Chemin relatif normalisé
        $relativePath = '/' . ltrim($relativePath, '/');

        // Si déjà en cache → retour direct
        if (isset($this->cache[$relativePath])) {
            return $this->cache[$relativePath];
        }

        $fullSystemPath = $this->baseSystemPath . $relativePath;

        if (!is_file($fullSystemPath)) {
            // un fichier manquant reste une information utile (upload perdu,
            // déploiement incomplet) mais ce n'est pas une erreur php : elle va
            // dans le journal applicatif, pas dans le log d'erreurs du serveur
            $this->logger?->warning("Asset not found", ['path' => $fullSystemPath]);
            return $this->cache[$relativePath] = $this->baseUrl . $relativePath;
        }

        $hash = substr(md5_file($fullSystemPath), 0, 8);

        return $this->cache[$relativePath] = $this->baseUrl . $relativePath . '?v=' . $hash;
    }
}
