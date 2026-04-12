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
//    abstract protected function getSystemDirPath(): string;
//    abstract protected function getBaseUrl(): string;
//
//    public function getWebPath(string $filename): string
//    {
//        return $this->getBaseUrl() . '/' . $filename;
//    }


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

    public static function getWebPath(string $filePath): string
    {
        return ASSETS_DIR . self::$urlDirPath . $filePath;
    }

    /**
     * Retourne le chemin relatif à ASSETS_DIR, utilisable directement avec $assets->get().
     * Ex : /uploads/evenements/ + s_flyer.jpg → /uploads/evenements/s_flyer.jpg
     */
    public static function getAssetPath(string $filePath): string
    {
        return self::$urlDirPath . $filePath;
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

        foreach (['', 's_'] as $prefix) {
            $srcFullPath = realpath($safeDir . DIRECTORY_SEPARATOR . static::getFilePath($safeSrc, $prefix));
            if ($srcFullPath === false || !str_starts_with($srcFullPath, $safeDir . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $destFilePath = static::getFilePath($safeDest, $prefix);
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
        foreach ([$safeName, 's_' . $safeName] as $name) {
            $resolvedPath = realpath($safeDir . DIRECTORY_SEPARATOR . $name);
            if ($resolvedPath !== false && str_starts_with($resolvedPath, $safeDir . DIRECTORY_SEPARATOR)) {
                unlink($resolvedPath);
            }
        }
    }
}
