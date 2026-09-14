<?php
/**
 * Ferme la session en cours et renvoie à l'accueil.
 *
 * Point d'entrée POST et non lien : une déconnexion en GET part toute seule au
 * moindre préchargement de lien (navigateur, antivirus, scanner d'e-mail) et
 * s'impose depuis n'importe quel site tiers (CSRF de déconnexion). Le jeton
 * déposé en session par _header.inc.php ferme cette dernière porte.
 */

require_once("../app/bootstrap.php");

$formTokenName = 'form_token_user_logout';

// requête qui ne vient pas du bouton « Sortir » : on ne fait rien d'autre que
// ramener à l'accueil, sans dire si une session était ouverte
if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_SESSION[$formTokenName])
    || !hash_equals($_SESSION[$formTokenName], (string) ($_POST[$formTokenName] ?? '')))
{
    header("Location: /index.php", true, 303);
    exit();
}

$logger->info('Logout', ['user' => $_SESSION['user'] ?? 'undefined']);

$videur->logout(); // destruction des caractéristiques de la session en cours

header("Location: /index.php", true, 303);
exit();
