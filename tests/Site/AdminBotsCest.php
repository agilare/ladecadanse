<?php

use Tests\Support\SiteTester;

use Codeception\Util\HttpCode;

/**
 * Tableau de bord admin/bots.php (monitoring des bots).
 *
 * Vues en lecture seule : aucune écriture, aucune purge déclenchée.
 */
class AdminBotsCest
{
    private const VIEWS = ['scrapers', 'suspects', 'officiels'];

    /**
     * La page est fermée aux non-admins.
     *
     * admin/bots.php:12-13 envoie un 403 *puis* un `Location:` ; PHP écrase alors
     * le statut par 302 (un Location remplace tout code hors 201 et 3xx), si bien
     * que le client ne voit jamais le 403. On asserte donc la redirection réelle,
     * pas le code que le source laisse espérer.
     */
    public function anonymousIsRedirectedToLogin(SiteTester $I)
    {
        $I->stopFollowingRedirects();
        $I->amOnPage('/admin/bots.php');
        $I->seeResponseCodeIsRedirection();

        $I->startFollowingRedirects();
        $I->amOnPage('/admin/bots.php');
        $I->seeCurrentUrlEquals('/user-login.php');
    }

    /**
     * Les trois vues répondent et marquent leur onglet comme actif.
     */
    public function adminSeesTheThreeViews(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ADMIN_USER', 'LADECADANSE_SITE_ADMIN_PASS');

        $I->loginAsAdmin();

        foreach (self::VIEWS as $view)
        {
            $I->amOnPage('/admin/bots.php?view=' . $view);
            $I->seeResponseCodeIs(HttpCode::OK);
            $I->seeElement('nav.bots-tabs a.ici[href="?view=' . $view . '"]');
            $I->seeElement('ul.bots-totaux');
        }
    }

    /**
     * Seule la vue « suspects » propose le filtre de seuil.
     */
    public function suspectsViewOffersThresholdFilter(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ADMIN_USER', 'LADECADANSE_SITE_ADMIN_PASS');

        $I->loginAsAdmin();

        $I->amOnPage('/admin/bots.php?view=suspects');
        $I->seeElement('#filters input#seuil[name=seuil]');

        $I->amOnPage('/admin/bots.php?view=scrapers');
        $I->dontSeeElement('#filters input#seuil');
    }

    /**
     * Une valeur de `view` inconnue retombe sur « scrapers » (ligne 22) plutôt que
     * de partir en erreur : c'est ce qui rend l'URL non exploitable pour sonder l'app.
     */
    public function unknownViewFallsBackToScrapers(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ADMIN_USER', 'LADECADANSE_SITE_ADMIN_PASS');

        $I->loginAsAdmin();
        $I->amOnPage('/admin/bots.php?view=valeur_invalide');

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('nav.bots-tabs a.ici[href="?view=scrapers"]');
    }
}
