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

    public static function nom_genre($nom): string
    {
        if ($nom == 'fête')
        {
            return 'fêtes';
        }
        else if ($nom == 'cinéma')
        {
            return 'ciné';
        }
        else
        {
            return $nom;
        }
    }

    /**
     * Marque le titre de l'?v?nement selon le statut qui lui attribu?
     *
     *
     * @param string $titre Titre de l'?v?nement
     * @param string $statut Statut actuel de l'?v?nement
     * @return string Le titre marqu?
     */
    public static function titre_selon_statut($titre, $statut)
    {
        $titre_avec_statut = $titre;

        if ($statut == "annule")
        {
            $titre_avec_statut = '<strike>' . $titre . '</strike> <span>ANNULÉ</span>';
        }
        if ($statut == "complet")
        {
            $titre_avec_statut = '<em>' . $titre . '</em> <span>COMPLET</span>';
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
        preg_match('/(\d{4}-\d{2}-\d{2})/', $fileName, $dateMatches);
        $eventYear = substr($dateMatches[1], 0, 4);
        //dump($dateMatches);
        if ( (new \DateTime('now'))->format('Y') > $eventYear )
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
