<?php
/**
 * Accomplis la déconnexion en detruisant la session et redirige vers la page d'accueil
 */

require_once("app/bootstrap.php");


$logger->info('Logout', ['user' => $_SESSION['user'] ?? 'undefined']);

$videur->logout(); // destruction des caractéristiques de la session en cours.

header("Location: /index.php");
exit();
