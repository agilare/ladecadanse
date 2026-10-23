<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use DateTimeImmutable;
use Ladecadanse\Dto\RecordDate;
use Ladecadanse\Repository\EvenementRepository;
use Ladecadanse\Repository\LieuRepository;
use Ladecadanse\Repository\OrganisateurRepository;
use Ladecadanse\Seo\SitemapGenerator;
use SimpleXMLElement;

final class SitemapGeneratorTest extends Unit
{
    private const string BASE_URL = 'https://www.ladecadanse.ch';

    public function testUneFicheEstAnnonceeSurSonUrlCanoniqueAvecSaDate(): void
    {
        $urls = $this->generate(evenements: [new RecordDate(12, new DateTimeImmutable('2026-10-01 12:00:00'))]);

        $this->assertSame((new DateTimeImmutable('2026-10-01 12:00:00'))->format(DATE_ATOM), $urls['/event/evenement.php?idE=12']);
    }

    public function testUneFicheSansDateNEstPasDatee(): void
    {
        $urls = $this->generate(lieux: [new RecordDate(7, null)]);

        $this->assertArrayHasKey('/lieu/lieu.php?idL=7', $urls);
        $this->assertNull($urls['/lieu/lieu.php?idL=7']);
    }

    public function testUneListeEstDateeParSaFicheLaPlusRecente(): void
    {
        $urls = $this->generate(organisateurs: [
            new RecordDate(1, new DateTimeImmutable('2026-09-01 08:00:00')),
            new RecordDate(2, new DateTimeImmutable('2026-10-03 08:00:00')),
            new RecordDate(3, null),
        ]);

        $this->assertSame((new DateTimeImmutable('2026-10-03 08:00:00'))->format(DATE_ATOM), $urls['/organisateur/organisateurs.php']);
    }

    public function testLesPagesFixesNeSontPasDatees(): void
    {
        $urls = $this->generate();

        $this->assertArrayHasKey('/articles/apropos.php', $urls);
        $this->assertNull($urls['/articles/apropos.php']);
    }

    public function testUnePageFixeAbsenteNEstPasAnnoncee(): void
    {
        $urls = $this->generate(projectDir: sys_get_temp_dir());

        $this->assertArrayNotHasKey('/articles/apropos.php', $urls);
    }

    public function testSansAucuneDateLeDocumentNEstPasDate(): void
    {
        $this->assertNull(SitemapGenerator::contentDate($this->generator()->generate('2026-10-16')));
    }

    /**
     * @param list<RecordDate> $evenements
     * @param list<RecordDate> $lieux
     * @param list<RecordDate> $organisateurs
     *
     * @return array<string, string|null> chemin => lastmod
     */
    private function generate(array $evenements = [], array $lieux = [], array $organisateurs = [], ?string $projectDir = null): array
    {
        $urls = [];

        foreach ((new SimpleXMLElement($this->generator($evenements, $lieux, $organisateurs, $projectDir)->generate('2026-10-16')))->url as $url)
        {
            $urls[substr((string) $url->loc, strlen(self::BASE_URL))] = isset($url->lastmod) ? (string) $url->lastmod : null;
        }

        return $urls;
    }

    /**
     * @param list<RecordDate> $evenements
     * @param list<RecordDate> $lieux
     * @param list<RecordDate> $organisateurs
     */
    private function generator(array $evenements = [], array $lieux = [], array $organisateurs = [], ?string $projectDir = null): SitemapGenerator
    {
        $evenementRepository = $this->createStub(EvenementRepository::class);
        $evenementRepository->method('findUpcomingPublicDates')->willReturn($evenements);
        $lieuRepository = $this->createStub(LieuRepository::class);
        $lieuRepository->method('findPublicDates')->willReturn($lieux);
        $organisateurRepository = $this->createStub(OrganisateurRepository::class);
        $organisateurRepository->method('findPublicDates')->willReturn($organisateurs);

        return new SitemapGenerator($evenementRepository, $lieuRepository, $organisateurRepository, self::BASE_URL, $projectDir ?? dirname(__DIR__, 2));
    }
}
