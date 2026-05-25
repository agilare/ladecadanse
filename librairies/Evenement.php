<?php
// declare(strict_types=1);

namespace Ladecadanse;

use Ladecadanse\Element;
use Ladecadanse\HasDocuments;
use Ladecadanse\Utils\Text;

class Evenement extends Element
{
    use HasDocuments;

    public static $systemDirPath;
    public static $urlDirPath;
    public static $statuts_evenement = ['propose' => 'Proposé', 'actif' => '', 'complet' => 'Complet', 'annule' => 'Annulé', 'inactif' => 'Dépublié'];

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
            AND e.statut <> 'ancien'
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
