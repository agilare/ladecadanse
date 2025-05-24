<?php
// declare(strict_types=1);

namespace Ladecadanse;

use Ladecadanse\Element;

class Evenement extends Element
{

    static $systemDirPath;
    static $urlDirPath;

    function __construct() {

        parent::__construct();
        $this->table = "evenement";
    }

    public static function nom_genre(string $nom): string
    {
        if ($nom == 'fête')
        {
            return 'fêtes';
        }
        else if ($nom == 'cinéma')
        {
            return 'ciné';
        }

        return $nom;
    }

    public static function titre_selon_statut(string $titre, string $statut): string
    {
        $titre_avec_statut = $titre;

        if ($statut == "annule")
        {
            $titre_avec_statut = '<strike>' . $titre . '</strike> <span class="even-statut-badge ' . $statut . '">ANNULÉ</span>';
        }
        if ($statut == "complet")
        {
            $titre_avec_statut = '<em>' . $titre . '</em> <span class="even-statut-badge ' . $statut . '">COMPLET</span>';
        }

        return $titre_avec_statut;
    }

    public static function rmImageAndItsMiniature(string $fileName): void
    {
        unlink(self::getSystemFilePath(self::getFilePath($fileName)));
        unlink(self::getSystemFilePath(self::getFilePath($fileName, "s_")));
    }

    public static function getFilePath(string $fileName, string $fileNamePrefix = '', string $fileNameSuffix = ''): string
    {
        $filePath = $fileNamePrefix . $fileName . $fileNameSuffix;

        // extract year from $fileName : 12345_2024-11-18.jpg or 12345_2024-11-18_img.jpg
        //$dateMatches = [];
        if (!preg_match('/(\d{4}-\d{2}-\d{2})/', $fileName, $dateMatches))
        {
            return $filePath;
        }

        $eventYear = substr($dateMatches[1], 0, 4);
        if ((new \DateTime('now'))->format('Y') > $eventYear)
        {
            $filePath = $eventYear . "/" . $filePath;
        }
	    return $filePath;
    }

    public static function getFileHref(string $filePath, bool $isWithAntiCache = false): string
    {
	    $result = self::$urlDirPath . $filePath;
        $systemFilePath = self::getSystemFilePath($filePath);
        if ($isWithAntiCache && file_exists($systemFilePath))
        {
            $result .= "?" . filemtime($systemFilePath);
        }

	    return $result;
    }

    public static function getSystemFilePath(string $filePath): string
    {
        return self::$systemDirPath . $filePath;
    }
}
