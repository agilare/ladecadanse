<?php

namespace Ladecadanse\Utils;

class AssetManager
{
    private string $baseSystemPath;
    private string $baseUrl;
    /**
     *
     * @var array cache interne par requête
     */
    private array $cache = [];

    /**
     * @param string $baseSystemPath chemin système vers les fichiers
     * @param string $baseUrl chemin URL (optionnel)
     */
    public function __construct(string $baseSystemPath, string $baseUrl = '')
    {
        $this->baseSystemPath = rtrim($baseSystemPath, '/');
        $this->baseUrl  = rtrim($baseUrl, '/');
    }

    /**
     * @param string $relativePath
     * @return string base base url + relative path + hash
     */
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
            error_log("Asset not found: " . $fullSystemPath);
            return $this->cache[$relativePath] = $this->baseUrl . $relativePath;
        }

        $hash = substr(md5_file($fullSystemPath), 0, 8);

        return $this->cache[$relativePath] = $this->baseUrl . $relativePath . '?v=' . $hash;
    }
}
