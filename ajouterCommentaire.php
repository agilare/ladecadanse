<?php
/**
 * Permet d'ajouter une commentaire sur un lieu de la base
 *
 * Le traitement de suppression est suivi par le traitement d'ajout/edition et le formulaire
 * est à la fin
 *
 * @category   modification d'une table de la base
 * @author     Michel Gaudry <michel@ladecadanse.ch>
 */

if (is_file("config/reglages.php"))
{
	require_once("config/reglages.php");
}

require_once($rep_librairies."Sentry.php");
$videur = new Sentry();

if (!$videur->checkGroup(12))
{
	header("Location: index.php"); die();
}


if (isset($_GET['id']))
{
	$get['id'] = verif_get($_GET['id'], "int", 1);
}

$tab_elements = array("evenement", "lieu");
$get['element'] = "";

if (isset($_GET['element']))
{
	$get['element'] = verif_get($_GET['element'], "enum", 0, $tab_elements);
}
else if ($get['action'] == 'ajouter' || $get['action'] == 'insert')
{
	msgErreur("element requis");
	exit;
}


$url_retour = $url_site.'evenement.php?idE='.$get['id'].'#commentaires';
if ($get['element'] == 'lieu')
{
	$url_retour = $url_site.'lieu.php?idL='.$get['id'].'&complement=commentaires#ajouter_editer';
}

//$cache_lieux = $rep_cache."lieux/";
header("Cache-Control: max-age=30, must-revalidate");
header ("Refresh: 1.5;URL=".$url_retour);
$nom_page = "ajouterCommentaire";
$page_titre = "ajouter/éditer un commentaire";
$page_description = "ajouter/éditer un commentaire";
$extra_css = array("formulaires");
include("includes/header.inc.php");


/*
* action choisie, idL et idP si édition
*/
/* if (isset($_GET['action']))
{
	if (!in_array($_GET['action'], $actions))
	{
		formaterTexte("Action non autorisée", "p");
		exit;
	}
	else
	{
		$get['action'] = $_GET['action'];
	}
} */
$tab_actions = array("ajouter", "insert", "update", "editer");
$get['action'] = "";
$get['action'] = verif_get($_GET['action'], "enum", 0, $tab_actions);



if (isset($_GET['idC']))
{
	if (!verif_get($_GET['idC'], "int", 1))
	{
		msgErreur("Un commentaire doit être désigné par un entier");
		exit;
	}
	else
	{

		if ($connector->getNumRows($connector->query("SELECT idCommentaire FROM commentaire WHERE idCommentaire=".$_GET['idC'])) < 1)
		{
				msgErreur("Ce commentaire n'existe pas");
				exit;
		}

		$get['idC'] = $_GET['idC'];

		//$cache_lieu = $rep_cache."lieu/".$get['idL'].".php";
	}
}
else if ($get['action'] == 'editer' || $get['action'] == 'update')
{
	msgErreur("idC requis");
	exit;
}


if (isset($_GET['idP']))
{
	$get['idP'] = verif_get($_GET['idP'], "int", 1);
}
elseif (isset($_SESSION['SidPersonne']))
{
	$get['idP'] = $_SESSION['SidPersonne'];
}

if (isset($_GET['id']))
{
	$get['id'] = verif_get($_GET['id'], "int", 1);
}

$tab_elements = array("evenement", "lieu");
$get['element'] = "";

if (isset($_GET['element']))
{
	$get['element'] = verif_get($_GET['element'], "enum", 0, $tab_elements);
}
else if ($get['action'] == 'ajouter' || $get['action'] == 'insert')
{
	msgErreur("element requis");
	exit;
}

?>




<!-- D?t Contenu -->
<div id="contenu" class="colonne">


<?php

/* VERIFICATION POUR MODIFICATION
* Si ce n'est pas un ajout et que la personne, n'est pas l'auteur de la desc ni admin -> exit
*/
if ($get['action'] != "ajouter" && $get['action'] != "insert")
{
	if ($_SESSION['SidPersonne'] != $get['idP'] && $_SESSION['Sgroupe'] > 2)
	{
		msgErreur("Vous n'avez pas les droits pour éditer cette commentaire");
		exit;
	}
}


/*
* TRAITEMENT DU FORMULAIRE (EDITION OU AJOUT)
*/
require_once($rep_librairies.'Validateur.php');
$verif = new Validateur();

$champs = array("contenu" => '');
$action_terminee = false;

if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok' )
{

	/*
	 * Copie des champs envoyes par POST
	 */
	foreach ($champs as $c => $v)
	{
		if (get_magic_quotes_gpc())
		{
			$champs[$c] = stripslashes($_POST[$c]);
		}
		else if (isset($_POST[$c]))
		{
			$champs[$c] = $_POST[$c];
		}
	}

	if (isset($_POST['idP']))
	{
		$get['idP'] = $_POST['idP'];
	}



	$verif->valider($champs['contenu'], "contenu", "texte", 2, 1000, 1);
	/*
	 * Si c'est un AJOUT, vérifie si la personne n'a pas déjà écrit une commentaire
	 * MAX 1 desc/pers
	 */
/* 	if ($get['action'] == 'insert')
	{
		if ($connector->getNumRows($connector->query("SELECT * FROM commentaire WHERE idPersonne=".$_SESSION['SidPersonne']." AND idEvenement=".$get['idE'])) > 0)
		{
			$verif->setErreur('doublon', "Vous avez déjÃ  écrit un commentaire pour cet événement");

		}
	} */



	/*
	 * Pas d'erreur, donc ajout ou update executés
	 */
	if ($verif->nbErreurs() === 0)
	{

		//creation/nettoyage des valeurs à insérer dans la table
		$pers = $_SESSION['SidPersonne'];
		if (isset($get['id']))
		{
			$champs['id'] = $get['id'];
		}

		if (isset($get['element']))
		{
			$champs['element'] = $get['element'];
		}
		/*
		* Insertion dans la base : INSERT
		*/
		if ($get['action'] == 'insert')
		{

			$sql_insert_attributs = "";
			$sql_insert_valeurs = "";

			foreach ($champs as $c => $v)
			{
				$sql_insert_attributs .= $c.", ";
				$sql_insert_valeurs .= "'".$connector->sanitize($v)."', ";
			}

			$sql_insert_attributs .= "dateAjout, date_derniere_modif, idPersonne";
			$sql_insert_valeurs .= "'".date("Y-m-d H:i:s")."', '".date("Y-m-d H:i:s")."', ".$pers;

			$sql_insert = "INSERT INTO commentaire (".$sql_insert_attributs.") VALUES (".$sql_insert_valeurs.")";

			//TEST
			//echo "<p>".$sql_insert."</p>";
			//

			//message résultat et réinit
			//PROD
			if ($connector->query($sql_insert))
			//TEST
			//if (1)
			//
			{

				if ($get['element'] == 'evenement')
				{
					msgOk("Commentaire de <a href=\"".$url_site."evenement.php?idE=".$get['id']."\" title=\"Voir la fiche de l'événement\">".$iconeVoirFiche."l'événement</a> ajouté");
				}
				else
				{
					msgOk("Commentaire du  <a href=\"".$url_site."lieu.php?idL=".$get['id']."&amp;complement=commentaires#menu_complement\" title=\"Voir la fiche du lieu\">".$iconeVoirFiche."lieu</a> ajouté");
				}

				/*
				 * Suppression des caches
				 * - le lieu de la commentaire
				 * - la page Lieux
				 */
/* 				@unlink($cache_lieu);
				if ($rc = opendir($cache_lieux))
				{
					while ($fichierLieux = readdir($rc))
					{
						@unlink($cache_lieux.$fichierLieux);
					}
					closedir($rc);
				} //si le lieu est l'un des dernier ajoutés ou si son nom est changé (menu lieux) */

				$action_terminee = true;

			}
			else
			{
				msgErreur("La requête INSERT dans commentairelieu a échoué");
			}

		/*
		* Insertion dans la base : UPDATE
		*/
		}
		elseif ($get['action'] == 'update')
		{

			$req_update = $connector->query("UPDATE commentaire
			SET contenu='".$connector->sanitize($champs['contenu'])."',
			date_derniere_modif='".date("Y-m-d H:i:s")."' WHERE idCommentaire=".$get['idC']);

			//message résultat et réinit de l'action
			if ($req_update)
			{

				msgOk("Commentaire modifié");

				/*
				 * Suppression des caches
				 * - le lieu de la commentaire
				 * - la page Lieux
				 */
/* 				@unlink($cache_lieu);
				if ($rc = opendir($cache_lieux))
				{
					while ($fichierLieux = readdir($rc))
					{
						@unlink($cache_lieux.$fichierLieux);
					}
					closedir($rc);
				} //si le lieu est l'un des dernier ajoutés ou si son nom est changé (menu lieux)
				 */
				$get['action'] = 'editer';
				$action_terminee = true;

			}
			else
			{
				msgErreur("La requête UPDATE a échoué");
			}

		} //if action


	} // if erreurs == 0


} // if POST != ""


if (!$action_terminee)
{

echo '<div id="entete_contenu">';
/*
 * PREPARATION DES URLS SELON LES ACTIONS,
 * update et idB en cas d'édition, insert pour ajout
 */
if ($get['action'] == 'editer' || $get['action'] == 'update')
{
	echo "<h2>Éditer le commentaire ".$get['idC']."</h2>";
	$act = "update&amp;idC=".$get['idC'];

	@mysqli_free_result($req_lieu);

}
else
{
	$act = "insert&amp;id=".$get['id'];
	echo "<h2>Ajouter un commentaire</h2>";
}
/*
* POUR EDITER UNE commentaire, ALLER CHERCHER SES VALEURS DANS LA BASE
* Accessible par son auteur ou un admin
* Récupération des valeurs de la table et remplissage des champs pour le formulaire
* Affichage d'un menu d'actions pour l'admin
*/
if ($get['action'] == 'editer' && isset($get['idC']))
{

	if ($_SESSION['SidPersonne'] == $get['idP'] || $_SESSION['Sgroupe'] < 2)
	{
		echo '<ul class="entete_contenu_menu">';
		echo "<li class=\"action_supprimer\">";
		$req_desc = $connector->query("SELECT *
		FROM commentaire WHERE idCommentaire=".$get['idC']);

		if ($tabDesc = $connector->fetchArray($req_desc))
		{
				foreach($tabDesc as $c => $v)
				{
					$champs[$c] = $v;
				}
		}

		@mysqli_free_result($req_desc);

		echo " <a href=\"".$url_site."supprimer.php?action=confirmation&amp;type=commentaire&amp;id=".$get['idC']."\" title=\"Supprimer le commentaire\"  onclick=\"return confirm('Voulez-vous vraiment supprimer ce commentaire ?');\">Supprimer</a>";
		echo "</li>";
	}
} // if GET action

?>

<div class="spacer></div>
<?php


echo '</div>';

if ($verif->nbErreurs() > 0)
{
	msgErreur("Il y a ".$verif->nbErreurs()." erreur(s).");
	//print_r($verif->getErreurs());
}
?>


<!-- FORMULAIRE POUR UNE commentaire -->
<form method="post" id="ajouter_editer" action="<?php echo basename(__FILE__)."?action=".$act; ?>" onsubmit="return validerAjouterDescription()">

<fieldset>
<!-- Description Texte -->

	<p>
	<label for="commentaire">Le commentaire* :</label>
	<?php
	$id_textarea = "commentaire";
	?>
	<textarea name="contenu" id="commentaire" title="écrivez ici votre commentaire" cols="50" rows="8"><?php echo securise_string($champs['contenu']) ?></textarea>
	<?php
	echo $verif->getErreur('contenu');
	?>
	</p>

	<p class="piedForm">
	<input type="hidden" name="formulaire" value="ok" />
	<input type="submit" value="Enregistrer" class="submit" />
	</p>

</fieldset>

</form>

<?php
} // if action_terminee
?>
</div>
<!-- fin Evenements -->

<div id="colonne_gauche" class="colonne">

<?php include("includes/navigation_calendrier.inc.php"); ?>
</div>
<!-- Fin Colonne gauche -->

<?php
include("includes/footer.inc.php");
?>
