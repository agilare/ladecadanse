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

    public static function getWebPath(string $filePath, bool $isWithAntiCache = false): string
    {
	    $result = self::$urlDirPath . $filePath;
        $systemFilePath = self::getSystemFilePath($filePath);
        if ($isWithAntiCache && file_exists($systemFilePath))
        {
            $result .= "?" . filemtime($systemFilePath);
        }

	    return $result;
    }

    public static function rmImageAndItsMiniature(string $fileName): void
    {
        unlink(self::getSystemFilePath(self::getFilePath($fileName)));
        unlink(self::getSystemFilePath(self::getFilePath($fileName, "s_")));
    }
}
