<?php

use Tests\Support\SiteTester;
use Tests\Support\TestEnv;

use Codeception\Util\HttpCode;

/**
 * Bloc d'autorisation d'édition d'un événement (evenement-edit.php, en tête de fichier).
 *
 * Son SELECT a été étendu par l'issue #149 (idPersonne, user_email, dateAjout) puis par le
 * verrouillage des événements passés (dateEvenement, horaire_fin) : une régression y serait
 * à la fois plausible et coûteuse. Tests en lecture seule.
 *
 * Les fixtures d'événements « éditables » doivent pointer sur des événements à venir :
 * un événement passé est désormais une archive en lecture seule sous le groupe AUTHOR.
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

    /**
     * Un événement passé est une archive : même son auteur ne peut plus le modifier.
     *
     * C'est le verrou qui empêche de recycler un ancien événement en changeant sa date,
     * ce qui écraserait l'événement d'origine.
     */
    public function actorCannotEditOwnPastEvent(SiteTester $I)
    {
        $I->skipUnlessConfigured(
            'LADECADANSE_SITE_ACTOR_USER',
            'LADECADANSE_SITE_ACTOR_PASS',
            'LADECADANSE_TEST_EVENT_ID_ACTOR_OWN_PAST'
        );

        $I->loginAsActor();
        $I->amOnPage($this->editUrl(TestEnv::getInt('LADECADANSE_TEST_EVENT_ID_ACTOR_OWN_PAST')));

        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->see('est passé');
        $I->dontSeeElement('#ajouter_editer');
    }

    /**
     * Un éditeur (groupe <= AUTHOR) échappe au verrouillage : les archives restent
     * corrigeables par l'équipe.
     */
    public function adminCanEditPastEvent(SiteTester $I)
    {
        $I->skipUnlessConfigured(
            'LADECADANSE_SITE_ADMIN_USER',
            'LADECADANSE_SITE_ADMIN_PASS',
            'LADECADANSE_TEST_EVENT_ID_ACTOR_OWN_PAST'
        );

        $I->loginAsAdmin();
        $I->amOnPage($this->editUrl(TestEnv::getInt('LADECADANSE_TEST_EVENT_ID_ACTOR_OWN_PAST')));

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('#ajouter_editer');
    }

    /**
     * Copier et Dépublier restent offerts sur un événement passé.
     *
     * Le test de non-régression le plus utile du lot : sur la fiche événement, les liens
     * Copier, Modifier et Dépublier partageaient un seul bloc `if`. Restreindre ce bloc
     * plutôt que le seul lien Modifier supprimerait la copie — soit exactement le geste
     * que le verrouillage est censé encourager.
     */
    public function actorStillSeesCopyOnPastEvent(SiteTester $I)
    {
        $I->skipUnlessConfigured(
            'LADECADANSE_SITE_ACTOR_USER',
            'LADECADANSE_SITE_ACTOR_PASS',
            'LADECADANSE_TEST_EVENT_ID_ACTOR_OWN_PAST'
        );

        $idEvenement = TestEnv::getInt('LADECADANSE_TEST_EVENT_ID_ACTOR_OWN_PAST');

        $I->loginAsActor();
        $I->amOnPage('/event/evenement.php?idE=' . $idEvenement);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('a[href="/event/copy.php?idE=' . $idEvenement . '"]');
        $I->dontSeeElement('a[href*="evenement-edit.php?action=editer"]');
    }

    private function editUrl(int $idEvenement): string
    {
        return '/evenement-edit.php?action=editer&idE=' . $idEvenement;
    }
}
