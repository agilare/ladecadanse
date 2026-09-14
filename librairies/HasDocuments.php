<?php

/*
 * @package ladecadanse
 * @copyright  Copyright (c) 2007 - 2025 Michel Gaudry <michel@ladecadanse.ch>
 * @license    AGPL License; see LICENSE file for details.
 */

namespace Ladecadanse;

/**
 *
 * @author Michel Gaudry <michel@ladecadanse.ch>
 */
trait HasDocuments
{
    public static function getSystemFilePath(string $filePath): string
    {
        return self::$systemDirPath . $filePath;
    }

    /**
     *
     * @param string $fileNamePrefix "s_"
     * @param string $fileNameSuffix "img", "logo"...
     * @return string
     */
    public static function getFilePath(string $fileName, string $fileNamePrefix = '', string $fileNameSuffix = ''): string
    {
        return $fileNamePrefix . $fileName . $fileNameSuffix;
    }

    /**
     * Retourne le chemin relatif à ASSETS_DIR, utilisable directement avec $assets->get().
     * Ex : /uploads/evenements/ + s_flyer.jpg → /uploads/evenements/s_flyer.jpg
     */
    public static function getAssetPath(string $filePath): string
    {
        return self::$urlDirPath . $filePath;
    }

    /**
     * Noms possibles de la miniature d'un fichier, du plus récent au plus ancien.
     *
     * Une entité qui change le format de ses miniatures redéfinit cette seule méthode : la
     * copie et la suppression la suivent sans être retouchées, et un candidat absent du
     * disque est simplement ignoré.
     *
     * @return list<string>
     */
    protected static function thumbNameCandidates(string $fileName): array
    {
        return ['s_' . $fileName];
    }

    public static function safeCopyWithMiniature(string $srcFileName, string $destFileName): void
    {
        $safeDir = realpath(self::$systemDirPath);
        if ($safeDir === false) {
            return;
        }

        $safeSrc = basename($srcFileName);
        $safeDest = basename($destFileName);
        if ($safeSrc === '' || $safeDest === '') {
            return;
        }

        // L'image elle-même, puis chaque forme de miniature. Les noms portent déjà leur
        // préfixe : getFilePath() n'a plus qu'à préfixer l'année pour les fiches archivées.
        $pairs = [[$safeSrc, $safeDest]];
        $srcThumbs = static::thumbNameCandidates($safeSrc);
        $destThumbs = static::thumbNameCandidates($safeDest);
        foreach ($srcThumbs as $rank => $srcThumb) {
            if (isset($destThumbs[$rank])) {
                $pairs[] = [$srcThumb, $destThumbs[$rank]];
            }
        }

        foreach ($pairs as [$srcName, $destName]) {
            $srcFullPath = realpath($safeDir . DIRECTORY_SEPARATOR . static::getFilePath($srcName));
            if ($srcFullPath === false || !str_starts_with($srcFullPath, $safeDir . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $destFilePath = static::getFilePath($destName);
            $destFullPath = $safeDir . DIRECTORY_SEPARATOR . $destFilePath;
            $destDirPath = realpath(dirname($destFullPath));
            if ($destDirPath === false || !str_starts_with($destDirPath . DIRECTORY_SEPARATOR, $safeDir . DIRECTORY_SEPARATOR)) {
                continue;
            }

            copy($srcFullPath, $destFullPath);
        }
    }

    public static function rmImageAndItsMiniature(string $fileName): void
    {
        $safeName = basename($fileName);
        if ($safeName === '') {
            return;
        }
        $safeDir = realpath(self::$systemDirPath);
        if ($safeDir === false) {
            return;
        }
        foreach ([$safeName, ...static::thumbNameCandidates($safeName)] as $name) {
            $resolvedPath = realpath($safeDir . DIRECTORY_SEPARATOR . $name);
            if ($resolvedPath !== false && str_starts_with($resolvedPath, $safeDir . DIRECTORY_SEPARATOR)) {
                unlink($resolvedPath);
            }
        }
    }
}
