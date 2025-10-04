<?php
// declare(strict_types=1);

namespace Ladecadanse;

use Ladecadanse\Element;
use Ladecadanse\HasDocuments;

class Evenement extends Element
{
    use HasDocuments;

    static $systemDirPath;
    static $urlDirPath;
    static $statuts_evenement = ['propose' => 'Proposé', 'actif' => '', 'complet' => 'Complet', 'annule' => 'Annulé', 'inactif' => 'Dépublié'];

    public const int AGENDA_START_YEAR = 2005;

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
            return [
                'idLieu' => $event['e_idLieu'],
                'nom' => $event['l_nom'],
                'determinant' => $event['l_determinant'] ?? "",
                'adresse' => $event['l_adresse'],
                'quartier' => $event['l_quartier'],
                'lat' => $event['l_lat'] ?? "",
                'lng' => $event['l_lng'] ?? "",
                'localite' => $event['lloc_localite'],
                'region' => $event['l_region'] ?? "",
                'url' => $event['l_URL'],
                'salle' => $event['s_nom'] ?? "",
            ];
        }

        return [
                'idLieu' => null,
                'nom' => $event['e_nomLieu'],
                'adresse' => $event['e_adresse'],
                'quartier' => $event['e_quartier'],
                'lat' => '',
                'lng' => '',
                'localite' => $event['e_localite'],
                'region' => $event['e_region'] ?? "",
                'url' => $event['e_urlLieu'],
                'salle' => ""
            ];
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
}
