<?php

require_once("app/bootstrap.php");

use Ladecadanse\Utils\Validateur;
use Ladecadanse\HtmlShrink;

if (!$videur->checkGroup(8))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
	header("Location: /user-login.php");
    die();
}

/*
* action choisie, idL et idP si édition
*/
$tab_actions = ["ajouter", "insert", "editer", "update"];
$get['action'] = "ajouter";
if (isset($_GET['action']))
{
	$get['action'] = Validateur::validateUrlQueryValue($_GET['action'], "enum", 0, $tab_actions);
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

$page_titre = "ajouter/modifier une salle";
$extra_css = ["formulaires"];
include("_header.inc.php");
?>

<main id="contenu" class="colonne">

<?php

/* VERIFICATION POUR MODIFICATION
* Si ce n'est pas un ajout et que la personne, n'est pas l'auteur de la desc ni admin -> exit
*/
if ($get['action'] != "ajouter" && $get['action'] != "insert")
{
	if ($_SESSION['Sgroupe'] > 6)
	{
		HtmlShrink::msgErreur("Vous n'avez pas les droits pour éditer cette salle");
		exit;
	}
}


/*
* TRAITEMENT DU FORMULAIRE (EDITION OU AJOUT)
*/
$verif = new Validateur();

$champs = ["idLieu" => '', "nom" => '', "emplacement" => ''];

$action_terminee = false;

if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok' )
{
	/*
	 * Copie des champs envoyê³ par POST
	 */
	foreach ($champs as $c => $v)
	{
		$champs[$c] = $_POST[$c];
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

				HtmlShrink::msgOk("Salle <em>".sanitizeForHtml($champs['nom'])."</em> ajoutée");
				$action_terminee = true;
			}
			else
			{
				HtmlShrink::msgErreur("La requête INSERT a échoué");
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
			WHERE idSalle=".(int)$get['idS'];

			$req_update = $connector->query($sql_update);

			//message résultat et réinit de l'action
			if ($req_update)
			{

				HtmlShrink::msgOk('Salle du <a href="/lieu/lieu.php?idL='.(int)$champs['idLieu'].'">lieu</a> modifiée');

				$get['action'] = 'editer';
				$action_terminee = true;
			}
			else
			{
				HtmlShrink::msgErreur("La requête UPDATE a échoué");
			}

		} //if action


	} // if erreurs == 0


} // if POST != ""

if (!$action_terminee)
{

echo '<header id="entete_contenu">';


/*
 * PREPARATION DES URLS SELON LES ACTIONS,
 * update et idB en cas d'édition, insert pour ajout
 */
if ($get['action'] == 'editer' || $get['action'] == 'update')
{
	$act = "update&idS=".(int)$get['idS'];
	$req_lieu = $connector->query("SELECT * FROM salle WHERE idSalle=".(int)$get['idS']);
 	$detailsLieu = $connector->fetchArray($req_lieu);
    echo '<h1>Modifier</h1>';
}
else
{
    $act = "insert";
	echo "<h1>Ajouter une salle à un lieu</h1>";
}

/*
* POUR EDITER UNE DESCRIPTION, ALLER CHERCHER SES VALEURS DANS LA BASE
* Accessible par son auteur ou un admin
* Récupération des valeurs de la table et remplissage des champs pour le formulaire
* Affichage d'un menu d'actions pour l'admin
*/
if ($get['action'] == 'editer' && isset($get['idS']))
{

	$sql = "SELECT * FROM salle WHERE idSalle=".(int)$get['idS'];
	//echo $sql;
	$req_desc = $connector->query($sql);

	if ($tabDesc = $connector->fetchArray($req_desc))
	{
			foreach($tabDesc as $c => $v)
			{
				$champs[$c] = $v;
			}
	}
} // if GET action

echo '<div class="spacer"></div></header>';



if ($verif->nbErreurs() > 0)
{
	HtmlShrink::msgErreur("Il y a ".$verif->nbErreurs()." erreur(s).");
	//print_r($verif->getErreurs());
}


?>



<!-- FORMULAIRE POUR UNE DESCRIPTION -->
<form method="post" id="ajouter_editer" enctype="multipart/form-data" class="js-submit-freeze-wait" action="<?php echo basename(__FILE__)."?action=".$act; ?>">

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
	echo "<select name=\"idLieu\" id=\"idLieu\" class=\"js-select2-options-with-style\" data-placeholder=\"\">
	<option value=\"\"></option>";
    $req_lieux = $connector->query("SELECT idLieu, nom FROM lieu ORDER BY nom");

	while ($lieuTrouve = $connector->fetchArray($req_lieux))
	{
	    echo "<option";
		if ($lieuTrouve['idLieu'] == $champs['idLieu'])
		{
		  	echo " selected=\"selected\"";
		}
		echo " value=\"" . $lieuTrouve['idLieu'] . "\">" . sanitizeForHtml($lieuTrouve['nom']) . "</option>";
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
<input name="nom" id="nom" type="text" size="30" title="nom" value="<?php echo sanitizeForHtml($champs['nom']) ?>" />
<?php echo $verif->getErreur("nom"); ?>
</p>

<p>
<label for="emplacement">Emplacement :</label>
<input name="emplacement" id="emplacement" type="text" size="30" title="emplacement" value="<?php echo sanitizeForHtml($champs['emplacement']) ?>" />
<?php echo $verif->getErreur("emplacement"); ?>
</p>


</fieldset>

    <p class="piedForm">
    <input type="hidden" name="formulaire" value="ok" />
    <input type="submit" value="Enregistrer" class="submit submit-big" />
    </p>


</form>

<?php
} // if action_terminee
?>

</main>
<!-- fin contenu  -->

<div id="colonne_gauche" class="colonne">

<?php include("event/_navigation_calendrier.inc.php"); ?>
</div>
<!-- Fin Colonne gauche -->

<div id="colonne_droite" class="colonne">
</div>

<?php
include("_footer.inc.php");
?>
