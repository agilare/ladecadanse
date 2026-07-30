<?php

use Tests\Support\SiteTester;
use Tests\Support\TestEnv;

use Codeception\Util\HttpCode;

/**
 * Bloc d'autorisation d'édition d'un événement (evenement-edit.php:85-100).
 *
 * Son SELECT a été étendu par l'issue #149 (idPersonne, user_email, dateAjout) :
 * une régression y serait à la fois plausible et coûteuse. Tests en lecture seule.
 */
class EvenementEditPermissionsCest
{
    public function _before(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_TEST_EVENT_ID_AUTEUR');
    }

    /**
     * Un visiteur non connecté n'édite rien : 403 et message d'erreur, avant même
     * l'inclusion du header (le formulaire n'est jamais rendu).
     */
    public function anonymousCannotEdit(SiteTester $I)
    {
        $I->amOnPage($this->editUrl(TestEnv::getInt('LADECADANSE_TEST_EVENT_ID_AUTEUR')));

        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->see("n'avez pas les droits suffisants");
        $I->dontSeeElement('#ajouter_editer');
    }

    /**
     * Un acteur ne peut pas éditer l'événement d'un autre : ni auteur, ni groupe <= 6,
     * ni affilié au lieu ou à un organisateur de l'événement.
     */
    public function actorCannotEditForeignEvent(SiteTester $I)
    {
        $I->skipUnlessConfigured(
            'LADECADANSE_SITE_ACTOR_USER',
            'LADECADANSE_SITE_ACTOR_PASS',
            'LADECADANSE_TEST_EVENT_ID_FOREIGN'
        );

        $I->loginAsActor();
        $I->amOnPage($this->editUrl(TestEnv::getInt('LADECADANSE_TEST_EVENT_ID_FOREIGN')));

        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->see("n'avez pas les droits suffisants");
        $I->dontSeeElement('#ajouter_editer');
    }

    /**
     * Le pendant positif : l'auteur d'un événement garde la main dessus.
     */
    public function actorCanEditOwnEvent(SiteTester $I)
    {
        $I->skipUnlessConfigured(
            'LADECADANSE_SITE_ACTOR_USER',
            'LADECADANSE_SITE_ACTOR_PASS',
            'LADECADANSE_TEST_EVENT_ID_ACTOR_OWN'
        );

        $I->loginAsActor();
        $I->amOnPage($this->editUrl(TestEnv::getInt('LADECADANSE_TEST_EVENT_ID_ACTOR_OWN')));

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('#ajouter_editer');
    }

    /**
     * Un admin (groupe <= 6) édite n'importe quel événement.
     */
    public function adminCanEditAnyEvent(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ADMIN_USER', 'LADECADANSE_SITE_ADMIN_PASS');

        $I->loginAsAdmin();
        $I->amOnPage($this->editUrl(TestEnv::getInt('LADECADANSE_TEST_EVENT_ID_AUTEUR')));

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('#ajouter_editer');
    }

    private function editUrl(int $idEvenement): string
    {
        return '/evenement-edit.php?action=editer&idE=' . $idEvenement;
    }
}
