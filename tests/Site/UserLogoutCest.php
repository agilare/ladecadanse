<?php

use Tests\Support\SiteTester;

/**
 * Déconnexion (user/logout.php).
 *
 * La page a longtemps vécu à la racine du site, et `Sentry::logout()` efface le cookie
 * « Rester connecté-e » par un `setcookie()` dont le path était omis : PHP retombait alors
 * sur le répertoire du script appelant, soit « / », et l'oubli restait invisible. Passée
 * sous user/, la même ligne visait « /user » et laissait intact le cookie posé sur « / » ;
 * la requête suivante reconnectait la personne qui venait de cliquer « Sortir ».
 *
 * Ces tests écrivent en base ce que toute connexion mémorisée y écrit déjà — une ligne de
 * `remember_token` par appareil, effacée à la déconnexion — et rien d'autre.
 */
class UserLogoutCest
{
    private const COOKIE_MEMORISER = 'ladecadanse_remember';

    /** Rendu par _header.inc.php à la seule condition qu'une session soit ouverte. */
    private const BOUTON_SORTIR = '#menu_pratique form.deconnexion';

    public function _before(SiteTester $I)
    {
        $I->skipUnlessConfigured(
            'LADECADANSE_SITE_ACTOR_USER',
            'LADECADANSE_SITE_ACTOR_PASS'
        );
    }

    /** Cas nominal, sans cookie de longue durée à effacer. */
    public function logoutClosesTheSession(SiteTester $I)
    {
        $I->loginAsActor();
        $I->logout();

        $I->amOnPage('/articles/apropos.php');
        $I->dontSeeElement(self::BOUTON_SORTIR);
    }

    /**
     * Le cookie doit disparaître, et pas seulement la session : tant qu'il subsiste,
     * `Sentry` rouvre une session au chargement suivant.
     */
    public function logoutDropsTheRememberMeCookie(SiteTester $I)
    {
        $I->loginAsActor(true);
        $I->seeCookie(self::COOKIE_MEMORISER);

        $I->logout();
        $I->dontSeeCookie(self::COOKIE_MEMORISER);

        $I->amOnPage('/articles/apropos.php');
        $I->dontSeeElement(self::BOUTON_SORTIR);
    }

    /**
     * Chaque appareil a son jeton : se connecter sur un second ne déconnecte plus le premier,
     * et se déconnecter du premier laisse le second connecté. Les deux appareils sont simulés
     * en vidant les cookies du navigateur entre les deux connexions.
     */
    public function rememberMeHoldsOnTwoDevices(SiteTester $I)
    {
        $I->loginAsActor(true);
        $premier = $I->grabCookie(self::COOKIE_MEMORISER);
        $I->resetCookie('PHPSESSID');
        $I->resetCookie(self::COOKIE_MEMORISER);

        $I->loginAsActor(true);
        $second = $I->grabCookie(self::COOKIE_MEMORISER);
        $I->assertNotEquals($premier, $second);
        $I->resetCookie('PHPSESSID');
        $I->resetCookie(self::COOKIE_MEMORISER);

        // le premier appareil revient sans session : seul son cookie le reconnecte
        $I->setCookie(self::COOKIE_MEMORISER, $premier);
        $I->amOnPage('/articles/apropos.php');
        $I->seeElement(self::BOUTON_SORTIR);
        $I->logout();

        $I->resetCookie('PHPSESSID');
        $I->setCookie(self::COOKIE_MEMORISER, $second);
        $I->amOnPage('/articles/apropos.php');
        $I->seeElement(self::BOUTON_SORTIR);
        $I->logout();
    }

    /**
     * « Se déconnecter des autres appareils » (user/dashboard.php) ferme la session ouverte
     * ailleurs et oublie son cookie, et laisse connecté l'appareil qui le demande.
     */
    public function logoutOtherDevicesClosesTheirSessions(SiteTester $I)
    {
        $I->loginAsActor(true);
        $autreSession = $I->grabCookie('PHPSESSID');
        $autreCookie = $I->grabCookie(self::COOKIE_MEMORISER);
        $I->resetCookie('PHPSESSID');
        $I->resetCookie(self::COOKIE_MEMORISER);

        $I->loginAsActor(true);
        $I->click('a[href^="/user/dashboard.php?idP="]');
        $I->click('button[name="logout_other_devices"]');
        $I->see('Vos autres appareils sont déconnectés');

        $I->amOnPage('/articles/apropos.php');
        $I->seeElement(self::BOUTON_SORTIR);
        $ceCookie = $I->grabCookie(self::COOKIE_MEMORISER);
        $I->logout();

        // l'autre appareil revient avec sa session et son cookie : ni l'une ni l'autre ne vaut plus
        $I->setCookie('PHPSESSID', $autreSession);
        $I->setCookie(self::COOKIE_MEMORISER, $autreCookie);
        $I->amOnPage('/articles/apropos.php');
        $I->dontSeeElement(self::BOUTON_SORTIR);

        // et cet appareil avait reçu un jeton neuf, que la déconnexion a effacé à son tour
        $I->assertNotEquals($autreCookie, $ceCookie);
    }

    /**
     * Un GET ne déconnecte pas : c'est tout l'objet du passage en POST, qui met la
     * déconnexion hors de portée des préchargements de lien et des sites tiers.
     */
    public function getDoesNotLogOut(SiteTester $I)
    {
        $I->loginAsActor(true);

        $I->amOnPage('/user/logout.php');

        $I->seeCookie(self::COOKIE_MEMORISER);
        $I->amOnPage('/articles/apropos.php');
        $I->seeElement(self::BOUTON_SORTIR);

        // ne pas laisser de session ouverte au test suivant
        $I->logout();
    }
}
