<?php

require_once("../app/bootstrap.php");

use Ladecadanse\UserLevel;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Utils\Utils;
use Ladecadanse\Utils\Text;

if (!$videur->checkGroup(4))
{
	header("Location: /user-login.php"); die();
}


$page_titre = "gérer";
require_once '../_header.inc.php';

$tab_listes = ["evenement" => "Événements", "lieu" => "Lieux", "organisateur" => "Organisateurs", "description" => "Descriptions", "personne" => "Personnes"];

$get = [];

if (!empty($_GET['element']))
{

	if (array_key_exists($_GET['element'], $tab_listes))
	{
		$get['element'] = $_GET['element'];
	}
	else
	{
		echo "element faux";
		exit;
	}
}
else
{
	$get['element'] = "evenements";
}


$get['page'] = 1;
if (isset($_GET['page']))
{
	$get['page'] = Validateur::validateUrlQueryValue($_GET['page'], "int", 1);
}

$tab_tris = ["dateAjout", "date_ajout", "idOrganisateur", "date_derniere_modif", "statut", "date_debut", "date_fin", "id", "titre", "groupe", "pseudo", "idPersonne"];

$get['tri_gerer'] = "dateAjout";
if (isset($_GET['tri_gerer']))
{
	$get['tri_gerer'] = Validateur::validateUrlQueryValue($_GET['tri_gerer'], "enum", 1, $tab_tris);

}

$tab_ordre = ["asc", "desc"];
$get['ordre'] = "desc";
$ordre_inverse = "asc";
if (isset($_GET['ordre']))
{
	$get['ordre'] = Validateur::validateUrlQueryValue($_GET['ordre'], "enum", 1, $tab_ordre);
	if ($get['ordre'] == "asc")
	{
		$ordre_inverse = "desc";
	}
	else if ($get['ordre'] == "desc")
	{
		$ordre_inverse = "asc";
	}
}

$get['nblignes'] = 500;
if (!empty($_GET['nblignes']))
{
	$get['nblignes'] = Validateur::validateUrlQueryValue($_GET['nblignes'], "int", 1);
}


$get['terme'] = '';
if (!empty($_GET['terme']))
{
	$get['terme'] = $_GET['terme'];
}


$_SESSION['region_admin'] = '';
if ($_SESSION['Sgroupe'] >= UserLevel::ADMIN && !empty($_SESSION['Sregion']) && in_array($get['element'], ['lieu'])) {
    $_SESSION['region_admin'] = $_SESSION['Sregion'];
}


$sql_where_region = '';
$titre_region = '';
if (!empty($_SESSION['region_admin']))
{
    $sql_where_region = " WHERE region='".$connector->sanitize($_SESSION['region_admin'])."' ";


        $titre_region = " - ".$glo_regions[$_SESSION['region_admin']];
}

?>

<!-- Deb Contenu -->
<div id="contenu" class="colonne">

	<div id="entete_contenu">
		<h2>Gérer les <?php echo $tab_listes[$get['element']].$titre_region; ?></h2>
	</div>

	<div class="spacer"></div>

<?php
if ($get['element'] == "description")
{
	$req_des = $connector->query("
	SELECT idLieu, idPersonne, dateAjout, contenu, date_derniere_modif
	FROM descriptionlieu
	ORDER BY ".$get['tri_gerer']." ".$get['ordre']." LIMIT ".($get['page'] - 1) * $get['nblignes'].",".$get['nblignes']);

	$req_nbdesc = $connector->query("SELECT COUNT(*) AS total FROM descriptionlieu");
	$tab_nbdesc = $connector->fetchArray($req_nbdesc);
	$tot_elements = $tab_nbdesc['total'];

	$th_descriptions = ["idLieu" => "Lieu",  "contenu" => "Contenu", "dateAjout" => "Date d'ajout", "date_derniere_modif" => "m-à-j"];

	echo HtmlShrink::getPaginationString($tot_elements, $get['page'], $get['nblignes'], 1, "", "?" . Utils::urlQueryArrayToString($get, "page") . "&page=");

        echo '<ul class="menu_nb_res">';
	foreach ($tab_nblignes as $nbl)
	{
		echo '<li ';
		if ($get['nblignes'] == $nbl) { echo 'class="ici"'; }

		echo '><a href="/admin/gerer.php?'.Utils::urlQueryArrayToString($get, "nblignes").'&nblignes='.$nbl.'">'.$nbl.'</a></li>';
	}
	echo '</ul>';
echo '<div class="spacer"></div>';
	echo "<table id=\"ajouts\"><tr>";
	foreach ($th_descriptions as $att => $th)
	{
		if ($att == "idLieu" || $att == "idPersonne" || $att == "contenu")
		{
			echo "<th>".$th."</th>";
		}
		else
		{
			if ($att == $get['tri_gerer'])
			{
				echo "<th class=\"ici\">".$icone[$get['ordre']];
			}
			else
			{
				echo "<th>";
			}

			echo "<a href=\"?element=" . $get['element'] . "&page=" . $get['page'] . "&tri_gerer=" . $att . "&ordre=" . $ordre_inverse . "&nblignes=" . $get['nblignes'] . "\">" . $th . "</a></th>";
            }
	}
	echo "<th>&nbsp;</th></tr>";

	$pair = 0;

	while($tab_desc = $connector->fetchArray($req_des))
	{

		$req_auteur = $connector->query("SELECT pseudo FROM personne WHERE idPersonne=".(int) $tab_desc['idPersonne']);
		$tabAuteur = $connector->fetchArray($req_auteur);

		$req_lieu = $connector->query("SELECT nom FROM lieu WHERE idLieu=".(int) $tab_desc['idLieu']);
		$tabLieu = $connector->fetchArray($req_lieu);
		$nomLieu = "<a href=\"/lieu.php?idL=".$tab_desc['idLieu']."\" title=\"Éditer le lieu\">".sanitizeForHtml($tabLieu['nom'])."</a>";

		if ($pair % 2 == 0)
		{
			echo "<tr>";
		}
		else
		{
			echo "<tr class=\"impair\" >";
		}

		echo "<td>".$nomLieu."</td>";

		if (mb_strlen((string) $tab_desc['contenu']) > 50)
		{
			$tab_desc['contenu'] = mb_substr((string) $tab_desc['contenu'], 0, 50)." [...]";
		}
		echo "<td class=\"tdleft\">".Text::wikiToHtml(sanitizeForHtml($tab_desc['contenu']))."</td>";
		echo "<td>".date_iso2app($tab_desc['dateAjout'])."</td>";

		echo "<td>";
		if ($tab_desc['date_derniere_modif'] != "0000-00-00 00:00:00")
		{
			echo date_iso2app($tab_desc['date_derniere_modif']);
		}
		echo "</td>";

		if ($_SESSION['Sgroupe'] <= UserLevel::ADMIN) {
			echo "<td><a href=\"/lieu-text-edit.php?action=editer&idL=" . $tab_desc['idLieu'] . "&idP=" . $tab_desc['idPersonne'] . "\" title=\"Éditer le lieu\">" . $iconeEditer . "</a></td>";
        }
		echo "</tr>";

		$pair++;
	} // while

	echo "</table>";




}
else if ($get['element'] == "lieu")
{
	$req_lieux = $connector->query("
	SELECT idLieu, idPersonne, nom, quartier, categorie, URL, statut, dateAjout, date_derniere_modif
	FROM lieu
        ".$sql_where_region."
	ORDER BY ".$get['tri_gerer']." ".$get['ordre']."
	LIMIT ".($get['page'] - 1) * $get['nblignes'].",".$get['nblignes']);

	$req_count = $connector->query("SELECT COUNT(*) AS total FROM lieu");
	$tab_count = $connector->fetchArray($req_count);
	$tot_elements = $tab_count['total'];

	$th_lieu = ["idLieu" => "ID",  "nom" => "Nom", "categorie" => "Catégorie", "URL" => "URL",
	"description" => "Desc", "dateAjout" => "Créé", "date_derniere_modif" => "Modifié", "statut" => "Statut"];

	echo HtmlShrink::getPaginationString($tot_elements, $get['page'], $get['nblignes'], 1, "", "?element=" . $get['element'] . "&tri_gerer=" . $get['tri_gerer'] . "&ordre=" . $get['ordre'] . "&page=");

        echo '<ul class="menu_nb_res">';
	foreach ($tab_nblignes as $nbl)
	{
		echo '<li ';
		if ($get['nblignes'] == $nbl) { echo 'class="ici"'; }

		echo '><a href="/admin/gerer.php?'.Utils::urlQueryArrayToString($get, "nblignes").'&nblignes='.$nbl.'">'.$nbl.'</a></li>';
	}
	echo '</ul>';
echo '<div class="spacer"></div>';
	echo "
	<table id=\"ajouts\">
	<tr>";

	foreach ($th_lieu as $att => $th)
	{
		if ($att == "adresse" || $att == "categorie" || $att == "URL" || $att == "description")
		{
			echo "<th>".$th."</th>";
		}
		else
		{
			if ($att == $get['tri_gerer'])
			{
				echo "<th class=\"ici\">".$icone[$get['ordre']];
			}
			else
			{
				echo "<th>";
			}
			echo "<a href=\"?element=" . $get['element'] . "&page=" . $get['page'] . "&tri_gerer=" . $att . "&ordre=" . $ordre_inverse . "&nblignes=" . $get['nblignes'] . "\">" . $th . "</a></th>";
        }
	}

	echo "<th></th></tr>";

	$pair = 0;

	while($tab_lieux = $connector->fetchArray($req_lieux))
	{

		$req_nbDes = $connector->query("SELECT COUNT(*) AS total_desc FROM descriptionlieu WHERE idLieu=".(int) $tab_lieux['idLieu']);
		$tabDes = $connector->fetchArray($req_nbDes);


		if ($pair % 2 == 0)
		{
			echo "<tr>";
		}
		else
		{
			echo "<tr class=\"impair\" >";
		}

		echo "
		<td>".$tab_lieux['idLieu']."</td>
		<td><a href=\"/lieu.php?idL=".$tab_lieux['idLieu']."\" title=\"Voir la fiche du lieu :".sanitizeForHtml($tab_lieux['nom'])."\">".sanitizeForHtml($tab_lieux['nom'])."</a></td>
		<td class=\"tdleft\"><ul>";

		$listeCat = explode(",", (string) $tab_lieux['categorie']);

		for ($i = 0, $totalCat = count($listeCat); $i<$totalCat; $i++)
		{
			echo "<li>".$listeCat[$i]."</li>";
		}

		echo "</ul></td>";
		echo "<td>";
		if (!empty($tab_lieux['URL']))
		{
			echo "<a href=\"http://".$tab_lieux['URL']."\" title=\"Aller sur le site du lieu\">".$iconeURL."</a>\n";
		}
		echo "</td>";
		echo "
		<td>".$tabDes['total_desc']."</td>
		<td>".date_iso2app($tab_lieux['dateAjout'])."</td>";

		echo "<td>";
		if ($tab_lieux['date_derniere_modif'] != "0000-00-00 00:00:00")
		{
			echo date_iso2app($tab_lieux['date_derniere_modif']);
		}
		echo "</td>
		<td>".$tab_icones_statut[$tab_lieux['statut']]."</td>";

		//Edition pour l'admin ou l'auteur
		if ($_SESSION['Sgroupe'] <= UserLevel::ADMIN) {
			echo "<td><a href=\"/lieu-edit.php?action=editer&idL=".$tab_lieux['idLieu']."\" title=\"Éditer le lieu\">".$iconeEditer."</a></td>";
		}
		echo "</tr>";

		$pair++;

	}

	echo "</table>";



}
else if ($get['element'] == "organisateur")
{
	if ($get['tri_gerer'] == 'dateAjout')
	{
		$get['tri_gerer'] = 'date_ajout';
	}

	$req_lieux = $connector->query("
	SELECT *
	FROM organisateur
	ORDER BY ".$get['tri_gerer']." ".$get['ordre']."
	LIMIT ".($get['page'] - 1) * $get['nblignes'].",".$get['nblignes']);

	$req_count = $connector->query("SELECT COUNT(*) AS total FROM organisateur");
	$tab_count = $connector->fetchArray($req_count);
	$tot_elements = $tab_count['total'];

	$th_lieu = ["idOrganisateur" => "ID",  "nom" => "Nom",
	 "date_ajout" => "Créé", "date_derniere_modif" => "Modifié", "statut" => "Statut"];

	echo HtmlShrink::getPaginationString($tot_elements, $get['page'], $get['nblignes'], 1, "", "?element=" . $get['element'] . "&tri_gerer=" . $get['tri_gerer'] . "&ordre=" . $get['ordre'] . "&page=");

        echo '<ul class="menu_nb_res">';
	foreach ($tab_nblignes as $nbl)
	{
		echo '<li ';
		if ($get['nblignes'] == $nbl) { echo 'class="ici"'; }

		echo '><a href="/admin/gerer.php?'.Utils::urlQueryArrayToString($get, "nblignes").'&nblignes='.$nbl.'">'.$nbl.'</a></li>';
	}
	echo '</ul>';
echo '<div class="spacer"></div>';
	echo "
	<table id=\"ajouts\">
	<tr>";

	foreach ($th_lieu as $att => $th)
	{
		if ( $att == "URL")
		{
			echo "<th>".$th."</th>";
		}
		else
		{
			if ($att == $get['tri_gerer'])
			{
				echo "<th class=\"ici\">".$icone[$get['ordre']];
			}
			else
			{
				echo "<th>";
			}
			echo "<a href=\"?element=" . $get['element'] . "&page=" . $get['page'] . "&tri_gerer=" . $att . "&ordre=" . $ordre_inverse . "&nblignes=" . $get['nblignes'] . "\">" . $th . "</a></th>";
        }
	}

	echo "<th></th></tr>";

	$pair = 0;

	while($tab = $connector->fetchArray($req_lieux))
	{

		if ($pair % 2 == 0)
		{
			echo "<tr>";
		}
		else
		{
			echo "<tr class=\"impair\" >";
		}

		echo "
		<td>".$tab['idOrganisateur']."</td>
		<td><a href=\"/organisateur.php?idO=".$tab['idOrganisateur']."\" title=\"Voir la fiche\">".sanitizeForHtml($tab['nom'])."</a></td>";

		echo "
		<td>".date_iso2app($tab['date_ajout'])."</td>";

		echo "<td>";
		if ($tab['date_derniere_modif'] != "0000-00-00 00:00:00")
		{
			echo date_iso2app($tab['date_derniere_modif']);
		}
		echo "</td>
		<td>".$tab_icones_statut[$tab['statut']]."</td>";

		//Edition pour l'admin ou l'auteur
		if ($_SESSION['Sgroupe'] <= UserLevel::ADMIN) {
			echo "<td><a href=\"/organisateur-edit.php?action=editer&idO=".$tab['idOrganisateur']."\" title=\"Éditer\">".$iconeEditer."</a></td>";
		}
		echo "</tr>";

		$pair++;

	}
	echo "</table>";
}
else if ($get['element'] == "personne")
{
	$sql_terme = '';
	if (!empty($get['terme']))
		$sql_terme = " WHERE ( LOWER(pseudo) like LOWER('%".$connector->sanitize($get['terme'])."%') OR LOWER(email) like LOWER('%".$connector->sanitize($get['terme'])."%')) ";

	$sql_pers = "
	SELECT idPersonne, pseudo, email, groupe, affiliation, statut, dateAjout, date_derniere_modif
	FROM personne
	".$sql_terme."
	ORDER BY ".$get['tri_gerer']." ".$get['ordre'];

	$req_pers_total = $connector->query($sql_pers);
	$num_pers_total = $connector->getNumRows($req_pers_total);

	$pers_total_page_max = ceil($num_pers_total / $get['nblignes']);
	if ($pers_total_page_max > 0 && $get['page'] > $pers_total_page_max)
		$get['page'] = $pers_total_page_max;

/* 	echo "<p>num_pers_total : $num_pers_total";
	echo "<p>pers_total_page_max : $pers_total_page_max";	 */

	$sql_pers .= " LIMIT ".($get['page'] - 1) * $get['nblignes'].",".$get['nblignes'];

	$req_pers = $connector->query($sql_pers);

	$th_lieu = ["idPersonne" => "ID", "pseudo" => "Pseudo",  "email" => "E-mail",  "groupe" => "Groupe",
	"dateAjout" => "Création",
	"statut" => "Statut"
	];

	?>

	<form method="get" action="" id="ajouter_editer">

		<input type="hidden" name="page" value="<?php echo $get['page']; ?>" />
		<input type="hidden" name="nblignes" value="<?php echo $get['nblignes']; ?>" />
		<input type="hidden" name="tri_gerer" value="<?php echo $get['tri_gerer']; ?>" />
		<input type="hidden" name="element" value="<?php echo $get['element']; ?>" />
		<input type="hidden" name="ordre" value="<?php echo $get['ordre']; ?>" />


		<input type="text" name="terme" value="<?php echo $get['terme']; ?>" placeholder="pseudo ou email" size="20" />
		<input type="submit" name="submit" value="Filtrer" />

	</form>

	<?php

	echo HtmlShrink::getPaginationString($num_pers_total, $get['page'], $get['nblignes'], 1, "",
                "?element=".$get['element']."&tri_gerer=".$get['tri_gerer']."&ordre=".$get['ordre']."&nblignes=".$get['nblignes']."&terme=".$get['terme']."&page=");
	echo '<ul class="menu_nb_res">';

	foreach ($tab_nblignes as $nbl)
	{
		echo '<li ';
		if ($get['nblignes'] == $nbl) { echo 'class="ici"'; }

		echo '><a href="/admin/gerer.php?'.Utils::urlQueryArrayToString($get, "nblignes").'&nblignes='.$nbl.'">'.$nbl.'</a></li>';
	}
	echo '</ul>';
echo '<div class="spacer"></div>';
	echo "<table id=\"ajouts\">
	<tr>";

	foreach ($th_lieu as $att => $th)
	{

		if ($att == $get['tri_gerer'])
		{
			echo "<th class=\"ici\">".$icone[$get['ordre']];
		}
		else
		{
			echo "<th>";
		}
		echo "<a href=\"?element=" . $get['element'] . "&page=" . $get['page'] . "&tri_gerer=" . $att . "&ordre=" . $ordre_inverse . "&nblignes=" . $get['nblignes'] . "\">" . $th . "</a></th>";
    }

	echo "<th></th></tr>";

	$pair = 0;

	while ($tab_pers = $connector->fetchArray($req_pers))
	{
		$nom_groupe = $tab_pers['groupe'];
		if ($nom_groupe == UserLevel::ACTOR) {
			$nom_groupe = 'Acteur culturel';
		}
		else if ($nom_groupe == UserLevel::MEMBER) {
            $nom_groupe = "Membre";
        }
        else if ($nom_groupe == UserLevel::AUTHOR) {
            $nom_groupe = "Rédacteur";
		}
		else if ($nom_groupe == UserLevel::ADMIN) {
			$nom_groupe = "Admin";
		}


		echo "<tr";

		if ($pair % 2 != 0) { echo " class=\"impair\""; }

		echo ">
		<td>".$tab_pers ['idPersonne']."</td>
		<td style='width:20%'><a href=\"/user.php?idP=".$tab_pers['idPersonne']."\" title=\"Voir le profile :".sanitizeForHtml($tab_pers['pseudo'])."\">".sanitizeForHtml($tab_pers['pseudo'])."</a></td>
		<td><a href='mailto:".$tab_pers['email']."'>".$tab_pers['email']."</a></td>
		<td>".$nom_groupe."</td>";
		echo "

		<td>".date_iso2app($tab_pers['dateAjout'])."</td>";


		echo "<td>".$tab_icones_statut[$tab_pers['statut']]."</td>";






		//Edition pour l'admin ou l'auteur
		if ( $_SESSION['Sgroupe'] < 2)
		{
			echo "<td><a href=\"/user-edit.php?action=editer&idP=".$tab_pers['idPersonne']."\" title=\"Éditer\">".$iconeEditer."</a></td>";
		}
		echo "</tr>";

		$pair++;

	}

	echo "</table>";

	echo HtmlShrink::getPaginationString($num_pers_total, $get['page'], $get['nblignes'], 1, "", "?element=" . $get['element'] . "&tri_gerer=" . $get['tri_gerer'] . "&ordre=" . $get['ordre'] . "&nblignes=" . $get['nblignes'] . "&terme=" . $get['terme'] . "&page=");
}



?>
	</table>

</div>
<!-- fin Contenu -->


<div id="colonne_gauche" class="colonne">
    <?php
    include("_menuAdmin.inc.php");
    ?>
</div>

<div class="spacer"><!-- --></div>
<?php
include("../_footer.inc.php");
?>
