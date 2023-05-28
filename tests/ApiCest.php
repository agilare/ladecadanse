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

    public function getEvent(ApiTester $I)
    {
        $I->amHttpAuthenticated(LADECADANSE_API_USER_NOCTAMBUS, LADECADANSE_API_KEY);

        $I->sendGet('/', ['entity' => 'event', 'region' => 'ge', 'category' => 'fête', 'date' => '2023-05-20', 'endtime' => '01:00:00']);
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

        $I->seeResponseMatchesJsonType(
                [
                    'idevenement' => 'string:>0',
                    'statut' => 'string:ladecadanse-event-statut',
                    'titre' => 'string',
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
                        'nom' => 'string',
                        'adresse' => 'string',
                        'quartier' => 'string',
                        'localite' => 'string',
                        'url' => 'string',
                    ],
                    'created' => 'string',
                    'updated' => 'string',
                ], '$.events[*]');

        // TODO: 1.0 check horaire fin value >= endtime and endtime <= 06:01 if endtime param present
        // TODO: 1.0 notempty : titre, fin, lieu (nom, adresse, localite), created, updated
        // TODO: test horaire debut & fin formats
    }

    // TODO: 1.0 test empty credentials and getting response 403
    // TODO: 1.0 test wrong credentials and getting response 403
    // TODO: 1.1 test wrong params (missing, value or format) and getting response 401
    // TODO: test various param entity, region, category, date values
    //
    //
    // TODO: 1.0 test values of an event (on prod site)

}