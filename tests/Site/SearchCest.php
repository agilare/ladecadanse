<?php

use Tests\Support\SiteTester;

use Codeception\Util\HttpCode;

/**
 * Recherche d'événements (event/search.php).
 *
 * Lecture seule : uniquement des GET, aucune écriture.
 */
class SearchCest
{
    /**
     * Les opérateurs du mode booléen FULLTEXT tapés dans le champ (« <b>y », « (jazz) », « -jazz »)
     * faisaient rejeter toute la requête par InnoDB : 500 au lieu d'une liste de résultats.
     */
    public function booleanOperatorsInTermsDoNotBreakSearch(SiteTester $I)
    {
        $I->amOnPage('/event/search.php?mots=' . rawurlencode('<b>y (jazz) ~rock -live @home 100% "concert"'));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('h1');
    }

    public function termsMadeOnlyOfOperatorsAreRejected(SiteTester $I)
    {
        $I->amOnPage('/event/search.php?mots=' . rawurlencode('<> *'));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }
}
