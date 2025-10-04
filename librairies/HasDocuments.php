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
//    public function getDocumentUrl(string $filename): string
//    {
//        return $this->getBaseUrl() . '/' . $filename;
//    }


    public static function getSystemFilePath(string $filePath): string
    {
        return self::$systemDirPath . $filePath;
    }

    public static function getFilePath(string $fileName, string $fileNamePrefix = '', string $fileNameSuffix = ''): string
    {
        return $fileNamePrefix . $fileName . $fileNameSuffix;
    }
}
