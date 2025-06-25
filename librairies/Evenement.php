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

    /**
     * Affichage du lieu selon son existence ou non dans la base
     * @param array $event
     * @return array
     */
    public static function getLieu(array $event): array
    {
        if ($event['e_idLieu'] != 0)
        {
            $result =
            [
                'idLieu' => $event['e_idLieu'],
                'nom' => $event['l_nom'],
                'adresse' => $event['l_adresse'],
                'quartier' => $event['l_quartier'],
                'localite' => $event['lloc_localite'],
                'url' => $event['l_URL'],
            ];

            if ($event['s_nom'])
            {
                $result['nom'] .= " - " . sanitizeForHtml($event['s_nom']);
            }

            return $result;
        }

        return [
                'nom' => $event['e_nomLieu'],
                'adresse' => $event['e_adresse'],
                'quartier' => $event['e_quartier'],
                'localite' => $event['e_localite'],
                'url' => $event['e_urlLieu'],
            ];
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

    public static function titreSelonStatutHtml(string $titreHtml, string $statut): string
    {
        if ($statut == "annule")
        {
            return '<strike>' . $titreHtml . '</strike> <span class="even-statut-badge ' . $statut . '">ANNULÉ</span>';
        }
        if ($statut == "complet")
        {
            return '<em>' . $titreHtml . '</em> <span class="even-statut-badge ' . $statut . '">COMPLET</span>';
        }

        return $titreHtml;
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
