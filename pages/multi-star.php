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

require_once("../app/bootstrap.php");

use Ladecadanse\Security\Sentry;
use Ladecadanse\Utils\Logger;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\HtmlShrink;

$videur = new Sentry();

if (!$videur->checkGroup(12))
{
	header("Location: index.php"); die();
}

/* if (is_file($rep_includes."tracerVisiteur.php"))
{
	include_once($rep_includes."tracerVisiteur.php");
} */

//$cache_lieux = $rep_cache."lieux/";
// header("Cache-Control: max-age=30, must-revalidate");
header ("Refresh: 2;URL=".$_SERVER['HTTP_REFERER']);
$nom_page = "action_favori";
$page_titre = "ajouter/éditer un favori";
$page_description = "ajouter/édite";
$extra_css = array("formulaires");
include("_header.inc.php");


$tab_actions = array("ajouter", "insert", "delete", "supprimer");
$get['action'] = "";
$get['action'] = Validateur::validateUrlQueryValue($_GET['action'], "enum", 0, $tab_actions);

$tab_elements = array("evenement", "lieu");
$get['element'] = "";
$get['element'] = Validateur::validateUrlQueryValue($_GET['element'], "enum", 0, $tab_elements);


if (isset($_GET['idE']))
{
	$get['idE'] = Validateur::validateUrlQueryValue($_GET['idE'], "int", 1);
}
if (isset($_GET['idL']))
{
	$get['idL'] = Validateur::validateUrlQueryValue($_GET['idL'], "int", 1);
}

if (!isset($_GET['idL']) && !isset($_GET['idE']))
{
	HtmlShrink::msgErreur("Paramètre id obligatoire");
	exit;
}

?>


<!-- D?t Contenu -->
<div id="contenu" class="colonne">


<?php


//creation/nettoyage des valeurs à insérer dans la table
$pers = $_SESSION['SidPersonne'];


if ($get['element'] == 'evenement')
{

	/*
	* Insertion dans la base : INSERT
	*/
	if ($get['action'] == 'ajouter')
	{

		$sql_insert = "INSERT INTO evenement_favori (idPersonne, idEvenement, date_ajout)
		VALUES (".$_SESSION['SidPersonne'].",".$get['idE'].",'".date("Y-m-d H:i:s")."')";

		//TEST
		//echo $sql_insert;
		//

		//message résultat et réinit
		if ($connector->query($sql_insert))
		{
			HtmlShrink::msgOk("Favori de <a href=\"/pages/evenement.php?idE=".$get['idE']."\" title=\"Voir la fiche du lieu\">".$iconeVoirFiche."l'événement</a> ajouté");

		}
		else
		{
			HtmlShrink::msgErreur("La requête INSERT dans favoris a échoué");
		}

	}
	elseif ($get['action'] == 'supprimer')
	{

		$req_update = $connector->query("DELETE FROM evenement_favori
		WHERE idEvenement=".$get['idE']. " AND idPersonne=".$_SESSION['SidPersonne']);

		//message résultat et réinit de l'action
		if ($req_update)
		{
			HtmlShrink::msgOk("Favori de <a href=\"/pages/evenement.php?idE=".$get['idE']."\" title=\"Voir la fiche du lieu\">".$iconeVoirFiche."l'événement</a> supprimé");
		}
		else
		{
			HtmlShrink::msgErreur("La requête UPDATE a échoué");
		}

        $logger->log('global', 'activity', "[action_favori] ".$get['action']." on idE ".$get['idE']." by user ".$_SESSION['user'], Logger::GRAN_YEAR);         
        
	} //if action

}
else if ($get['element'] == 'lieu')
{
	/*
	* Insertion dans la base : INSERT
	*/
	if ($get['action'] == 'ajouter')
	{

		$sql_insert = "INSERT INTO lieu_favori (idPersonne, idLieu, date_ajout)
		VALUES (".$_SESSION['SidPersonne'].",".$get['idL'].",'".date("Y-m-d H:i:s")."')";

		//TEST
		//echo "<p>".$sql_insert."</p>";
		//

		//message résultat et réinit
		if ($connector->query($sql_insert))
		{

			HtmlShrink::msgOk("Favori pour le <a href=\"/pages/lieu.php?idL=".$get['idL']."\"
			title=\"Voir la fiche du lieu\">".$iconeVoirFiche."lieu</a> ajouté");

			$action_terminee = true;

		}
		else
		{
			HtmlShrink::msgErreur("La requête INSERT dans favoris a échoué");
		}

	/*
	* Insertion dans la base : UPDATE
	*/
	}
	elseif ($get['action'] == 'supprimer')
	{

		$req_update = $connector->query("DELETE FROM lieu_favori
		WHERE idLieu=".$get['idL']. " AND idPersonne=".$_SESSION['SidPersonne']);

		//message résultat et réinit de l'action
		if ($req_update)
		{

			HtmlShrink::msgOk("Favori pour <a href=\"/pages/lieu.php?idL=".$get['idL']."\" title=\"Voir la fiche du lieu\">".$iconeVoirFiche."le lieu</a> supprimé");

		}
		else
		{
			HtmlShrink::msgErreur("La requête UPDATE a échoué");
		}

	} //if action
   
    $logger->log('global', 'activity', "[action_favori] ".$get['action']." on idL ".$get['idL']." by user ".$_SESSION['user'], Logger::GRAN_YEAR);  

}



echo '</div>';

?>

</div>
<!-- fin Evenements -->

<div id="colonne_gauche" class="colonne">

<?php include("_navigation_calendrier.inc.php"); ?>
</div>
<!-- Fin Colonne gauche -->

<?php
include("_footer.inc.php");
?>
