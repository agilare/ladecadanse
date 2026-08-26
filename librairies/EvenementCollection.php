<?php
namespace Ladecadanse;

use Ladecadanse\Evenement;
use Ladecadanse\UserLevel;
use PDO;

class EvenementCollection
{
    /**
     * Colonnes sur lesquelles la liste d'administration accepte d'être triée : les clés sont
     * les valeurs d'URL, les valeurs les expressions SQL. Cette liste blanche est la seule
     * protection du fragment ORDER BY, qui ne peut pas être un paramètre préparé.
     */
    public const array ADMIN_ORDER_COLUMNS = [
        "dateAjout"           => "e.dateAjout",
        "date_derniere_modif" => "e.date_derniere_modif",
        "statut"              => "e.statut",
        "dateEvenement"       => "e.dateEvenement",
        "titre"               => "e.titre",
        "genre"               => "e.genre",
    ];

    /**
     * Supprime un événement et ses images.
     *
     * Ne dit rien : c'est l'appelant qui rend compte, une fois qu'il connaît le sort de toute
     * la série. L'ancienne version écrivait ses messages elle-même, ce qui interdisait toute
     * redirection après coup.
     *
     * @return bool false si la personne n'a pas le droit de supprimer, ou si le DELETE échoue
     */
    public static function deleteEvenement(int $get_idE): bool
    {
        global $authorization;
        global $connector;

        $peutSupprimer = ($authorization->isAuthor("evenement", $_SESSION['SidPersonne'], $get_idE) && $_SESSION['Sgroupe'] <= UserLevel::AUTHOR)
            || $_SESSION['Sgroupe'] == UserLevel::SUPERADMIN;

        if (!$peutSupprimer)
        {
            return false;
        }

        $req_im = $connector->query("SELECT flyer, image FROM evenement WHERE idEvenement=" . $get_idE);
        $val_even = $connector->fetchArray($req_im);

        foreach (['flyer', 'image'] as $colonne)
        {
            if (!empty($val_even[$colonne]))
            {
                Evenement::rmImageAndItsMiniature($val_even[$colonne]);
            }
        }

        return (bool) $connector->query("DELETE FROM evenement WHERE idEvenement=" . $get_idE);
    }

    /**
     * Une page de la liste d'administration : événements filtrés, triés et paginés, avec le
     * lieu, la localité et l'auteur qu'affiche le tableau.
     *
     * @param array{terme?: string, lieu?: string, personne?: string, region?: string} $filtres
     * @return list<array<string, mixed>>
     */
    public static function getForAdmin(array $filtres, string $orderBy, string $orderDir, int $page, int $nbLignes): array
    {
        global $connectorPdo;

        [$where, $params] = self::buildAdminWhere($filtres);

        $colonne = self::ADMIN_ORDER_COLUMNS[$orderBy] ?? self::ADMIN_ORDER_COLUMNS['dateAjout'];
        $sens = strtolower($orderDir) === 'asc' ? 'ASC' : 'DESC';
        $offset = max(0, $page - 1) * $nbLignes;

        $stmt = $connectorPdo->prepare("
        SELECT

          e.genre AS e_genre,
          e.idEvenement AS e_idEvenement,
          e.titre AS e_titre,
          e.statut AS e_statut,
          e.idPersonne AS e_idPersonne,
          e.dateEvenement AS e_dateEvenement,
          e.horaire_debut AS e_horaire_debut,
          e.horaire_fin AS e_horaire_fin,
          e.horaire_complement AS e_horaire_complement,
          e.idLieu AS e_idLieu,
          e.idSalle AS e_idSalle,
          e.nomLieu AS e_nomLieu,
          e.adresse AS e_adresse,
          e.quartier AS e_quartier,
          loc.localite AS e_localite,
          e.region AS e_region,
          e.urlLieu AS e_urlLieu,
          e.dateAjout AS e_dateAjout,
          e.flyer AS e_flyer,
          e.image AS e_image,

          l.nom AS l_nom,
          l.adresse AS l_adresse,
          l.quartier AS l_quartier,
          l.URL AS l_URL,
          lloc.localite AS lloc_localite,
          l.region AS l_region,
          s.nom AS s_nom,

          p.idPersonne AS idPersonne,
          p.pseudo AS pseudo

        FROM evenement e
        LEFT JOIN localite loc ON e.localite_id = loc.id
        LEFT JOIN lieu l ON e.idLieu = l.idLieu
        LEFT JOIN localite lloc ON l.localite_id = lloc.id
        LEFT JOIN salle s ON e.idSalle = s.idSalle
        LEFT JOIN personne p ON e.idPersonne = p.idPersonne
        " . $where . "
        ORDER BY " . $colonne . " " . $sens . "
        LIMIT " . (int) $offset . "," . (int) $nbLignes);

        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Le nombre total d'événements que les mêmes filtres retiennent, pour la pagination.
     *
     * @param array{terme?: string, lieu?: string, personne?: string, region?: string} $filtres
     */
    public static function countForAdmin(array $filtres): int
    {
        global $connectorPdo;

        [$where, $params] = self::buildAdminWhere($filtres);

        $stmt = $connectorPdo->prepare("SELECT COUNT(*) FROM evenement e
            LEFT JOIN personne p ON e.idPersonne = p.idPersonne " . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Les organisateurs des événements donnés, indexés par événement — en une requête plutôt
     * qu'une par ligne du tableau.
     *
     * @param list<int|string> $idsEvenements
     * @return array<int|string, list<array{idOrganisateur: int|string, nom: string, url: string|null}>>
     */
    public static function getOrganisateursParEvenement(array $idsEvenements): array
    {
        global $connectorPdo;

        if ($idsEvenements === [])
        {
            return [];
        }

        [$clauseIn, $paramsIn] = $connectorPdo->buildInClause('eo.idEvenement', $idsEvenements);

        $stmt = $connectorPdo->prepare("SELECT
            eo.idEvenement AS idEvenement,
            o.idOrganisateur AS o_idOrganisateur,
            o.nom AS o_nom,
            o.URL AS o_URL
            FROM evenement_organisateur eo
            JOIN organisateur o ON eo.idOrganisateur = o.idOrganisateur AND $clauseIn
            ORDER BY nom DESC");
        $stmt->execute($paramsIn);

        $par_evenement = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $eo)
        {
            $par_evenement[$eo['idEvenement']][] = [
                'idOrganisateur' => $eo['o_idOrganisateur'],
                'nom' => $eo['o_nom'],
                'url' => $eo['o_URL'],
            ];
        }

        return $par_evenement;
    }

    /**
     * Le WHERE de la liste d'administration et ses paramètres, construits une seule fois pour
     * la requête de comptage comme pour celle de la page : deux conditions écrites deux fois
     * finissent par diverger, et la pagination se met alors à compter autre chose que ce
     * qu'elle affiche.
     *
     * Chaque marqueur est unique, même quand une saisie sert deux fois : avec
     * `EMULATE_PREPARES` à false, réutiliser un marqueur nommé lève un HY093.
     *
     * @param array{terme?: string, lieu?: string, personne?: string, region?: string} $filtres
     * @return array{0: string, 1: array<string, string>}
     */
    private static function buildAdminWhere(array $filtres): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filtres['terme']))
        {
            $conditions[] = "LOWER(e.titre) LIKE LOWER(:terme)";
            $params[':terme'] = "%" . $filtres['terme'] . "%";
        }

        if (!empty($filtres['lieu']))
        {
            $conditions[] = "LOWER(e.nomLieu) LIKE LOWER(:lieu)";
            $params[':lieu'] = "%" . $filtres['lieu'] . "%";
        }

        if (!empty($filtres['personne']))
        {
            $conditions[] = "(p.pseudo LIKE :personne OR p.email LIKE :personne2)";
            $params[':personne'] = "%" . $filtres['personne'] . "%";
            $params[':personne2'] = "%" . $filtres['personne'] . "%";
        }

        if (!empty($filtres['region']))
        {
            $conditions[] = "e.region = :region";
            $params[':region'] = $filtres['region'];
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        return [$where, $params];
    }
}
