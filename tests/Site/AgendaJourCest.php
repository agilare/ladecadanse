<?php

use Tests\Support\SiteTester;

use Codeception\Util\HttpCode;

class AgendaJourCest
{
    public function agendaRetombeSurAujourdhuiSurUneDateImpossible(SiteTester $I)
    {
        foreach (['2030-13-45', '2030-2-30'] as $impossible)
        {
            $I->amOnPage('/index.php?courant=' . $impossible);

            $I->seeResponseCodeIs(HttpCode::OK);
            $I->dontSeeInTitle("Agenda d'événements du");
        }
    }

    public function calendrierAjaxAccepteUneDateImpossible(SiteTester $I)
    {
        $I->haveHttpHeader('X-Requested-With', 'XMLHttpRequest');
        $I->amOnPage('/event/calendrier-ajax.php?courant=2030-13-45&page_courant=2030-2-30');

        $I->seeResponseCodeIs(HttpCode::OK);
    }
}
