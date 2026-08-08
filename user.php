<?php

require_once("app/bootstrap.php");

use Ladecadanse\UserLevel;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Utils\DateHelper;
use Ladecadanse\Utils\Utils;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\Text;
use Ladecadanse\EvenementRenderer;

if (!$videur->checkGroup(UserLevel::MEMBER))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
	header("Location: /user-login.php");
    die();
}

$page_titre = "profil";
include("_header.inc.php");

$tab_elements = ["evenement" => "Événements", "description" => "Descriptions"];

if (isset($_GET['idP']))
{
	$get['idP'] = (int)$_GET['idP'];
}
else
{
	HtmlShrink::msgErreur("id obligatoire");
	exit;
}


if (isset($_GET['elements']))
{

	if (array_key_exists($_GET['elements'], $tab_elements))
	{
		$get['elements'] = $_GET['elements'];
	}
	else
	{
		HtmlShrink::msgErreur("element faux");
		exit;
	}
}
else
{

		$get['elements'] = "evenement";

}

if (isset($_GET['page']))
{
	$get['page'] = (int)$_GET['page'];
}
else
{
	$get['page'] = 1;
}

// seules les colonnes que les en-tetes des deux tables restantes savent emettre
$tab_tri = ["dateAjout", "dateEvenement", "titre", "statut"];

if (isset($_GET['tri']))
{
	$get['tri'] = Validateur::validateUrlQueryValue($_GET['tri'], "enum", 1, $tab_tri);
}
else
{
	$get['tri'] = "dateAjout";
}


$tab_ordre = ["asc", "desc"];
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
else
{
	$get['ordre'] = "desc";
	$ordre_inverse = "asc";
}

$get['nblignes'] = 100;
if (!empty($_GET['nblignes']))
{
	$get['nblignes'] = Validateur::validateUrlQueryValue($_GET['nblignes'], "int", 1);
}

if ($_SESSION['SidPersonne'] != $get['idP'] && $_SESSION['Sgroupe'] > 4 )
{
	HtmlShrink::msgErreur("Vous ne pouvez accédez à cette page");
	exit;
}

//details de la personne
$req_personne = $connector->query("SELECT idPersonne, pseudo, affiliation, email, groupe, actif FROM personne WHERE idPersonne=" . (int)$get['idP']);

$detailsPersonne = $connector->fetchArray($req_personne);

//jointure pour obtenir toutes les affiliations qui sont liées à cette personne
$req_affPers = $connector->query("SELECT lieu.idLieu, lieu.nom
FROM affiliation INNER JOIN lieu ON affiliation.idAffiliation=lieu.idLieu
 WHERE affiliation.idPersonne=".(int)$get['idP']." AND affiliation.genre='lieu'");

$detailsAff = $connector->fetchArray($req_affPers);
?>

<main id="contenu" class="colonne personne">

	<header id="entete_contenu">
		<h1>Profil</h1>
		<div class="spacer"></div>
	</header>

	<div class="spacer"></div>

	<!-- Deb profile -->
	<div id="profile" style="padding: 0.4em;width: 94%;margin: 0 auto 0 auto;">

		<table>
            <tr><th>Identifiant</th><td><?php echo sanitizeForHtml($detailsPersonne['pseudo']) ?></td></tr>
            <tr><th>E-mail</th><td><?php echo sanitizeForHtml($detailsPersonne['email']) ?></td></tr>
            <tr><th>Affiliations</th><td>
            <?php
            //si l'affiliation est un lieu, crée un lien vers ce lieu
            if ($connector->getNumRows($req_affPers) > 0)
            {
                echo "<a href=\"/lieu/lieu.php?idL=".sanitizeForHtml($detailsAff['idLieu'])."\" title=\"Voir la fiche du lieu : ".sanitizeForHtml($detailsAff['idLieu'])."\" >".sanitizeForHtml($detailsAff['nom'])."</a>";
            }
            else
            {
                echo sanitizeForHtml($detailsPersonne['affiliation']);
            }
            echo '<br />';
            $sql = "SELECT * FROM personne_organisateur, organisateur WHERE personne_organisateur.idOrganisateur=organisateur.idOrganisateur AND personne_organisateur.idPersonne=".(int)$get['idP'];
            $req = $connector->query($sql);
            while ($tab = $connector->fetchArray($req))
            {
                echo '<a href="/organisateur/organisateur.php?idO=' . (int)$tab['idOrganisateur'] . '">' . sanitizeForHtml($tab['nom']) . '</a><br />';
            }
            ?>

                </td></tr>
        </table>
        <?php if ((isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 1)) || $_SESSION['SidPersonne'] == $get['idP']) { ?>
        <a href="/user-edit.php?idP=<?php echo (int)$get['idP'] ?>&action=editer"><img src="/web/interface/icons/user_edit.png" alt="" />Modifier</a><?php } ?>
    </div> <!-- Fin profile -->

    <ul id="menu_ajouts">
	<?php
	if ((isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= UserLevel::ACTOR)) {
	?>

	<li <?php if ($get['elements'] == "evenement") { echo ' class="ici" '; } ?>>
	<img src="/web/interface/icons/calendar.png" />
        <a href="?idP=<?php echo (int)$get['idP'] . "&nblignes=" . (int)$get['nblignes'] . "&elements=evenement" ?>">événements</a>
        </li>
	<?php
	}
	if ((isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 8))
	{
	?>
	<li <?php if ($get['elements'] == "description") { echo ' class="ici" '; } ?>>
	<img src="/web/interface/icons/page_white_text.png" />
        <a href="?idP=<?php echo (int)$get['idP'] . "&nblignes=" . (int)$get['nblignes'] . "&elements=description" ?>">textes</a>
        </li>
	<?php
	}
	?>

    </ul>


	<?php
	// EVENEMENTS ANNONCES

	$limite = 30;

	if ($get['elements'] == "evenement")
	{
		$sql_evenement = "SELECT idEvenement, idLieu, statut, idPersonne, genre,titre, dateEvenement, horaire_fin, nomLieu, flyer, dateAjout
		 FROM evenement WHERE idPersonne=".(int)$get['idP']." ORDER BY ".$connector->sanitize($get['tri'])." ".$connector->sanitize($get['ordre'])." LIMIT ".((int)$get['page'] - 1) * (int)$get['nblignes'].",".(int)$get['nblignes'];


		$req_evenement = $connector->query($sql_evenement);

		$req_nbeven = $connector->query("SELECT COUNT(*) AS nbeven FROM evenement WHERE idPersonne=".(int)$get['idP']);
		$tab_nbeven = $connector->fetchArray($req_nbeven);
		$tot_elements = $tab_nbeven['nbeven'];

		echo HtmlShrink::getPaginationString($tot_elements, $get['page'], $get['nblignes'], 1, "", "?idP=" . (int)$get['idP'] . "&elements=" . $get['elements'] . "&tri=" . $get['tri'] . "&ordre=" . $get['ordre'] . "&nblignes=" . (int)$get['nblignes'] . "&page=");

    if ($connector->getNumRows($req_evenement) > 0)
		{
			echo '<ul id="menu_nb_res">';
			foreach ($tab_nblignes as $nbl)
			{
				echo '<li ';
				if ($get['nblignes'] == $nbl) { echo 'class="ici"'; }

				echo '><a href="?' . Utils::urlQueryArrayToString($get, "nblignes") . '&nblignes=' . (int)$nbl . '">' . (int)$nbl . '</a></li>';
        }
			echo '</ul>';
			echo '<div class="spacer"><!-- --></div>';
			$th_evenements = ["dateEvenement" => "Date", "idLieu" => "Lieu", "titre" => "Titre", "dateAjout" => "Date d'ajout", "statut" => ""];


			echo "<table id=\"ajouts\"><tr>";

			foreach ($th_evenements as $att => $th)
			{
				if ($att == "idLieu" || $att == "flyer")
				{
					echo "<th>".$th."</th>";
				}
				else
				{
					if ($att == $get['tri'])
					{
						echo "<th class=\"ici\">".$icone[$get['ordre']];
					}
					else
					{
						echo "<th>";
					}

					echo "<a href=\"?idP=" . (int)$get['idP'] . "&elements=" . sanitizeForHtml($get['elements']) . "&page=" .(int) $get['page'] . "&tri=" . sanitizeForHtml($att) . "&ordre=" . sanitizeForHtml($ordre_inverse) . "&nblignes=" . (int)$get['nblignes'] . "\">" . $th . "</a></th>";
            }
			}

			echo "<th></th></tr>";

			$pair = 0;

			while ($tab_even = $connector->fetchArray($req_evenement))
			{

				$nomLieu = sanitizeForHtml($tab_even['nomLieu']);

				if ($tab_even['idLieu'] != 0)
				{
					$req_lieu = $connector->query("SELECT nom FROM lieu WHERE idLieu=".(int)$tab_even['idLieu']);
					$tabLieu = $connector->fetchArray($req_lieu);
					$nomLieu = "<a href=\"/lieu/lieu.php?idL=".(int)$tab_even['idLieu']."\" title=\"Voir la fiche du lieu : ".sanitizeForHtml($tabLieu['nom'])." \">".sanitizeForHtml($tabLieu['nom'])."</a>";
				}

				// un événement passé est une archive : non modifiable (sauf par un éditeur),
				// et sa ligne est atténuée pour que la disparition du bouton Éditer se comprenne
				$is_even_ancien = DateHelper::isEvenementPast($tab_even['dateEvenement'], $tab_even['horaire_fin']);
				$can_edit_even = !$is_even_ancien || $authorization->isPersonneEditor($_SESSION);

				$tr_classes = [];
				if ($pair % 2 != 0)
				{
					$tr_classes[] = "impair";
				}
				if ($is_even_ancien)
				{
					$tr_classes[] = "ancien";
				}

				echo $tr_classes === [] ? "<tr>" : "<tr class=\"" . implode(" ", $tr_classes) . "\">";

				echo "
				<td>".DateHelper::isoToApp($tab_even['dateEvenement'])."</td>
				<td>".$nomLieu."</td>
				<td><a href=\"/event/evenement.php?idE=".(int)$tab_even['idEvenement']."\" title=\"Voir la fiche de l'événement\">".sanitizeForHtml($tab_even['titre'])."</a></td>";
				echo "
				<td>".mb_substr(DateHelper::isoToApp($tab_even['dateAjout']), 9)."</td><td>".EvenementRenderer::$iconStatus[$tab_even['statut']]."</td>";

				if ($_SESSION['SidPersonne'] == $detailsPersonne['idPersonne'] || $_SESSION['Sgroupe'] <= 4)
				{
					$depublier = "";
					if ($tab_even['statut'] != 'inactif')
					{
						$depublier = EvenementRenderer::unpublishLinkHtml((int)$tab_even['idEvenement'], $icone['depublier'], EvenementRenderer::UNPUBLISH_THEN_STATUS)."&nbsp;";
					}
					// Éditer disparaît sur les événements passés ; Dépublier reste toujours disponible
					$editer = "";
					if ($can_edit_even)
					{
						$editer = "<a href=\"/evenement-edit.php?action=editer&idE=".(int)$tab_even['idEvenement']."\" title=\"Éditer l'événement\">".$iconeEditer."</a>";
					}

					// nowrap : Dépublier et Modifier restent côte à côte, jamais l'une sous l'autre
					// aligné à droite : les cellules à une seule icône restent dans l'axe des autres
					echo "<td style=\"white-space:nowrap;text-align:right\">".$depublier.$editer."</td>";
				}
				echo "</tr>";

				$pair++;
			} // fin while

			echo "</table>";

		}
		else
		{
			echo '<p>Aucun '.sanitizeForHtml($get['elements']).' ajouté pour le moment</p>';
		}//if nbrows evenements

		@mysqli_free_result($req_evenement);

	}
	else if ($get['elements'] == "description")
	{

		// DESCRIPTIONS DE LIEUX

		$req_des = $connector->query("SELECT idLieu, idPersonne, dateAjout, contenu, type
		FROM descriptionlieu WHERE idPersonne=".(int)$get['idP']. " ORDER BY ".$connector->sanitize($get['tri'])." ".$connector->sanitize($get['ordre'])." LIMIT ".(int)($get['page'] - 1) * (int)$get['nblignes'].",".(int)$get['nblignes']);

		$req_nbdesc = $connector->query("SELECT COUNT(*) AS total FROM descriptionlieu WHERE idPersonne=".(int)$get['idP']);
		$tab_nbdesc = $connector->fetchArray($req_nbdesc);
		$tot_elements = $tab_nbdesc['total'];



		echo HtmlShrink::getPaginationString($tot_elements, $get['page'], $get['nblignes'], 1, "", "?idP=" . (int)$get['idP'] . "&elements=" . $get['elements'] . "&tri=" . $get['tri'] . "&ordre=" . $get['ordre'] . "&nblignes=" . $get['nblignes'] . "&page=");

    if ($connector->getNumRows($req_des) > 0)
		{
			echo '<ul id="menu_nb_res">';
			foreach ($tab_nblignes as $nbl)
			{
				echo '<li ';
				if ($get['nblignes'] == $nbl) { echo 'class="ici"'; }

				echo '><a href="?' . Utils::urlQueryArrayToString($get, "nblignes") . '&nblignes=' . (int)$nbl . '">' . (int)$nbl . '</a></li>';
        }
			echo '</ul>';
			echo '<div class="spacer"><!-- --></div>';

			$th_descriptions = ["idLieu" => "Lieu",  "contenu" => "Contenu", "type" => "Type", "dateAjout" => "Date d'ajout"];

			echo "<table id=\"ajouts\"><tr>";
			foreach ($th_descriptions as $att => $th)
			{
				if ($att == "idLieu" || $att == "idPersonne" || $att == "contenu")
				{
					echo "<th>".$th."</th>";
				}
				else
				{
					if ($att == $get['tri'])
					{
						echo "<th class=\"ici\">".$icone[$get['ordre']];
					}
					else
					{
						echo "<th>";
					}

					echo "<a href=\"?idP=" . (int)$get['idP'] . "&elements=" . sanitizeForHtml($get['elements']) . "&page=" . (int)$get['page'] . "&tri=" . sanitizeForHtml($att) . "&ordre=" . sanitizeForHtml($ordre_inverse) . "&nblignes=" .(int) $get['nblignes'] . "\">" . $th . "</a></th>";
            }
			}
			echo "<th></th></tr>";

			$pair = 0;

			while($tab_desc = $connector->fetchArray($req_des))
			{

				$req_auteur = $connector->query("SELECT pseudo FROM personne WHERE idPersonne=".(int)$tab_desc['idPersonne']);
				$tabAuteur = $connector->fetchArray($req_auteur);

				$req_lieu = $connector->query("SELECT nom FROM lieu WHERE idLieu=".(int)$tab_desc['idLieu']);
				$tabLieu = $connector->fetchArray($req_lieu);
				$nomLieu = "<a href=\"/lieu/lieu.php?idL=".(int)$tab_desc['idLieu']."\" title=\"Éditer le lieu\">".sanitizeForHtml($tabLieu['nom'])."</a>";


				if ($pair % 2 == 0)
				{
					echo "<tr>";
				}
				else
				{
					echo "<tr class=\"impair\" >";
				}

				echo "<td>".$nomLieu."</td>";
				if (mb_strlen((string) $tab_desc['contenu']) > 200)
				{
					$tab_desc['contenu'] = mb_substr((string) $tab_desc['contenu'], 0, 200)." [...]";
				}

				echo "<td class=\"tdleft\" style=\"width:150px\">".Text::lnAndUrlToHtml(sanitizeForHtml($tab_desc['contenu']))."</td>";
				echo '<td>'.$tab_desc['type'].'</td>';
				echo "<td>".mb_substr(DateHelper::isoToApp($tab_desc['dateAjout']), 8)."</td>";
				if ($_SESSION['SidPersonne'] == $detailsPersonne['idPersonne'] || $_SESSION['Sgroupe'] <= 4)
				{
					echo "<td><a href=\"/lieu-text-edit.php?action=editer&idL=" . (int)$tab_desc['idLieu'] . "&idP=" . (int)$tab_desc['idPersonne'] . "&type=" . $tab_desc['type'] . "\" title=\"Éditer le lieu\">" . $iconeEditer . "</a></td>";
                }
				echo "</tr>";

				$pair++;
			} // while

			echo "</table>";
		}
		else
		{
			echo '<p>Aucun texte ajouté pour le moment</p>';
		}
		 //if nbrow descriptions

		@mysqli_free_result($req_des);


	}

echo HtmlShrink::getPaginationString($tot_elements, $get['page'], $get['nblignes'], 1, "", "?idP=" . (int)$get['idP'] . "&elements=" . $get['elements'] . "&tri=" . $get['tri'] . "&ordre=" . $get['ordre'] . "&nblignes=" . (int)$get['nblignes'] . "&page=");
?>
	</table>
</main>

<div id="colonne_gauche" class="colonne">
<?php
include("event/_navigation_calendrier.inc.php");
?>
</div>

<div id="colonne_droite" class="colonne personne"></div>

<?php
include("_footer.inc.php");
?>
