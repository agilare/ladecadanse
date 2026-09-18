<?php

use Tests\Support\SiteTester;
use Tests\Support\TestEnv;

use Codeception\Util\HttpCode;

/**
 * Ce que les fiches et les listes réservent aux administrateurs (niveau ADMIN, 4).
 *
 * La liste des membres d'un organisateur — pseudos et e-mails — s'affichait à l'auteur de la
 * fiche et à chacun de ses membres. Celle des affiliés d'un lieu s'affichait à tout auteur ; elle
 * n'est pas couverte ici, faute de compte AUTHOR et de lieu affilié parmi les fixtures.
 *
 * La colonne « Note » des listes porte la note d'administration des fiches. Son contenu dépend
 * des données de l'instance et ne s'assure pas ici : seule la garde d'affichage l'est.
 *
 * Lecture seule : ces tests ne font que charger des pages.
 */
class ReserveAuxAdminsCest
{
    /** @var list<string> */
    private const LISTINGS = ['/lieu/lieux.php', '/organisateur/organisateurs.php'];

    /**
     * Témoin du test suivant : sans membre sur la fiche, le <details> n'existe pour personne, et
     * l'absence constatée pour l'acteur ne prouverait rien.
     */
    public function administrateurVoitLesMembresDeLOrganisateur(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ADMIN_USER', 'LADECADANSE_SITE_ADMIN_PASS', 'LADECADANSE_TEST_ORGA_ID_ACTOR_OWN');

        $I->loginAsAdmin();
        $I->amOnPage('/organisateur/organisateur.php?idO=' . TestEnv::getInt('LADECADANSE_TEST_ORGA_ID_ACTOR_OWN'));

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->see('Membres', '#pratique details summary');
    }

    /**
     * Un membre de l'organisateur ne voit plus les autres membres, ni leur e-mail.
     */
    public function membreNeVoitPasLesMembresDeLOrganisateur(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ACTOR_USER', 'LADECADANSE_SITE_ACTOR_PASS', 'LADECADANSE_TEST_ORGA_ID_ACTOR_OWN');

        $I->loginAsActor();
        $I->amOnPage('/organisateur/organisateur.php?idO=' . TestEnv::getInt('LADECADANSE_TEST_ORGA_ID_ACTOR_OWN'));

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeElement('#pratique details');
    }

    public function colonneNoteAbsentePourUnVisiteur(SiteTester $I)
    {
        foreach (self::LISTINGS as $url)
        {
            $I->amOnPage($url);
            $I->seeResponseCodeIs(HttpCode::OK);
            $I->dontSeeElement('th.admin-note');
            $I->dontSeeElement('td.admin-note');
        }
    }

    /**
     * La colonne précède directement celle du premier mois, et chaque ligne a sa cellule, note ou
     * pas : sans elle, les compteurs mensuels se décaleraient d'une colonne sous leur en-tête.
     */
    public function colonneNoteAvantLesMoisPourUnAdministrateur(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ADMIN_USER', 'LADECADANSE_SITE_ADMIN_PASS');

        $I->loginAsAdmin();

        foreach (self::LISTINGS as $url)
        {
            $I->amOnPage($url);
            $I->seeResponseCodeIs(HttpCode::OK);
            $I->seeNumberOfElements('table#derniers_lieux thead th.admin-note', 1);
            $I->seeElement('table#derniers_lieux thead th.admin-note + th.mois');

            $lignesNb = count($I->grabMultiple('table#derniers_lieux tbody tr'));
            $I->assertGreaterThan(0, $lignesNb, "aucune ligne sur $url");
            $I->seeNumberOfElements('table#derniers_lieux tbody td.admin-note', $lignesNb);
        }
    }
}
