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


$page_titre = "ajouter/éditer une salle";
$page_description = "ajouter/éditer une salle";
$extra_css = array("formulaires", "description");
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

$get['idS'] = 0;
if (isset($_GET['idS']))
{
	$get['idS'] = (int)$_GET['idS'];
}

$get['idL'] = 0;
if (isset($_GET['idL']))
{
	$get['idL'] = (int)$_GET['idL'];
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
	if ($_SESSION['Sgroupe'] > 6)
	{
		msgErreur("Vous n'avez pas les droits pour éditer cette salle");
		exit;
	}
}


/*
* TRAITEMENT DU FORMULAIRE (EDITION OU AJOUT)
*/
require_once($rep_librairies.'Validateur.php');
$verif = new Validateur();

$champs = array("idLieu" => '', "nom" => '', "emplacement" => '');

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


	$verif->valider($champs['idLieu'], "idLieu", "texte", 1, 60, 1);

	$verif->valider($champs['nom'], "nom", "texte", 2, 100, 1);
	$verif->valider($champs['emplacement'], "emplacement", "texte", 2, 100, 0);

	/*
	 * Nom du lieu obligatoire et vê³©f si le lieu dê´©gné¡°ar idL existe bien dans la table lieu
	 */
	if ($connector->getNumRows($connector->query("SELECT idLieu FROM lieu WHERE idLieu=".$connector->sanitize($champs['idLieu']))) < 1)
	{
			$verif->setErreur("idLieu", "Ce lieu n'est pas dans la liste");
	}



	/*
	 * Pas d'erreur, donc ajout ou update executés
	 */
	if ($verif->nbErreurs() === 0)
	{
		//creation/nettoyage des valeurs à insérer dans la table
		$pers = $_SESSION['SidPersonne'];

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

			$sql_insert = "INSERT INTO salle (".$sql_insert_attributs.") VALUES (".$sql_insert_valeurs.")";

			//TEST
			//echo "<p>".$sql_insert."</p>";
			//

			//message résultat et réinit
			if ($connector->query($sql_insert))
			{

				msgOk("Salle <em>".securise_string($champs['nom'])."</em> ajoutée");

				$action_terminee = true;
			}
			else
			{
				msgErreur("La requête INSERT a échoué");
			}

		/*
		* Insertion dans la base : UPDATE
		*/
		}
		elseif ($get['action'] == 'update')
		{

			$champs['date_derniere_modif'] = date("Y-m-d H:i:s");

			$sql_update = "UPDATE salle SET
			nom='".$connector->sanitize($champs['nom'])."', emplacement='".$connector->sanitize($champs['emplacement'])."', date_derniere_modif='".$champs['date_derniere_modif']."'
			WHERE idSalle=".$get['idS'];

			//TEST
			//echo "<p>".$sql_update."</p>";
			//

			$req_update = $connector->query($sql_update);

			//message résultat et réinit de l'action
			if ($req_update)
			{

				msgOk('Salle du <a href="'.$url_site.'lieu.php?idL='.$champs['idLieu'].'" title="Fiche du lieu">lieu</a> modifiée');

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

	$act = "update&idS=".$get['idS'];

	$req_lieu = $connector->query("SELECT * FROM salle WHERE idSalle=".$get['idS']);
 	$detailsLieu = $connector->fetchArray($req_lieu);

	echo '
	<h2>Éditer</h2>';


}
else
{

	$act = "insert";
	echo "<h2>Ajouter une salle à un lieu</h2>";
}

/*
* POUR EDITER UNE DESCRIPTION, ALLER CHERCHER SES VALEURS DANS LA BASE
* Accessible par son auteur ou un admin
* Récupération des valeurs de la table et remplissage des champs pour le formulaire
* Affichage d'un menu d'actions pour l'admin
*/
if ($get['action'] == 'editer' && isset($get['idS']))
{

	$sql = "SELECT * FROM salle WHERE idSalle=".$get['idS'];
	//echo $sql;
	$req_desc = $connector->query($sql);

	if ($tabDesc = $connector->fetchArray($req_desc))
	{
			foreach($tabDesc as $c => $v)
			{
				$champs[$c] = $v;
			}
	}

	echo '<ul class="entete_contenu_menu">';
	echo "<li class=\"action_supprimer\">
	<a href=\"".$url_site."supprimer.php?type=salle&id=".$get['idS']."\" title=\"Supprimer la salle\">Supprimer</a></li>";
	echo "</ul>";

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

<p>* indique un champ obligatoire</p>

<fieldset>
<legend>Salle</legend>
<!-- Select liste des lieux -->

<p>
<label for="idLieu">Lieu* :</label>
<?php
if (($get['action'] != 'editer' || $get['action'] != 'update') && !empty($get['idL']))
{
	$champs['idLieu'] = $get['idL'];

}
	echo "<select name=\"idLieu\" id=\"idLieu\" title=\"Choisissez le lieu que vous voulez décrire\" >
	<option value=\"0\"></option>";
	$req_lieux = $connector->query("SELECT idLieu, nom FROM lieu ORDER BY nom");

	while ($lieuTrouve = $connector->fetchArray($req_lieux))
	{
	    echo "<option";
		if ($lieuTrouve['idLieu'] == $champs['idLieu'])
		{
		  	echo " selected=\"selected\"";
		}
		echo " value=\"".$lieuTrouve['idLieu']."\">".$lieuTrouve['nom']."</option>";
	}

?>
</select>
<?php
echo $verif->getErreur('idLieu');
echo $verif->getErreur('doublon');
echo $verif->getErreur('nom');
?>
</p>


<p>
<label for="nom">Nom* :</label>
<input name="nom" id="nom" type="text" size="30" title="nom" value="<?php echo securise_string($champs['nom']) ?>" />
<?php echo $verif->getErreur("nom"); ?>
</p>

<p>
<label for="emplacement">Emplacement :</label>
<input name="emplacement" id="emplacement" type="text" size="30" title="emplacement" value="<?php echo securise_string($champs['emplacement']) ?>" />
<?php echo $verif->getErreur("emplacement"); ?>
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
