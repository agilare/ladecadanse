<?php
/**
 *  included at the beginning of each page
 */

require_once __DIR__ . '/env.php';

require __DIR__ . '/../vendor/autoload.php';

use Ladecadanse\Utils\DbConnector;
use Ladecadanse\Utils\Logger;
use Ladecadanse\Utils\RegionConfig;
use Ladecadanse\Security\Authorization;
use Ladecadanse\Security\Sentry;
use Ladecadanse\Utils\Utils;
use Whoops\Handler\PrettyPageHandler;

require_once __DIR__ . '/config.php';

$whoops = new \Whoops\Run;
$whoopsHandler = new PrettyPageHandler();
$whoopsHandler->setEditor('netbeans');
$whoops->pushHandler($whoopsHandler);
$whoops->register();

session_save_path(__ROOT__ . "/var/sessions");
session_start();

$regionConfig = new RegionConfig($glo_regions);
list($url_query_region, $url_query_region_et, $url_query_region_1er) = $regionConfig->getAppVars();

$logger = new Logger(__DIR__ . "/../var/logs/");

$connector = new DbConnector(DB_HOST, DB_NAME, DB_USERNAME, DB_PASSWORD);

$authorization = new Authorization();

$videur = new Sentry();

$site_full_url = Utils::getBaseUrl()."/";
$nom_page = basename($_SERVER["SCRIPT_FILENAME"], '.php');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: "ALLOW-FROM https://epic-magazine.ch/"');

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

/**
 * FIXME: mv to Text class
 * @param string $chaine dirty
 * @return string clean
 */
function sanitizeForHtml(?string $chaine): string
{
    return trim(htmlspecialchars($chaine));
}
