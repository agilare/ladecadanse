<?php

require_once("app/bootstrap.php");

use Ladecadanse\Security\Sentry;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\Logger;
use Ladecadanse\Utils\Utils;
use Ladecadanse\HtmlShrink;

$videur = new Sentry();

//$cache_lieux = $rep_cache."lieux/";
$nom_page = "evenement-report";
$page_titre = "Signaler une erreur";
$page_description = "";
$extra_css = array("formulaires", "evenement_inc");
include("_header.inc.php");


if (isset($_GET['idE']))
{
	$get['idE'] = Validateur::validateUrlQueryValue($_GET['idE'], "int", 1);
}
else
{
	HtmlShrink::msgErreur("idE obligatoire");
	exit;
}

?>




<!-- D?t Contenu -->
<div id="contenu" class="colonne signaler-erreur">


<?php
//TEST
//printr($_SESSION);
//

$tab_type_erreur = array(
"info" => "mauvaise information au sujet de l’événement",
"enlever" => "événement à enlever",
"bug" => "un bug",
"autre" => "autre"
);

/*
* TRAITEMENT DU FORMULAIRE (EDITION OU AJOUT)
*/

$verif = new Validateur();

$champs = array("type_erreur" => '', 'message' => '', 'name' => '', 'email' => '');
$action_terminee = false;

if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok' )
{

	/*
	 * Copie des champs envoyes par POST
	 */
	foreach ($champs as $c => $v)
	{

			$champs[$c] = $_POST[$c];
		
	}

	$champs['idEvenement'] = $get['idE'];

	$verif = new Validateur();
	$erreurs = array();

	$verif->valider($champs['message'], "message", "texte", 2, 10000, 1);

	if (empty($champs['type_erreur']))
	{
		$verif->setErreur("type_erreur", "Veuillez choisir un type d'erreur");
	}
        
	$verif->valider($champs['email'], "email", "email", 4, 80, 0);
        
	
	if (empty($champs['name']))
	{
	
/*
	 * Pas d'erreur, donc ajout ou update executés
	 */
	if ($verif->nbErreurs() === 0)
	{
		
		$from = '"La décadanse" <'.$glo_email_support.'>';
		if (isset($_SESSION['user']))
		{
			$from = '"'.$_SESSION['user'].'" <'.$_SESSION['Semail'].'>';
		}
                elseif (!empty($champs['email']))
                {
                    $from = '"'.$champs['email'].'" <'.$champs['email'].'>';    
                }
		
		$to = '"La décadanse" <'.$glo_email_support.'>';
                $subject = "[La décadanse] Rapport d'erreur sur un événement";
		$contenu_message = "Type : ".$tab_type_erreur[$champs['type_erreur']];
		$contenu_message .= "\n\n";
		$contenu_message .= "Événement : ".$url_site."/evenement.php?idE=".$champs['idEvenement'];
		$contenu_message .= "\n\n";
		$contenu_message .= "Description : ".$champs['message']."\n\n";

        $headers = array (
		"Content-Type" => "text/plain; charset=\"UTF-8\"",
		'From' => $from, 
		'To' => $to, 
		'Subject' => $subject,
        'Message-ID' => Utils::generateMessageID()            
		);
        
        $smtp = Mail::factory(
		'smtp',
        array ('host' => $glo_email_host,
        'auth' => true,
        'username' => $glo_email_username,
        'password' => $glo_email_password)
		);

        $mail = $smtp->send($to, $headers, $contenu_message);

		// HACK : pear http://forum.revive-adserver.com/topic/1597-non-static-method-peariserror-should-not-be-called-statically/
        //if (PEAR::isError($mail)){
        if ((new PEAR)->isError($mail))
		{
			echo("<p>" . $mail->getMessage() . "</p>");
			HtmlShrink::msgErreur('L\'envoi a echoué, veuillez réessayer ou alors utilisez le formulaire de contact');
        }
		else
		{
			
				HtmlShrink::msgOk('Erreur envoyée au webmaster. Merci de l\'avoir signalée');
                $logger->log('global', 'activity', "[signaler_erreur] by ".$from." for ".$url_site."/evenement.php?idE=".$champs['idEvenement'], Logger::GRAN_YEAR);
              
                
				$action_terminee = true;
        }		
		

		unset($_POST);

		$action_terminee = true;


	} // if erreurs == 0

	} // if antispam
	else
	{
		HtmlShrink::msgErreur("Ne seriez-vous pas un robot ? Veuillez réessayer");
	}

} // if POST != ""


if (!$action_terminee)
{

echo '<div id="entete_contenu">';

echo "<h2>Signaler une erreur</h2>";
?>
<div class="spacer"></div>

<?php
echo '</div>';

/*
 * Récupérations des détails de l'événement à copier, affichage dans une boîte
 */
if (isset($get['idE']))
{
	$req_getEven = $connector->query("SELECT idEvenement, idLieu, idSalle, idPersonne, titre, genre, dateEvenement,
	 nomLieu, adresse, quartier, urlLieu, description, flyer, prix, horaire_debut,horaire_fin, horaire_complement, URL1, ref, prelocations, statut, localite 
	  FROM evenement, localite WHERE evenement.localite_id=localite.id AND idEvenement =".$get['idE']);

	if ($affEven = $connector->fetchArray($req_getEven))
	{

		$evenement = $affEven;

		//echo date_fr($affEven['dateEvenement']);
		include($rep_absolu."_evenement.inc.php");
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


<!-- FORMULAIRE POUR UNE commentaire -->
<form method="post" id="ajouter_editer"  class="submit-freeze-wait" action="<?php echo basename(__FILE__)."?idE=".$get['idE']; ?>" onsubmit="return validerAjouterDescription()">

<fieldset style="width:100%">
<!-- Description Texte -->


<legend>Type d'erreur*</legend>
<ul class="radio_vert" >
<?php
foreach ($tab_type_erreur as $t => $libelle)
{
	$coche = '';
	if (strcmp($t, $champs['type_erreur']) == 0)
	{
		$coche = 'checked="checked"';
	}
	echo '<li><input type="radio" name="type_erreur" value="'.$t.'" '.$coche.' id="type_'.$t.'"
	title="" class="radio_vert" /><label for="type_'.$t.'">'.$libelle.'</label></li>';
}
?>
</ul>
<?php
echo $verif->getHtmlErreur("type_erreur");
?>
</fieldset>

<p>
<label for="message">Description de l’erreur*</label>
<textarea name="message" id="message" cols="35" rows="8" title="">
<?php echo sanitizeForHtml($champs['message']) ?>
</textarea>
<?php echo $verif->getHtmlErreur("message"); ?>
</p>

<?php
// email, utile si visiteur non logué
if (!isset($_SESSION['user']))
{
?>                                                    
    <p>
    <label for="email" id="label_email">Votre e-mail</label>
    <input name="email" id="email" type="text" size="40" value="<?php echo sanitizeForHtml($champs['email']) ?>"  />
    <?php echo $verif->getErreur("email"); ?>
    </p>
<?php
}
 else {
?>
    <input name="email" id="email" type="hidden" value=""  />

<?php    
}
?>

<div class="mr_as">
<label for="mr_as">
Antispam, ne pas remplir ce champ
</label>
<input type="text" id="mr_as" name="name">
</div>

<p class="piedForm">
<input type="hidden" name="formulaire" value="ok" />

<input type="submit" value="Envoyer" class="submit submit-big" />
</p>



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
