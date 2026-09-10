<?php

/*
 * @package ladecadanse
 * @copyright  Copyright (c) 2007 - 2025 Michel Gaudry <michel@ladecadanse.ch>
 * @license    AGPL License; see LICENSE file for details.
 */

namespace Ladecadanse;

use Ladecadanse\Utils\DateHelper;

/**
 * Position d'un événement dans le temps par rapport à l'instant du chargement de la page (#51).
 *
 * Un événement ne peut être situé que si ses horaires le permettent, ce qui est loin d'être
 * toujours le cas (cf. #65) :
 *   - « terminé » demande un horaire de fin ;
 *   - le compte à rebours et la progression demandent un horaire de début.
 * Faute d'horaire de fin, la progression d'un événement commencé est estimée jusqu'à minuit
 * plutôt qu'abandonnée : la barre le dit alors d'un « ? » (endEstimated), ce qui vaut mieux
 * que ne rien afficher là où l'agenda a justement le plus besoin d'un repère.
 * Sans horaire de début exploitable, en revanche, la fabrique rend toujours null.
 */
final class EvenementTimeStatus
{
    /** Pas encore commencé : le libellé est un compte à rebours (« dans 2h30 ») */
    public const string COMING = 'coming';

    /** En cours : le libellé est la part écoulée, arrondie (« 40 % ») */
    public const string RUNNING = 'running';

    /** Fini : le libellé le dit tel quel */
    public const string PAST = 'past';

    /**
     * Au-delà de ce délai, le compte à rebours s'arrondit à l'heure plutôt qu'à dix minutes :
     * savoir qu'un concert est « dans 5h » suffit, « dans 4h50 » ferait croire à une précision
     * que l'horaire annoncé par l'organisateur n'a de toute façon pas.
     */
    private const int COUNTDOWN_HOUR_ROUNDING_FROM_MINUTES = 180;

    /** Pas du compte à rebours en deçà de ce seuil, en minutes. */
    private const int COUNTDOWN_MINUTE_STEP = 10;

    /** Pas de la part écoulée, en points de pourcentage. */
    private const int PROGRESS_STEP = 5;

    /**
     * Genres où l'on ne rejoint pas une séance commencée : la salle est noire, la porte fermée.
     * Passé TOO_LATE_AFTER_START_MINUTES, la carte pâlit comme celle d'un événement terminé.
     */
    private const array GENRES_SEANCE = ['cinéma', 'théâtre'];

    /** Retard au-delà duquel une séance est considérée hors d'atteinte, en minutes. */
    private const int TOO_LATE_AFTER_START_MINUTES = 30;

    private function __construct(
        public readonly string $state,
        public readonly string $label,
        /** Part écoulée en pourcent pour RUNNING, null pour les autres états */
        public readonly ?int $percent = null,
        /** RUNNING sans horaire de fin : la progression est calculée jusqu'à minuit */
        public readonly bool $endEstimated = false,
        /** Séance de ciné ou de théâtre commencée depuis trop longtemps pour qu'on l'y rejoigne */
        public readonly bool $tooLate = false,
    ) {
    }

    /**
     * @param array<string, mixed> $tab_even ligne d'événement préfixée e_, telle que la lisent les listes
     * @param string|null $now ISO datetime, injectable pour les tests ; défaut : maintenant
     */
    public static function fromEvent(array $tab_even, ?string $now = null): ?self
    {
        return self::fromHoraires(
            (string) ($tab_even['e_dateEvenement'] ?? ''),
            isset($tab_even['e_horaire_debut']) ? (string) $tab_even['e_horaire_debut'] : null,
            isset($tab_even['e_horaire_fin']) ? (string) $tab_even['e_horaire_fin'] : null,
            $now,
            isset($tab_even['e_genre']) ? (string) $tab_even['e_genre'] : null
        );
    }

    /**
     * @param string $dateEvenement ISO date (YYYY-MM-DD)
     * @param string|null $horaireDebut ISO datetime, ou null/sentinelle si non renseigné
     * @param string|null $horaireFin ISO datetime, ou null/sentinelle si non renseigné
     * @param string|null $now ISO datetime, injectable pour les tests ; défaut : maintenant
     * @param string|null $genre clé de $glo_tab_genre, qui décide du sort des séances commencées
     */
    public static function fromHoraires(
        string $dateEvenement,
        ?string $horaireDebut,
        ?string $horaireFin,
        ?string $now = null,
        ?string $genre = null
    ): ?self {
        if ($dateEvenement === '')
        {
            return null;
        }

        $debut = DateHelper::horaireInstant($dateEvenement, $horaireDebut);
        $fin = DateHelper::horaireInstant($dateEvenement, $horaireFin);
        $now ??= date('Y-m-d H:i:s');

        // horaires incohérents : une fin avant le début ne dit rien de la temporalité
        if ($debut !== null && $fin !== null && $fin <= $debut)
        {
            $fin = null;
        }

        if ($fin !== null && $now > $fin)
        {
            return new self(self::PAST, 'terminé');
        }

        if ($debut === null)
        {
            return null;
        }

        if ($now < $debut)
        {
            return new self(self::COMING, self::countdownLabel($debut, $now));
        }

        // commencé : sans horaire de fin, la progression court jusqu'à minuit, faute de mieux
        $endEstimated = $fin === null;
        $percent = self::progressPercent($debut, $fin ?? self::midnightAfter($debut), $now);

        return new self(
            self::RUNNING,
            $percent . ' %',
            $percent,
            $endEstimated,
            self::isTooLateToJoin($debut, $now, $genre)
        );
    }

    /**
     * Temps restant avant le début, du plus fin au plus grossier : « dans 40min », « dans 2h30 »,
     * « dans 5h », « dans 3j ».
     *
     * Le pas est de dix minutes, puis d'une heure au-delà de trois heures. Le plancher de dix
     * minutes évite un « dans 0min » qui se lirait comme « c'est commencé » : quand la dernière
     * demi-dizaine de minutes s'égrène, l'horaire affiché juste au-dessus reste la référence.
     */
    private static function countdownLabel(string $debut, string $now): string
    {
        $minutes = (int) ceil((self::timestamp($debut) - self::timestamp($now)) / 60);

        if ($minutes > self::COUNTDOWN_HOUR_ROUNDING_FROM_MINUTES)
        {
            $heures = (int) round($minutes / 60);

            return $heures >= 24
                ? 'dans ' . intdiv($heures, 24) . 'j'
                : 'dans ' . $heures . 'h';
        }

        $minutes = max(
            self::COUNTDOWN_MINUTE_STEP,
            (int) round($minutes / self::COUNTDOWN_MINUTE_STEP) * self::COUNTDOWN_MINUTE_STEP
        );

        if ($minutes >= 60)
        {
            $reste = $minutes % 60;
            return 'dans ' . intdiv($minutes, 60) . 'h' . ($reste > 0 ? sprintf('%02d', $reste) : '');
        }

        return 'dans ' . $minutes . 'min';
    }

    /**
     * Part écoulée de l'événement, arrondie à 5 %.
     *
     * Bornée à [5 %, 95 %] : « 0 % » et « 100 % » se liraient comme « pas commencé » et
     * « terminé », alors que l'événement est justement en cours. La borne haute sert aussi de
     * garde-fou quand la fin est estimée : minuit passé, la barre reste presque pleine sans
     * jamais prétendre que c'est fini.
     */
    private static function progressPercent(string $debut, string $fin, string $now): int
    {
        $ecoule = self::timestamp($now) - self::timestamp($debut);
        $duree = self::timestamp($fin) - self::timestamp($debut);

        $pas = self::PROGRESS_STEP;

        return min(100 - $pas, max($pas, (int) round($ecoule / $duree * 100 / $pas) * $pas));
    }

    /**
     * Trop tard pour rejoindre une séance de ciné ou de théâtre ?
     *
     * Une soirée se rejoint à toute heure, une projection non : passé le premier quart d'heure
     * de retard, l'événement n'est plus une sortie possible mais une information d'archive.
     */
    private static function isTooLateToJoin(string $debut, string $now, ?string $genre): bool
    {
        if (!in_array($genre, self::GENRES_SEANCE, true))
        {
            return false;
        }

        return self::timestamp($now) > self::timestamp($debut) + self::TOO_LATE_AFTER_START_MINUTES * 60;
    }

    /**
     * Le premier minuit qui suit l'instant donné, borne haute des événements sans horaire de fin.
     *
     * « tomorrow midnight » est relatif au jour de $instant, et non au jour courant : une soirée
     * commencée à 21:00 se voit donc estimée sur trois heures, et non sur le reste de la nuit.
     */
    private static function midnightAfter(string $instant): string
    {
        return (new \DateTimeImmutable($instant))->modify('tomorrow midnight')->format('Y-m-d H:i:s');
    }

    private static function timestamp(string $isoDatetime): int
    {
        return (new \DateTimeImmutable($isoDatetime))->getTimestamp();
    }
}
