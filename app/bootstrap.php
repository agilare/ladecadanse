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
use Ladecadanse\Security\Sentry;
use Ladecadanse\Utils\DbConnector;
use Ladecadanse\Utils\DbConnectorPdo;
use Ladecadanse\Utils\Logger;
use Ladecadanse\Utils\RegionConfig;
use Ladecadanse\Utils\Utils;
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
[$url_query_region, $url_query_region_et, $url_query_region_1er] = $regionConfig->getAppVars();

$_SESSION['user_prefs_agenda_order'] = $_SESSION['user_prefs_agenda_order'] ?? 'dateAjout';

$logger = new Logger(__DIR__ . "/../var/logs/");

$connector = new DbConnector(DB_HOST, DB_NAME, DB_USERNAME, DB_PASSWORD);
$connectorPdo = DbConnectorPdo::getInstance();

$authorization = new Authorization();

$videur = new Sentry();

$tplEngine = new TemplateEngine(__DIR__ . "/../resources/");

$translator = new Translator(__DIR__ . '/../resources/messages.yml');

$site_full_url = Utils::getBaseUrl()."/";

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

$assets = new AssetManager(__ROOT__ . "/web", "/web");

if (DARKVISITORS_ENABLED)
{
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

header('X-Content-Type-Options: nosniff');
define("CSP_NONCE", bin2hex(openssl_random_pseudo_bytes(32)));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-" . CSP_NONCE . "' https://unpkg.com https://tools.ladecadanse.ch/ https://code.jquery.com https://darkvisitors.com https://browser.sentry-cdn.com https://www.google.com https://www.gstatic.com https://www.paypalobjects.com https://liberapay.com https://wemakeit.com https://assets.wemakeit.com https://cdn.tiny.cloud https://browser.sentry-cdn.com; img-src 'self' https://tile.openstreetmap.org https://tools.ladecadanse.ch/ https://unpkg.com https://streetviewpixels-pa.googleapis.com https://lh3.ggpht.com https://www.paypalobjects.com https://sp.tinymce.com data:; style-src 'self' 'unsafe-inline' https://unpkg.com https://fonts.googleapis.com https://cdn.tiny.cloud https://www.tiny.cloud https://wemakeit.com https://assets.wemakeit.com/; font-src 'self' https://fonts.gstatic.com https://www.tiny.cloud https://assets.wemakeit.com; connect-src 'self' https://tools.ladecadanse.ch/ https://cdn.tiny.cloud https://unpkg.com https://wemakeit.com; frame-ancestors 'self' https://epic-magazine.ch; frame-src https://www.google.com; object-src 'none'; media-src 'none'; form-action 'self' https://www.paypal.com; base-uri 'self'; worker-src 'none';");
header("Permissions-Policy: accelerometer=(), ambient-light-sensor=(), autoplay=(), battery=(), camera=(), cross-origin-isolated=(), display-capture=(), document-domain=(), encrypted-media=(), execution-while-not-rendered=(), execution-while-out-of-viewport=(), fullscreen=(self), geolocation=(), gyroscope=(), keyboard-map=*, magnetometer=(), microphone=(), midi=(), navigation-override=(), payment=(), picture-in-picture=(), publickey-credentials-get=*, screen-wake-lock=(), sync-xhr=(self), usb=(), web-share=*, xr-spatial-tracking=()");

//header("Access-Control-Allow-Origin: *");
header('X-Frame-Options:    SAMEORIGIN');
header('Referrer-Policy: no-referrer-when-downgrade'); // This sends complete URL information to a potentially trustworthy URL from modern HTTPS State or from not modern HTTPS state to any origin . Information is sent for HTTPS -> HTTPS and HTTP -> HTTPS transition . This is the default Referrer-Policy

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
