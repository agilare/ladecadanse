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
 *   - le compte à rebours demande un horaire de début ;
 *   - la progression demande les deux.
 * Hors de ces cas — un événement commencé dont on ignore la fin, notamment — la fabrique rend
 * null : mieux vaut ne rien afficher qu'inventer une fin de soirée.
 */
final class EvenementTimeStatus
{
    /** Pas encore commencé : le libellé est un compte à rebours (« -2h30 ») */
    public const string COMING = 'coming';

    /** En cours : le libellé est la part écoulée, arrondie (« 40 % ») */
    public const string RUNNING = 'running';

    /** Fini : le libellé le dit tel quel */
    public const string PAST = 'past';

    private function __construct(
        public readonly string $state,
        public readonly string $label,
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
            $now
        );
    }

    /**
     * @param string $dateEvenement ISO date (YYYY-MM-DD)
     * @param string|null $horaireDebut ISO datetime, ou null/sentinelle si non renseigné
     * @param string|null $horaireFin ISO datetime, ou null/sentinelle si non renseigné
     * @param string|null $now ISO datetime, injectable pour les tests ; défaut : maintenant
     */
    public static function fromHoraires(
        string $dateEvenement,
        ?string $horaireDebut,
        ?string $horaireFin,
        ?string $now = null
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

        if ($debut !== null && $now < $debut)
        {
            return new self(self::COMING, self::countdownLabel($debut, $now));
        }

        // commencé : reste à savoir où on en est, ce que seule la fin permet de dire
        if ($debut !== null && $fin !== null)
        {
            return new self(self::RUNNING, self::progressLabel($debut, $fin, $now));
        }

        return null;
    }

    /**
     * Temps restant avant le début, du plus fin au plus grossier : « -45min », « -2h30 », « -3j ».
     * Les minutes sont arrondies vers le haut pour ne jamais afficher « -0min ».
     */
    private static function countdownLabel(string $debut, string $now): string
    {
        $minutes = (int) ceil((self::timestamp($debut) - self::timestamp($now)) / 60);

        if ($minutes >= 1440)
        {
            return '-' . intdiv($minutes, 1440) . 'j';
        }

        if ($minutes >= 60)
        {
            $reste = $minutes % 60;
            return '-' . intdiv($minutes, 60) . 'h' . ($reste > 0 ? sprintf('%02d', $reste) : '');
        }

        return '-' . $minutes . 'min';
    }

    /**
     * Part écoulée de l'événement, arrondie à 10 %.
     *
     * Bornée à [10 %, 90 %] : « 0 % » et « 100 % » se liraient comme « pas commencé » et
     * « terminé », alors que l'événement est justement en cours.
     */
    private static function progressLabel(string $debut, string $fin, string $now): string
    {
        $ecoule = self::timestamp($now) - self::timestamp($debut);
        $duree = self::timestamp($fin) - self::timestamp($debut);

        $pourcent = min(90, max(10, (int) round($ecoule / $duree * 10) * 10));

        return $pourcent . ' %';
    }

    private static function timestamp(string $isoDatetime): int
    {
        return (new \DateTimeImmutable($isoDatetime))->getTimestamp();
    }
}
