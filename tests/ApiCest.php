<?php

use Tests\Support\ApiTester;

use Codeception\Util\HttpCode;
use Codeception\Util\JsonType;

require_once __DIR__ . '/../app/env.php';

class ApiCest
{
    /**
     * @var array|string[]
     */
    private array $validRequestParams = ['entity' => 'event', 'region' => 'ge', 'category' => 'fête'];

    public function _before(ApiTester $I)
    {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->load();
        $this->validRequestParams['date'] = $_ENV['LADECADANSE_API_KEY_QUERY_DATE'];
        $this->validRequestParams['endtime'] = $_ENV['LADECADANSE_API_KEY_QUERY_ENDTIME'];
    }

    /**
     * @dataProvider authenticateProvider
     */
    public function authenticate(ApiTester $I, \Codeception\Example $example)
    {
        $I->amHttpAuthenticated($example[0], $example[1]);
        $I->sendGet('/', $this->validRequestParams);
        $I->seeResponseCodeIs($example[2]);
    }

    /**
     * @return array
     */
    protected function authenticateProvider()
    {
        return [
            ["plop", "faux", HttpCode::UNAUTHORIZED],
            [$_ENV['LADECADANSE_API_USER'], "faux", HttpCode::UNAUTHORIZED],
            ["plop", LADECADANSE_API_KEY, HttpCode::UNAUTHORIZED],
            [$_ENV['LADECADANSE_API_USER'], $_ENV['LADECADANSE_API_KEY'], HttpCode::OK],
        ];
    }

    /**
     * @dataProvider badParamsProvider
     */
    public function badParams(ApiTester $I, \Codeception\Example $example)
    {
        $I->amHttpAuthenticated($_ENV['LADECADANSE_API_USER'], $_ENV['LADECADANSE_API_KEY']);
        $I->sendGet('/', $example[0]);
        $I->seeResponseCodeIs($example[1]);
    }

    /**
     * @return array
     */
    protected function badParamsProvider()
    {
        return [
            [['entity' => '', 'region' => 'ge', 'category' => 'fête', 'date' => '2023-05-20', 'endtime' => '01:00:00'], HttpCode::BAD_REQUEST],
            [['entity' => 'event', 'region' => 'bz', 'category' => 'fête', 'date' => '2023-05-20', 'endtime' => '01:00:00'], HttpCode::BAD_REQUEST],
            [['entity' => 'event', 'region' => 'ge', 'category' => 'ciné', 'date' => '2023-05-20', 'endtime' => '01:00:00'], HttpCode::BAD_REQUEST],
            [['entity' => 'event', 'region' => 'ge', 'category' => 'fête', 'date' => '2023.05.20', 'endtime' => '01:00:00'], HttpCode::BAD_REQUEST],
            [['entity' => 'event', 'region' => 'ge', 'category' => 'fête', 'date' => '2023-05-20', 'endtime' => '01h00:00'], HttpCode::BAD_REQUEST],
        ];
    }

    public function getEventsByDay(ApiTester $I)
    {

        $I->amHttpAuthenticated($_ENV['LADECADANSE_API_USER'], $_ENV['LADECADANSE_API_KEY']);

        $I->sendGet('/', $this->validRequestParams);

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
        $dateMinEnd = new Datetime($this->validRequestParams['date']);
        $dateMinEnd->modify('+1 day');
        $datetimeMinEnd = $dateMinEnd->format('Y-m-d') . ' ' . $this->validRequestParams['endtime'];
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
            if (!empty($this->validRequestParams['endtime'])) {

                $I->assertGreaterOrEquals($datetimeMinEnd, $e['horaire']['fin']);
            }
            $I->assertLessThanOrEqual($dateMinEnd->format('Y-m-d') . ' 06:00:01', $e['horaire']['fin']);
        }
    }

    // TODO: test values of an event (on prod site)
}
