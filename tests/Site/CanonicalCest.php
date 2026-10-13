<?php

use Tests\Support\SiteTester;
use Tests\Support\TestEnv;

use Codeception\Util\HttpCode;

/**
 * URL canonique de chaque page (#227). L'hôte dépend de SITE_CANONICAL_URL : seule la fin de l'URL est vérifiée.
 */
class CanonicalCest
{
    private const CANONICAL = 'link[rel=canonical]';

    private function grabCanonical(SiteTester $I): string
    {
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeNumberOfElements(self::CANONICAL, 1);

        return $I->grabAttributeFrom(self::CANONICAL, 'href');
    }

    public function accueilSeCanoniseSurLaRacine(SiteTester $I)
    {
        $I->amOnPage('/');

        $canonical = $this->grabCanonical($I);

        $I->assertStringEndsWith('/', $canonical);
        $I->assertStringNotContainsString('?', $canonical);
    }

    public function accueilIgnoreLesParametresQuiNeChangentRien(SiteTester $I)
    {
        $I->amOnPage('/index.php?utm_source=newsletter&tri_agenda=horaire_debut');

        $canonical = $this->grabCanonical($I);

        $I->assertStringEndsWith('/', $canonical);
        $I->assertStringNotContainsString('?', $canonical);
    }

    public function laCanoniqueNeSuitPasLHoteDeLaRequete(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_CANONICAL_URL');

        $I->amOnPage('/lieu/lieux.php');

        $I->assertStringStartsWith(TestEnv::get('LADECADANSE_SITE_CANONICAL_URL') . '/', $this->grabCanonical($I));
    }

    public function agendaDuJourGardeSaDate(SiteTester $I)
    {
        $jour = (new DateTimeImmutable('+2 months'))->format('Y-m-d');
        $I->amOnPage('/index.php?courant=' . $jour . '&utm_medium=facebook');

        $I->assertStringEndsWith('/index.php?courant=' . $jour, $this->grabCanonical($I));
    }

    public function ficheEvenementGardeSonIdentifiant(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_TEST_EVENT_ID_AUTEUR');

        $idE = TestEnv::getInt('LADECADANSE_TEST_EVENT_ID_AUTEUR');
        $I->amOnPage('/event/evenement.php?idE=' . $idE . '&utm_source=twitter');

        $I->assertStringEndsWith('/event/evenement.php?idE=' . $idE, $this->grabCanonical($I));
    }

    public function ficheLieuGardeSaPeriodeEtSaPage(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_TEST_LIEU_ID');

        $idL = TestEnv::getInt('LADECADANSE_TEST_LIEU_ID');
        $I->amOnPage('/lieu/lieu.php?idL=' . $idL . '&periode=ancien&page=3');

        $I->assertStringEndsWith('/lieu/lieu.php?idL=' . $idL . '&periode=ancien&page=3', $this->grabCanonical($I));
    }

    public function listeDeLieuxSansPaginationSeCanoniseSurElleMeme(SiteTester $I)
    {
        $I->amOnPage('/lieu/lieux.php?order=nom');

        $I->assertStringEndsWith('/lieu/lieux.php', $this->grabCanonical($I));
    }

    public function listeDeLieuxPaginee(SiteTester $I)
    {
        $I->amOnPage('/lieu/lieux.php?page=2&order=nom');

        $I->assertStringEndsWith('/lieu/lieux.php?page=2', $this->grabCanonical($I));
    }

    public function listeDeLieuxGardeSaRegion(SiteTester $I)
    {
        $I->amOnPage('/lieu/lieux.php?region=vd&page=2');

        $I->assertStringEndsWith('/lieu/lieux.php?region=vd&page=2', $this->grabCanonical($I));

        $I->amOnPage('/lieu/lieux.php?region=ge');

        $I->assertStringEndsWith('/lieu/lieux.php', $this->grabCanonical($I));
    }

    public function listeDOrganisateursPaginee(SiteTester $I)
    {
        $I->amOnPage('/organisateur/organisateurs.php?page=3');

        $I->assertStringEndsWith('/organisateur/organisateurs.php?page=3', $this->grabCanonical($I));
    }

    public function pathInfoNeFabriquePasDeNouvellePage(SiteTester $I)
    {
        $I->amOnPage('/articles/apropos.php/xyz');

        $I->assertStringEndsWith('/articles/apropos.php', $this->grabCanonical($I));
    }

    public function pageDErreurNeDeclarePasDeCanonique(SiteTester $I)
    {
        $I->amOnPage('/misc/error.php');

        $I->dontSeeElement(self::CANONICAL);
        $I->dontSeeElement('meta[property="og:url"]');
    }
}
