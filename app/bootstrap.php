<?php
/**
 *  included at the beginning of each page
 */

require_once('env.php');

require $rep_absolu . 'vendor/autoload.php';

use Ladecadanse\Utils\DbConnector;
use Ladecadanse\Utils\Logger;
use Ladecadanse\Utils\RegionConfig;
use Ladecadanse\Security\Authorization;
use Ladecadanse\Utils\Utils;

require_once('config.php');

session_start();

$regionConfig = new RegionConfig($glo_regions);
list($url_query_region, $url_query_region_et, $url_query_region_1er) = $regionConfig->getAppVars();

$logger = new Logger($rep_absolu . "var/logs/");

$connector = new DbConnector($param['dbhost'], $param['dbname'], $param['dbusername'], $param['dbpassword']);

$authorization = new Authorization();

$site_full_url = Utils::getBaseUrl()."/";
$nom_page = basename($_SERVER["SCRIPT_FILENAME"], '.php');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: "ALLOW-FROM https://epic-magazine.ch/"');

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

/**
 * FIXME: mv to String class
 * @param string $chaine
 * @return string
 */
function sanitizeForHtml(?string $chaine): string
{
    return trim(htmlspecialchars($chaine));
}
