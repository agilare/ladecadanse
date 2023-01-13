<?php

require_once("app/bootstrap.php");

use Ladecadanse\Security\Sentry;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\Logger;
use Ladecadanse\Utils\Utils;
use Ladecadanse\HtmlShrink;

$videur = new Sentry();

if ($videur->checkGroup(12))
{
	header("Location: index.php"); die();
}


$nom_page = "user-reset.php";
$page_titre = "Mot de passe oublié";
$extra_css = array("formulaires", "login");
include("_header.inc.php");

$tab_messages = array('faux');


?>


<!-- D?t Contenu -->
<div id="contenu" class="colonne">

<div id="entete_contenu">
<h2>Mot de passe oublié</h2>
	<div class="spacer"></div>
</div>


<?php
$termine = false;
$champs = array("pseudo_email" => "");

$verif = new Validateur();

if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok' && empty($_POST['name_as']))
{

	foreach ($champs as $c => $v)
	{

			$champs[$c] = $_POST[$c];
		
	}


	$verif->valider($champs['pseudo_email'], "pseudo_email", "texte", 2, 80, 1);


	//Si le pseudo et le mot de passe sont au bon format
	if ($verif->nbErreurs() == 0)
	{
		$idPersonne = '';
		$email = '';
		$email_envoi = '';
		
		//trouver user selon pseudo
		$sql_pseudo = "SELECT idPersonne, email FROM personne WHERE pseudo='".$connector->sanitize($champs['pseudo_email'])."'";
		//echo $sql;
		$res_personne_pseudo = $connector->query($sql_pseudo);
		if ($connector->getNumRows($res_personne_pseudo) > 0)
		{	
			$tab_pers = $connector->fetchArray($res_personne_pseudo);
			$idPersonne = $tab_pers['idPersonne'];
			$email =  'NULL';
			$email_envoi =  $tab_pers['email'];
			$hash = $tab_pers['idPersonne'];
			
		}
		
		
		//trouver user selon email
		$sql_email = "SELECT idPersonne, email FROM personne WHERE email='".$connector->sanitize($champs['pseudo_email'])."'";
		
		$res_personne_email = $connector->query($sql_email);

		if ($connector->getNumRows($res_personne_email) > 0)
		{	

			$email = $champs['pseudo_email'];
			$idPersonne = 'NULL';
			$email_envoi = $champs['pseudo_email'];
			$hash = $champs['pseudo_email'];
		}	
		

		if ($email_envoi)
		{
			
			
			$salt = "ciek48";

			// Create the unique user password reset key
			$token = hash('sha256', $salt.rand(0, 1000).$hash);
			//$token = bin2hex(openssl_random_pseudo_bytes(16));;
			
			//création de demande avec nouveau token
			$sql = "INSERT INTO temp (idPersonne, email, token, expiration) VALUES (".$idPersonne.", '".$email."', '".$token."', NOW() + INTERVAL 1 DAY)";
			//echo $sql; 
			$connector->query($sql);			

            
			//envoi mail avec  user-reset2.php?token=...
			$from = '"'."La décadanse".'" <'.$glo_email_info.'>';
			$to = $email_envoi;
			$subject = "Votre demande de nouveau mot de passe sur La décadanse";

			$contenu_message = "Bonjour,\n\n";
			$contenu_message .= "Un visiteur de La décadanse, probablement vous, a fait une demande pour obtenir un nouveau mot de passe. Veuillez cliquer sur ce lien :\n\n";
			$contenu_message .= $url_site."/user-reset2.php?token=".$token;
			$contenu_message .= "\n\n";
			$contenu_message .= "qui vous permettra de choisir un nouveau mot de passe durant 24h (avant expiration)";
			$contenu_message .= "\n";
			$contenu_message .= "Si vous avez besoin d'aide, vous pouvez nous contacter à info@ladecadanse.ch";
			$contenu_message .= "\n\n";
			$contenu_message .= "La décadanse\n";
			$contenu_message .= "www.ladecadanse.ch";
			
			$headers = ['From' => $from,
			'To' => $to,
			'Subject' => $subject,
            'Content-type' => 'text/plain; charset="utf-8"',
            'Message-ID' => Utils::generateMessageID()    
            ];
			
			$smtp = Mail::factory('smtp',
			array ('host' => $glo_email_host,
			'auth' => true,
			'username' => $glo_email_username,
			'password' => $glo_email_password));

			$mail = $smtp->send($to, $headers, $contenu_message);

			// HACK : pear http://forum.revive-adserver.com/topic/1597-non-static-method-peariserror-should-not-be-called-statically/
			//if (PEAR::isError($mail)){
			if ((new PEAR)->isError($mail))
			{				
				echo("<p>Erreur : " . $mail->getMessage() . "</p>");
			}

			HtmlShrink::msgOk("Un email a été envoyé à ".$email_envoi." qui contient un lien vous permettant de modifier votre mot de passe.");	

            $logger->log('global', 'activity', "[motdepasse_demande] request by ".$to." ".$url_site."/user.php?idP=".$idPersonne, Logger::GRAN_YEAR);
		}
		else
		{
            $logger->log('global', 'activity', "[motdepasse_demande] request failed for pseudo/email ".$champs['pseudo_email'], Logger::GRAN_YEAR);
			HtmlShrink::msgErreur("L'email/identifiant que vous avez saisi pour votre demande n'est pas enregistré sur La décadanse");				
		}



		$termine = true;


	}
}
//$_SERVER['PHP_SELF'].'?msg=faux'
//si le formulaire n'a pas été validé, ou les valeurs entrées sont fausses



if (!$termine)
{





if ($verif->nbErreurs() > 0)
{
	HtmlShrink::msgErreur("Il y a ".$verif->nbErreurs()." erreur(s)");
}

?>


<form id="ajouter_editer" class="submit-freeze-wait" action="" method="post">
    <span class="mr_as"><label for="mr_as">Ne pas remplir ce champ</label><input type="text" id="name_as" name="name_as"></span>
    <p>
        <label for="pseudo" id="login_pseudo">Identifiant ou e-mail du compte</label>
        <input type="text" name="pseudo_email" id="pseudo_email" value="" size="30" />
        <?php
        echo $verif->getHtmlErreur("pseudo_email");
        ?>
    </p>

    <p class="piedForm">
        <input type="hidden" name="formulaire" value="ok" />
        <input type="submit" name="Submit" value="Envoyer la demande" class="submit" />
    </p>
</form>

<?php } ?>

</div>
<!-- fin  -->
<div id="colonne_gauche" class="colonne">
<?php
include("_navigation_calendrier.inc.php");
?>
</div>
<!-- Fin Colonne gauche -->

<div id="colonne_droite" class="colonne">

</div>


<?php
include("_footer.inc.php");
?>
