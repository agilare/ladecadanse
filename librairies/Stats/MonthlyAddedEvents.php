<?php

/*
 * @package ladecadanse
 * @copyright  Copyright (c) 2007 - 2026 Michel Gaudry <michel@ladecadanse.ch>
 * @license    AGPL License; see LICENSE file for details.
 */

namespace Ladecadanse\Stats;

use DateTimeImmutable;
use Ladecadanse\UserLevel;
use PDO;

/**
 * Nombre d'événements ajoutés mois par mois, par lieu ou par organisateur.
 *
 * Sert aux listes d'administration à repérer un membre rattaché qui a cessé d'annoncer : la date
 * du dernier événement ne dit ni le rythme, ni le moment où il s'est arrêté.
 *
 * Ne comptent que les ajouts faits par des membres actifs : les ajouts de l'équipe (groupe <=
 * AUTHOR) mesureraient le travail des administrateurs, pas l'activité du lieu.
 *
 * @author Michel Gaudry <michel@ladecadanse.ch>
 */
class MonthlyAddedEvents
{
    /** @var int nombre de mois affichés, mois en cours compris */
    public const int MONTHS_NB = 12;

    /**
     * Clés de mois, de la plus ancienne à la plus récente, la dernière étant le mois en cours.
     *
     * @return list<string> par exemple ['2025-09', …, '2026-08']
     */
    public static function monthKeys(int $monthsNb = self::MONTHS_NB): array
    {
        $first = self::firstMonth($monthsNb);

        $keys = [];
        for ($i = 0; $i < $monthsNb; $i++)
        {
            $keys[] = $first->modify("+$i months")->format('Y-m');
        }

        return $keys;
    }

    /**
     * @return array<int, array<string, int>> [idLieu => ['2026-08' => 3, …]], mois sans ajout absents
     */
    public static function forLieux(int $monthsNb = self::MONTHS_NB): array
    {
        return self::fetch("e.idLieu", "", $monthsNb);
    }

    /**
     * @return array<int, array<string, int>> [idOrganisateur => ['2026-08' => 3, …]]
     */
    public static function forOrganisateurs(int $monthsNb = self::MONTHS_NB): array
    {
        return self::fetch(
            "eo.idOrganisateur",
            "JOIN evenement_organisateur eo ON e.idEvenement = eo.idEvenement",
            $monthsNb
        );
    }

    /**
     * @param string $idColumn colonne d'agrégation, jamais une saisie utilisateur
     * @param string $joinSql  jointure supplémentaire, jamais une saisie utilisateur
     *
     * @return array<int, array<string, int>>
     */
    private static function fetch(string $idColumn, string $joinSql, int $monthsNb): array
    {
        global $connectorPdo;

        // le statut filtré est celui de la personne et non celui de l'événement : on mesure un
        // geste d'ajout, qu'un administrateur ait dépublié l'événement ensuite n'y change rien.
        // La jointure interne écarte au passage les propositions sans auteur (idPersonne = 0).
        $sql = "SELECT
            $idColumn AS element_id,
            DATE_FORMAT(e.dateAjout, '%Y-%m') AS mois,
            COUNT(*) AS nb
        FROM evenement e
        JOIN personne p ON p.idPersonne = e.idPersonne
        $joinSql
        WHERE e.dateAjout >= :debut
          AND p.groupe > :editor_level
          AND p.statut = 'actif'
        GROUP BY element_id, mois";

        $stmt = $connectorPdo->prepare($sql);
        $stmt->execute([
            // borne au premier jour du mois : elle écarte aussi les vieux '0000-00-00 00:00:00'
            ':debut' => self::firstMonth($monthsNb)->format('Y-m-d H:i:s'),
            ':editor_level' => UserLevel::AUTHOR,
        ]);

        $counts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
        {
            $counts[(int) $row['element_id']][$row['mois']] = (int) $row['nb'];
        }

        return $counts;
    }

    private static function firstMonth(int $monthsNb): DateTimeImmutable
    {
        $offset = $monthsNb - 1;

        return (new DateTimeImmutable('first day of this month'))
            ->setTime(0, 0)
            ->modify("-$offset months");
    }
}
