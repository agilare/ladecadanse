<?php

use Tests\Support\SiteTester;
use Tests\Support\TestEnv;

use Codeception\Util\HttpCode;

/**
 * Garde-fous côté serveur des fonctionnalités JavaScript récentes.
 *
 * PhpBrowser n'exécute pas de JS : les raccourcis clavier, le garde `beforeunload`
 * et le datepicker restent du ressort de Selenium (tests/ladecadanse.side). En
 * revanche le *contrat* dont ce JS dépend est rendu par le serveur, donc assertable
 * ici pour trois fois rien.
 */
class FormulairesRegressionCest
{
    /**
     * Régression fe67f17 : le champ caché `formulaire=ok` de event/copy.php.
     *
     * Son absence avait cassé silencieusement le collage d'un événement quand
     * `js-submit-freeze-wait` s'est mis à désactiver le bouton submit (le nom du
     * bouton n'étant alors plus envoyé, plus rien ne signalait la soumission).
     */
    public function copyFormKeepsFormulaireHiddenField(SiteTester $I)
    {
        $I->skipUnlessConfigured(
            'LADECADANSE_SITE_ACTOR_USER',
            'LADECADANSE_SITE_ACTOR_PASS',
            'LADECADANSE_TEST_EVENT_ID_ACTOR_OWN'
        );

        $I->loginAsActor();
        $I->amOnPage('/event/copy.php?idE=' . TestEnv::getInt('LADECADANSE_TEST_EVENT_ID_ACTOR_OWN'));

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('input[type=hidden][name=formulaire][value=ok]');
    }

    /**
     * `body[data-page]` (_header.inc.php:112) est la clé de dispatch des raccourcis
     * clavier (#112) et du garde `beforeunload` dans web/js/global.js : trois
     * assertions qui protègent tout le JS récent.
     */
    public function dataPageAttributeIsRendered(SiteTester $I)
    {
        $pages = [
            '/evenement-edit.php?action=ajouter' => 'evenement-edit',
            '/lieu/lieux.php' => 'lieu/lieux',
            '/articles/apropos.php' => 'articles/apropos',
        ];

        foreach ($pages as $url => $expected)
        {
            $I->amOnPage($url);
            $I->seeResponseCodeIs(HttpCode::OK);
            $I->seeElement('body[data-page="' . $expected . '"]');
        }
    }

    /**
     * Cas de la home isolé : elle peut échouer pour des raisons de données
     * (événements du jour) indépendantes de l'attribut testé.
     */
    public function homeExposesDataPageIndex(SiteTester $I)
    {
        $I->amOnPage('/');

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('body[data-page="index"]');
    }

    /**
     * Bouton « vider la recherche » (b1f4da3) déplacé à l'intérieur du champ :
     * c'est le JS qui le câble, mais c'est le serveur qui doit le rendre.
     */
    public function clearSearchButtonIsRenderedOnListings(SiteTester $I)
    {
        foreach (['/lieu/lieux.php', '/organisateur/organisateurs.php'] as $url)
        {
            $I->amOnPage($url);
            $I->seeResponseCodeIs(HttpCode::OK);
            $I->seeElement('button.js-clear-search-field');
        }
    }
}
