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
        $this->assertSame('dans 2h30', $status->label);
    }

    public function testCompteAReboursSansMinutesResteEnHeures(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:30:00', null, '2026-04-28 19:30:00');

        $this->assertSame('dans 2h', $status?->label);
    }

    public function testCompteAReboursSousUneHeureEstEnMinutes(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:30:00', null, '2026-04-28 21:00:00');

        $this->assertSame('dans 30min', $status?->label);
    }

    /** Le pas est de dix minutes, à l'arrondi le plus proche : 44 minutes se disent « 40min ». */
    public function testCompteAReboursArrondiADixMinutes(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:00:00', null, '2026-04-28 20:16:00');
        $this->assertSame('dans 40min', $status?->label);

        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:00:00', null, '2026-04-28 20:14:00');
        $this->assertSame('dans 50min', $status?->label);
    }

    /** Le plancher de dix minutes : la dernière poignée de minutes n'affiche pas « dans 0min ». */
    public function testCompteAReboursNeDescendPasSousDixMinutes(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:30:00', null, '2026-04-28 21:29:01');

        $this->assertSame('dans 10min', $status?->label);
    }

    /** Trois heures pile relèvent encore du pas de dix minutes. */
    public function testCompteAReboursDeTroisHeuresResteAuPasDeDixMinutes(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:00:00', null, '2026-04-28 18:00:00');
        $this->assertSame('dans 3h', $status?->label);

        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:00:00', null, '2026-04-28 18:20:00');
        $this->assertSame('dans 2h40', $status?->label);
    }

    /** Au-delà de trois heures, le pas passe à l'heure : plus de minutes affichées. */
    public function testCompteAReboursAuDelaDeTroisHeuresEstEnHeuresEntieres(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:00:00', null, '2026-04-28 16:50:00');
        $this->assertSame('dans 4h', $status?->label);

        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:00:00', null, '2026-04-28 17:47:00');
        $this->assertSame('dans 3h', $status?->label);
    }

    public function testCompteAReboursAuDelaDeVingtQuatreHeuresEstEnJours(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-05-01', '2026-05-01 20:00:00', null, '2026-04-28 18:00:00');

        $this->assertSame('dans 3j', $status?->label);
    }

    /** Un événement qui se termine après minuit reste rattaché à la journée d'agenda de la veille. */
    public function testPendantLevenementDonneLaPartEcoulee(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:00:00', '2026-04-29 01:00:00', '2026-04-28 22:24:00');

        $this->assertNotNull($status);
        $this->assertSame(EvenementTimeStatus::RUNNING, $status->state);
        $this->assertSame('35 %', $status->label);
        $this->assertSame(35, $status->percent);
        $this->assertFalse($status->endEstimated);
    }

    /** Le pas est de 5 % : 12,5 % de la soirée écoulés se disent « 15 % », et non « 10 % ». */
    public function testPartEcouleeArrondieACinqPourCent(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:00:00', '2026-04-29 01:00:00', '2026-04-28 21:30:00');

        $this->assertSame(15, $status?->percent);
    }

    public function testPartEcouleeBorneeACinqPourCent(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:00:00', '2026-04-29 01:00:00', '2026-04-28 21:05:00');

        $this->assertSame('5 %', $status?->label);
    }

    public function testPartEcouleeBorneeAQuatreVingtQuinzePourCent(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:00:00', '2026-04-29 01:00:00', '2026-04-29 00:55:00');

        $this->assertSame('95 %', $status?->label);
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

    /**
     * Commencé, fin inconnue (cf. #65) : la progression est estimée jusqu'à minuit et signalée
     * comme telle, plutôt que passée sous silence.
     */
    public function testCommenceSansHoraireDeFinEstEstimeJusquaMinuit(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:00:00', '2026-04-29 06:00:01', '2026-04-28 22:00:00');

        $this->assertNotNull($status);
        $this->assertSame(EvenementTimeStatus::RUNNING, $status->state);
        $this->assertTrue($status->endEstimated);
        // une heure écoulée sur les trois qui séparent le début de minuit
        $this->assertSame(35, $status->percent);
    }

    /** Minuit passé, la fin restant inconnue, la barre plafonne sans jamais dire « terminé ». */
    public function testEstimationApresMinuitPlafonneSansDireTermine(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:00:00', null, '2026-04-29 03:00:00');

        $this->assertSame(EvenementTimeStatus::RUNNING, $status?->state);
        $this->assertSame(95, $status->percent);
    }

    public function testSansHoraireDeDebutRienNestSituable(): void
    {
        $this->assertNull(EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-29 06:00:01', '2026-04-29 06:00:01', '2026-04-28 22:00:00'));
        $this->assertNull(EvenementTimeStatus::fromHoraires('2026-04-28', '0000-00-00 00:00:00', '0000-00-00 00:00:00', '2026-04-28 22:00:00'));
        $this->assertNull(EvenementTimeStatus::fromHoraires('2026-04-28', null, null, '2026-04-28 22:00:00'));
    }

    /** Une fin antérieure au début est une donnée cassée : elle ne doit pas rendre l'événement passé. */
    public function testFinAnterieureAuDebutEstIgnoree(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:00:00', '2026-04-28 20:00:00', '2026-04-28 22:00:00');

        $this->assertSame(EvenementTimeStatus::RUNNING, $status?->state);
        $this->assertTrue($status->endEstimated);
    }

    /** Une date d'horaire aberrante est ramenée au jour de l'événement, pas prise au mot. */
    public function testDateDhoraireAberranteNeRendPasLevenementPasse(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2019-01-01 20:00:00', null, '2026-04-28 18:00:00');

        $this->assertSame(EvenementTimeStatus::COMING, $status?->state);
        $this->assertSame('dans 2h', $status->label);
    }

    /** Une séance de ciné commencée depuis plus d'une demi-heure ne se rattrape plus. */
    public function testSeanceCommenceeDepuisPlusDeTrenteMinutesEstTropTard(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:00:00', '2026-04-28 23:00:00', '2026-04-28 21:45:00', 'cinéma');

        $this->assertSame(EvenementTimeStatus::RUNNING, $status?->state);
        $this->assertTrue($status->tooLate);
    }

    public function testSeanceCommenceeDepuisMoinsDeTrenteMinutesResteAtteignable(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:00:00', '2026-04-28 23:00:00', '2026-04-28 21:20:00', 'théâtre');

        $this->assertFalse($status?->tooLate);
    }

    /** Une soirée se rejoint à toute heure : le retard ne la met pas hors d'atteinte. */
    public function testUneFeteCommenceeResteAtteignable(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:00:00', '2026-04-29 04:00:00', '2026-04-29 01:00:00', 'fête');

        $this->assertFalse($status?->tooLate);
    }

    /** Sans genre connu, on ne présume pas d'une séance : l'événement reste atteignable. */
    public function testSansGenreLevenementResteAtteignable(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:00:00', '2026-04-28 23:00:00', '2026-04-28 22:00:00');

        $this->assertFalse($status?->tooLate);
    }

    /** Un ciné terminé est terminé : « trop tard » ne concerne que les séances en cours. */
    public function testUneSeanceTermineeNestPasMarqueeTropTard(): void
    {
        $status = EvenementTimeStatus::fromHoraires('2026-04-28', '2026-04-28 21:00:00', '2026-04-28 23:00:00', '2026-04-28 23:30:00', 'cinéma');

        $this->assertSame(EvenementTimeStatus::PAST, $status?->state);
        $this->assertFalse($status->tooLate);
    }

    public function testFromEventLitLaLigneDevenement(): void
    {
        $tab_even = [
            'e_dateEvenement' => '2026-04-28',
            'e_horaire_debut' => '2026-04-28 21:00:00',
            'e_horaire_fin' => '2026-04-29 01:00:00',
            'e_genre' => 'cinéma',
        ];

        $status = EvenementTimeStatus::fromEvent($tab_even, '2026-04-28 23:00:00');

        $this->assertSame(EvenementTimeStatus::RUNNING, $status?->state);
        $this->assertSame('50 %', $status->label);
        $this->assertTrue($status->tooLate);
    }
}
