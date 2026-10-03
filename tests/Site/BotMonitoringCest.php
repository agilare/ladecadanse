<?php

use Tests\Support\SiteTester;

use Codeception\Util\HttpCode;

/**
 * Tests fonctionnels du monitoring des bots (BOT_MONITORING_ENABLED requis sur le site testé).
 */
class BotMonitoringCest
{
    private string $honeypotPath = '/annuaire-membres.php';

    /**
     * Le piège renvoie 204 sans contenu pour ne pas éveiller les soupçons des scrapers.
     */
    public function honeypotReturnsNoContent(SiteTester $I)
    {
        $I->amOnPage($this->honeypotPath);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
        $I->assertSame('', trim($I->grabPageSource()));
    }

    /**
     * Le lien piège est présent dans le pied de page, invisible pour les humains
     * (hors écran, ignoré des lecteurs d'écran et de la navigation clavier).
     */
    public function honeypotLinkIsInFooter(SiteTester $I)
    {
        // page statique : la home peut planter en cours de rendu pour des raisons
        // indépendantes du footer (données événements du jour)
        $I->amOnPage('/articles/apropos.php');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('footer a.hp-link[href="' . $this->honeypotPath . '"][aria-hidden="true"][tabindex="-1"][rel="nofollow"]');
    }

    /**
     * Le piège est interdit dans robots.txt : seuls les robots qui ignorent
     * robots.txt (la population visée) doivent le suivre.
     */
    public function honeypotIsDisallowedInRobotsTxt(SiteTester $I)
    {
        $I->amOnPage('/robots.txt');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInSource('Disallow: ' . $this->honeypotPath);
    }

    /**
     * Une page vue anonyme ouvre (ou prolonge) la fenêtre de comptage de son IP : la vue
     * « rafales » la montre parmi les fenêtres en cours, puis parmi les pics.
     */
    public function anonymousPageViewShowsUpInRafalesView(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ADMIN_USER', 'LADECADANSE_SITE_ADMIN_PASS');

        $I->amOnPage('/articles/apropos.php');

        $I->loginAsAdmin();
        $I->amOnPage('/admin/bots.php?view=rafales&seuil=1');

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('table.bots-now td.bots-ip');
        $I->seeElement('table#ajouts td.bots-ip');
    }

    /**
     * Une requête terminée en 4xx remonte dans la vue « sondeurs » avec son chemin, sans sa
     * query string — elle porte ailleurs un jeton de réinitialisation de mot de passe.
     *
     * Le chemin est rendu unique par un path info : sans cela, le chemin laissé par un passage
     * précédent ferait passer le test même si plus rien n'était enregistré. Un 404 décidé par
     * l'application plutôt qu'une page inexistante, pour que le test vaille aussi sous
     * `php -S`, qui ignore l'ErrorDocument d'Apache.
     */
    public function failedRequestShowsUpInSondeursViewWithoutItsQueryString(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ADMIN_USER', 'LADECADANSE_SITE_ADMIN_PASS');

        $path = '/event/evenement.php/sonde-' . uniqid();

        $I->amOnPage($path . '?idE=999999999&token=secretdetest');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);

        $I->loginAsAdmin();
        $I->amOnPage('/admin/bots.php?view=sondeurs&seuil=1');

        $I->see($path, 'td.bots-path');
        $I->dontSee('secretdetest', 'td.bots-path');
    }

    /**
     * Un fichier statique manquant dit quelque chose du site, pas du visiteur : son 404
     * n'est pas compté comme une erreur.
     */
    public function missingStaticFileIsNotCountedAsAnError(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ADMIN_USER', 'LADECADANSE_SITE_ADMIN_PASS');

        $path = '/event/evenement.php/sonde-' . uniqid() . '.jpg';

        $I->amOnPage($path . '?idE=999999999');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);

        $I->loginAsAdmin();
        $I->amOnPage('/admin/bots.php?view=sondeurs&seuil=1');

        $I->dontSee($path, 'td.bots-path');
    }
}
