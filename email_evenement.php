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

//$cache_lieux = $rep_cache."lieux/";
$nom_page = "email_evenement";
$page_titre = "Envoyer un événement";
$page_description = "Envoyer un événement par email";
$extra_css = array("formulaires", "evenement_inc", "email_evenement_formulaire");
include("_header.inc.php");


if (isset($_GET['idE']))
{
	$get['idE'] = verif_get($_GET['idE'], "int", 1);
}
else
{
	msgErreur("idE obligatoire");
	exit;
}

?>




<!-- D?t Contenu -->
<div id="contenu" class="colonne email_evenement">


<?php
//TEST
//printr($_SESSION);
//

/*
* TRAITEMENT DU FORMULAIRE (EDITION OU AJOUT)
*/
require_once($rep_librairies.'Validateur.php');
$verif = new Validateur();

$champs = array("email_destinataire" => '', 'message' => '');
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

	$champs['idEvenement'] = $get['idE'];

	$verif = new Validateur();
	$erreurs = array();

	$verif->valider($champs['email_destinataire'], "email_destinataire", "email", 4, 80, 1);

	$verif->valider($champs['message'], "message", "texte", 2, 10000, 0);

/*
	 * Pas d'erreur, donc ajout ou update executés
	 */
	if ($verif->nbErreurs() === 0)
	{

		require_once "Mail.php";

		$from = '"'.$_SESSION['user'].'" <'.$_SESSION['Semail'].'>';

		$to = $champs['email_destinataire'];

		$contenu_message = $champs['message']."\n\n";

		$contenu_message .= "------------------\n";

		$req_getEven = $connector->query("SELECT idEvenement, idLieu, idSalle, idPersonne, titre, genre, dateEvenement,
		 nomLieu, adresse, quartier, urlLieu, description, flyer, prix, horaire_debut,horaire_fin, horaire_complement, URL1, ref, prelocations, statut
		  FROM evenement WHERE idEvenement =".$get['idE']);

		if ($tab_even = $connector->fetchArray($req_getEven))
		{
			$contenu_message .= $tab_even['titre']."\n\n";
			$contenu_message .= ucfirst(html_entity_decode(date_fr($tab_even['dateEvenement'], "annee", "", "", false)))."\n\n";
			$contenu_message .= $url_site.'evenement.php?idE='.$get['idE']."\n\n";

			$contenu_message .= $tab_even['nomLieu']."\n";
			$contenu_message .= $tab_even['adresse']." - ".$tab_even['quartier']."\n\n";
			$items = "";
			$maxChar = trouveMaxChar($tab_even['description'], 60, 5);
			if (mb_strlen($tab_even['description']) > $maxChar)
			{
				$items = texteHtmlReduit(textToHtml(securise_string($tab_even['description'])), $maxChar, "");
			}
			else
			{
				$items = textToHtml(securise_string($tab_even['description']));
			}

			$contenu_message .= strip_tags($items);
			$contenu_message .= "\n\n";
			$contenu_message .= afficher_debut_fin($tab_even['horaire_debut'], $tab_even['horaire_fin'], $tab_even['dateEvenement'])."\n";
			$contenu_message .= securise_string($tab_even['horaire_complement'])."\n";
			$contenu_message .= securise_string($tab_even['prix'])."\n\n";

			$contenu_message .= "------------------\n";
			
			
			$subject = $tab_even['titre'];

		}
		else
		{
			msgErreur("Aucun événement n'est associé à ".$get['idE']);
			exit;
		} // if fetchArray

		/*
		* Envoi de l'email
		*/
		
		
		$headers = array (
		"Content-Type" => "text/plain; charset=\"UTF-8\"",
		'From' => $from,
		'To' => $to,
		'Subject' => $subject);
		
		$smtp = Mail::factory('smtp',
		array ('host' => $glo_email_host,
		'auth' => true,
		'username' => $glo_email_username,
		'password' => $glo_email_password));

		$mail = $smtp->send($to, $headers, $contenu_message);




		// HACK : pear http://forum.revive-adserver.com/topic/1597-non-static-method-peariserror-should-not-be-called-statically/
        //if (PEAR::isError($mail)){
        if (!(new PEAR)->isError($mail))
		{
			msgOk('Événement <strong>'.$tab_even['titre'].'</strong> envoyé à '.$champs['email_destinataire']);

			$action_terminee = true;
		}
		else
		{
			msgErreur('L\'envoi a echoué');
			echo("<p>" . $mail->getMessage() . "</p>");
		}
		
        $logger->log('global', 'activity', "[email_evenement] event ".$tab_even['titre']." (idE ".$get['idE'].") sent from $from to $to", Logger::GRAN_YEAR);
		unset($_POST);

		$action_terminee = true;

	} // if erreurs == 0


} // if POST != ""


if (!$action_terminee)
{

?>

<div id="entete_contenu" >
	<h2>Envoyer l'événement à un ami</h2>

	<div class="spacer"></div>
</div>

<?php
/*
 * Récupérations des détails de l'événement à copier, affichage dans une boîte
 */
if (isset($get['idE']))
{
	$req_getEven = $connector->query("SELECT idEvenement, idLieu, idSalle, idPersonne, titre, genre, dateEvenement,
	 nomLieu, adresse, quartier, urlLieu, description, flyer, prix, horaire_debut,horaire_fin, horaire_complement, URL1, ref, prelocations, statut
	  FROM evenement WHERE idEvenement =".$get['idE']);

	if ($affEven = $connector->fetchArray($req_getEven))
	{

		$evenement = $affEven;

		//echo date_fr($affEven['dateEvenement']);
		include("_evenement.inc.php");
	}
	else
	{
		msgErreur("Aucun événement n'est associé à ".$get['idE']);
		exit;
	} // if fetchArray


} // if isset idE



if ($verif->nbErreurs() > 0)
{
	msgErreur("Il y a ".$verif->nbErreurs()." erreur(s).");
	//print_r($verif->getErreurs());
}
?>


<!-- FORMULAIRE POUR UNE commentaire -->
<form method="post" id="ajouter_editer"  class="submit-freeze-wait" action="<?php echo basename(__FILE__)."?idE=".$get['idE']; ?>" onsubmit="return validerAjouterDescription()">

<fieldset style="width: 100%;">
<!-- Description Texte -->
<p>
<label for="email_destinataire">Email du destinataire* :</label>
<input name="email_destinataire" id="email_destinataire" value="<?php echo securise_string($champs['email_destinataire']) ?>" size="35" />
<?php echo $verif->getHtmlErreur("email_destinataire"); ?>
</p>
<div class="guideChamp">L'adresse email restera confidentielle.</div>


<p>
<label for="message">Message* :</label><textarea name="message" id="message" cols="35" rows="8" style="width: auto;">
<?php echo securise_string($champs['message']) ?>
</textarea>
<?php echo $verif->getHtmlErreur("message"); ?>
</p>

<p class="piedForm">
<input type="hidden" name="formulaire" value="ok" />
<input type="submit" value="Envoyer" class="submit submit-big" />
</p>

</fieldset>

</form>




<?php



} // if action_terminee
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
