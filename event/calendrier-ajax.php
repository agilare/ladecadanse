<?php
/**
 * AJAX endpoint returning only the calendar HTML fragment.
 * Used by the Calendar JS module to update the calendar without full page reload.
 */

require_once("../app/bootstrap.php");

if (empty($_SERVER['HTTP_X_REQUESTED_WITH']))
{
    http_response_code(403);
    exit;
}

header('X-Robots-Tag: noindex');

$get['courant'] = $glo_auj_6h;
$courant_input = trim((string) ($_GET['courant'] ?? ''));
if (!empty($courant_input) && preg_match("/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}$/", $courant_input))
{
    $get['courant'] = $courant_input;
}

$calendar_no_selection = true;

include("_navigation_calendrier.inc.php");
