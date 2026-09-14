<?php

use Tests\Support\SiteTester;
use Tests\Support\TestEnv;

use Codeception\Util\HttpCode;

/**
 * Fieldset « Statut de l'événement » (evenement-edit.php:1004-1038).
 *
 * Sept commits consécutifs de mise en page l'ont remanié sans aucun test :
 * on verrouille ici sa structure (les radios rendues et à qui) plutôt que son style.
 */
class EvenementStatutCest
{
    public function _before(SiteTester $I)
    {
        $I->skipUnlessConfigured(
            'LADECADANSE_SITE_ADMIN_USER',
            'LADECADANSE_SITE_ADMIN_PASS',
            'LADECADANSE_TEST_EVENT_ID_AUTEUR'
        );
    }

    /**
     * En édition, l'admin dispose des statuts de publication et des libellés
     * colorés (span.even-statut-label) réintroduits par la refonte du fieldset.
     *
     * `propose` n'est volontairement pas asserté ici : il est masqué dès que
     * l'événement porte un user_email et n'est plus au statut « proposé »
     * (ligne 1012), ce qui dépend du jeu de données.
     */
    public function statutRadiosVisibleForAdminOnEditer(SiteTester $I)
    {
        $I->loginAsAdmin();
        $I->amOnPage('/evenement-edit.php?action=editer&idE=' . TestEnv::getInt('LADECADANSE_TEST_EVENT_ID_AUTEUR'));

        $I->seeResponseCodeIs(HttpCode::OK);

        foreach (['actif', 'complet', 'annule', 'inactif'] as $statut)
        {
            $I->seeElement('input[type=radio][name=statut][value=' . $statut . ']#statut_' . $statut);
            $I->seeElement('label[for=statut_' . $statut . '] strong');
        }

        $I->seeElement('label[for=statut_complet] span.even-statut-label.statut-complet');
        $I->seeElement('label[for=statut_annule] span.even-statut-label.statut-annule');
    }

    /**
     * En création il n'y a pas de choix de statut : un champ caché impose `actif`
     * (ligne 1038). Sa disparition publierait — ou dépublierait — en silence.
     */
    public function statutIsHiddenAndActifOnAjouter(SiteTester $I)
    {
        $I->loginAsAdmin();
        $I->amOnPage('/evenement-edit.php?action=ajouter');

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('input[type=hidden][name=statut][value=actif]');
        $I->dontSeeElement('input[type=radio][name=statut]');
    }

    /**
     * Le statut « proposé » est réservé aux groupes <= 6 (ligne 1012) : un acteur
     * ne doit pas pouvoir remettre son propre événement en attente de validation.
     */
    public function proposeIsNotOfferedToActor(SiteTester $I)
    {
        $I->skipUnlessConfigured(
            'LADECADANSE_SITE_ACTOR_USER',
            'LADECADANSE_SITE_ACTOR_PASS',
            'LADECADANSE_TEST_EVENT_ID_ACTOR_OWN'
        );

        $I->loginAsActor();
        $I->amOnPage('/evenement-edit.php?action=editer&idE=' . TestEnv::getInt('LADECADANSE_TEST_EVENT_ID_ACTOR_OWN'));

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('input[type=radio][name=statut][value=actif]');
        $I->dontSeeElement('input[type=radio][name=statut][value=propose]');
    }
}
