<?php
/**
 * Accomplis la déconnexion en detruisant la session et redirige vers la page d'accueil
 *
 * @category   acces
 * @author     Michel Gaudry <michel@ladecadanse.ch>
 */

if (is_file("config/reglages.php"))
{
	require_once("config/reglages.php");
}

require_once($rep_librairies."Sentry.php");
$videur = new Sentry();

$videur->logout();
// destruction des caractéristiques de la session en cours.

header("Location: ".$url_site."index.php");
exit();
?>
