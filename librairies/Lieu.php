<?php

namespace Ladecadanse;

use Ladecadanse\Element;
use PDO;
use Ladecadanse\HasDocuments;

class Lieu extends Element
{
    use HasDocuments;

    public static $systemDirPath;
    public static $urlDirPath;

    public const int RESULTS_PER_PAGE = 100;

    function __construct()
	{
		parent::__construct();
        $this->table = "lieu";
    }

    public static function prepositionToPutInSentence($preposition): string
    {
        $result = $preposition;

        if ($preposition == '')
        {
            return "- ";
        }

        // if "au", "chez", etc. add a separation
        if (!in_array(trim($preposition), ["l'", "à l'"]))
        {
            $result .= " ";
        }
        return $result;
    }

    public static function getLinkNameHtml(string $nom, ?int $idLieu, ?string $salle = null): string
    {
        $result = sanitizeForHtml($nom);

        if ($idLieu)
        {
            $result = '<a href="/lieu/lieu.php?idL=' . (int) $idLieu . '">' . $result . '</a>';
            if ($salle)
            {
                $result .= " - " . sanitizeForHtml($salle);
            }
        }

        return $result;
    }

    public static function getLieu(int $idLieu): array
    {
        global $connectorPdo;
        $sql_event = "SELECT

          l.*,
          loc.localite AS loc_localite,
          loc.canton AS loc_canton,
          loc.commune AS loc_commune

        FROM lieu l
        JOIN localite loc ON l.localite_id = loc.id
        WHERE l.idLieu = ?";

        $stmt = $connectorPdo->prepare($sql_event);
        $stmt->execute([$idLieu]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function getLieux(array $filters, string $order = 'dateAjout', ?int $page = 1): array
    {
        global $connectorPdo;

        $params = [':region' => $filters['region'], ':statut' => $filters['statut']];

        if (!empty($filters['nom']))
        {
            $params[':nom'] = "%" . $filters['nom'] . "%";
        }

        if (!empty($filters['localite']))
        {
            $params[':localite'] = $filters['localite'];
        }

        if (!empty($filters['categorie']))
        {
            $params[':categorie'] = $filters['categorie'];
        }

        // build SQL
        $sql_order = "l." . $order . " DESC";
        if ($order == "nom")
        {
            $sql_order = "l.nom ASC";
        }

        $sql_event = "SELECT
          l.*,
          loc.localite AS loc_localite,
          loc.canton AS loc_canton,
          loc.commune AS loc_commune
        FROM lieu l
        JOIN localite loc ON l.localite_id = loc.id
        WHERE l.statut = :statut";

        if (!empty($filters['nom']))
        {
            $sql_event .= " AND l.nom LIKE :nom";
        }

        if (!empty($filters['localite']))
        {
            $sql_event .= " AND l.localite_id = :localite";
        }

        if (!empty($filters['categorie']))
        {
            $sql_event .= " AND FIND_IN_SET (:categorie, categorie)";
        }

        $sql_event .= " AND (l.region IN (:region, 'rf', 'hs')  )"; // OR FIND_IN_SET (:region, loc.regions_covered)
        $sql_event .= " ORDER BY $sql_order";

        if (!empty($page))
        {
            $sql_event .= " LIMIT " . (int) (($page - 1) * self::RESULTS_PER_PAGE) . ", " . (int) self::RESULTS_PER_PAGE; // (($page - 1) * self::RESULTS_PER_PAGE +
        }

        //echo $sql_event;
        $stmt = $connectorPdo->prepare($sql_event);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public static function getActivesSalles(int $idLieu): array
    {
        global $connectorPdo;

        $stmt = $connectorPdo->prepare("SELECT * FROM salle WHERE idLieu = ? AND salle.status='actif' ");
        $stmt->execute([$idLieu]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getActivesOrganisateurs(int $idLieu): array
    {
        global $connectorPdo;

        $stmt = $connectorPdo->prepare("SELECT lo.idOrganisateur AS idOrganisateur, o.nom AS nom
            FROM lieu_organisateur lo
            JOIN organisateur o on lo.idOrganisateur = o.idOrganisateur AND o.statut = 'actif'
            WHERE lo.idLieu=?");
        $stmt->execute([$idLieu]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getImagesUploaded(int $idLieu): array
    {
        global $connectorPdo;

        $stmt = $connectorPdo->prepare("SELECT lf.idFichierrecu AS idFichierrecu, description, mime, extension
            FROM lieu_fichierrecu lf
            JOIN fichierrecu f ON lf.idFichierrecu = f.idFichierrecu AND f.type = 'image'
            WHERE lf.idLieu = ?
            ORDER BY dateAjout DESC");
        $stmt->execute([$idLieu]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getDescriptions(int $idLieu): array
    {
        global $connectorPdo;

        $stmt = $connectorPdo->prepare("SELECT type, dl.idLieu AS idLieu, contenu, dl.dateAjout AS dateAjout, pseudo, groupe, dl.idPersonne AS idPersonne, dl.date_derniere_modif
		 FROM descriptionlieu dl
		 INNER JOIN personne p ON dl.idPersonne = p.idPersonne
		 WHERE dl.idLieu = ?
		 ORDER BY type, dl.dateAjout");

        $stmt->execute([$idLieu]);
        return $stmt->fetchAll(PDO::FETCH_GROUP);
    }
}
