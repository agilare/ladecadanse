<?php

use Tests\Support\SiteTester;
use Tests\Support\TestEnv;

use Codeception\Util\HttpCode;

/**
 * Ce que les fiches réservent aux administrateurs (niveau ADMIN, 4).
 *
 * La liste des membres d'un organisateur — pseudos et e-mails — s'affichait à l'auteur de la
 * fiche et à chacun de ses membres. Celle des affiliés d'un lieu s'affichait à tout auteur ; elle
 * n'est pas couverte ici, faute de compte AUTHOR et de lieu affilié parmi les fixtures.
 *
 * Lecture seule : ces tests ne font que charger des pages.
 */
class ReserveAuxAdminsCest
{
    public function _before(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_TEST_ORGA_ID_ACTOR_OWN');
    }

    /**
     * Témoin du test suivant : sans membre sur la fiche, le <details> n'existe pour personne, et
     * l'absence constatée pour l'acteur ne prouverait rien.
     */
    public function administrateurVoitLesMembresDeLOrganisateur(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ADMIN_USER', 'LADECADANSE_SITE_ADMIN_PASS');

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
        $I->skipUnlessConfigured('LADECADANSE_SITE_ACTOR_USER', 'LADECADANSE_SITE_ACTOR_PASS');

        $I->loginAsActor();
        $I->amOnPage('/organisateur/organisateur.php?idO=' . TestEnv::getInt('LADECADANSE_TEST_ORGA_ID_ACTOR_OWN'));

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeElement('#pratique details');
    }
}
