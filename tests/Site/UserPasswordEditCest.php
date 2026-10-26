<?php

use Tests\Support\SiteTester;
use Tests\Support\TestEnv;

use Codeception\Util\HttpCode;

/**
 * Changement de mot de passe (user/pwd-edit.php, #249).
 *
 * La suite `site` est en lecture seule : seuls des envois refusés sont soumis ici, aucun ne
 * change le mot de passe du compte de test. Le changement réussi se vérifie hors suite.
 */
class UserPasswordEditCest
{
    public function _before(SiteTester $I)
    {
        $I->skipUnlessConfigured(
            'LADECADANSE_SITE_ACTOR_USER',
            'LADECADANSE_SITE_ACTOR_PASS'
        );
    }

    private function amOnMyPasswordEdit(SiteTester $I): string
    {
        $I->amOnPage('/articles/apropos.php');
        $I->click('a[href^="/user/dashboard.php?idP="]');
        $idP = $I->grabFromCurrentUrl('~idP=(\d+)~');
        $I->click('a[href^="/user/pwd-edit.php"]');
        $I->seeResponseCodeIs(HttpCode::OK);

        return $idP;
    }

    public function anonymousIsSentToLogin(SiteTester $I)
    {
        $I->amOnPage('/user/pwd-edit.php?idP=1');
        $I->seeInCurrentUrl('/user/login.php');
    }

    /**
     * Sans ces attributs, Firefox et Chrome ne reconnaissent pas un formulaire de changement et
     * ne proposent pas de mettre à jour le mot de passe enregistré.
     */
    public function ownFormIsMarkedForPasswordManagers(SiteTester $I)
    {
        $I->loginAsActor();
        $this->amOnMyPasswordEdit($I);

        $I->seeElement('input[autocomplete="username"]');
        $I->seeElement('input[type="password"][name="motdepasse_actuel"][autocomplete="current-password"]');
        $I->seeElement('input[type="password"][name="motdepasse"][autocomplete="new-password"]');
    }

    public function wrongCurrentPasswordIsRefused(SiteTester $I)
    {
        $I->loginAsActor();
        $this->amOnMyPasswordEdit($I);

        $I->submitForm('#ajouter_editer', ['motdepasse_actuel' => 'pas-le-bon-' . bin2hex(random_bytes(4)), 'motdepasse' => 'une phrase assez longue pour passer']);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->see('Le mot de passe actuel est faux', '.msg_erreur');
        $I->seeInCurrentUrl('/user/pwd-edit.php');
    }

    public function passwordRefusedByThePolicy(SiteTester $I)
    {
        $I->loginAsActor();
        $this->amOnMyPasswordEdit($I);

        $I->submitForm('#ajouter_editer', ['motdepasse_actuel' => TestEnv::get('LADECADANSE_SITE_ACTOR_PASS'), 'motdepasse' => 'court']);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->see('caractères', '.msg_erreur');
        $I->seeInCurrentUrl('/user/pwd-edit.php');
    }

    public function anotherMembersAccountIsForbidden(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ADMIN_USER', 'LADECADANSE_SITE_ADMIN_PASS');

        $I->loginAsAdmin();
        $I->amOnPage('/articles/apropos.php');
        $I->click('a[href^="/user/dashboard.php?idP="]');
        $idAdmin = $I->grabFromCurrentUrl('~idP=(\d+)~');
        $I->logout();

        $I->loginAsActor();
        $I->amOnPage('/user/pwd-edit.php?idP=' . $idAdmin);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    /** Le formulaire de compte ne porte plus aucun champ de mot de passe. */
    public function accountFormHasNoPasswordField(SiteTester $I)
    {
        $I->loginAsActor();
        $I->amOnPage('/articles/apropos.php');
        $I->click('a[href^="/user/dashboard.php?idP="]');
        $I->click('a[href*="/user/edit.php"]');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeElement('#ajouter_editer input[type="password"]');
    }
}
