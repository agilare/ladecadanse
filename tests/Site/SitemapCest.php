<?php

use Tests\Support\SiteTester;

use Codeception\Util\HttpCode;

class SitemapCest
{
    private const string NAMESPACE_SITEMAP = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    // sans effet quand le site testé n'est pas sur cette machine
    public function _before(SiteTester $I): void
    {
        @unlink(__DIR__ . '/../../var/cache/sitemap.xml');
    }

    public function sitemapIsServedAtItsPublicUrl(SiteTester $I)
    {
        $I->amOnPage('/sitemap.xml');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertStringContainsString('xml', $I->grabResponseHeader('Content-Type'));
    }

    public function sitemapIsAWellFormedUrlset(SiteTester $I)
    {
        $document = $this->chargerDocument($I);

        $I->assertSame('urlset', $document->documentElement->localName);
        $I->assertSame(self::NAMESPACE_SITEMAP, $document->documentElement->namespaceURI);
        $I->assertGreaterThan(0, $document->getElementsByTagName('url')->length, 'Le sitemap doit lister des URL');
    }

    public function everyLocationIsAbsoluteAndOnTheSameOrigin(SiteTester $I)
    {
        $origines = [];

        foreach ($this->locations($this->chargerDocument($I)) as $loc)
        {
            $I->assertStringStartsWith('http', $loc, "« $loc » doit être une URL absolue");
            $origines[] = parse_url($loc, PHP_URL_SCHEME) . '://' . parse_url($loc, PHP_URL_HOST);
        }

        $I->assertCount(1, array_unique($origines), 'Toutes les URL doivent être sur la même origine');
    }

    public function locationsAreNotRepeated(SiteTester $I)
    {
        $locations = $this->locations($this->chargerDocument($I));

        $I->assertSame(count($locations), count(array_unique($locations)), 'Aucune URL ne doit figurer deux fois');
    }

    public function sitemapListsThePublicLandingPages(SiteTester $I)
    {
        $chemins = array_map(
            static fn (string $loc): string => (string) parse_url($loc, PHP_URL_PATH),
            $this->locations($this->chargerDocument($I))
        );

        foreach (['/', '/lieu/lieux.php', '/organisateur/organisateurs.php', '/articles/apropos.php'] as $attendu)
        {
            $I->assertContains($attendu, $chemins, "Le sitemap doit lister $attendu");
        }
    }

    public function sitemapOmitsUrlsClosedByRobotsTxt(SiteTester $I)
    {
        $interdits = ['courant=', 'tri_agenda=', 'order=', 'genre=', '/event/search.php', '/admin/', '/annuaire-membres.php'];

        foreach ($this->locations($this->chargerDocument($I)) as $loc)
        {
            foreach ($interdits as $motif)
            {
                $I->assertStringNotContainsString($motif, $loc, "« $loc » est interdite par robots.txt");
            }
        }
    }

    public function everyLastmodIsAValidW3cDate(SiteTester $I)
    {
        $document = $this->chargerDocument($I);
        $lastmods = $document->getElementsByTagNameNS(self::NAMESPACE_SITEMAP, 'lastmod');

        $I->assertGreaterThan(0, $lastmods->length, 'Le sitemap doit dater ses URL');

        foreach ($lastmods as $lastmod)
        {
            $valeur = trim($lastmod->textContent);
            $horodatage = strtotime($valeur);

            $I->assertNotFalse($horodatage, "« $valeur » doit être une date W3C valide");
            $I->assertGreaterThan(strtotime('2000-01-01'), $horodatage, "« $valeur » est une date nulle");
        }
    }

    public function knownEtagYieldsNotModified(SiteTester $I)
    {
        $I->amOnPage('/sitemap.xml');
        $I->seeResponseCodeIs(HttpCode::OK);

        $etag = $I->grabResponseHeader('ETag');
        $I->assertNotEmpty($etag, 'Le sitemap doit émettre un ETag');

        $I->haveHttpHeader('If-None-Match', $etag);
        $I->amOnPage('/sitemap.xml');
        $I->seeResponseCodeIs(HttpCode::NOT_MODIFIED);
        $I->assertSame('', trim($I->grabPageSource()), 'Un 304 ne doit pas avoir de corps');

        $I->deleteHeader('If-None-Match');
    }

    public function freshSitemapCarriesNoSessionCookie(SiteTester $I)
    {
        $I->amOnPage('/sitemap.xml');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertEmpty($I->grabResponseHeader('Set-Cookie'), 'Une réponse publique ne doit pas porter de cookie de session');
    }

    public function fixedPagesOpenWithoutLogin(SiteTester $I)
    {
        $pagesFixes = array_filter(
            $this->locations($this->chargerDocument($I)),
            static fn (string $loc): bool => str_contains($loc, '/articles/')
        );

        $I->stopFollowingRedirects();

        foreach ($pagesFixes as $loc)
        {
            $I->amOnPage((string) parse_url($loc, PHP_URL_PATH));
            $I->seeResponseCodeIs(HttpCode::OK);
        }

        $I->startFollowingRedirects();
    }

    private function chargerDocument(SiteTester $I): DOMDocument
    {
        $I->amOnPage('/sitemap.xml');
        $I->seeResponseCodeIs(HttpCode::OK);

        $document = new DOMDocument();
        $I->assertTrue($document->loadXML($I->grabPageSource()), 'Le sitemap doit être un XML bien formé');

        return $document;
    }

    /**
     * @return string[]
     */
    private function locations(DOMDocument $document): array
    {
        $locations = [];

        foreach ($document->getElementsByTagNameNS(self::NAMESPACE_SITEMAP, 'loc') as $loc)
        {
            $locations[] = trim($loc->textContent);
        }

        return $locations;
    }
}
