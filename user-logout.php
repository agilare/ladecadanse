<?php
/**
 * Accomplis la déconnexion en detruisant la session et redirige vers la page d'accueil
 */

require_once("app/bootstrap.php");

use Ladecadanse\Utils\Logger;


$logger->log('global', 'activity', "Logout of " . ($_SESSION['user'] ?? "undefined"), Logger::GRAN_YEAR);

$videur->logout(); // destruction des caractéristiques de la session en cours.

header("Location: /index.php");
exit();
