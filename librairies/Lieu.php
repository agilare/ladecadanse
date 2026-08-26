<?php

namespace Ladecadanse;

use Ladecadanse\Element;
use PDO;
use Ladecadanse\HasDocuments;

class Lieu extends Element
{
    use HasDocuments;

    // TODO: rn to $documentsSystemDirPath ?
    public static $systemDirPath;
    // TODO: rn to $documentsUrlDirPath ?
    public static $urlDirPath;

    // TODO: explain better utility in var. name or context
    // TODO: mv to a future LieuxRenderer class ?
    public const int LOW_ACTIVITY_MONTHS_NB = 6;
    public const int VERY_LOW_ACTIVITY_MONTHS_NB = 12;
    public const int RESULTS_PER_PAGE = 100;

    function __construct()
	{
		parent::__construct();
        $this->table = "lieu";
    }

    /**
     * TODO: rn to getNamePrepositionForSentence
     * in the future : no argument and usage of $this->preposition in body
     */
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

    /**
     * Used by all pages who need to target this lieu's page
     *
     * * TODO: find a better name
     * TODO: mv to a LieuRenderer class
     * @param string $baseUrl préfixe sans slash final, à fournir hors du site lui-même (flux RSS,
     *                        courriels) où un href relatif ne se résout pas correctement
     */
    public static function getLinkNameHtml(string $nom, ?int $idLieu, ?string $salle = null, string $baseUrl = ''): string
    {
        $result = sanitizeForHtml($nom);

        if ($idLieu)
        {
            $result = '<a href="' . $baseUrl . '/lieu/lieu.php?idL=' . (int) $idLieu . '">' . $result . '</a>';
            if ($salle)
            {
                $result .= " - " . sanitizeForHtml($salle);
            }
        }

        return $result;
    }

    /**
     * TODO: mv to a repository
     */
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

    /**
     * Only used for orgas table in organisateurs.php page
     * mv to a LieuxRepository::getList or ::find
    */
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
     * Reprend le tri du select de evenement-edit.php : cantons dans l'ordre de
     * Localite::CANTONS, et à l'intérieur d'un canton les noms sans leur article initial, pour
     * que « Le Sagittario » se range à S. La colonne `canton` sert à construire les <optgroup>.
     *
     * $exclureFribourg reproduit le filtre que le formulaire d'ajout applique à la création : un lieu
     * qu'on ne peut pas choisir en ajoutant un événement ne doit pas pouvoir être réglé comme lieu
     * par défaut.
     *
     * Les valeurs par défaut du profil (user-edit.php) se règlent au niveau du lieu et
     * appellent donc sans salles ; les formulaires d'événement, qui laissent choisir une
     * salle, passent $avecSalles.
     *
     * @return list<array{idLieu: int, nom: string, canton: string, salles?: list<array>}>
     */
    public static function getActifsPourSelect(bool $exclureFribourg = true, bool $avecSalles = false): array
    {
        global $connectorPdo;

        $where = $exclureFribourg ? " AND lieu.region != 'fr' " : '';

        $stmt = $connectorPdo->prepare("SELECT lieu.idLieu, lieu.nom, COALESCE(localite.canton, '') AS canton
            FROM lieu
            LEFT JOIN localite ON lieu.localite_id = localite.id
            WHERE lieu.statut = 'actif' " . $where . "
            ORDER BY
              " . Localite::sqlOrdreCantons("COALESCE(localite.canton, '')") . ",
              TRIM(LEADING 'L\'' FROM (TRIM(LEADING 'Les ' FROM (TRIM(LEADING 'La ' FROM (TRIM(LEADING 'Le ' FROM lieu.nom))))))) COLLATE utf8mb4_unicode_ci");
        $stmt->execute();
        $lieux = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$avecSalles)
        {
            return $lieux;
        }

        $salles_par_lieu = self::getSallesActivesParLieu();
        foreach ($lieux as $rang => $lieu)
        {
            $lieux[$rang]['salles'] = $salles_par_lieu[(int) $lieu['idLieu']] ?? [];
        }

        return $lieux;
    }

    /**
     * Les <option> et <optgroup> du <select> « Lieu », partagés par les formulaires d'ajout et
     * d'édition d'événement et par le formulaire d'édition groupée de l'administration. Le
     * <select> lui-même reste dans chaque formulaire, dont il porte les attributs.
     *
     * $idLieu est la valeur à présélectionner : un id de lieu, ou « 12_3 » quand une salle a
     * été choisie. $idSalle ne sert qu'au chargement d'une fiche existante, où lieu et salle
     * arrivent dans deux colonnes distinctes.
     */
    public static function getOptionsHtml(string|int|null $idLieu, string|int|null $idSalle = 0, bool $exclureFribourg = true): string
    {
        return self::renderOptions(
            self::getActifsPourSelect($exclureFribourg, avecSalles: true),
            (string) $idLieu,
            (string) $idSalle
        );
    }

    /**
     * Rendu pur des options, sans base de données ni globale : c'est ce que couvrent les tests.
     *
     * @param list<array{idLieu: int|string, nom: string, canton: string, salles?: list<array{idSalle: int|string, nom: string}>}> $lieux
     */
    public static function renderOptions(array $lieux, string $idLieu, string $idSalle = ''): string
    {
        // Après une erreur de saisie, le formulaire réaffiche la valeur postée telle quelle,
        // salle comprise ; il n'y a alors qu'un champ à relire.
        if (str_contains($idLieu, '_'))
        {
            [$idLieu, $idSalle] = explode('_', $idLieu, 2);
        }

        // « 0 » est le vide de la colonne evenement.idSalle, pas une salle sélectionnée
        if ($idSalle === '0')
        {
            $idSalle = '';
        }

        $html = '<option value=""></option>';

        $canton_courant = null;
        foreach ($lieux as $lieu)
        {
            $canton = (string) $lieu['canton'];
            if ($canton !== $canton_courant)
            {
                if ($canton_courant !== null)
                {
                    $html .= '</optgroup>';
                }
                $html .= '<optgroup label="' . sanitizeForHtml(Localite::CANTONS[$canton] ?? $canton) . '">';
                $canton_courant = $canton;
            }

            $selection = ((string) $lieu['idLieu'] === $idLieu && $idSalle === '') ? ' selected="selected"' : '';
            $html .= '<option value="' . (int) $lieu['idLieu'] . '"' . $selection . '>' . sanitizeForHtml($lieu['nom']) . '</option>';

            // Les salles suivent leur lieu, sous la même valeur composée « idLieu_idSalle »
            // que relisent les traitements de formulaire
            foreach ($lieu['salles'] ?? [] as $salle)
            {
                $selection = ($idSalle !== '' && (string) $salle['idSalle'] === $idSalle) ? ' selected="selected"' : '';
                $html .= '<option style="font-style:italic;color:#444;" value="' . (int) $lieu['idLieu'] . '_' . (int) $salle['idSalle'] . '"' . $selection . '>'
                    . sanitizeForHtml($lieu['nom']) . '&nbsp;– ' . sanitizeForHtml($salle['nom']) . '</option>';
            }
        }

        if ($canton_courant !== null)
        {
            $html .= '</optgroup>';
        }

        return $html;
    }

    /**
     * Toutes les salles actives, indexées par lieu — en une requête plutôt qu'une par lieu affiché.
     *
     * @return array<int, list<array{idSalle: int, idLieu: int, nom: string}>>
     */
    public static function getSallesActivesParLieu(): array
    {
        global $connectorPdo;

        $stmt = $connectorPdo->prepare("SELECT idSalle, idLieu, nom FROM salle WHERE status='actif' ORDER BY idLieu, idSalle");
        $stmt->execute();

        $salles_par_lieu = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $salle)
        {
            $salles_par_lieu[(int) $salle['idLieu']][] = $salle;
        }

        return $salles_par_lieu;
    }

    /**
     * mv to a LieuSalleRepository
     */
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

    /**
     * TODO: fix incoherence of names : images uploaded, documents, fichierrecu table name
     */
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
