<?php

declare(strict_types=1);

namespace Ladecadanse\Seo;

use DateTimeImmutable;
use DateTimeInterface;
use Ladecadanse\Dto\RecordDate;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Repository\EvenementRepository;
use Ladecadanse\Repository\LieuRepository;
use Ladecadanse\Repository\OrganisateurRepository;
use XMLWriter;

/**
 * Aucune URL fermée par robots.txt (courant=, tri_agenda=, order=, genre=) ni réservée aux
 * comptes : la Search Console signale l'une et l'autre en erreur.
 */
final readonly class SitemapGenerator
{
    // Sans <lastmod> : la date du fichier change à chaque déploiement, pas la page.
    private const array FIXED_PAGES = [
        '/articles/apropos.php',
        '/articles/annoncerEvenement.php',
        '/articles/charte-editoriale.php',
        '/articles/confidentialite.php',
        '/articles/contributing.php',
        '/articles/faireUnDon.php',
        '/articles/liens.php',
    ];

    public function __construct(
        private EvenementRepository $evenementRepository,
        private LieuRepository $lieuRepository,
        private OrganisateurRepository $organisateurRepository,
        private string $baseUrl,
        private string $projectDir,
    ) {
    }

    public function generate(string $today): string
    {
        $evenements = $this->entries($this->evenementRepository->findUpcomingPublicDates($today), '/event/evenement.php', 'idE');
        $lieux = $this->entries($this->lieuRepository->findPublicDates(), '/lieu/lieu.php', 'idL');
        $organisateurs = $this->entries($this->organisateurRepository->findPublicDates(), '/organisateur/organisateur.php', 'idO');

        return $this->toXml([
            new SitemapEntry('/', $this->latest($evenements)),
            new SitemapEntry('/lieu/lieux.php', $this->latest($lieux)),
            new SitemapEntry('/organisateur/organisateurs.php', $this->latest($organisateurs)),
            ...$this->fixedPages(),
            ...$evenements,
            ...$lieux,
            ...$organisateurs,
        ]);
    }

    // relue dans le XML : servi depuis le cache, le document n'a pas été généré
    public static function contentDate(string $xml): ?int
    {
        preg_match_all('#<lastmod>(.*?)</lastmod>#', $xml, $matches);
        $dates = array_filter(array_map(strtotime(...), $matches[1]));

        return $dates === [] ? null : max($dates);
    }

    /**
     * @param list<RecordDate> $records
     *
     * @return list<SitemapEntry>
     */
    private function entries(array $records, string $script, string $idParam): array
    {
        return array_map(
            static fn (RecordDate $record): SitemapEntry => new SitemapEntry(HtmlShrink::urlCanonique($script, [$idParam => $record->id]), $record->modifiedAt),
            $records
        );
    }

    /**
     * @param list<SitemapEntry> $entries
     */
    private function latest(array $entries): ?DateTimeImmutable
    {
        $dates = array_filter(array_map(static fn (SitemapEntry $entry): ?DateTimeImmutable => $entry->lastModified, $entries));

        return $dates === [] ? null : max($dates);
    }

    /**
     * @return list<SitemapEntry>
     */
    private function fixedPages(): array
    {
        $existing = array_filter(self::FIXED_PAGES, fn (string $path): bool => is_file($this->projectDir . $path));

        return array_map(static fn (string $path): SitemapEntry => new SitemapEntry($path, null), array_values($existing));
    }

    /**
     * @param list<SitemapEntry> $entries
     */
    private function toXml(array $entries): string
    {
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElementNs(null, 'urlset', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($entries as $entry)
        {
            $xml->startElement('url');
            $xml->writeElement('loc', $this->baseUrl . $entry->path);

            if ($entry->lastModified !== null)
            {
                $xml->writeElement('lastmod', $entry->lastModified->format(DateTimeInterface::ATOM));
            }

            $xml->endElement();
        }

        $xml->endElement();

        return $xml->outputMemory();
    }
}
