<?php
/**
 *  included at the beginning of each page
 */

require_once __DIR__ . '/env.php';

require __DIR__ . '/../vendor/autoload.php';

use Ladecadanse\Evenement;
use Ladecadanse\Lieu;
use Ladecadanse\Organisateur;
use Ladecadanse\Security\Authorization;
use Ladecadanse\Security\AuthorizationRepository;
use Ladecadanse\Security\Sentry;
use Ladecadanse\Utils\DbConnector;
use Ladecadanse\Utils\DbConnectorPdo;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Ladecadanse\RegionConfig;
use Ladecadanse\TemplateEngine;
use Ladecadanse\Translator;
use Whoops\Handler\PrettyPageHandler;
use Ladecadanse\Utils\AssetManager;

require_once __DIR__ . '/config.php';

date_default_timezone_set(DATE_DEFAULT_TIMEZONE);

if (ENV === 'dev') {
    $whoops = new \Whoops\Run;
    $whoopsHandler = new PrettyPageHandler();
    $whoopsHandler->setEditor('netbeans');
    $whoops->pushHandler($whoopsHandler);
    $whoops->register();
}

//define("bOf", 42, true);
//$bof = (real) 45;
//$a = mktime();
// FIXME: seems to not work on current depl server (darksite.ch)
// session_save_path(__ROOT__ . "/var/sessions");
// ini_set('session.gc_probability', 1); // to enable auto clean of old session in Debian https://www.php.net/manual/en/function.session-save-path.php#98106

// Enable cookie_secure only when using HTTPS to allow docker config without SSL enable
$isHttps =
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on');

session_start([
    'cookie_secure'   => $isHttps,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);



$regionConfig = new RegionConfig($glo_regions);
$regionConfig->setPersistentRegion($_COOKIE, $_GET);
[$url_query_region, $url_query_region_et, $url_query_region_1er] = $regionConfig->getAppVars();

$_SESSION['user_prefs_agenda_order'] = $_SESSION['user_prefs_agenda_order'] ?? 'dateAjout';
// filtre de genre de l'agenda, 'tous' = aucun filtre
$_SESSION['user_prefs_agenda_genre'] ??= 'tous';
// tri des événements passés sur les fiches lieu et organisateur, partagé par les deux pages
$_SESSION['user_prefs_past_events_order'] ??= 'asc';

$logFormatter = new LineFormatter(null, 'Y-m-d H:i:sP');

$activityHandler = new RotatingFileHandler(
    __DIR__ . '/../var/logs/activity.log',
    36,
    \Monolog\Level::Debug,
    true,
    null,
    false,
    'Y-m'
);
$activityHandler->setFormatter($logFormatter);
$logger = new Logger('activity');
$logger->pushHandler($activityHandler);

$apiHandler = new RotatingFileHandler(
    __DIR__ . '/../var/logs/api.log',
    36,
    \Monolog\Level::Debug,
    true,
    null,
    false,
    'Y-m'
);
$apiHandler->setFormatter($logFormatter);
$loggerApi = new Logger('api');
$loggerApi->pushHandler($apiHandler);

$connector = new DbConnector(DB_HOST, DB_NAME, DB_USERNAME, DB_PASSWORD);
$connectorPdo = DbConnectorPdo::getInstance();

$authorization = new Authorization(new AuthorizationRepository($connectorPdo->getPDO()));

$videur = new Sentry($connectorPdo->getPDO(), $logger);

$tplEngine = new TemplateEngine(__DIR__ . "/../resources/templates/");

$translator = new Translator(__DIR__ . '/../resources/messages.yml');

$site_scheme = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on") ? "https://" : "http://";
$site_full_url = $site_scheme . $_SERVER["SERVER_NAME"] . "/";

Evenement::$systemDirPath = $rep_images_even;
Evenement::$urlDirPath = $url_uploads_events;
Lieu::$systemDirPath = $rep_uploads_lieux;
Lieu::$urlDirPath = $url_uploads_lieux;
Organisateur::$systemDirPath = $rep_uploads_organisateurs;
Organisateur::$urlDirPath = $url_uploads_organisateurs;

//$nom_page = basename((string) $_SERVER["SCRIPT_FILENAME"], '.php');
$pathinfo = pathinfo($_SERVER['SCRIPT_NAME']);
//dump($pathinfo);
//( strlen($pathinfo['dirname']) > 1 ? $pathinfo['dirname'] . '/' : "/")
$nom_page = ltrim($pathinfo['dirname'] . '/' . $pathinfo['filename'], '\\/');
//echo $_SERVER["SCRIPT_FILENAME"]."<br>";
//echo $nom_page;

$assets = new AssetManager(__ROOT__ . ASSETS_DIR, ASSETS_DIR, $logger);

if (DARKVISITORS_ENABLED)
{
    if (!function_exists('getallheaders')) {
        /**
         * Polyfill : getallheaders() n'existe nativement que sur les SAPI apache2handler
         * et fpm — absente en CLI et sous le serveur intégré (`php -S`) des tests locaux.
         * Composer ne la fournit pas non plus en production : ralouphie/getallheaders
         * n'arrive que par l'arbre require-dev (guzzlehttp/psr7 2.x, sous Codeception),
         * et psr7 3.0 abandonne cette dépendance. Le suivi DarkVisitors ci-dessous est
         * le seul appelant du projet.
         *
         * @return array<string, string> en-têtes de la requête, noms en Camel-Case
         */
        function getallheaders(): array
        {
            $headers = [];

            foreach ($_SERVER as $name => $value) {
                if (!is_string($value) || !str_starts_with((string) $name, 'HTTP_')) {
                    continue;
                }

                $name = str_replace('_', ' ', strtolower(substr((string) $name, 5)));
                $headers[str_replace(' ', '-', ucwords($name))] = $value;
            }

            return $headers;
        }
    }

    function trackVisitAsync(array $data, string $accessToken): void
    {
        $ch = curl_init('https://api.darkvisitors.com/visits');

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data, JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT_MS => 200, // timeout très court pour libérer vite
            CURLOPT_CONNECTTIMEOUT_MS => 100,
        ]);

        curl_exec($ch); // on lance sans lire la réponse
        curl_close($ch);
    }

    // will only run at the end of the script, i.e. after your page has been generated. This will avoid slowing down the page rendering for the user, especially if you limit the timeout in cURL
    register_shutdown_function(function () {
        trackVisitAsync([
            'request_path' => $_SERVER['REQUEST_URI'],
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'request_headers' => getallheaders(),
                ], DARKVISITORS_ACCESS_TOKEN);
    });
}

// suivi léger des bots et IP suspectes (voir librairies/BotMonitor.php) ;
// exécuté au shutdown pour ne pas ralentir le rendu ; utilisateurs connectés exclus ;
// le garde defined() protège si le env.php de prod n'a pas encore la constante
if (defined('BOT_MONITORING_ENABLED') && BOT_MONITORING_ENABLED && empty($_SESSION['logged'])) {
    register_shutdown_function(function () use ($connectorPdo, $logger) {
        (new \Ladecadanse\BotMonitor($connectorPdo->getPDO(), $logger))->track();
    });
}

header('X-Content-Type-Options: nosniff');
define("CSP_NONCE", bin2hex(openssl_random_pseudo_bytes(32)));
$csp = implode('; ', [
    "default-src 'self'",
    "script-src 'self' 'nonce-" . CSP_NONCE . "' https://unpkg.com https://tools.ladecadanse.ch/ https://code.jquery.com https://knownagents.com https://browser.sentry-cdn.com https://www.paypalobjects.com https://liberapay.com https://wemakeit.com https://assets.wemakeit.com https://cdn.tiny.cloud",
    "img-src 'self' https://tile.openstreetmap.org https://tools.ladecadanse.ch/ https://unpkg.com https://www.paypalobjects.com https://sp.tinymce.com data:",
    "style-src 'self' 'unsafe-inline' https://unpkg.com https://cdn.tiny.cloud https://www.tiny.cloud https://wemakeit.com https://assets.wemakeit.com/ https://fonts.googleapis.com",
    "font-src 'self' https://www.tiny.cloud https://assets.wemakeit.com https://fonts.gstatic.com",
    "connect-src 'self' https://tools.ladecadanse.ch/ https://cdn.tiny.cloud https://unpkg.com https://wemakeit.com https://knownagents.com https://app.glitchtip.com",
    "frame-ancestors 'self' https://epic-magazine.ch",
    "frame-src 'none'",
    "object-src 'none'",
    "media-src 'none'",
    "form-action 'self' https://www.paypal.com",
    "base-uri 'self'",
    // pdf.js décode les PDF déposés dans les formulaires d'édition, et le fait
    // dans un worker. 'self' revient à la valeur qu'aurait donnée default-src :
    // aucun domaine tiers n'est ouvert, le worker vient de nos propres fichiers.
    "worker-src 'self'",
]);
if (ENV !== 'dev') {
    header("Content-Security-Policy: $csp");
}

$permissions = implode(', ', [
    'accelerometer=()',
    'ambient-light-sensor=()',
    'autoplay=()',
    'battery=()',
    'camera=()',
    'cross-origin-isolated=()',
    'display-capture=()',
    'document-domain=()',
    'encrypted-media=()',
    'execution-while-not-rendered=()',
    'execution-while-out-of-viewport=()',
    'fullscreen=(self)',
    'geolocation=()',
    'gyroscope=()',
    'keyboard-map=(self)',
    'magnetometer=()',
    'microphone=()',
    'midi=()',
    'navigation-override=()',
    'payment=()',
    'picture-in-picture=()',
    'publickey-credentials-get=(self)',
    'screen-wake-lock=()',
    'sync-xhr=(self)',
    'usb=()',
    'web-share=(self)',
    'xr-spatial-tracking=()',
]);
header("Permissions-Policy: $permissions");

//header("Access-Control-Allow-Origin: *");
//header('X-Frame-Options:    SAMEORIGIN');
header('Referrer-Policy: no-referrer-when-downgrade'); // This sends complete URL information to a potentially trustworthy URL from modern HTTPS State or from not modern HTTPS state to any origin . Information is sent for HTTPS -> HTTPS and HTTP -> HTTPS transition . This is the default Referrer-Policy

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
