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
 * Ces tests écrivent en base ce que toute connexion y écrit déjà — `personne.cookie`, le
 * jeton opaque renouvelé par `initSession()` — et rien d'autre.
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
