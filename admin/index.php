<?php

require_once("../app/bootstrap.php");

use Ladecadanse\Utils\Text;
use Ladecadanse\HtmlShrink;

if (!$videur->checkGroup(4))
{
	header("Location: /user-login.php"); die();
}

$_SESSION['region_admin'] = '';
if ($_SESSION['Sgroupe'] >= 4 && !empty($_SESSION['Sregion']))
{
    $_SESSION['region_admin'] = $_SESSION['Sregion'];
}

$page_titre = "administration";
$extra_css = array("admin");
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

<?php if ($_SESSION['Sgroupe'] < 4) { ?>

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
 ORDER BY dateAjout DESC, pseudo ASC");

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
	$tab_datetime_dateajout = explode(" ", $datetime_dateajout);
	echo "<td>".$tab_datetime_dateajout[1]."</td><td>".$tab_datetime_dateajout[0]."</td>
	<td><a href=\"/user.php?idP=".$tab_pers['idPersonne']."\" >".sanitizeForHtml($tab_pers['pseudo'])."</a></td>
	<td><a href='mailto:".$tab_pers['email']."'>".$tab_pers['email']."</a></td>
	<td>".$tab_pers['groupe']."</td>
	<td>".$tab_pers['affiliation']."</td>

	";

	if ($_SESSION['Sgroupe'] < 2)
	{
		echo "<td><a href=\"/user-edit.php?action=editer&amp;idP=".$tab_pers['idPersonne']."\" title=\"Modifier\">".$iconeEditer."</a></td>";
	}
	echo "</tr>";

	$pair++;
}
?>
</table>

<h3 style="padding:0.4em 0">Derniers commentaires</h3>
<table class="ajouts" summary="Derniers commentaires ajoutés">
<tr>
    <th colspan="2">Date d'ajout</th>
    <th>Contenu</th>
    <th>Élément</th>
    <th>Type</th>
    <th>Statut</th>
    <th>par</th>
    <th>&nbsp;</th>
</tr>
<?php
$th_comm = array("contenu" => "Commentaire", "idEvenement" => "Événement", "element" => "Élément", "statut" => "Statut", "dateAjout" => "Date d'ajout", "heure" => "Heure");

$req_comm = $connector->query("
SELECT idCommentaire, id, idPersonne, contenu, statut, element, dateAjout
FROM commentaire WHERE dateAjout >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)
ORDER BY dateAjout DESC, idCommentaire DESC LIMIT 0, 10");

$pair = 0;

while($tab_comm = $connector->fetchArray($req_comm))
{

	if ($pair % 2 == 0)
	{
		echo "<tr>";
	}
	else
	{
		echo "<tr class=\"impair\">";
	}
	$datetime_dateajout = date_iso2app($tab_comm['dateAjout']);
	$tab_datetime_dateajout = explode(" ", $datetime_dateajout);
	echo "<td>".$tab_datetime_dateajout[1]."</td><td>".$tab_datetime_dateajout[0]."</td>";
	echo "<td class='small'>".mb_substr(sanitizeForHtml($tab_comm['contenu']), 0, 50)."</td>";

	$req_even = $connector->query("SELECT titre FROM evenement WHERE idEvenement=".$tab_comm['id']);
	$tab_even = $connector->fetchArray($req_even);

	if ($tab_comm['element'] == 'evenement')
	{

		$req_even = $connector->query("SELECT titre FROM evenement WHERE idEvenement=".$tab_comm['id']);
		$tab_even = $connector->fetchArray($req_even);
		echo "<td><a href=\"/evenement.php?idE=".$tab_comm['id']."\" title=\"Voir l'événement\">".$tab_even['titre']."</a></td>";

	}
	else if ($tab_comm['element'] == 'lieu')
	{
		$req_even = $connector->query("SELECT nom FROM lieu WHERE idLieu=".$tab_comm['id']);
		$tab_even = $connector->fetchArray($req_even);
		echo "<td><a href=\"/lieu.php?idL=".$tab_comm['id']."\" title=\"Voir le lieu\">".$tab_even['nom']."</a></td>";

	}

	echo "<td>".$tab_comm['element']."</td>";
	echo "<td>".$tab_icones_statut[$tab_comm['statut']]."</td>";

	$nom_auteur = "<i>Ancien membre</i>";

	if ($tab_auteur = $connector->fetchArray($connector->query("SELECT pseudo FROM personne WHERE idPersonne=".$tab_comm['idPersonne'])))
	{
		$nom_auteur = "<a href=\"/user.php?idP=".$tab_comm['idPersonne']."\"
		title=\"Voir le profile de la personne\">".sanitizeForHtml($tab_auteur['pseudo'])."</a>";
	}
	echo "<td>".$nom_auteur."</td>";

	//Edition pour l'admin ou l'auteur
	if ($_SESSION['Sgroupe'] <= 4)
	{
		echo "<td><a href=\"/multi-comment.php?action=editer&amp;idC=".$tab_comm['idCommentaire']."\" title=\"Éditer la brêve\">".$iconeEditer."</a></td>";
	}

	echo "</tr>";

	$pair++;
}
?>
</table>


<?php } ?>

<?php if ($_SESSION['Sgroupe'] < 4) { ?>
<p><a href="/admin/gerer.php?element=personne">Gérer les personnes</a></p>
<?php } ?>

<?php if (!empty($_SESSION['region_admin'])) { ?>
    <h3><?php echo $glo_regions[$_SESSION['region_admin']]; ?></h3>
<?php } ?>

<h4 style="padding:0.4em 0">Événements ajoutés ces 3 derniers jours</h4>

<?php

$troisJoursAvant = date("Y-m-d H:i:s", time() - (3*86400));

/* EVENEMENTS
* classés par date d'ajout
*/


$sql_region = '';
if (!empty( $_SESSION['region_admin']))
    $sql_region = " AND region='".$connector->sanitize( $_SESSION['region_admin'])."'";

$sql_even = "SELECT idEvenement, idLieu, idPersonne, titre,
 dateEvenement, horaire_debut, horaire_fin, genre, nomLieu, adresse, statut, flyer, dateAjout
 FROM evenement WHERE dateAjout >= DATE_SUB(CURDATE(), INTERVAL 3 DAY) ".$sql_region."
 ORDER BY dateAjout DESC, idEvenement DESC";

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
		$req_lieu = $connector->query("SELECT nom FROM lieu WHERE idLieu=".$tab_even['idLieu']);
		$tabLieu = $connector->fetchArray($req_lieu);
		$nomLieu = "<a href=\"/lieu.php?idL=".$tab_even['idLieu']."\" title=\"Voir la fiche du lieu : ".sanitizeForHtml($tabLieu['nom'])." \">".sanitizeForHtml($tabLieu['nom'])."</a>";
	}


	if ($pair % 2 == 0)
	{
		echo "<tr>";
	}
	else
	{
		echo "<tr class=\"impair\">";
	}


	echo "<td><a href=\"/evenement.php?idE=".$tab_even['idEvenement']."\" title=\"Voir la fiche de l'événement\" class='titre'>".sanitizeForHtml($tab_even['titre'])."</a></td>
	<td>".$nomLieu."</td>
	<td>".date_iso2app($tab_even['dateEvenement'])."</td>";

        echo "<td>".ucfirst($glo_tab_genre[$tab_even['genre']])."</td>";

        echo "<td>";

	echo afficher_debut_fin($tab_even['horaire_debut'], $tab_even['horaire_fin'], $tab_even['dateEvenement']);

	echo "</td>
	<td style='text-align: center;'>".$tab_icones_statut[$tab_even['statut']]."</td>";

	$datetime_dateajout = date_iso2app($tab_even['dateAjout']);
	$tab_datetime_dateajout = explode(" ", $datetime_dateajout);
	echo "<td>".$tab_datetime_dateajout[1]." ".$tab_datetime_dateajout[0]."</td>";

	$nom_auteur = "-";

	if ($tab_auteur = $connector->fetchArray($connector->query("SELECT pseudo FROM personne WHERE idPersonne=".$tab_even['idPersonne'])))
	{
		$nom_auteur = "<a href=\"/user.php?idP=".$tab_even['idPersonne']."\"
		title=\"Voir le profile de la personne\">".sanitizeForHtml($tab_auteur['pseudo'])."</a>";
	}
	echo "<td>".$nom_auteur."</td>";

	if ($_SESSION['Sgroupe'] <= 4)
	{
		echo "<td><a href=\"/evenement-edit.php?action=editer&amp;idE=".$tab_even['idEvenement']."\" title=\"Éditer l'événement\">".$iconeEditer."</a></td>";
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

<?php if (0) { ?>
<h3>Derniers événements modifiés</h3>

<table summary="Derniers événements modifiés">
<tr>
<th colspan="2">Modification</th>
<th>Titre</th>
<th>Lieu</th>
<th>Date</th>
<th>Flyer</th>
<th>Statut</th>
<th>Ajouté par</th>
<th>&nbsp;</th>
</tr>
<?php

$troisJoursAvant = date("Y-m-d H:i:s", time() - (3*86400));



$req_getEvenement = $connector->query("SELECT idEvenement, idLieu, idPersonne, titre,
 dateEvenement, genre, nomLieu, adresse, statut, flyer, dateAjout, date_derniere_modif
 FROM evenement
 WHERE dateAjout!=date_derniere_modif
 ORDER BY date_derniere_modif DESC, idEvenement DESC LIMIT 0, 10");

$pair = 0;
while($tab_even = $connector->fetchArray($req_getEvenement))
{
	$nomLieu = sanitizeForHtml($tab_even['nomLieu']);

	if ($tab_even['idLieu'] != 0)
	{
		$req_lieu = $connector->query("SELECT nom FROM lieu WHERE idLieu=".$tab_even['idLieu']);
		$tabLieu = $connector->fetchArray($req_lieu);
		$nomLieu = "<a href=\"/lieu.php?idL=".$tab_even['idLieu']."\" title=\"Voir la fiche du lieu : ".sanitizeForHtml($tabLieu['nom'])." \">".sanitizeForHtml($tabLieu['nom'])."</a>";
	}


	if ($pair % 2 == 0)
	{
		echo "<tr>";
	}
	else
	{
		echo "<tr class=\"impair\">";
	}

	$datetime_dateajout = date_iso2app($tab_even['date_derniere_modif']);
	$tab_datetime_dateajout = explode(" ", $datetime_dateajout);
	echo "<td>".$tab_datetime_dateajout[1]."</td><td>".$tab_datetime_dateajout[0]."</td>";

	echo "
	<td><a href=\"/evenement.php?idE=".$tab_even['idEvenement']."\" title=\"Voir la fiche de l'événement\">".sanitizeForHtml($tab_even['titre'])."</a></td>
	<td>".$nomLieu."</td>
	<td>".date_iso2app($tab_even['dateEvenement'])."</td>
	<td>";
	if (!empty($tab_even['flyer']))
	{
		$imgInfo = getimagesize($rep_images_even.$tab_even['flyer']);
		echo HtmlShrink::popupLink($url_uploads_events.$tab_even['flyer'], "Flyer", $imgInfo[0]+20, $imgInfo[1]+20, $iconeImage);
	}
	echo "</td>
	<td>".$tab_icones_statut[$tab_even['statut']]."</td>";

	$nom_auteur = "<i>Ancien membre</i>";

	if ($tab_auteur = $connector->fetchArray($connector->query("SELECT pseudo FROM personne WHERE idPersonne=".$tab_even['idPersonne'])))
	{
		$nom_auteur = "<a href=\"/user.php?idP=".$tab_even['idPersonne']."\"
		title=\"Voir le profile de la personne\">".sanitizeForHtml($tab_auteur['pseudo'])."</a>";
	}
	echo "<td>".$nom_auteur."</td>";

	if ($_SESSION['Sgroupe'] <= 4)
	{
		echo "<td><a href=\"/evenement-edit.php?action=editer&amp;idE=".$tab_even['idEvenement']."\" title=\"Éditer l'événement\">".$iconeEditer."</a></td>";
	}
	echo "</tr>";

	$pair++;
}

?>
</table>
<?php } ?>

<?php if ($_SESSION['Sgroupe'] < 4) { ?>

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

	$req_auteur = $connector->query("SELECT pseudo FROM personne WHERE idPersonne=".$tab_desc['idPersonne']);
	$tabAuteur = $connector->fetchArray($req_auteur);

	$req_lieu = $connector->query("SELECT nom FROM lieu WHERE idLieu=".$tab_desc['idLieu']);
	$tabLieu = $connector->fetchArray($req_lieu);
	$nomLieu = "<a href=\"/lieu.php?idL=".$tab_desc['idLieu']."\" title=\"Éditer le lieu\">".sanitizeForHtml($tabLieu['nom'])."</a>";


	if ($pair % 2 == 0)
	{
		echo "<tr>";
	}
	else
	{
		echo "<tr class=\"impair\">";
	}

	$datetime_dateajout = date_iso2app($tab_desc['dateAjout']);
	$tab_datetime_dateajout = explode(" ", $datetime_dateajout);
	echo "<td>".$tab_datetime_dateajout[1]."</td><td>".$tab_datetime_dateajout[0]."</td>";

	echo "<td>".$nomLieu."</td>";
	if (mb_strlen($tab_desc['contenu']) > 200)
	{
		$tab_desc['contenu'] = mb_substr($tab_desc['contenu'], 0, 200)." [...]";
	}
	echo "<td class=\"tdleft small\">".Text::html_substr($tab_desc['contenu'])."</td>";
	$nom_auteur = "<i>Ancien membre</i>";

	if ($tab_auteur = $connector->fetchArray($connector->query("SELECT pseudo FROM personne WHERE idPersonne=".$tab_desc['idPersonne'])))
	{
		$nom_auteur = "<a href=\"/user.php?idP=".$tab_desc['idPersonne']."\"
		title=\"Voir le profile de la personne\">".sanitizeForHtml($tab_auteur['pseudo'])."</a>";
	}
	echo "<td>".$tab_desc['type']."</td>";
	echo "<td>".$nom_auteur."</td>";
	if ( $_SESSION['Sgroupe'] <= 4)
	{
		echo "<td><a href=\"/lieu-text-edit.php?action=editer&amp;idL=" . $tab_desc['idLieu'] . "&amp;idP=" . $tab_desc['idPersonne'] . "&type=" . $tab_desc['type'] . "\" title=\"Éditer le lieu\">" . $iconeEditer . "</a></td>";
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
