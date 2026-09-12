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
    public const string CATEGORY_DEFAULT = 'divers';

    /**
     * Boîte maximale des miniatures d'événement (#84).
     *
     * Les trois points d'écriture et le choix miniature/image de EvenementRenderer lisaient
     * chacun leur propre 120 : la largeur d'affichage la plus grande est 100 px, mais un
     * écran à densité 2x en demande le double pour rester net.
     */
    public const int THUMBNAIL_MAX_WIDTH = 150;
    public const int THUMBNAIL_MAX_HEIGHT = 240;

    /**
     * Format d'écriture des miniatures, quel que soit le format de l'original (#170).
     *
     * ImageDriver2 écrivait la miniature dans le format du fichier reçu. Un flyer envoyé en
     * PNG — un quart des envois — produisait une miniature de 76 Ko là où le même contenu en
     * pèse 8 : à eux seuls ces PNG portaient 73 % du poids des miniatures du site.
     */
    public const string THUMBNAIL_MIME = 'image/webp';
    public const string THUMBNAIL_EXTENSION = '.webp';

    /** 06:00 en secondes : borne haute de la journée d'agenda, qui court de 06:00:01 au lendemain 06:00:00 */
    public const int JOURNEE_AGENDA_FIN_EN_SECONDES = 21_600;

    /**
     * TODO: mv to EvenementRenderer ?
     *
     * Libellé d'affichage d'une catégorie.
     *
     * Le repli des catégories en préversion s'applique ici, et nulle part ailleurs : c'est
     * le point de passage unique de tout affichage de catégorie — agenda, recherche, fiches
     * lieu et organisateur, tableaux d'administration, tableau de bord. Sans lui, chacune
     * de ces pages laisserait fuiter « concerts » à qui ne doit pas encore le voir.
     *
     * Les catégories sont stockées en varchar : d'anciens événements peuvent en porter une
     * qui n'est plus dans la liste, on retombe alors sur "divers" plutôt que d'afficher (ou
     * pire, de passer plus loin) une valeur nulle.
     */
    public static function categoryLabel(?string $category): string
    {
        global $glo_tab_genre;

        $category = EventCategory::visible($category, EventCategory::isEnabled());

        return $glo_tab_genre[$category] ?? $glo_tab_genre[self::CATEGORY_DEFAULT] ?? self::CATEGORY_DEFAULT;
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
                'preposition_nom' => $event['l_preposition_nom'] ?? "",
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
                'preposition_nom' => "",
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
     * Nom du fichier miniature à écrire pour une image d'événement.
     *
     * L'extension du format est ajoutée à celle du nom stocké en base plutôt que substituée :
     * la base ne connaît qu'un nom par image, et `s_x.jpg.webp` dit de quel original la
     * miniature sort tout en étant servi en image/webp — Apache ne retient que la dernière
     * extension, et MultiViews est désactivé.
     */
    public static function thumbFileName(string $fileName): string
    {
        return 's_' . $fileName . self::THUMBNAIL_EXTENSION;
    }

    /**
     * Chemin d'affichage de la miniature, avec repli sur les miniatures historiques.
     *
     * Les images antérieures au passage au WebP n'ont pas de `.webp` sur le disque et gardent
     * la miniature écrite à l'époque. Ce repli rend la migration auto-portante : rien à
     * convertir d'avance, les fiches rééditées basculent d'elles-mêmes, et un script de
     * reprise reste possible quand ça arrange.
     *
     * Le test d'existence ne coûte rien de plus : AssetManager::get() ouvre déjà le fichier
     * pour en calculer l'empreinte.
     */
    public static function getThumbFilePath(string $fileName): string
    {
        $webpPath = self::getFilePath(self::thumbFileName($fileName));

        if (is_file(self::getSystemFilePath($webpPath)))
        {
            return $webpPath;
        }

        return self::getFilePath($fileName, 's_');
    }

    /**
     * Copie et suppression suivent le même ordre que l'affichage : le WebP d'abord, la
     * miniature historique ensuite.
     *
     * @return list<string>
     */
    protected static function thumbNameCandidates(string $fileName): array
    {
        return [self::thumbFileName($fileName), 's_' . $fileName];
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

            // Seule `lieu.URL` est nullable parmi les colonnes lues ici, et plusieurs lieux y
            // portent NULL. La colonne `evenement.urlLieu` visée, elle, est NOT NULL DEFAULT '' :
            // le repli produit la valeur attendue, là où le null traversait jusqu'à
            // DbConnector::sanitize(), typé string — erreur fatale à l'enregistrement.
            $champs['urlLieu']     = $tab_lieu['URL'] ?? '';

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
