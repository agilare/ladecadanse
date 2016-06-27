<? /**/ ?>
<?php
/**
 * Permet d'ajouter une description sur un lieu de la base
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

if (!$videur->checkGroup(8))
{
	header("Location: index.php"); die();
}

$cache_lieux = $rep_cache."lieux/";
header("Cache-Control: max-age=30, must-revalidate");


$page_titre = "ajouter/éditer une description de lieu";
$page_description = "ajouter/éditer une description de lieu";
$nom_page = "ajouterDescription";
$extra_css = array("formulaires", "description", "chosen.min");
$extra_js = array( "zebra_datepicker", "chosen.jquery.min", "jquery.shiftcheckbox");
include("includes/header.inc.php");



/*
* action choisie, idL et idP si édition
*/
$tab_actions = array("ajouter", "insert", "editer", "update");
$get['action'] = "ajouter";
if (isset($_GET['action']))
{
	$get['action'] = verif_get($_GET['action'], "enum", 0, $tab_actions);
}

$tab_types = array("description", "presentation");
if (isset($_GET['type']))
{
	$get['type'] = verif_get($_GET['type'], "enum", 0, $tab_types);
}
else
{
	trigger_error("type obligatoire", E_USER_WARNING);
	exit;
}

$get['idL'] = 0;
if (isset($_GET['idL']))
{
	$get['idL'] = (int)$_GET['idL'];
}

if (isset($_GET['idP']))
{
	$get['idP'] = (int)$_GET['idP'];
}
elseif (isset($_SESSION['SidPersonne']))
{
	$get['idP'] = $_SESSION['SidPersonne'];
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
		msgErreur("Vous n'avez pas les droits pour éditer cette description");
		exit;
	}
}

if ($get['type'] == 'description' && $_SESSION['Sgroupe'] > 6)
{
	msgErreur("Vous n'avez pas les droits pour éditer cette description");
	exit;

}
else if ($get['type'] == 'presentation' && $_SESSION['Sgroupe'] > 8)
{
	msgErreur("Vous n'avez pas les droits pour éditer cette présentation");
	exit;

}

if ($get['type'] == 'presentation' && $_SESSION['Sgroupe'] == 8 && ($get['idL'] && !est_organisateur_lieu($_SESSION['SidPersonne'], $get['idL']))) 
{
	msgErreur("Vous n'avez pas les droits pour éditer cette présentation");
	exit;

}
/*
* TRAITEMENT DU FORMULAIRE (EDITION OU AJOUT)
*/
require_once($rep_librairies.'Validateur.php');
$verif = new Validateur();

$champs = array("idLieu" => '', "contenu" => '');

$action_terminee = false;

if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok' )
{

	/*
	 * Copie des champs envoyê³ par POST
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

	$verif->valider($champs['idLieu'], "idLieu", "texte", 1, 60, 1);

	$verif->valider($champs['contenu'], "contenu", "texte", 50, 10000, 1);
	/*
	 * Nom du lieu obligatoire et vê³©f si le lieu dê´©gné¡°ar idL existe bien dans la table lieu
	 */
	if ($connector->getNumRows($connector->query("SELECT idLieu FROM lieu WHERE idLieu=".$connector->sanitize($champs['idLieu']))) < 1)
	{
			$verif->setErreur("idLieu", "Ce lieu n'est pas dans la liste");
	}

	/*
	 * Si c'est un AJOUT, vérifie si la personne n'a pas déjà écrit une description
	 * MAX 1 desc/pers
	 */
	if ($get['action'] == 'insert')
	{
		if ($connector->getNumRows($connector->query("SELECT * FROM descriptionlieu WHERE idPersonne=".$_SESSION['SidPersonne']." AND idLieu=".$connector->sanitize($champs['idLieu'])." > 0 AND type='".$get['type']."'") ))
		{
			$verif->setErreur('doublon', "Vous avez déjà écrit une <a href=\"".basename(__FILE__)."?action=editer&idL=".securise_string($champs['idLieu'])."&idP=".$_SESSION['SidPersonne']."\"  title=\"Voir la description de ".$_SESSION['user']."\">description</a> pour ce lieu");

		}
		else if ($get['type'] == 'presentation' && $connector->getNumRows($connector->query("SELECT * FROM descriptionlieu WHERE idLieu=".$connector->sanitize($champs['idLieu'])." > 0 AND type='presentation'") ))
		{
			$verif->setErreur('doublon', "Il y a déjà une présentation pour ce lieu.");

		}
	}

	/*
	 * Pas d'erreur, donc ajout ou update executés
	 */
	if ($verif->nbErreurs() === 0)
	{
		//creation/nettoyage des valeurs à insérer dans la table
		$pers = $_SESSION['SidPersonne'];
		$champs['type'] = $get['type'];
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

			$sql_insert = "INSERT INTO descriptionlieu (".$sql_insert_attributs.") VALUES (".$sql_insert_valeurs.")";
			$sql_insert = "INSERT INTO descriptionlieu (".$sql_insert_attributs.") VALUES (".$sql_insert_valeurs.")";

			//TEST
			//echo "<p>".$sql_insert."</p>";
			//

			//message résultat et réinit
			if ($connector->query($sql_insert))
			{

				msgOk("Description du <a href=\"".$url_site."lieu.php?idL=".securise_string($champs['idLieu'])."\" title=\"Voir la fiche du lieu\">".$iconeVoirFiche." lieu ".securise_string($champs['idLieu'])."</a> ajoutée");

				$descriptionlieu = $champs;
				$descriptionlieu['auteur'] = $get['idP'];
				$descriptionlieu['date_derniere_modif'] = date("Y-m-d H:i:s");
				//include("templates/descriptionlieu.inc.php");

				/*
				 * Suppression des caches
				 * - le lieu de la description
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
				msgErreur("La requête INSERT dans descriptionlieu a échoué");
			}

		/*
		* Insertion dans la base : UPDATE
		*/
		}
		elseif ($get['action'] == 'update')
		{

			$champs['date_derniere_modif'] = date("Y-m-d H:i:s");

			$sql_update = "UPDATE descriptionlieu SET
			contenu='".$connector->sanitize(nl2br($champs['contenu']))."', date_derniere_modif='".$champs['date_derniere_modif']."'
			WHERE idPersonne=".$get['idP']." AND idLieu=".$get['idL'];

			//TEST
			//echo "<p>".$sql_update."</p>";
			//

			$req_update = $connector->query($sql_update);

			//message résultat et réinit de l'action
			if ($req_update)
			{

				msgOk("Description du <a href=\"".$url_site."lieu.php?idL=".securise_string($champs['idLieu'])."\"
				title=\"Voir la fiche du lieu\">lieu</a> modifiée");

				$descriptionlieu = $champs;
				$descriptionlieu['auteur'] = $get['idP'];
				//include("templates/descriptionlieu.inc.php");


				/*
				 * Suppression des caches
				 * - le lieu de la description
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

	$act = "update&type=".$get['type']."&idL=".$get['idL'];

	$req_lieu = $connector->query("SELECT nom, adresse, quartier, categorie, URL FROM lieu WHERE idLieu=".$get['idL']);
 	$detailsLieu = $connector->fetchArray($req_lieu);

	echo '
	<h2>Éditer la '.$get['type'].' sur
<a href="'.$url_site.'lieu.php?idL='.$get['idL'].'" title="Fiche du lieu '.securise_string($detailsLieu['nom']).'">'.securise_string($detailsLieu['nom']).'</a></h2>';


}
else
{

	$act = "insert&type=".$get['type'];
	echo "<h2>Ajouter une ".$get['type']."</h2>";
}

/*
* POUR EDITER UNE DESCRIPTION, ALLER CHERCHER SES VALEURS DANS LA BASE
* Accessible par son auteur ou un admin
* Récupération des valeurs de la table et remplissage des champs pour le formulaire
* Affichage d'un menu d'actions pour l'admin
*/
if ($get['action'] == 'editer' && isset($get['idL']) && isset($get['idP']))
{

	if ($_SESSION['SidPersonne'] == $get['idP'] || $_SESSION['Sgroupe'] < 2)
	{
		$sql = "SELECT idPersonne, idLieu, contenu, type
		FROM descriptionlieu WHERE idLieu =".$get['idL']." AND idPersonne=".$get['idP']." AND type='".$get['type']."'";
		$req_desc = $connector->query($sql);

		if ($tabDesc = $connector->fetchArray($req_desc))
		{
				foreach($tabDesc as $c => $v)
				{
					$champs[$c] = $v;
				}
		}

		@mysqli_free_result($req_desc);
		
		if ($_SESSION['Sgroupe'] < 2) 
		{
			echo '<ul class="entete_contenu_menu">';
			echo "<li class=\"action_supprimer\">
			<a href=\"".$url_site."supprimer.php?type=descriptionlieu&id=".$get['idL']."&idP=".$get['idP']."\" title=\"Supprimer la description\">Supprimer</a></li>";
			echo "</ul>";
		}
	}
} // if GET action

echo '<div class="spacer"></div></div>';



if ($verif->nbErreurs() > 0)
{
	msgErreur("Il y a ".$verif->nbErreurs()." erreur(s).");
	//print_r($verif->getErreurs());
}


?>



<!-- FORMULAIRE POUR UNE DESCRIPTION -->
<form method="post" id="ajouter_editer" enctype="multipart/form-data" action="<?php echo basename(__FILE__)."?action=".$act; ?>" onsubmit="return validerAjouterDescription()">
<?php
if ($get['type'] == 'presentation')
{
?>
	<p>Si vous vous occupez d'un lieu, vous pouvez ici le présenter en son nom. Ce texte s'affichera dans la fiche du lieu.</p>
<?php
}
?>
<p>* indique un champ obligatoire</p>

<fieldset>
<legend>Description</legend>
<!-- Select liste des lieux -->

<input type="hidden" name="type" value="<?php echo $get['type']; ?>" />
<p>
<label for="idLieu">Lieu* :</label>
<?php
if (($get['action'] == 'editer' || $get['action'] == 'update') && isset($get['idL']))
{

	echo "<input type=\"hidden\" name=\"idLieu\" value=\"".$get['idL']."\" />
	<input type=\"hidden\" name=\"idP\" value=\"".$get['idP']."\" />";
	echo securise_string($detailsLieu['nom']);
}
else
{

	echo "<select name=\"idLieu\" id=\"idLieu\"  class=\"chosen-select\" title=\"Choisissez le lieu que vous voulez décrire\" style=\"max-width:300px;\">
	<option value=\"0\"></option>";
	$req_lieux = $connector->query("SELECT idLieu, nom FROM lieu WHERE actif=1 AND statut='actif' ORDER BY nom");

	while ($lieuTrouve = $connector->fetchArray($req_lieux))
	{
	    echo "<option";
		if ($lieuTrouve['idLieu'] == $champs['idLieu'] || $lieuTrouve['idLieu'] == $get['idL'])
		{
		  	echo " selected=\"selected\"";
		}
		echo " value=\"".$lieuTrouve['idLieu']."\">".$lieuTrouve['nom']."</option>";
	}
}
?>
</select>
<?php
echo $verif->getErreur('idLieu');
echo $verif->getHtmlErreur('doublon');
echo $verif->getErreur('nom');
?>
</p>

<!-- Description Texte -->
<p>
<label for="contenu">La description* :</label>

<textarea style="float:left" name="contenu" cols="45" rows="16">
<?php
   
        $desc = strip_tags($champs['contenu']);

echo $desc;
?>
</textarea>
<?php
echo $verif->getHtmlErreur('contenu');
?>
</p>

</fieldset>

<p class="piedForm">
<input type="hidden" name="formulaire" value="ok" />
<input type="submit" value="Enregistrer" class="submit" />
</p>


</form>

<?php
} // if action_terminee
?>

</div>
<!-- fin contenu  -->

<div id="colonne_gauche" class="colonne">

<?php include("includes/navigation_calendrier.inc.php"); ?>
</div>
<!-- Fin Colonne gauche -->

<div id="colonne_droite" class="colonne">
</div>

<?php
include("includes/footer.inc.php");
?>
