<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\EvenementRenderer;

/**
 * Couvre la valeur de la propriété hCalendar dtstart, que Google Search Console
 * signalait comme non conforme à la norme ISO 8601.
 */
final class EvenementRendererTest extends Unit
{
    public function testDtstartIsoGardeDateEtHeureDeDebut(): void
    {
        $this->assertSame('2026-04-28T21:30:00', EvenementRenderer::dtstartIso('2026-04-28', '2026-04-28 21:30:00'));
    }

    /**
     * La journée d'agenda court jusqu'à 6h du matin : un événement qui commence à 2h
     * est rattaché à la veille, mais son dtstart réel est bien le lendemain.
     */
    public function testDtstartIsoSuitLeLendemainQuandLevenementCommenceApresMinuit(): void
    {
        $this->assertSame('2026-04-29T02:00:00', EvenementRenderer::dtstartIso('2026-04-28', '2026-04-29 02:00:00'));
    }

    public function testDtstartIsoSansHoraireRendLaDateSeule(): void
    {
        // sentinelle « sans horaire » : lendemain 06:00:01
        $this->assertSame('2026-04-28', EvenementRenderer::dtstartIso('2026-04-28', '2026-04-29 06:00:01'));
        // même sentinelle, variante posée le jour même (cf. resources/database/evenement-fix-horaires.sql)
        $this->assertSame('2026-04-28', EvenementRenderer::dtstartIso('2026-04-28', '2026-04-28 06:00:01'));
    }

    public function testDtstartIsoToleredonneesAbsentes(): void
    {
        $this->assertSame('2026-04-28', EvenementRenderer::dtstartIso('2026-04-28', '0000-00-00 00:00:00'));
        $this->assertSame('2026-04-28', EvenementRenderer::dtstartIso('2026-04-28', ''));
        $this->assertSame('2026-04-28', EvenementRenderer::dtstartIso('2026-04-28', null));
    }

    /** Une date d'horaire aberrante ne doit pas déplacer l'événement dans le temps. */
    public function testDtstartIsoIgnoreUneDateDhoraireIncoherente(): void
    {
        $this->assertSame('2026-04-28T20:00:00', EvenementRenderer::dtstartIso('2026-04-28', '2019-01-01 20:00:00'));
    }

    public function testDtstartIsoAccepteUneDateEvenementDatetime(): void
    {
        $this->assertSame('2026-04-28T21:30:00', EvenementRenderer::dtstartIso('2026-04-28 00:00:00', '2026-04-28 21:30:00'));
    }

    /**
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function evenementsProvider(): array
    {
        return [
            'avec horaire'      => ['2026-04-28', '2026-04-28 21:30:00'],
            'après minuit'      => ['2026-04-28', '2026-04-29 02:00:00'],
            'sans horaire'      => ['2026-04-28', '2026-04-29 06:00:01'],
            'horaire manquant'  => ['2026-04-28', '0000-00-00 00:00:00'],
        ];
    }

    /**
     * @dataProvider evenementsProvider
     */
    public function testDtstartIsoEstToujoursParsableCommeIso8601(string $dateEvenement, ?string $horaireDebut): void
    {
        $dtstart = EvenementRenderer::dtstartIso($dateEvenement, $horaireDebut);
        $format = mb_strlen($dtstart) === 10 ? 'Y-m-d' : 'Y-m-d\TH:i:s';

        $this->assertInstanceOf(\DateTimeImmutable::class, \DateTimeImmutable::createFromFormat($format, $dtstart));
        $this->assertSame([], \DateTimeImmutable::getLastErrors() ?: []);
    }

    /**
     * Colonne « par » des listes d'administration.
     */
    public function testAuthorLinkHtmlRendLeLienVersLaFiche(): void
    {
        $this->assertSame(
            '<a href="/user/dashboard.php?idP=42" title="michel">michel</a>',
            EvenementRenderer::authorLinkHtml(42, 'michel')
        );
    }

    /**
     * Un événement proposé sans compte n'a pas d'auteur : la colonne rendait quand même
     * un lien, vers `idP=0`, sans libellé ni infobulle.
     */
    public function testAuthorLinkHtmlSansAuteurNeRendAucunLien(): void
    {
        foreach ([[0, null], [0, ''], [0, 'restant'], [42, null], [42, '']] as [$idPersonne, $pseudo])
        {
            $html = EvenementRenderer::authorLinkHtml($idPersonne, $pseudo);

            $this->assertSame('anonyme', $html);
            $this->assertStringNotContainsString('<a', $html);
        }
    }

    /**
     * Le texte visible est coupé — un seul pseudo long élargissait toute la colonne —
     * mais le pseudo entier reste dans l'infobulle.
     */
    public function testAuthorLinkHtmlCoupeLeTexteEtGardeLePseudoEnInfobulle(): void
    {
        $html = EvenementRenderer::authorLinkHtml(42, 'michelangelo', 10);

        $this->assertStringContainsString('title="michelangelo"', $html);
        $this->assertStringContainsString('>michelange…<', $html);
    }

    public function testAuthorLinkHtmlEchappeLePseudo(): void
    {
        $html = EvenementRenderer::authorLinkHtml(42, '<script>x</script>');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
