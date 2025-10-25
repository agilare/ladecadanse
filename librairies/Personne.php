<?php

/*
 * @package ladecadanse
 * @copyright  Copyright (c) 2007 - 2025 Michel Gaudry <michel@ladecadanse.ch>
 * @license    AGPL License; see LICENSE file for details.
 */

namespace Ladecadanse;

use PDO;

/**
 * @author Michel Gaudry <michel@ladecadanse.ch>
 */
class Personne
{
    public static $statuts = ['demande', 'actif', 'inactif'];

    public const int LOW_ACTIVITY_MONTHS_NB = 12;
    public const int VERY_LOW_ACTIVITY_MONTHS_NB = 24;

    public static function getPersonnesOfOrganisateur(int $idOrga): array
    {
        global $connectorPdo;

        $stmt = $connectorPdo->prepare("SELECT p.idPersonne AS idPersonne, pseudo, p.email AS email
            FROM personne_organisateur po
            JOIN personne p ON po.idPersonne = p.idPersonne
            WHERE po.idOrganisateur=?");
        $stmt->execute([$idOrga]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public static function getPersonnes(array $filters, string $orderBy = 'dateAjout', string $orderDir = 'DESC', ?int $page = null, ?int $nbLignes = null): array
    {
        global $connectorPdo;

        // TODO: $params = [':statut' => $filters['statut']];
        $params = [];

        $where = '';
        if (!empty($filters['terme']))
        {
            $where = " WHERE (p.pseudo LIKE :terme OR p.email LIKE :terme2)";
            $params[':terme'] = "%" . $filters['terme'] . "%";
            $params[':terme2'] = "%" . $filters['terme'] . "%";
        }

        $limit = '';
        if (!empty($page))
        {
            $limit = " LIMIT " . (int) (($page - 1) * (int) $nbLignes) . ", " . (int) $nbLignes;
        }

        // TODO: sanitize $orderBy $orderDir
        // FIXME: replace left join with affiliation and lieu temp; create a separate query
        $sql_event = "SELECT
          p.*,
          l.idLieu AS idLieu,
          l.nom AS l_nom,
          DATE(p.dateAjout) AS dateAjout,
          DATE(p.last_login) AS last_login
          FROM personne p
          LEFT JOIN affiliation a ON p.idPersonne = a.idPersonne
          LEFT JOIN lieu l ON a.idAffiliation = l.idLieu
          $where ORDER BY $orderBy $orderDir $limit
           ";

        //echo $sql_event;
        $stmt = $connectorPdo->prepare($sql_event);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
