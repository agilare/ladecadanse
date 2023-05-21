<?php
class ApiCest
{
    public function tryApi(ApiTester $I)
    {
        $I->sendGet('/?entity=event&region=ge&category=fête&date=2023-05-20&endtime=01:00:00');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
    }
}