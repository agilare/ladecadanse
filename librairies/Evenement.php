<?php
// declare(strict_types=1);

namespace Ladecadanse;

use Ladecadanse\HasDocuments;

class Evenement
{
    use HasDocuments;

    public static $systemDirPath;
    public static $urlDirPath;
    public static $statuts_evenement = ['propose' => 'Proposé', 'actif' => '', 'complet' => 'Complet', 'annule' => 'Annulé', 'inactif' => 'Dépublié'];

    public const int AGENDA_START_YEAR = 2005;
    // evenement.genre default value in database
    public const string GENRE_DEFAULT = 'divers';

    /**
     * TODO: mv to EvenementRenderer ?
     *
     * Libellé d'affichage d'un genre
     *
     * Les genres sont stockés en varchar : d'anciens événements peuvent porter
     * un genre qui n'est plus dans $glo_tab_genre, on retombe alors sur "divers"
     * plutôt que d'afficher (ou pire, de passer plus loin) une valeur nulle
     */
    public static function genreLabel(?string $genre): string
    {
        global $glo_tab_genre;

        return $glo_tab_genre[$genre] ?? $glo_tab_genre[self::GENRE_DEFAULT] ?? self::GENRE_DEFAULT;
    }

    /**
     * Consolide un tableau des données du lieu, selon son existence dans la table lieu ou dans l'événement directement
     * TODO: mv to EvenementRenderer ?
     *
     * @param array $event
     * @return array
     *
     */
    public static function getLieu(array $event): array
    {
        // l_nom is null when the LEFT JOIN found no lieu : the event references a
        // deleted lieu, fall back on the free text location stored in the event
        if ($event['e_idLieu'] != 0 && isset($event['l_nom']))
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
                'determinant' => "",
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

    /**
     * TODO: find a better name
     * overrides HasDocuments method
     */
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
