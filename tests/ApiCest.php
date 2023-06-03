<?php

use Codeception\Util\HttpCode;
use Codeception\Util\JsonType;

require_once __DIR__ . '/../app/env.php';

class ApiCest
{

    function _before(ApiTester $I)
    {
        // will be executed at the beginning of each test

    }

    // TODO: 1.0 test empty or wrong credentials and getting response 403

    public function getEvent(ApiTester $I)
    {
        $I->amHttpAuthenticated(LADECADANSE_API_USER_NOCTAMBUS, LADECADANSE_API_KEY);

        // TODO: test various param entity, region, category, date values

        $apiParams = ['entity' => 'event', 'region' => 'ge', 'category' => 'fête', 'date' => '2023-05-20', 'endtime' => '01:00:00'];
        $I->sendGet('/', $apiParams);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();

        $I->seeResponseJsonMatchesJsonPath('$.date');

        list($events) = $I->grabDataFromResponseByJsonPath('$.events');

        if (empty($events)) {
            return;
        }

        $I->seeResponseJsonMatchesJsonPath('$.events[*].idevenement');

        JsonType::addCustomFilter('ladecadanse-event-statut', function ($value) {
            return array_key_exists($value, ['propose' => 'Proposé', 'actif' => 'Proposé', 'complet' => 'Complet', 'annule' => 'Annulé', 'inactif' => 'Dépublié']);
        });

        JsonType::addCustomFilter('ladecadanse-notempty', function ($value) {
            return !empty($value);
        });

        $I->seeResponseMatchesJsonType(
                [
                    'idevenement' => 'string:>0:ladecadanse-notempty',
                    'statut' => 'string:ladecadanse-event-statut',
                    'titre' => 'string:ladecadanse-notempty',
                    'image' => 'string:regex(/(|\.jpg|\.jpeg|\.png|\.gif)$/)',
                    'description' => 'string',
                    'references' => 'string',
                    'horaire' => [
                        'debut' => 'string',
                        'fin' => 'string',
                        'complement' => 'string'
                    ],
                    'prix' => 'string',
                    'lieu' => [
                        'nom' => 'string:ladecadanse-notempty',
                        'adresse' => 'string:ladecadanse-notempty',
                        'quartier' => 'string',
                        'localite' => 'string:ladecadanse-notempty',
                        'url' => 'string',
                    ],
                    'created' => 'string:ladecadanse-notempty',
                    'updated' => 'string:ladecadanse-notempty',
                ], '$.events[*]');


        // check values
        // events
        // check horaire fin value >= endtime and endtime <= 06:01 if endtime param present
        $dateMinEnd = new Datetime($apiParams['date']);
        $dateMinEnd->modify('+1 day');
        $datetimeMinEnd = $dateMinEnd->format('Y-m-d') . ' ' . $apiParams['endtime'];
        foreach ($events as $e)
        {
            $datetimeRegexPattern = "/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/";
            if (!empty($e['horaire']['debut'])) {
                $I->assertMatchesRegularExpression($datetimeRegexPattern, $e['horaire']['debut']);
            }

            if (!empty($e['horaire']['fin'])) {
                $I->assertMatchesRegularExpression($datetimeRegexPattern, $e['horaire']['fin']);
            }

            // TODO: test horaire debut & fin formats
            if (!empty($apiParams['endtime'])) {

                $I->assertGreaterOrEquals($datetimeMinEnd, $e['horaire']['fin']);
            }
            $I->assertLessThanOrEqual($dateMinEnd->format('Y-m-d') . ' 06:00:01', $e['horaire']['fin']);
        }
    }


    // TODO: 1.1 test wrong params (missing, value or format) and getting response 401
    // TODO: test values of an event (on prod site)
}