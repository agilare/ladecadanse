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
 * Faute d'horaire de fin, la durée d'un événement est estimée selon son genre plutôt
 * qu'abandonnée : la barre le dit alors d'un « ? » (endEstimated), ce qui vaut mieux que ne
 * rien afficher là où l'agenda a justement le plus besoin d'un repère.
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
     * Passé TOO_LATE_AFTER_START_MINUTES, la carte pâlit ; faute d'horaire de fin, la séance
     * dure SEANCE_ESTIMATED_DURATION_MINUTES.
     */
    private const array GENRES_SEANCE = ['cinéma', 'théâtre'];

    /** Retard au-delà duquel une séance est considérée hors d'atteinte, en minutes. */
    private const int TOO_LATE_AFTER_START_MINUTES = 60;

    /** Durée prêtée à une séance dont l'horaire de fin manque, en minutes. */
    private const int SEANCE_ESTIMATED_DURATION_MINUTES = 120;

    private function __construct(
        public readonly string $state,
        public readonly string $label,
        /** Part écoulée en pourcent : 0 avant le début, [5, 95] en cours, null une fois terminé */
        public readonly ?int $percent = null,
        /** Durée de l'événement en minutes, fin estimée comprise ; null une fois terminé */
        public readonly ?int $durationMinutes = null,
        /** Fin de l'événement (ISO datetime), réelle ou estimée ; null une fois terminé */
        public readonly ?string $end = null,
        /** Faute d'horaire de fin, durée et part écoulée reposent sur une fin estimée */
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
     * @param string|null $genre clé d'EventCategory::ALL, qui décide du sort des séances
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

        // seule une fin réelle dit « terminé » : une fin estimée dépassée ne prouve rien
        if ($fin !== null && $now > $fin)
        {
            return new self(self::PAST, 'terminé');
        }

        if ($debut === null)
        {
            return null;
        }

        $endEstimated = $fin === null;
        $fin ??= self::estimatedEnd($dateEvenement, $debut, $genre);
        $durationMinutes = intdiv(self::timestamp($fin) - self::timestamp($debut), 60);

        if ($now < $debut)
        {
            return new self(
                self::COMING,
                self::countdownLabel($debut, $now),
                0,
                $durationMinutes,
                $fin,
                $endEstimated
            );
        }

        $percent = self::progressPercent($debut, $fin, $now);

        return new self(
            self::RUNNING,
            $percent . ' %',
            $percent,
            $durationMinutes,
            $fin,
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
     * garde-fou quand la fin est estimée : l'estimation dépassée, la barre reste presque pleine
     * sans jamais prétendre que c'est fini.
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
     * Une soirée se rejoint à toute heure, une projection non : passée la première heure,
     * l'événement n'est plus une sortie possible mais une information d'archive.
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
     * La fin prêtée à un événement dont l'horaire de fin manque (#65).
     *
     * Une séance de ciné ou de théâtre dure deux heures. Tout le reste — fêtes, concerts, divers,
     * et par défaut les genres sans règle propre — court jusqu'au minuit qui clôt la soirée de la
     * journée d'agenda. Commencé après ce minuit (00:30, rattaché à la veille), un événement n'en
     * a plus devant lui : il court alors jusqu'à la fin de la journée d'agenda, 06:00, plutôt que
     * jusqu'au minuit suivant, près d'un jour plus tard. Un début plus tardif encore est un horaire
     * aberrant ; il reçoit la durée d'une séance, ce qui garantit au moins une fin après le début.
     *
     * @return string ISO datetime, toujours postérieur à $debut
     */
    private static function estimatedEnd(string $dateEvenement, string $debut, ?string $genre): string
    {
        if (in_array($genre, self::GENRES_SEANCE, true))
        {
            return self::addMinutes($debut, self::SEANCE_ESTIMATED_DURATION_MINUTES);
        }

        $lendemain = DateHelper::isoToNextDay(mb_substr($dateEvenement, 0, 10));

        foreach ([$lendemain . ' 00:00:00', $lendemain . ' ' . DateHelper::AGENDA_DAY_END_TIME] as $borne)
        {
            if ($borne > $debut)
            {
                return $borne;
            }
        }

        return self::addMinutes($debut, self::SEANCE_ESTIMATED_DURATION_MINUTES);
    }

    private static function addMinutes(string $isoDatetime, int $minutes): string
    {
        return (new \DateTimeImmutable($isoDatetime))->modify('+' . $minutes . ' minutes')->format('Y-m-d H:i:s');
    }

    private static function timestamp(string $isoDatetime): int
    {
        return (new \DateTimeImmutable($isoDatetime))->getTimestamp();
    }
}
