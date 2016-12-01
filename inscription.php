<?php
/**
 * Permet d'ajouter une personne ou de la modifier
 * Un admin peut tout modifier, un membre seulement son profile
 *
 * Le traitement de suppression est suivi par le traitement d'ajout/edition et le formulaire
 * est à la fin
 *
 * @category   modification d'une table de la base
 * @see personne.php, login.php
 * @author     Michel Gaudry <michel@ladecadanse.ch>
 */

if (is_file("config/reglages.php"))
{
	require_once("config/reglages.php");
}
else
{
	echo "<p>Problème de chargement de la configuration du site, veuillez repasser plus tard</p>";
	exit;
}

require_once($rep_librairies."Sentry.php");
$videur = new Sentry();


require_once($rep_librairies.'Validateur.php');



/*
* action choisie, ID si édition
* action "ajouter" par défaut
*/

$get['action'] = "ajouter";

/*
* Vérification et attribution des variables d'URL GET
*/
if (isset($_GET['idP']))
{

	$get['idP'] = verif_get($_GET['idP'], "int", 1);
}

/* if (isset($_GET['action']))
{
	$get['action'] = verif_get($_GET['action'], "enum", 1, $actions);

	if (($_GET['action'] == "ajouter" || $_GET['action'] == 'insert') && $_SESSION['Sgroupe'] > 1)
	{
		msgErreur("Vous n'avez pas le droit d'ajouter une personne");
		exit;
	}
	elseif (($get['action'] == "update" || $get['action'] == "editer") && $_SESSION['SidPersonne'] != $get['idP'] && $_SESSION['Sgroupe'] > 1)
	{
		msgErreur("Vous n'avez pas le droit de modifier cette personne");
		exit;
	}

}
else
{
	formaterTexte("Vous devez faire une action", "p");
	exit;
} */

//header("Cache-Control: max-age=600, must-revalidate");
/* $cache_index = $rep_cache."index/";
$cache_lieux = $rep_cache."lieux/"; */
$page_titre = "Inscription";
$page_description = "Création d'une compte sur La décadanse";
$nom_page = "ajouterPersonne";
$extra_css = array("formulaires", "inscription_formulaire", "chosen.min");
$extra_js = array("zebra_datepicker", "chosen.jquery.min", "jquery.shiftcheckbox");
include("includes/header.inc.php");
?>





<!-- Début Evenements -->
<div id="contenu" class="colonne inscription">

<?php

/*
 * TRAITEMENT DU FORMULAIRE (EDITION OU AJOUT)
 */

$verif = new Validateur();

$champs = array("utilisateur" => '',
"motdepasse" => '',
"motdepasse2" => '',
"nom" => '',
"prenom" => '',
'organisateurs' => '',
"affiliation" => '',
"lieu" => '',
"email" => '',
"URL" => '',
"telephone" => '',
"groupe" => '',
);

$action_terminee = false;

if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok')
{
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
	$champs['organisateurs'] = $_POST['organisateurs'];
	/*
	 * VERIFICATION DES CHAMPS ENVOYES par POST
	 */

	$verif->valider($champs['utilisateur'], "utilisateur", "texte", 2, 50, 1);

	$sql_existance = "SELECT pseudo FROM personne
	WHERE pseudo='".$connector->sanitize($champs['utilisateur'])."'";
	$req_existance = $connector->query($sql_existance);

	if ($connector->getNumRows($req_existance) > 0)
	{
		$verif->setErreur("utilisateur_existant", "Un membre avec l'identifiant <em>".$champs['utilisateur']."</em> existe déjà.");
	}


	$verif->valider($champs['motdepasse'], "motdepasse", "texte", 6, 18, 1);
	$verif->valider($champs['motdepasse2'], "motdepasse2", "texte", 6, 18, 1);


	if (!empty($champs['motdepasse']) || !empty($champs['motdepasse2']))
	{
		if ($champs['motdepasse'] != $champs['motdepasse2'])
		{
			$verif->setErreur("motdepasse_inegaux", 'Les 2 mots de passe doivent être identiques.');
		}
		
		if (in_array($champs['motdepasse'], $g_mauvais_mdp))
		{
			$verif->setErreur("motdepasse", "Veuillez choisir un meilleur mot de passe");	
		}
		
		
		if (!empty($champs['motdepasse'])&& !preg_match("/[0-9]/", $champs['motdepasse']))
		{
			$verif->setErreur("motdepasse", 'Le mot de passe doit comporter au moins 1 chiffre.');
		}

	}

	$verif->valider($champs['email'], "email", "email", 4, 250, 1);

	$sql_existance = "SELECT email FROM personne
	WHERE email='".$connector->sanitize($champs['email'])."'";
	$req_existance = $connector->query($sql_existance);

	if ($connector->getNumRows($req_existance) > 0)
	{
		$verif->setErreur("email_identique", "Un membre avec l'email ".$champs['email']." existe déjà.");
	}


	$verif->valider($champs['groupe'], "groupe", "int", 1, 2, 1);
	/*
	 * Modification du groupe, vérifie si le groupe envoyé existe et a bien un nom
	 */
	if (!empty($champs['groupe']))
	{
		if ($connector->getNumRows($connector->query("SELECT idGroupe FROM groupes
		WHERE nom !='' AND idGroupe=".$connector->sanitize($champs['groupe']))) < 1)
		{
			$verif->setErreur("groupe", "Le groupe ".$champs['groupe']." n'est pas valable");
		}
	}

	$verif->valider($champs['affiliation'], "affiliation", "texte", 2, 60, 0);

	/*
	 * Si l'affiliation texte et l'affiliation lieu ont été choisies
	 */
	if ($champs['lieu'] != 0 && !empty($champs['affiliation']) )
	{
		$verif->setErreur("affiliation", "Vous ne pouvez pas choisir 2 affiliations");
	}

	/*
	 * Si l'affiliation texte et l'affiliation lieu ont été choisies
	 */
	if ($champs['groupe'] == 8 && (empty($champs['affiliation']) && empty($champs['lieu']) && count($champs['organisateurs']) == 0))
	{
		$verif->setErreur("affiliation", "Vous devez choisir une affiliation");
	}

	/*
	 * Si l'affiliation texte et l'affiliation lieu ont été choisies
	 */
	if ($champs['groupe'] == 12 && (!empty($champs['affiliation']) || !empty($champs['lieu'])))
	{
		$verif->setErreur("affiliation", "Le choix d'une affiliation est réservéee aux organisateurs");
	}

	//TEST
	//echo $sql_existance;
	//

	/*
	if(!empty($_SESSION['freecap_word_hash']) && !empty($_POST['word']))
	{
		// all freeCap words are lowercase.
		// font #4 looks uppercase, but trust me, it's not...
		if($_SESSION['hash_func'](mb_strtolower($_POST['word']))==$_SESSION['freecap_word_hash'])
		{
			// reset freeCap session vars
			// cannot stress enough how important it is to do this
			// defeats re-use of known image with spoofed session id
			$_SESSION['freecap_attempts'] = 0;
			$_SESSION['freecap_word_hash'] = false;


			// now process form


			// now go somewhere else
			// header("Location: somewhere.php");

		} else {
			$verif->setErreur("freecap", "Le texte que vous avez entré ne correspond pas au mot dans l'image");
		}
	} else {
		$verif->setErreur("freecap", "Vous devez entrer un mot correspondant à celui de l'image ci-dessus");
	}
	*/

	/*
	 * PAS D'ERREUR, donc ajout ou update executés
	 */
	if ($verif->nbErreurs() === 0)
	{


/* 		if (!empty($champs['motdepasse']))
		{
			$champs['gds'] = mb_substr(sha1(uniqid(rand(), true)), 0, 5);
			$champs['mot_de_passe'] = sha1($champs['gds'].sha1($champs['motdepasse']));
		}
 */
		$champs['pseudo'] = $champs['utilisateur'];
		$champs['mot_de_passe'] = $champs['motdepasse'];

		$champs['dateAjout'] = date("Y-m-d H:i:s");
		$champs['date_derniere_modif'] = date("Y-m-d H:i:s");
		$champs['statut'] = 'demande';

		// pour mail de notif admin (plus bas
		$type_compte = 'membre';
		if ($champs['groupe'] == 8)
			$type_compte = 'organisateur';		
		
		$sql_insert_attributs = "";
		$sql_insert_valeurs = "";

		foreach ($champs as $c => $v)
		{
			if ($c != "utilisateur" && $c != "motdepasse" && $c != "motdepasse2" && $c != "lieu" && $c != "organisateurs")
			{
				$sql_insert_attributs .= $c.", ";
				$sql_insert_valeurs .= "'".$connector->sanitize($v)."', ";
			}
		}

		$sql_insert_attributs = mb_substr($sql_insert_attributs, 0, -2);
		$sql_insert_valeurs = mb_substr($sql_insert_valeurs, 0, -2);



		$sql_insert =  "INSERT INTO personne (".$sql_insert_attributs.") VALUES (".$sql_insert_valeurs.")";
		//TEST
		//echo $sql_insert;
		//
		$req_insert = $connector->query($sql_insert);

		$req_id = $connector->getInsertId();

		//si un lieu a été choisi comme affiliation
		if (isset($champs['lieu']) && $champs['lieu'] != 0)
		{
			$req_insAff = $connector->query("INSERT INTO affiliation
			(idPersonne, idAffiliation,
			 genre) VALUES ('".$req_id."','".$champs['lieu']."','lieu')");
		}

		foreach ($champs['organisateurs'] as $idOrg)
		{
			if ($idOrg != 0)
			{
				$sql = "INSERT INTO personne_organisateur (idPersonne, idOrganisateur) VALUES (".$req_id.", ".$idOrg.")";
				//echo $sql;
				$connector->query($sql);
			}
		}		
		
		
		
		
		/*
		* Insertion réussie, message OK, et RAZ des champs
		*/
		if ($req_insert)
		{


			
			$req_pers = $connector->query("
			SELECT pseudo, mot_de_passe, email
			FROM personne
			WHERE idPersonne=".$req_id);

			$tab_pers = $connector->fetchArray($req_pers);

			$champs = array('gds' => '', 'mot_de_passe' => '');

			$pass_email = $tab_pers['mot_de_passe'];

			if (!empty($tab_pers['mot_de_passe']))
				{
					$champs['gds'] = mb_substr(sha1(uniqid(rand(), true)), 0, 5);
					$champs['mot_de_passe'] = sha1($champs['gds'].sha1($tab_pers['mot_de_passe']));
				}

			$sql_update = "UPDATE personne SET mot_de_passe='".$champs['mot_de_passe']."', gds='".$champs['gds']."',
			statut='actif' WHERE idPersonne=".$req_id;

			//TEST
			//echo $sql_update;
			//

			//message résultat et réinit
			//PROD
			if ($connector->query($sql_update))
			{
				
			
	
				require_once "Mail.php";
				// Mail de notification au nouvel utilisateur

				
				$from = '"'."La décadanse".'" <'.$glo_email_info.'>';
				$to = '"'.$tab_pers['pseudo'].'" <'.$tab_pers['email'].'>';
				$subject = "Votre compte sur La décadanse";

				$contenu_message = "Bonjour,\n\n";
				$contenu_message .= "Merci de vous être inscrit-e sur www.ladecadanse.ch";
				$contenu_message .= "\n\n";
				$contenu_message .= "Voici vos données de connexion :";
				$contenu_message .= "\n";
				$contenu_message .= "\n";
				$contenu_message .= "Identifiant : ".$tab_pers['pseudo'];
				$contenu_message .= "\n";
				$contenu_message .= "Mot de passe : ".$pass_email;
				$contenu_message .= "\n\n";
				$contenu_message .= "Pour vous connecter : ".$url_site."login.php";
				$contenu_message .= "\n\n";
				$contenu_message .= "Vous pouvez compléter votre profil sur votre page de membre : ";
				$contenu_message .= $url_site."personne.php?idP=".$req_id;
				$contenu_message .= "\n\n";
				$contenu_message .= "Bonne visite";
				$contenu_message .= "\n\n";
				$contenu_message .= "La décadanse";
				
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
/* 				if ((new PEAR)->isError($mail))
				{	
					msgErreur('L\'envoi a echoué, veuillez contacter le webmaster');
					echo("<p>" . $mail->getMessage() . "</p>");
				}  */
				

				//email			
				
				// Mail de notification à l'admin


		
				/*
				$to = '"'."La décadanse".'" <'.$glo_email_info.'>';
				$subject = "[La décadanse] Nouvelle inscription d'un ".$type_compte;
				$contenu_message = "Une nouvelle inscription sur La décadanse";
				$contenu_message .= "\n\n";
				$contenu_message .= "Identifiant : ".$tab_pers['pseudo'];
				$contenu_message .= "\nE-mail : ".$tab_pers['email'];
				$contenu_message .= "\n\n";
				$contenu_message .= "Tableau de bord Admin : ".$url_admin;
				



				$headers = array (
				"Content-Type" => "text/plain; charset=\"UTF-8\"",				
				'From' => $from,
				'To' => $to,
				'Subject' => $subject);
				
				$smtp = Mail::factory('smtp',
				array (
				'host' => $glo_email_host,
				'auth' => true,
				'username' => $glo_email_username,
				'password' => $glo_email_password));

				$mail = $smtp->send($to, $headers, $contenu_message);

				// HACK : pear http://forum.revive-adserver.com/topic/1597-non-static-method-peariserror-should-not-be-called-statically/
				//if (PEAR::isError($mail)){
				if ((new PEAR)->isError($mail))
				{
					echo("<p>" . $mail->getMessage() . "</p>");
				}
				*/
				
				$compte_organisateur = "";
				if (isset($champs['organisateurs']) && count($champs['organisateurs']) > 0)
					$compte_organisateur = " en tant qu'organisateur";
				
				msgOk("<strong>Votre compte".$compte_organisateur." a été créé</strong>; vous pouvez maintenant vous <a href=\"".$url_site."login.php\">connecter</a> avec l'identifiant et le mot de passe que vous venez de saisir.
				<br />Un e-mail de confirmation récapitulant vos données d'accès vous a été envoyé à l'adresse : ".$tab_pers['email']);

			}
			
			
			foreach ($champs as $k => $v)
			{
				$champs[$k] = '';
			}


			$action_terminee = true;
		}
		else
		{
			msgErreur("La requête INSERT dans 'personne' a échoué");
		}

	} // if erreurs == 0
} // if POST != ""


if (!$action_terminee)
{

?>
<div id="entete_contenu">
<h2>Inscription à La décadanse</h2>
<div class="spacer"></div>
</div>

<?php
/*
 * PREPARATION DES URLS SELON LES ACTIONS,
 * update et idE en cas d'édition, insert pour ajout
 */

$act = 'insert';


if ($verif->nbErreurs() > 0)
{
	msgErreur("Il y a ".$verif->nbErreurs()." erreur(s).");
}
?>


<!-- FORMULAIRE -->

<form method="post" id="ajouter_editer" action="<?php echo $_SERVER['PHP_SELF']."?action=".$act; ?>" onsubmit="return validerAjouterPersonne();">

    <p>Avant de vous inscrire en tant que Organisateur, veillez svp à ce que les événements que vous souhaitez ajouter respectent notre <b><a href="charte-editoriale.php">charte&nbsp;éditoriale</a></b>.</p>

<p>* indique un champ obligatoire</p>

<fieldset>
<legend>Avec :</legend>

<!-- Pseudo* (text) -->
<p>
<label for="utilisateur">Identifiant*</label>

<input type="text" name="utilisateur" id="utilisateur" size="25" maxlength="80"
value="<?php echo htmlspecialchars($champs['utilisateur']) ?>" />
<?php
echo $verif->getHtmlErreur('utilisateur');
echo $verif->getHtmlErreur("utilisateur_existant");
?>
</p>
<!--<div class="guide_champ">Utilisé pour vous connecter</div>-->



<!-- Nouveau mot de passe* en cas de mise à jour -->
<p>
<label for="motdepasse">Mot de passe*</label>
<input type="password" name="motdepasse" id="motdepasse" size="16" maxlength="20" value="" />
<?php echo $verif->getHtmlErreur("motdepasse");?>
</p>

<!-- Nouveau mot de passe* à confirmation en cas de mise à jour -->
<p>
<label for="motdepasse2">Confirmer le mot de passe*</label>
<input type="password" name="motdepasse2" id="motdepasse2" size="16" maxlength="20" value="" />
<?php echo $verif->getHtmlErreur("motdepasse2");?>
</p>
<div class="guide_champ">Le mot de passe doit faire au minimum 6 caractères et comporter au moins un chiffre</div>
<?php echo $verif->getHtmlErreur("motdepasse_inegaux");?>

<!-- Email* (text) -->
<p>
<label for="email">E-mail*</label>
<input type="text" name="email" id="email" size="30" maxlength="80" value="<?php echo htmlspecialchars($champs['email']) ?>" onblur="validerEmail('email', true);"/>
<?php echo $verif->getHtmlErreur("email");
echo $verif->getHtmlErreur("email_identique");?>
</p>
</fieldset>



<fieldset>

	<legend>En tant que*</legend>
	<ul class="radio">
		<li class="listehoriz" style="float: left;display:block;">
			<label for="membre" style="float:left"><strong>Membre</strong><br>
			Pour écrire des commentaires et garder en favori des lieux et des événements
			
			</label><input type="radio" name="groupe" id="membre" value="12" 
			<?php if ($champs['groupe'] == 12) { echo ' checked'; } ?>
			/>
	
		</li>

		<li class="listehoriz" style="float: left;display:block;">
			<label for="inscription_organisateur" style="float:left"><strong>Organisateur</strong><br>Mêmes droits qu'un membre + possibilité d'ajouter des événements
			</label><input type="radio" name="groupe" id="inscription_organisateur" value="8" 
			<?php if ($champs['groupe'] == 8) { echo ' checked'; } ?>
			 />
		</li>
	</ul>
<div class="spacer"></div>
	<?php echo $verif->getHtmlErreur("groupe");?>

	
	<!-- Affiliation (text) -->
	<fieldset class="affiliation" id="inscription_references" >

		<legend>Affiliation</legend>
		<div class="guide_affiliation">Si vous avez choisi <em>Organisateur</em>, veuillez indiquer à quel groupe, assoc, etc. existant vous appartenez.</div>



		<p>
		<label for="lieu" class="affil">Lieu&nbsp;</label>
		<select name="lieu" id="lieu" class="chosen-select" data-placeholder="Choisir..."  style="max-width:350px">
		<?php

		echo "<option value=\"0\"></option>";
		$req_lieux = $connector->query("
		SELECT idLieu, nom FROM lieu WHERE actif=1 AND statut='actif' ORDER BY TRIM(LEADING 'L\'' FROM (TRIM(LEADING 'Les '
		FROM (TRIM(LEADING 'La ' FROM (TRIM(LEADING 'Le ' FROM nom))))))) COLLATE utf8_general_ci"
		 );
		while ($lieuTrouve = $connector->fetchArray($req_lieux))
		{
			echo "<option ";
			if ($lieuTrouve['idLieu'] == $champs['lieu'])
			{
				echo "selected=\"selected\" ";
			}
			echo "value=\"".$lieuTrouve['idLieu']."\">".$lieuTrouve['nom']."</option>";

		}
		?>

		</select>
		</p>
		
		<p class="entreLabels"><strong>ou</strong></p>
		<div class="spacer"></div>

		<p>
		<label class="affil">Organisateur&nbsp;</label>
		<select name="organisateurs[]" id="organisateurs" class="chosen-select" title="Un organisateur dans base de données de La décadanse" style="max-width:350px"  data-placeholder="Choisir...">
		<?php
		echo "<option value=\"0\"></option>";
		$req = $connector->query("
		SELECT idOrganisateur, nom FROM organisateur WHERE statut='actif' ORDER BY TRIM(LEADING 'L\'' FROM (TRIM(LEADING 'Les ' FROM (TRIM(LEADING 'La ' FROM (TRIM(LEADING 'Le ' FROM nom))))))) COLLATE utf8_general_ci"
		 );

		while ($tab = $connector->fetchArray($req))
		{
			echo "<option ";
			echo "value=\"".$tab['idOrganisateur']."\">".$tab['nom']."</option>";
		}
		?>
		</select>

		</p>		

<?php echo $verif->getHtmlErreur("doublon_organisateur"); ?>
		
		<p class="entreLabels"><strong>ou</strong></p>
		<div class="spacer"></div>


		<p>
		<label for="affiliation" class="affil">Nom&nbsp;</label>
		<input type="text" name="affiliation" id="affiliation" size="30" maxlength="80" value="<?php echo htmlspecialchars($champs['affiliation']); ?>" />
		</p>
		<?php echo $verif->getHtmlErreur("affiliation"); ?>




</fieldset>
</fieldset>
<?php /* ?>
<fieldset>

<legend>Antispam</legend>

<img src="librairies/freecap/freecap.php" id="freecap" alt="captcha" />
<p class="guide_captcha"><a href="#" onClick="this.blur();new_freecap();return false;">Générer un nouveau mot</a></p>
<div class="spacer"></div>
<label for="word">Veuillez copier le mot ci-dessus* :</label>
<input type="text" name="word" id="word" onblur="javascript:callServer();" onFocus="javascript:document.getElementById('captcha_result').innerHTML='';" />
<span id="captcha_result"></span>
<?php echo $verif->getHtmlErreur("freecap"); ?>

</fieldset>
<?php */ ?>

<p class="piedForm">
<input type="hidden" name="formulaire" value="ok" />
<input type="submit" value="S'inscrire" class="submit" />
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
