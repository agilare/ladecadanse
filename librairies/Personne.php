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


    public static function getActivesPersonnes(array $filters, string $orderBy = 'dateAjout', string $order = 'asc', ?int $page = null, ?int $nbLignes = null): array
    {
        global $connectorPdo;

        $sql_event = "SELECT
          p.*,
           dateAjout,
          DATE(last_login) AS last_login
          FROM personne p
          WHERE p.statut = 'actif'";

        if (!empty($filters['terme']))
        {
            $sql_event .= " AND (p.pseudo LIKE :terme OR p.email LIKE :terme2)";
        }

        $sql_event .= " ORDER BY $orderBy $order ";

        // TODO
//        $pers_total_page_max = ceil($num_pers_total / $nbLignes);
//        if ($pers_total_page_max > 0 && $page > $pers_total_page_max)
//        {
//            $page = $pers_total_page_max;
//        }

        if (!empty($page))
        {
            $sql_event .= " LIMIT " . (int) (($page - 1) * (int) $nbLignes) . ", " . (int) $nbLignes;
        }

        //echo $sql_event;
        $stmt = $connectorPdo->prepare($sql_event);

        $params = [];
        // TODO: $params = [':statut' => $filters['statut']];

        if (!empty($filters['terme']))
        {
            $params[':terme'] = "%" . $filters['terme'] . "%";
            $params[':terme2'] = "%" . $filters['terme'] . "%";
        }

        //$params[':orderBy'] = $orderBy;
//dump($params);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
