<?php

if (is_file("config/reglages.php"))
{
	require_once("config/reglages.php");
}

use Ladecadanse\Security\Sentry;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\Logger;
use Ladecadanse\HtmlShrink;

$videur = new Sentry();

if ($videur->checkGroup(12))
{
	header("Location: index.php"); die();
}

require_once($rep_librairies.'Validateur.php');

//$cache_lieux = $rep_cache."lieux/";
$nom_page = "motdepasse_reset";
$page_titre = "Réinitialisation du mot de passe";
$page_description = "";
$extra_css = ["formulaires"];
include("_header.inc.php");



if (isset($_GET['token']))
{
	$get['token'] = Validateur::validateUrlQueryValue($_GET['token'], "alpha_numeric", 1);
}
else
{
	HtmlShrink::msgErreur("token obligatoire");
	exit;
}


$verif = new Validateur();

$champs = ["idPersonne" => '',
"motdepasse" => '',
"motdepasse2" => ''
];

$action_terminee = false;
?>

<!-- Deb Contenu -->
<div id="contenu" class="colonne">
		
<div id="entete_contenu">
<h2>Réinitialisation du mot de passe</h2>
<div class="spacer"></div>
</div>


<?php
// vérification en tous temps; un seul enregistrement accepté

$sql_temp = "SELECT * FROM temp WHERE token='".$connector->sanitize($get['token'])."' AND expiration > NOW()";
//echo $sql_temp;
$req_temp = $connector->query($sql_temp);


if ($connector->getNumRows($req_temp) == 1)
{
	$tab_temp = $connector->fetchArray($req_temp);

	if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok'  && empty($_POST['as_nom']))
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
		
		if (empty($tab_temp['idPersonne']) && empty($champs['idPersonne']))
		{
			$verif->setErreur("motdepasse", "Veuillez choisir un compte");			
		}
		
		// si l'email avait été fourni, vérification qu'il correspond bien au compte choisi
		if (!empty($tab_temp['email']) && !empty($champs['idPersonne']))
		{		
			// retrouver user dans personne à partir de la demande
			$sql_auth = "SELECT pseudo, idPersonne FROM personne WHERE ";
			
			$tab_auth_where = [];	
	
			$tab_auth_where[] = " email='".$connector->sanitize($tab_temp['email'])."' ";
			// si le demandeur a choisi un compte parmi plusieurs qui ont son email : et l'id du compte correspondant
			$tab_auth_where[] = " idPersonne='".$connector->sanitize($champs['idPersonne'])."' ";

			$sql_auth .= implode(" AND ", $tab_auth_where);
		
			//echo $sql_auth;
		
			$req_auth = $connector->query($sql_auth);	
			
			// on doit obtenir un seul compte
			if ($connector->getNumRows($req_auth) != 1)
			{
				$verif->setErreur("auth", "Vous n'êtes pas autorisé à modifier ce compte");
			}				
			else
			{
				// un seul row donc
				$tab_auth = $connector->fetchArray($req_auth);	
			}

	
		}	

		$idPersonne = '';
		
		if (!empty($tab_temp['idPersonne']))
		{
			$idPersonne = $tab_temp['idPersonne'];
		}			
		else if (!empty($tab_temp['email']))
		{
			$idPersonne = $tab_auth['idPersonne'];		
		}
		else
		{
			$verif->setErreur("motdepasse", "L'utilisateur n'a pu être retrouvé");			
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

		/*
		 * PAS D'ERREUR
		 */
		if ($verif->nbErreurs() === 0)
		{
			$champs['gds'] = mb_substr(sha1(uniqid(rand(), true)), 0, 5);
			$champs['motdepasse'] = sha1($champs['gds'].sha1($champs['motdepasse']));


			$sql_update = "UPDATE personne SET mot_de_passe='".$connector->sanitize($champs['motdepasse'])."', gds='".$connector->sanitize($champs['gds'])."', date_derniere_modif=NOW() WHERE idPersonne=".$idPersonne;

			//TEST
			//echo "<p>".$sql_update."</p>";
			//
			
			$sql_delete = "DELETE FROM temp WHERE token='".$connector->sanitize($get['token'])."'";

			
			if ($connector->query($sql_update) && $connector->query($sql_delete))
			{
				HtmlShrink::msgOk("Le mot de passe a été mis à jour, vous pouvez maintenant vous <a href='login.php'>connecter</a> avec votre identifiant et ce nouveau mot de passe");
                $logger->log('global', 'activity', "[motdepasse_reset] success by user idP ".$idPersonne, Logger::GRAN_YEAR);                
			}
			else
			{
				HtmlShrink::msgErreur("La requête UPDATE a échoué, veuillez contacter le webmaster");
			}
			
			$action_terminee = true;
			

		} // if erreurs == 0
	} // if POST != ""

	// si l'email avait été fourni, retrouve le(s) compte(s) associé(s)
	$tab_comptes = [];
	
	if (empty($tab_temp['idPersonne']) && !empty($tab_temp['email']))
	{
		$sql_comptes = "SELECT * FROM personne WHERE email ='".$connector->sanitize($tab_temp['email'])."' AND actif='1'";
		//echo $sql_comptes;
		$req_comptes = $connector->query($sql_comptes);

		if ($connector->getNumRows($req_comptes) > 0)		
		{
			
			while ($tab_compte = $connector->fetchArray($req_comptes))
			{
				$tab_comptes[] = $tab_compte;
			}
		}
	}

	?>


<?php
if (!$action_terminee)
{

$act = 'insert';


if ($verif->nbErreurs() > 0)
{
	HtmlShrink::msgErreur("Il y a ".$verif->nbErreurs()." erreur(s).");
	//echo $verif->listErreurs();
}
?>


<!-- FORMULAIRE -->

<form method="post" id="ajouter_editer"  class="submit-freeze-wait" action="<?php echo $_SERVER['PHP_SELF']."?token=".$get['token'] ?>">


<fieldset>
<span class="mr_as">
	<label for="mr_as">Ne pas remplir ce champ</label><input name="as_nom" id="as_nom" type="text">
</span>
<?php
if (count($tab_comptes) > 1) {
?>
<p>
<label for="idPersonne"  class="large">Lequel de votre compte ?</label>
<select name="idPersonne" id="idPersonne" >

	<?php
	foreach ($tab_comptes as $c)
	{
		$selected = '';
		if (isset($_POST['idPersonne']) && $c['idPersonne'] == $_POST['idPersonne'])
			$selected = " selected";
	?>

	<option value="<?php echo $c['idPersonne']; ?>" <?php echo $selected; ?>><?php echo $c['pseudo']; ?></option>

	<?php } ?>
</select>
<?php echo $verif->getHtmlErreur("auth");?>
</p>
<?php 
} 
else if (count($tab_comptes) == 1)
{
?>
<input type="hidden" name="idPersonne" value="<?php echo $tab_comptes[0]['idPersonne']; ?>" />

<?php	
}
?>

<!-- Nouveau mot de passe* en cas de mise à jour -->
<p>
<label for="motdepasse" class="large">Nouveau mot de passe</label>
<input type="password" name="motdepasse" id="motdepasse" size="20" maxlength="20" value="" />
<?php echo $verif->getHtmlErreur("motdepasse");?>
</p>

<!-- Nouveau mot de passe* à confirmation en cas de mise à jour -->
<p>
<label for="motdepasse2" class="large">Confirmer le nouveau mot de passe</label>
<input type="password" name="motdepasse2" id="motdepasse2" size="20" maxlength="20" value="" />
<?php echo $verif->getHtmlErreur("motdepasse2");?>
</p>
<div class="guideChamp">Le mot de passe doit faire au minimum 6 caractères et comporter au moins un chiffre</div>
<?php echo $verif->getHtmlErreur("motdepasse_inegaux");?>

</fieldset>


<p class="piedForm">
<input type="hidden" name="formulaire" value="ok" />
<input type="submit" value="Envoyer" class="submit" />
</p>

</form>		
		
<?php
} // if action

}
else
{
	HtmlShrink::msgErreur("Cette demande n'est pas valable");

}

?>




</div>
<!-- #contenu -->

<div id="colonne_gauche" class="colonne">

<?php include("_navigation_calendrier.inc.php"); ?>
</div>
<!-- Fin Colonne gauche -->

<?php
include("_footer.inc.php");
?>
