<?php
/**
 * Accomplis la déconnexion en detruisant la session et redirige vers la page d'accueil
 */

require_once("app/bootstrap.php");

use Ladecadanse\Security\Sentry;
use Ladecadanse\Utils\Logger;

$videur = new Sentry();

$logger->log('global', 'activity', "Logout of ".$_SESSION['user'], Logger::GRAN_YEAR);

$videur->logout(); // destruction des caractéristiques de la session en cours.

header("Location: ".$url_site."/index.php");
exit();