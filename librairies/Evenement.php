<?php
// declare(strict_types=1);

namespace Ladecadanse;

use Ladecadanse\HasDocuments;
use Ladecadanse\Utils\DateHelper;
use Ladecadanse\Utils\Text;

class Evenement
{
    use HasDocuments;

    public static $systemDirPath;
    public static $urlDirPath;
    public static $statuts_evenement = ['propose' => 'Proposé', 'actif' => '', 'complet' => 'Complet', 'annule' => 'Annulé', 'inactif' => 'Dépublié'];

    public const int AGENDA_START_YEAR = 2005;
    // evenement.genre default value in database
    public const string GENRE_DEFAULT = 'divers';

    /** 06:00 en secondes : borne haute de la journée d'agenda, qui court de 06:00:01 au lendemain 06:00:00 */
    public const int JOURNEE_AGENDA_FIN_EN_SECONDES = 21_600;

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

    public static function normalizeTitre(string $titre): string
    {
        $stripped = Text::stripAccents($titre);
        $lower = mb_strtolower($stripped, 'UTF-8');
        $collapsed = preg_replace('/\s+/u', ' ', $lower);
        return trim((string) $collapsed);
    }

    public static function findSimilarEvenements(
        string $titre,
        int $idLieu,
        string $nomLieu,
        string $dateEvenement,
        int $excludeIdE = 0,
        int $maxLevenshtein = 5,
    ): array {
        global $connectorPdo;

        $normalizedTitre = self::normalizeTitre($titre);
        if ($normalizedTitre === '')
        {
            return [];
        }

        $params = [
            ':date' => $dateEvenement,
            ':excludeIdE' => $excludeIdE,
        ];

        if ($idLieu <= 0 && $nomLieu === '')
        {
            return [];
        }

        if ($idLieu > 0)
        {
            $lieuClause = 'e.idLieu = :idLieu';
            $params[':idLieu'] = $idLieu;
        }
        else
        {
            $lieuClause = 'e.idLieu = 0 AND LOWER(e.nomLieu) = :nomLieu';
            $params[':nomLieu'] = mb_strtolower(trim($nomLieu), 'UTF-8');
        }

        $sql = "SELECT
            e.idEvenement,
            e.idLieu,
            e.idPersonne,
            e.titre,
            e.dateEvenement,
            e.horaire_debut,
            e.horaire_fin,
            e.statut,
            e.nomLieu,
            l.nom AS l_nom
        FROM evenement e
        LEFT JOIN lieu l ON e.idLieu = l.idLieu
        WHERE e.dateEvenement = :date
            AND e.statut NOT IN ('inactif', 'propose')
            AND e.idEvenement <> :excludeIdE
            AND $lieuClause";

        $stmt = $connectorPdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $similar = [];
        foreach ($rows as $row)
        {
            $candidateNorm = self::normalizeTitre((string) $row['titre']);
            if ($candidateNorm === '')
            {
                continue;
            }
            if (abs(strlen($normalizedTitre) - strlen($candidateNorm)) > $maxLevenshtein)
            {
                continue;
            }
            if (levenshtein($normalizedTitre, $candidateNorm) <= $maxLevenshtein)
            {
                $similar[] = $row;
            }
        }

        return $similar;
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

    /**
     * Consolide les champs de lieu d'un événement à partir de ce que le formulaire a posté.
     *
     * Deux sources s'excluent : un lieu choisi dans la liste (`idLieu`), dont on recopie
     * l'adresse dans l'événement, ou une saisie libre, dont seule la localité fixe la région.
     * Les formulaires refusent les deux à la fois en amont.
     *
     * @param  array $champs champs du formulaire, dont idLieu, localite_id, quartier
     * @return array{0: array, 1: bool} les champs consolidés, et si le lieu a été redéfini
     */
    public static function resolveLieuFields(array $champs): array
    {
        global $connector;

        // pour remplir les champs nomLieu, adresse, etc. de la table evenement
        if (!empty($champs['idLieu']))
        {
            // cast et non sanitize() : hors quotes, l'échappement ne bloque pas « 1 OR … »
            $req_lieu = $connector->query("SELECT nom, adresse, quartier, localite_id, region, URL
                FROM lieu WHERE idLieu=" . (int) $champs['idLieu']);
            $tab_lieu = $connector->fetchArray($req_lieu);

            $champs['nomLieu']     = $tab_lieu['nom'];
            $champs['adresse']     = $tab_lieu['adresse'];
            $champs['quartier']    = $tab_lieu['quartier'];
            $champs['localite_id'] = $tab_lieu['localite_id'];
            $champs['region']      = $tab_lieu['region'];
            $champs['urlLieu']     = $tab_lieu['URL'];

            return [$champs, true];
        }

        if (empty($champs['localite_id']))
        {
            return [$champs, false];
        }

        // « id_quartier » : les localités genevoises portent leur quartier dans la même valeur
        $loc_qua = explode("_", (string) $champs['localite_id']);
        if (count($loc_qua) > 1)
        {
            $champs['localite_id'] = $loc_qua[0];
            $champs['quartier']    = $loc_qua[1];
            $champs['region']      = 'ge';
        }
        else
        {
            $champs['quartier'] = '';

            // Nyon est vaudoise, mais rattachée à l'agenda genevois
            if ($champs['localite_id'] == 529)
            {
                $champs['region'] = 'ge';
            }
            else
            {
                // La région suit le canton de la localité choisie — la France ('rf') et
                // « Autre » ('hs') y comprises, depuis qu'elles sont des localités.
                // cast et non sanitize() : hors quotes, l'échappement ne bloque pas « 1 OR … »
                $req_lieu = $connector->query("SELECT canton FROM localite WHERE id=" . (int) $champs['localite_id']);
                $tab_lieu = $connector->fetchArray($req_lieu);
                $champs['region'] = $tab_lieu['canton'];
            }
        }

        $champs['idLieu'] = 0;

        return [$champs, true];
    }

    /**
     * Datetime d'un horaire saisi en hh:mm, rapporté au jour de l'événement.
     *
     * Une journée d'agenda court de 06:00:01 au lendemain 06:00:00 : une heure comprise
     * entre minuit et 06:00 appartient donc au lendemain civil, tout en restant le même
     * jour d'agenda. C'est la règle qu'annonce le guide du champ (« jusqu'à 06:00 »).
     *
     * @param string $horaire            heure saisie, au format hh:mm
     * @param string $dateEvenementIso   date de l'événement, au format Y-m-d
     */
    public static function horaireToDatetime(string $horaire, string $dateEvenementIso): string
    {
        [$heures, $minutes] = array_pad(explode(":", $horaire), 2, '0');
        $secondes_apres_minuit = (int) $heures * 3600 + (int) $minutes * 60;

        $date = ($secondes_apres_minuit >= 0 && $secondes_apres_minuit <= self::JOURNEE_AGENDA_FIN_EN_SECONDES)
            ? DateHelper::isoToNextDay($dateEvenementIso)
            : $dateEvenementIso;

        return $date . " " . $horaire . ":00";
    }

    /**
     * Datetime sentinelle d'un événement sans horaire : la fin de sa journée d'agenda,
     * pour qu'il s'affiche en dernier dans le jour plutôt que de disparaître du tri.
     */
    public static function horaireAbsentDatetime(string $dateEvenementIso): string
    {
        return DateHelper::isoToNextDay($dateEvenementIso) . " 06:00:01";
    }
}
