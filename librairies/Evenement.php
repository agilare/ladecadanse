<?php
// declare(strict_types=1);

namespace Ladecadanse;

use Ladecadanse\Element;
use Ladecadanse\HasDocuments;

class Evenement extends Element
{
    use HasDocuments;

    public static $systemDirPath;
    public static $urlDirPath;
    public static $statuts_evenement = ['propose' => 'Proposé', 'actif' => '', 'complet' => 'Complet', 'annule' => 'Annulé', 'inactif' => 'Dépublié'];

    public const int AGENDA_START_YEAR = 2005;

    public const LIST_SELECT_FIELDS = "
        e.idEvenement AS e_idEvenement,
        e.titre AS e_titre,
        e.statut AS e_statut,
        e.idPersonne AS e_idPersonne,
        e.dateEvenement AS e_dateEvenement,
        e.genre AS e_genre,
        e.ref AS e_ref,
        e.flyer AS e_flyer,
        e.image AS e_image,
        e.description AS e_description,
        e.horaire_debut AS e_horaire_debut,
        e.horaire_fin AS e_horaire_fin,
        e.horaire_complement AS e_horaire_complement,
        e.prix AS e_prix,
        e.prelocations AS e_prelocations,
        e.idLieu AS e_idLieu,
        e.idSalle AS e_idSalle,
        e.nomLieu AS e_nomLieu,
        e.adresse AS e_adresse,
        e.quartier AS e_quartier,
        loc.localite AS e_localite,
        e.region AS e_region,
        e.urlLieu AS e_urlLieu,
        l.nom AS l_nom,
        l.adresse AS l_adresse,
        l.quartier AS l_quartier,
        l.URL AS l_URL,
        lloc.localite AS lloc_localite,
        l.region AS l_region,
        s.nom AS s_nom";

    public const LIST_JOINS = "
        JOIN localite loc ON e.localite_id = loc.id
        LEFT JOIN lieu l ON e.idLieu = l.idLieu
        LEFT JOIN localite lloc ON l.localite_id = lloc.id
        LEFT JOIN salle s ON e.idSalle = s.idSalle";

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
