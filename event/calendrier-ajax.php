<?php
/**
 * AJAX endpoint returning only the calendar HTML fragment.
 * Used by the Calendar JS module to update the calendar without full page reload.
 */

require_once("../app/bootstrap.php");

use Ladecadanse\Utils\QueryParamValidator;

if (empty($_SERVER['HTTP_X_REQUESTED_WITH']))
{
    http_response_code(403);
    exit;
}

header('X-Robots-Tag: noindex');

$get['courant'] = QueryParamValidator::jourFromQuery($_GET['courant'] ?? '', $glo_auj_6h);

// Allow highlighting the page's current date when it falls in the displayed month
$calendar_page_courant = QueryParamValidator::jourFromQuery($_GET['page_courant'] ?? '', '');
if ($calendar_page_courant === '')
{
    $calendar_no_selection = true;
}

include("_navigation_calendrier.inc.php");
