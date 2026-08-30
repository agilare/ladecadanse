<?php


namespace Ladecadanse;

class Document
{
    /**
     * Extension de fichier correspondant à un type MIME d'image.
     *
     * Le format écrit par ImageDriver2 est celui du contenu, jamais celui du nom
     * fourni par le client : c'est donc du MIME que l'extension doit être
     * déduite. Un JPEG est le repli, aucun autre format n'étant accepté à
     * l'envoi (voir $mimes_images_acceptes dans app/config.php).
     */
    public static function extensionPourMime(string $mime): string
    {
        return match ($mime) {
            'image/png', 'image/x-png' => '.png',
            'image/gif' => '.gif',
            'image/webp' => '.webp',
            default => '.jpg',
        };
    }
}
