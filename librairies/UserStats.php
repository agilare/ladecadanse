<?php

namespace Ladecadanse;

use DateTimeImmutable;
use PDO;

/**
 * Reporting figures for the users list (admin/users.php): who contributes, who stopped, and
 * which venues and organizers live on the agenda without an account to look after them.
 *
 * Contribution is measured by evenement.dateAjout, the day an event was entered, not by its
 * date: an event entered in March for December says the account was active in March.
 */
class UserStats
{
    /**
     * Distinct accounts that entered at least one event, month by month, current month included.
     *
     * Months without any entry are present with zeros, so that a chart keeps one bar per month.
     *
     * @return list<array{month: string, contributors: int, events: int}> month as 'YYYY-MM', oldest first
     */
    public static function monthlyContributors(int $months = 12, ?DateTimeImmutable $now = null): array
    {
        global $connectorPdo;

        $firstMonth = ($now ?? new DateTimeImmutable())->modify('first day of this month')->setTime(0, 0)->modify('-' . ($months - 1) . ' months');

        $stmt = $connectorPdo->prepare("SELECT
            DATE_FORMAT(e.dateAjout, '%Y-%m') AS month,
            COUNT(DISTINCT e.idPersonne) AS contributors,
            COUNT(*) AS events
            FROM evenement e
            WHERE e.dateAjout >= :from AND e.idPersonne > 0
            GROUP BY month");
        $stmt->execute([':from' => $firstMonth->format('Y-m-d H:i:s')]);
        $rows = $stmt->fetchAll(PDO::FETCH_UNIQUE);

        $result = [];
        for ($i = 0; $i < $months; $i++)
        {
            $month = $firstMonth->modify("+$i months")->format('Y-m');
            $result[] = [
                'month' => $month,
                'contributors' => (int) ($rows[$month]['contributors'] ?? 0),
                'events' => (int) ($rows[$month]['events'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Active accounts that were regular contributors and have entered nothing for a while.
     *
     * Regular: at least $minEvents events entered during the $windowMonths that precede the
     * silence. Accounts that stopped long before that window are not listed: they are already
     * gone, the list is about those still worth a reminder. Team accounts (ADMIN and above) are
     * left out: their silence calls for no reminder.
     *
     * @return list<array{idPersonne: int, pseudo: string, email: string, events_nb: int, last_added: string}>
     */
    public static function lapsedContributors(int $minEvents = 10, int $silentMonths = 6, int $windowMonths = 12, int $limit = 20, ?DateTimeImmutable $now = null): array
    {
        global $connectorPdo;

        $silentSince = ($now ?? new DateTimeImmutable())->modify("-$silentMonths months");
        $windowStart = $silentSince->modify("-$windowMonths months");

        // every counted entry is in the window: the lower bound is in WHERE, the upper one in HAVING
        $stmt = $connectorPdo->prepare("SELECT
            p.idPersonne, p.pseudo, p.email,
            COUNT(*) AS events_nb,
            MAX(e.dateAjout) AS last_added
            FROM evenement e
            JOIN personne p ON p.idPersonne = e.idPersonne AND p.statut = 'actif' AND p.groupe > :team_level
            WHERE e.dateAjout >= :from
            GROUP BY p.idPersonne
            HAVING MAX(e.dateAjout) < :silent_since AND COUNT(*) >= :min_events
            ORDER BY events_nb DESC
            LIMIT :limit");
        $stmt->bindValue(':from', $windowStart->format('Y-m-d H:i:s'));
        $stmt->bindValue(':silent_since', $silentSince->format('Y-m-d H:i:s'));
        $stmt->bindValue(':min_events', $minEvents, PDO::PARAM_INT);
        $stmt->bindValue(':team_level', UserLevel::ADMIN, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Active venues with recent events but no active account affiliated to them.
     *
     * Their events are entered by the team or by third parties: candidates for an invitation.
     * Sorted by number of events, the whole list (a few hundred venues at most).
     *
     * @return list<array{idLieu: int, nom: string, events_nb: int}>
     */
    public static function venuesWithoutAccount(int $months = 12, ?DateTimeImmutable $now = null): array
    {
        global $connectorPdo;

        $stmt = $connectorPdo->prepare("SELECT l.idLieu, l.nom, COUNT(*) AS events_nb
            FROM evenement e
            JOIN lieu l ON l.idLieu = e.idLieu AND l.statut = 'actif'
            WHERE e.dateEvenement >= :from
              AND e.statut NOT IN ('inactif', 'propose')
              AND NOT EXISTS (
                SELECT 1 FROM affiliation a
                JOIN personne p ON p.idPersonne = a.idPersonne AND p.statut = 'actif'
                WHERE a.idAffiliation = l.idLieu AND a.genre = 'lieu')
            GROUP BY l.idLieu
            ORDER BY events_nb DESC, l.nom");
        $stmt->execute([':from' => self::monthsAgo($months, $now)]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Active organizers with recent events but no active account attached to them.
     *
     * @see venuesWithoutAccount()
     * @return list<array{idOrganisateur: int, nom: string, events_nb: int}>
     */
    public static function organizersWithoutAccount(int $months = 12, ?DateTimeImmutable $now = null): array
    {
        global $connectorPdo;

        $stmt = $connectorPdo->prepare("SELECT o.idOrganisateur, o.nom, COUNT(*) AS events_nb
            FROM evenement e
            JOIN evenement_organisateur eo ON eo.idEvenement = e.idevenement
            JOIN organisateur o ON o.idOrganisateur = eo.idOrganisateur AND o.statut = 'actif'
            WHERE e.dateEvenement >= :from
              AND e.statut NOT IN ('inactif', 'propose')
              AND NOT EXISTS (
                SELECT 1 FROM personne_organisateur po
                JOIN personne p ON p.idPersonne = po.idPersonne AND p.statut = 'actif'
                WHERE po.idOrganisateur = o.idOrganisateur)
            GROUP BY o.idOrganisateur
            ORDER BY events_nb DESC, o.nom");
        $stmt->execute([':from' => self::monthsAgo($months, $now)]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function monthsAgo(int $months, ?DateTimeImmutable $now): string
    {
        return ($now ?? new DateTimeImmutable())->modify("-$months months")->format('Y-m-d');
    }
}
