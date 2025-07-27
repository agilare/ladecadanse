<?php

global $connector, $tab_icones_statut;
require_once("../app/bootstrap.php");

use Ladecadanse\Utils\Text;
use Ladecadanse\HtmlShrink;
use Ladecadanse\UserLevel;

if (!$videur->checkGroup(UserLevel::ADMIN)) {
	header("Location: /user-login.php"); die();
}

$_SESSION['region_admin'] = '';
if ($_SESSION['Sgroupe'] >= UserLevel::ADMIN && !empty($_SESSION['Sregion'])) {
    $_SESSION['region_admin'] = $_SESSION['Sregion'];
}

$page_titre = "administration";
$extra_css = ["admin"];
require_once '../_header.inc.php';
?>

<div id="contenu" class="colonne">

    <?php
//les dates au delà de 2 jours sont dispo pour être archivées
define("JOUR_LIM", 2);

$troisJoursAvant = date("Y-m-d H:i:s", time() - (3*86400));

?>

<div id="entete_contenu">
	<h2>Tableau de bord</h2>
	<div class="spacer"></div>
</div>

<div id="tableaux">

    <?php if ($_SESSION['Sgroupe'] < UserLevel::ADMIN) { ?>

<h3 style="padding:0.4em 0">Inscriptions de ces 3 derniers jours</h3>
<table summary="Dernières inscriptions">
<tr>
    <th colspan="2">Date</th>
    <th>Identifiant</th>
    <th>E-mail</th>
    <th>Groupe</th>
    <th>Affiliation</th>
    <th>&nbsp;</th>
</tr>
<?php


/* PERSONNES
* classés par date d'ajout
*/

$req_get = $connector->query("SELECT idPersonne, pseudo, groupe, affiliation, email, dateAjout
 FROM personne WHERE dateAjout >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)
 ORDER BY dateAjout DESC, pseudo ASC LIMIT 200");

$pair = 0;
while($tab_pers = $connector->fetchArray($req_get))
{
	if ($pair % 2 == 0)
	{
		echo "<tr>";
	}
	else
	{
		echo "<tr class=\"impair\">";
	}

	$datetime_dateajout = date_iso2app($tab_pers['dateAjout']);
	$tab_datetime_dateajout = explode(" ", (string) $datetime_dateajout);
	echo "<td>".$tab_datetime_dateajout[1]."</td><td>".$tab_datetime_dateajout[0]."</td>
	<td><a href=\"/user.php?idP=".(int)$tab_pers['idPersonne']."\" >".sanitizeForHtml($tab_pers['pseudo'])."</a></td>
	<td><a href='mailto:".$tab_pers['email']."'>".$tab_pers['email']."</a></td>
	<td>".$tab_pers['groupe']."</td>
	<td>" . sanitizeForHtml($tab_pers['affiliation']) . "</td>

	";

	if ($_SESSION['Sgroupe'] < UserLevel::SUPERADMIN) {
		echo "<td><a href=\"/user-edit.php?action=editer&amp;idP=".(int)$tab_pers['idPersonne']."\" title=\"Modifier\">".$iconeEditer."</a></td>";
	}
	echo "</tr>";

	$pair++;
}
?>
    </table>

<?php } ?>

<?php if ($_SESSION['Sgroupe'] < UserLevel::ADMIN) { ?>
    <p><a href="/admin/gerer.php?element=personne">Gérer les personnes</a></p>
<?php } ?>

<?php if (!empty($_SESSION['region_admin'])) { ?>
    <h3><?php echo $glo_regions[$_SESSION['region_admin']]; ?></h3>
<?php } ?>

<h4 style="padding:0.4em 0">Événements ajoutés ces 3 derniers jours</h4>

<?php

$troisJoursAvant = date("Y-m-d H:i:s", time() - (3*86400));

$sql_region = '';
if (!empty( $_SESSION['region_admin']))
    $sql_region = " AND region='".$connector->sanitize( $_SESSION['region_admin'])."'";

$sql_even = "SELECT idEvenement, idLieu, idPersonne, titre,
 dateEvenement, horaire_debut, horaire_fin, genre, nomLieu, adresse, statut, flyer, dateAjout
 FROM evenement WHERE dateAjout >= DATE_SUB(CURDATE(), INTERVAL 3 DAY) ".$sql_region."
 ORDER BY dateAjout DESC, idEvenement DESC LIMIT 500";

//echo $sql_even;

$req_getEvenement = $connector->query($sql_even);

if ($connector->getNumRows($req_getEvenement) > 0)
{
?>
    <table summary="Derniers événements ajoutés" id="derniers_evenements_ajoutes">
    <tr>
        <th>Titre</th>
        <th>Lieu</th>
        <th>Date</th>
        <th>Catégorie</th>
        <th>Horaire</th>
        <th>Statut</th>
        <th>Ajouté</th>
        <th>par</th>
        <th>&nbsp;</th>
    </tr>
<?php

$pair = 0;
while($tab_even = $connector->fetchArray($req_getEvenement))
{
	$nomLieu = sanitizeForHtml($tab_even['nomLieu']);

	if ($tab_even['idLieu'] != 0)
	{
		$req_lieu = $connector->query("SELECT nom FROM lieu WHERE idLieu=".(int) $tab_even['idLieu']);
		$tabLieu = $connector->fetchArray($req_lieu);
		$nomLieu = "<a href=\"/lieu.php?idL=".(int) $tab_even['idLieu']."\" title=\"Voir la fiche du lieu : ".sanitizeForHtml($tabLieu['nom'])." \">".sanitizeForHtml($tabLieu['nom'])."</a>";
	}


	if ($pair % 2 == 0)
	{
		echo "<tr>";
	}
	else
	{
		echo "<tr class=\"impair\">";
	}


	echo "<td><a href=\"/event/evenement.php?idE=".(int)$tab_even['idEvenement']."\" title=\"Voir la fiche de l'événement\" class='titre'>".sanitizeForHtml($tab_even['titre'])."</a></td>
	<td>".$nomLieu."</td>
	<td>".date_iso2app($tab_even['dateEvenement'])."</td>";

        echo "<td>".ucfirst((string) $glo_tab_genre[$tab_even['genre']])."</td>";

        echo "<td>";

	echo afficher_debut_fin($tab_even['horaire_debut'], $tab_even['horaire_fin'], $tab_even['dateEvenement']);

	echo "</td>
	<td style='text-align: center;'>".$tab_icones_statut[$tab_even['statut']]."</td>";

	$datetime_dateajout = date_iso2app($tab_even['dateAjout']);
	$tab_datetime_dateajout = explode(" ", (string) $datetime_dateajout);
	echo "<td>".$tab_datetime_dateajout[1]." ".$tab_datetime_dateajout[0]."</td>";

	$nom_auteur = "-";

	if ($tab_auteur = $connector->fetchArray($connector->query("SELECT pseudo FROM personne WHERE idPersonne=".(int) $tab_even['idPersonne'])))
	{
		$nom_auteur = "<a href=\"/user.php?idP=".(int)$tab_even['idPersonne']."\"
		title=\"Voir le profile de la personne\">".sanitizeForHtml($tab_auteur['pseudo'])."</a>";
	}
	echo "<td>".$nom_auteur."</td>";

	if ($_SESSION['Sgroupe'] <= UserLevel::ADMIN) {
		echo "<td><a href=\"/evenement-edit.php?action=editer&amp;idE=".(int)$tab_even['idEvenement']."\" title=\"Éditer l'événement\">".$iconeEditer."</a></td>";
	}
	echo "</tr>";

	$pair++;
}

?>
</table>
<?php } else { ?>
Rien
<?php } ?>
<p><a href="/admin/gererEvenements.php">Gérer les événements</a></p>

<?php if ($_SESSION['Sgroupe'] < UserLevel::ADMIN) { ?>

    <h3 style="padding:0.2em">Derniers textes ajoutés</h3>

<table summary="Derniers textes ajoutés">
<tr>
    <th colspan="2">Ajouté le</th>
    <th>Lieu</th>
    <th>Contenu</th>
    <th>Type</th>
    <th>par</th>
    <th>&nbsp;</th>
</tr>

<?php

$sql_req = "SELECT descriptionlieu.idLieu AS idLieu, descriptionlieu.idPersonne, descriptionlieu.dateAjout, contenu, type
FROM descriptionlieu, lieu WHERE descriptionlieu.idLieu=lieu.idLieu ".$sql_region."  ORDER BY descriptionlieu.dateAjout DESC LIMIT 5";

//echo $sql_req;
$req_getDes = $connector->query($sql_req);

while ($tab_desc = $connector->fetchArray($req_getDes))
{

	$req_auteur = $connector->query("SELECT pseudo FROM personne WHERE idPersonne=".(int) $tab_desc['idPersonne']);
	$tabAuteur = $connector->fetchArray($req_auteur);

	$req_lieu = $connector->query("SELECT nom FROM lieu WHERE idLieu=".(int) $tab_desc['idLieu']);
	$tabLieu = $connector->fetchArray($req_lieu);
	$nomLieu = "<a href=\"/lieu.php?idL=".(int)$tab_desc['idLieu']."\" title=\"Éditer le lieu\">".sanitizeForHtml($tabLieu['nom'])."</a>";


	if ($pair % 2 == 0)
	{
		echo "<tr>";
	}
	else
	{
		echo "<tr class=\"impair\">";
	}

	$datetime_dateajout = date_iso2app($tab_desc['dateAjout']);
	$tab_datetime_dateajout = explode(" ", (string) $datetime_dateajout);
	echo "<td>".$tab_datetime_dateajout[1]."</td><td>".$tab_datetime_dateajout[0]."</td>";

	echo "<td>".$nomLieu."</td>";
	if (mb_strlen((string) $tab_desc['contenu']) > 200)
	{
		$tab_desc['contenu'] = mb_substr((string) $tab_desc['contenu'], 0, 200)." [...]";
	}
	echo "<td class=\"tdleft small\">" . Text::html_substr($tab_desc['contenu']) . "</td>";
        $nom_auteur = "<i>Ancien membre</i>";

        if ($tab_auteur = $connector->fetchArray($connector->query("SELECT pseudo FROM personne WHERE idPersonne=".(int) $tab_desc['idPersonne'])))
	{
		$nom_auteur = "<a href=\"/user.php?idP=".$tab_desc['idPersonne']."\"
		title=\"Voir le profile de la personne\">".sanitizeForHtml($tab_auteur['pseudo'])."</a>";
	}
	echo "<td>".$tab_desc['type']."</td>";
	echo "<td>".$nom_auteur."</td>";
	if ($_SESSION['Sgroupe'] <= UserLevel::ADMIN) {
		echo "<td><a href=\"/lieu-text-edit.php?action=editer&amp;idL=" . (int)$tab_desc['idLieu'] . "&amp;idP=" .(int) $tab_desc['idPersonne'] . "&type=" . $tab_desc['type'] . "\" title=\"Éditer le lieu\">" . $iconeEditer . "</a></td>";
        }
	echo "</tr>";

	$pair++;
}

?>
</table>

<?php } ?>

</div><!-- fin tableaux -->

</div><!-- fin contenu -->

<div id="colonne_gauche" class="colonne">
    <?php
    include("_menuAdmin.inc.php");
    ?>
</div>


<div class="spacer"><!-- --></div>
<?php
include("../_footer.inc.php");
?>
