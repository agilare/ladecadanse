<?php
/**
 *  included at the beginning of each page
 */

require_once('env.php');

require $rep_absolu . 'vendor/autoload.php';

require_once('config.php');

// BOOTSTRAP
use Ladecadanse\Utils\DbConnector;
use Ladecadanse\Utils\Logger;
use Ladecadanse\Security\Authorization;


session_start();

$connector = new DbConnector($param['dbhost'], $param['dbname'], $param['dbusername'], $param['dbpassword']);

// par défaut
if (empty($_SESSION['region']))
    $_SESSION['region'] = 'ge';

// à l'aide du cookie ou de l'IP
if (!empty($_COOKIE['ladecadanse_region']))
{
    $_SESSION['region'] = $_COOKIE['ladecadanse_region'];
}
/*
  elseif ($user_region_detected == 'VD')
  {
  $_SESSION['region'] = strtolower($user_region_detected);
  }
 */

$get['region'] = filter_input(INPUT_GET, "region", FILTER_SANITIZE_STRING);

if (array_key_exists($get['region'], $glo_regions))
{
    $_SESSION['region'] = $get['region'];
    setcookie("ladecadanse_region", $get['region'], time() + 36000, '', true, true);  /* , 'ladecadanse.darksite.ch' */
}

$url_query_region = '';
$url_query_region_et = '';
$url_query_region_1er = '';
$get['region'] = '';
if ($_SESSION['region'] != 'ge')
{
    $url_query_region = 'region=' . $_SESSION['region'];
    $url_query_region_et = 'region=' . $_SESSION['region'] . "&amp;";
    $url_query_region_1er = '?region=' . $_SESSION['region'];
    $get['region'] = $_SESSION['region'];
}

$logger = new Logger($rep_absolu . "var/logs/");

$authorization = new Authorization();

if (ENV == 'prod')
{
    include_once "Mail.php";
}


$nom_page = basename($_SERVER["SCRIPT_FILENAME"], '.php');

header('X-Content-Type-Options "nosniff"');
header('X-Frame-Options: "ALLOW-FROM https://epic-magazine.ch/"');

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

/**
 * FIXME: mv to String class
 * @param string $chaine
 * @return string
 */
function sanitizeForHtml(string $chaine): string
{
    return trim(htmlspecialchars($chaine));
}
