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

    public const int LOW_ACTIVITY_MONTHS_NB = 6;
    public const int VERY_LOW_ACTIVITY_MONTHS_NB = 12;
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

        // fetch() returns false for an unknown id, callers expect an empty array
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
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


    /**
     * Lieux actifs, prêts à peupler un <select> groupé par canton.
     *
     * Reprend le tri du select de evenement-edit.php : cantons dans l'ordre ge, vd, fr puis le
     * reste, et à l'intérieur d'un canton les noms sans leur article initial, pour que « Le
     * Sagittario » se range à S. La colonne `canton` sert à construire les <optgroup>.
     *
     * $exclureFrance reproduit le filtre que le formulaire d'ajout applique à la création : un lieu
     * qu'on ne peut pas choisir en ajoutant un événement ne doit pas pouvoir être réglé comme lieu
     * par défaut.
     *
     * Contrairement au select de evenement-edit.php, les salles ne sont pas jointes : les valeurs
     * par défaut du profil se règlent au niveau du lieu.
     *
     * @return list<array{idLieu: int, nom: string, canton: string}>
     */
    public static function getActifsPourSelect(bool $exclureFrance = true): array
    {
        global $connectorPdo;

        $where = $exclureFrance ? " AND lieu.region != 'fr' " : '';

        $stmt = $connectorPdo->prepare("SELECT lieu.idLieu, lieu.nom, COALESCE(localite.canton, '') AS canton
            FROM lieu
            LEFT JOIN localite ON lieu.localite_id = localite.id
            WHERE lieu.statut = 'actif' " . $where . "
            ORDER BY
              CASE COALESCE(localite.canton, '') WHEN 'ge' THEN 0 WHEN 'vd' THEN 1 WHEN 'fr' THEN 2 ELSE 3 END,
              TRIM(LEADING 'L\'' FROM (TRIM(LEADING 'Les ' FROM (TRIM(LEADING 'La ' FROM (TRIM(LEADING 'Le ' FROM lieu.nom))))))) COLLATE utf8mb4_unicode_ci");
        $stmt->execute();
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

    public static function getActivesAffiliates(int $idLieu): array
    {
        global $connectorPdo;

        $stmt = $connectorPdo->prepare("SELECT p.idPersonne AS idPersonne, pseudo, email, DATE(p.dateAjout) AS p_dateAjout
            FROM affiliation a
            LEFT JOIN personne p ON a.idPersonne = p.idPersonne AND p.statut = 'actif'
            WHERE a.genre = 'lieu' AND a.idAffiliation = ? ORDER BY p_dateAjout DESC");
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
