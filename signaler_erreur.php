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

//$cache_lieux = $rep_cache."lieux/";
$nom_page = "annonce_erreur";
$page_titre = "Signaler une erreur";
$page_description = "";
$extra_css = array("formulaires", "evenement_inc");
include("includes/header.inc.php");


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
require_once($rep_librairies.'Validateur.php');
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
		if (get_magic_quotes_gpc())
		{
			$champs[$c] = stripslashes($_POST[$c]);
		}
		else if (isset($_POST[$c]))
		{
			$champs[$c] = $_POST[$c];
		}
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
            require_once "Mail.php";
		
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
		$contenu_message .= "Événement : ".$url_site."evenement.php?idE=".$champs['idEvenement'];
		$contenu_message .= "\n\n";
		$contenu_message .= "Description : ".$champs['message']."\n\n";

        $headers = array (
		"Content-Type" => "text/plain; charset=\"UTF-8\"",
		'From' => $from, 
		'To' => $to, 
		'Subject' => $subject
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
			msgErreur('L\'envoi a echoué, veuillez réessayer ou alors utilisez le formulaire de contact');
        }
		else
		{
			
				msgOk('Erreur envoyée au webmaster. Merci de l\'avoir signalée');
				$action_terminee = true;
        }		
		

		unset($_POST);

		$action_terminee = true;


	} // if erreurs == 0

	} // if antispam
	else
	{
		msgErreur("Ne seriez-vous pas un robot ? Veuillez réessayer");
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
	 nomLieu, adresse, quartier, urlLieu, description, flyer, prix, horaire_debut,horaire_fin, horaire_complement, URL1, ref, prelocations, statut
	  FROM evenement WHERE idEvenement =".$get['idE']);

	if ($affEven = $connector->fetchArray($req_getEven))
	{

		$evenement = $affEven;

		//echo date_fr($affEven['dateEvenement']);
		include("templates/evenement.inc.php");
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
<form method="post" id="ajouter_editer" action="<?php echo basename(__FILE__)."?idE=".$get['idE']; ?>" onsubmit="return validerAjouterDescription()">

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
<?php echo securise_string($champs['message']) ?>
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
    <input name="email" id="email" type="text" size="40" value="<?php echo securise_string($champs['email']) ?>"  />
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

<input type="submit" value="Envoyer" class="submit" />
</p>



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
