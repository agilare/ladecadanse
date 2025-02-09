<?php
/**
 *  included at the beginning of each page
 */

require_once __DIR__ . '/env.php';

require __DIR__ . '/../vendor/autoload.php';

use Ladecadanse\Evenement;
use Ladecadanse\Security\Authorization;
use Ladecadanse\Security\Sentry;
use Ladecadanse\Utils\DbConnector;
use Ladecadanse\Utils\Logger;
use Ladecadanse\Utils\RegionConfig;
use Ladecadanse\Utils\Utils;
use Whoops\Handler\PrettyPageHandler;

require_once __DIR__ . '/config.php';

date_default_timezone_set(DATE_DEFAULT_TIMEZONE);

if (ENV === 'dev') {
    $whoops = new \Whoops\Run;
    $whoopsHandler = new PrettyPageHandler();
    $whoopsHandler->setEditor('netbeans');
    $whoops->pushHandler($whoopsHandler);
    $whoops->register();
}

// FIXME: seems to not work on current depl server (darksite.ch)
// session_save_path(__ROOT__ . "/var/sessions");
// ini_set('session.gc_probability', 1); // to enable auto clean of old session in Debian https://www.php.net/manual/en/function.session-save-path.php#98106
session_start(['cookie_secure' => true, 'cookie_httponly' => true, 'cookie_samesite' => 'Lax']);

$regionConfig = new RegionConfig($glo_regions);
list($url_query_region, $url_query_region_et, $url_query_region_1er) = $regionConfig->getAppVars();

$logger = new Logger(__DIR__ . "/../var/logs/");

$connector = new DbConnector(DB_HOST, DB_NAME, DB_USERNAME, DB_PASSWORD);

$authorization = new Authorization();

$videur = new Sentry();

$site_full_url = Utils::getBaseUrl()."/";

Evenement::$systemDirPath = $rep_images_even;
Evenement::$urlDirPath = $url_uploads_events;

$nom_page = basename($_SERVER["SCRIPT_FILENAME"], '.php');

header('X-Content-Type-Options: nosniff');
// v1
//header("Content-Security-Policy: frame-ancestors 'self' https://epic-magazine.ch");
// v2
define("CSP_NONCE", bin2hex(openssl_random_pseudo_bytes(32)));
header("Content-Security-Policy: default-src 'self'; script-src 'nonce-" . CSP_NONCE . "' https://code.jquery.com https://maps.googleapis.com https://browser.sentry-cdn.com https://www.google.com https://www.gstatic.com https://www.googletagmanager.com https://cdn.tiny.cloud; img-src https://maps.gstatic.com https://maps.googleapis.com https://streetviewpixels-pa.googleapis.com https://lh3.ggpht.com https://www.paypalobjects.com https://sp.tinymce.com data:; style-src  'unsafe-inline' https://fonts.googleapis.com https://cdn.tiny.cloud https://www.tiny.cloud; font-src https://fonts.gstatic.com https://www.tiny.cloud; connect-src https://maps.googleapis.com https://cdn.tiny.cloud https://www.google.com; frame-ancestors https://epic-magazine.ch; frame-src https://www.google.com; object-src 'none'; media-src 'none'; worker-src 'none'; upgrade-insecure-requests");
header('X-Frame-Options:    SAMEORIGIN');

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
