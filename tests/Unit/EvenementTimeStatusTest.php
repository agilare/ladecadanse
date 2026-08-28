<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\EvenementTimeStatus;

/**
 * Couvre la temporalité d'un événement par rapport à l'instant du chargement (#51).
 *
 * L'instant de référence est injecté : sans cela ces cas ne seraient testables qu'en journée.
 */
final class EvenementTimeStatusTest extends Unit
{
    public function testAvantLeDebutCompteARebours(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:30:00', null, '2026-04-28 19:00:00');

        $this->assertNotNull($status);
        $this->assertSame(EvenementTimeStatus::COMING, $status->state);
        $this->assertSame('-2h30', $status->label);
    }

    public function testCompteAReboursSansMinutesResteEnHeures(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:30:00', null, '2026-04-28 19:30:00');

        $this->assertSame('-2h', $status?->label);
    }

    public function testCompteAReboursSousUneHeureEstEnMinutes(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:30:00', null, '2026-04-28 21:00:00');

        $this->assertSame('-30min', $status?->label);
    }

    /** Les minutes sont arrondies vers le haut : la dernière minute n'affiche pas « -0min ». */
    public function testCompteAReboursArrondiVersLeHaut(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:30:00', null, '2026-04-28 21:29:01');

        $this->assertSame('-1min', $status?->label);
    }

    public function testCompteAReboursAuDelaDeVingtQuatreHeuresEstEnJours(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-05-01', '2026-05-01 20:00:00', null, '2026-04-28 18:00:00');

        $this->assertSame('-3j', $status?->label);
    }

    /** Un événement qui se termine après minuit reste rattaché à la journée d'agenda de la veille. */
    public function testPendantLevenementDonneLaPartEcoulee(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:00:00', '2026-04-29 01:00:00', '2026-04-28 22:24:00');

        $this->assertNotNull($status);
        $this->assertSame(EvenementTimeStatus::RUNNING, $status->state);
        $this->assertSame('40 %', $status->label);
    }

    public function testPartEcoulueBorneeADixPourCent(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:00:00', '2026-04-29 01:00:00', '2026-04-28 21:05:00');

        $this->assertSame('10 %', $status?->label);
    }

    public function testPartEcoulueBorneeAQuatreVingtDixPourCent(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:00:00', '2026-04-29 01:00:00', '2026-04-29 00:55:00');

        $this->assertSame('90 %', $status?->label);
    }

    public function testApresLaFinEstTermine(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:00:00', '2026-04-29 01:00:00', '2026-04-29 01:30:00');

        $this->assertNotNull($status);
        $this->assertSame(EvenementTimeStatus::PAST, $status->state);
        $this->assertSame('terminé', $status->label);
    }

    /** Rare mais possible : seule la fin est renseignée, ce qui suffit à savoir que c'est fini. */
    public function testTermineSeDeduitDeLaSeuleFin(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-29 06:00:01', '2026-04-28 23:00:00', '2026-04-29 00:00:00');

        $this->assertSame(EvenementTimeStatus::PAST, $status?->state);
    }

    /** Commencé, fin inconnue : rien à dire plutôt qu'une fin inventée (cf. #65). */
    public function testCommenceSansHoraireDeFinNestPasSituable(): void
    {
        $this->assertNull(EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:00:00', '2026-04-29 06:00:01', '2026-04-28 22:00:00'));
    }

    public function testSansAucunHoraireRienNestSituable(): void
    {
        $this->assertNull(EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-29 06:00:01', '2026-04-29 06:00:01', '2026-04-28 22:00:00'));
        $this->assertNull(EvenementTimeStatus::fromHoraires('2026-04-28', '0000-00-00 00:00:00', '0000-00-00 00:00:00', '2026-04-28 22:00:00'));
        $this->assertNull(EvenementTimeStatus::fromHoraires('2026-04-28', null, null, '2026-04-28 22:00:00'));
    }

    /** Une fin antérieure au début est une donnée cassée : elle ne doit pas rendre l'événement passé. */
    public function testFinAnterieureAuDebutEstIgnoree(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:00:00', '2026-04-28 20:00:00', '2026-04-28 22:00:00');

        $this->assertNull($status);
    }

    /** Une date d'horaire aberrante est ramenée au jour de l'événement, pas prise au mot. */
    public function testDateDhoraireAberranteNeRendPasLevenementPasse(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2019-01-01 20:00:00', null, '2026-04-28 18:00:00');

        $this->assertSame(EvenementTimeStatus::COMING, $status?->state);
        $this->assertSame('-2h', $status->label);
    }

    public function testFromEventLitLaLigneDevenement(): void
    {
        $tab_even = [
            'e_dateEvenement' => '2026-04-28',
            'e_horaire_debut' => '2026-04-28 21:00:00',
            'e_horaire_fin' => '2026-04-29 01:00:00',
        ];

        $status = EvenementTimeStatus::fromEvent($tab_even, '2026-04-28 23:00:00');

        $this->assertSame(EvenementTimeStatus::RUNNING, $status?->state);
        $this->assertSame('50 %', $status->label);
    }
}
