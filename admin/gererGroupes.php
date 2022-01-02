<?php
/**
 * Tableau listant tous les groupes, lien pour les éditer
 * Pour l'admin
 *
 * @category   gestion
 * @see ajouterGroupe.php
 * @author     Michel Gaudry <michel@ladecadanse.ch>
 */
if (is_file("../config/reglages.php")) {
	require_once("../config/reglages.php");
} else {
	echo "<p>Problème de chargement de la configuration du site, veuillez repasser plus tard</p>";
	exit;
}

use Ladecadanse\Security\Sentry;

$videur = new Sentry();

if (!$videur->checkGroup(1))
	header("Location: ".$url_site."index.php"); die();


require_once($rep_includes.'head.php');

// header("Cache-Control: max-age=600, must-revalidate");
header_html("La décadanse : gérer les groupes", $indexMotsClef, $indexCssScreen, $indexCssPrint);
?>


<body>

<div><a name="haut"></a></div>

<!-- Début Page -->
<div id="page">

<!-- Début Menu -->
<div class="menu">
<?php menu_genre(); ?>
</div>
<!-- Fin Menu-->

<!-- Début Colonne gauche -->
<div id="colonnegauche">

<!-- Début Logo -->
<div id="logo">
<?php logo(1); ?>
</div>
<!-- Fin Logo -->

<div id="acces_membre">
<?php acces_membre(); ?>
</div>

<!-- Actions -->
<div id="menu_actions">
<?php
menu_actions();
menu_admin(end(explode('/',$_SERVER['PHP_SELF'])));
?>
</div>
<!-- Fin actions -->

</div>
<!-- Fin Colonnegauche -->

<!-- Début Evenements -->
<div id="evenements">

<h1>Groupes</h1>
<?php

$getGroupes = $connector->query("SELECT idGroupe, nom, description FROM groupes");

echo "<table border=1>
<tr bgcolor=lightgray>
<th>ID</th><th>Nom</th><th>Description</th><th>Modifier</th>
</tr>";

while(list($idGroupe, $nom, $description) = $connector->fetchArray($getGroupes) ) {

	echo "<tr>
	<td>".$idGroupe."</td>
	<td>".sanitizeForHtml($nom)."</td>
	<td>".sanitizeForHtml($description)."</td>
	<td><a href=\"".$url_site."ajouterGroupe.php?action=editer&idG=".$idGroupe."\" title=\"Modifier le groupe\">".$iconeEditer."</a></td>
	</tr>";
}

echo "</table>";
?>

</div>
<!-- fin Evenements -->


</div>
<!-- fin Page -->
</body>

</html>
