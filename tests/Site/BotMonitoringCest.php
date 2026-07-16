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
}
