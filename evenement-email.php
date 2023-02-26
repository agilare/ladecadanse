<?php

require_once("app/bootstrap.php");

use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\Logger;
use Ladecadanse\Utils\Text;
use Ladecadanse\Utils\Mailing;
use Ladecadanse\HtmlShrink;

if (!$videur->checkGroup(12))
{
	header("Location: index.php"); die();
}

$page_titre = "Envoyer un événement";
$extra_css = array("formulaires", "evenement_inc", "email_evenement_formulaire");
include("_header.inc.php");


if (isset($_GET['idE']))
{
    try {
        $get['idE'] = Validateur::validateUrlQueryValue($_GET['idE'], "int", 1);
    } catch (Exception $e) { header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request"); exit; }
}
else
{
    header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
    exit;
}

?>

<div id="contenu" class="colonne email_evenement">

<?php

$verif = new Validateur();

$champs = array("email_destinataire" => '', 'message' => '');
$action_terminee = false;

if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok' )
{

	foreach ($champs as $c => $v)
	{
        $champs[$c] = $_POST[$c];
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

	if ($verif->nbErreurs() === 0)
	{
        $contenu_message = "Message envoyé depuis www.ladecadanse.ch par ".$_SESSION['user']." (".$_SESSION['Semail'].") :\n\n";
		$contenu_message .= "> ".$champs['message']."\n\n";
		$contenu_message .= "------------------\n";

		$req_getEven = $connector->query("SELECT idEvenement, idLieu, idSalle, idPersonne, titre, genre, dateEvenement,
		 nomLieu, adresse, quartier, urlLieu, description, flyer, prix, horaire_debut,horaire_fin, horaire_complement, ref, prelocations, statut
		  FROM evenement WHERE idEvenement =".$get['idE']);

		if ($tab_even = $connector->fetchArray($req_getEven))
		{
			$contenu_message .= $tab_even['titre']."\n\n";
			$contenu_message .= ucfirst(html_entity_decode(date_fr($tab_even['dateEvenement'], "annee", "", "", false)))."\n\n";
			$contenu_message .= $site_full_url.'/evenement.php?idE='.$get['idE']."\n\n";

			$contenu_message .= $tab_even['nomLieu']."\n";
			$contenu_message .= $tab_even['adresse']." - ".$tab_even['quartier']."\n\n";
			$items = "";
			$maxChar = Text::trouveMaxChar($tab_even['description'], 60, 5);
			if (mb_strlen($tab_even['description']) > $maxChar)
			{
				$items = Text::texteHtmlReduit(Text::wikiToHtml(sanitizeForHtml($tab_even['description'])), $maxChar, "");
			}
			else
			{
				$items = Text::wikiToHtml(sanitizeForHtml($tab_even['description']));
			}

			$contenu_message .= strip_tags($items);
			$contenu_message .= "\n\n";
			$contenu_message .= afficher_debut_fin($tab_even['horaire_debut'], $tab_even['horaire_fin'], $tab_even['dateEvenement'])."\n";
			$contenu_message .= sanitizeForHtml($tab_even['horaire_complement'])."\n";
			$contenu_message .= sanitizeForHtml($tab_even['prix'])."\n\n";
			$contenu_message .= "------------------\n";

			$subject = "Événement \"".$tab_even['titre']."\"";

            $mailer = new Mailing();
            if ($mailer->toUser($champs['email_destinataire'], $subject, $contenu_message, ['email' => $_SESSION['Semail'], 'name' => $_SESSION['user'] ]))
            {
                HtmlShrink::msgOk('Événement <strong>'.$tab_even['titre'].'</strong> envoyé à '.$champs['email_destinataire']);
                $logger->log('global', 'activity', "[evenement-email] event ".$tab_even['titre']." (idE ".$get['idE'].") sent from ".$_SESSION['user']." to ".$champs['email_destinataire'], Logger::GRAN_YEAR);
            }
		}
		else
		{
			HtmlShrink::msgErreur("Aucun événement n'est associé à ".$get['idE']);
			exit;
		} // if fetchArray

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
	 nomLieu, adresse, quartier, urlLieu, description, flyer, prix, horaire_debut,horaire_fin, horaire_complement, ref, prelocations, statut
	  FROM evenement WHERE idEvenement =".$get['idE']);

	if ($affEven = $connector->fetchArray($req_getEven))
	{
		$evenement = $affEven;
		include("_evenement.inc.php");
	}
	else
	{
		HtmlShrink::msgErreur("Aucun événement n'est associé à ".$get['idE']);
		exit;
	} // if fetchArray


} // if isset idE



if ($verif->nbErreurs() > 0)
{
	HtmlShrink::msgErreur("Il y a ".$verif->nbErreurs()." erreur(s).");
	//print_r($verif->getErreurs());
}
?>


        <form method="post" id="ajouter_editer"  class="submit-freeze-wait" action="<?php echo basename(__FILE__) . "?idE=" . $get['idE']; ?>">

<fieldset style="width: 100%;">
<!-- Description Texte -->
<p>
<label for="email_destinataire">Email du destinataire* :</label>
<input name="email_destinataire" id="email_destinataire" value="<?php echo sanitizeForHtml($champs['email_destinataire']) ?>" size="35" />
<?php echo $verif->getHtmlErreur("email_destinataire"); ?>
</p>
    <div class="guideChamp">L'adresse email restera confidentielle</div>


<p>
<label for="message">Message* :</label><textarea name="message" id="message" cols="35" rows="8" style="width: auto;">
<?php echo sanitizeForHtml($champs['message']) ?>
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
