<?php
/**
 * Vérifie les entrées d'un formulaire de login
 *
 *
 * @category   affichage
 * @author     Michel Gaudry <michel@ladecadanse.ch>
 */
if (is_file("config/reglages.php"))
{
	require_once("config/reglages.php");
}

require_once($rep_librairies."Sentry.php");
$videur = new Sentry();

if ($videur->checkGroup(12))
{
	header("Location: index.php"); die();
}

include("librairies/Validateur.php");

$tab_messages = array('faux');

/**
* Valeur auto-reçue en cas d'échec dans la vérification du login
*/
if (isset($_GET['msg']))
{
	$get['msg'] = verif_get($_GET['msg'], "enum", 1, $tab_messages);
}

$champs = array("pseudo" => "", "motdepasse" => "", "memoriser" => "", "origine" => "");

$pseudo = '';
$motdepasse = '';
$origine = $url_site;

if (isset($_GET['origine']))
{
	$champs['origine'] = verif_get($_GET['origine'], "string", 1);
}


//TEST
//printr($_POST);
//

$verif = new Validateur();

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


	$verif->valider($champs['pseudo'], "pseudo", "texte", 2, 50, 1);
	$verif->valider($champs['motdepasse'], "motdepasse", "texte", 4, 50, 1);
	//$verif->valider($champs['origine'], "origine", "url", 10, 800, 1);

	if (!empty($champs['memoriser']) && $champs['memoriser'] != 1)
	{
		$verif->setErreur("memoriser", "Valeur fausse");
	}



	//Si le pseudo et le mot de passe sont au bon format
	if ($verif->nbErreurs() == 0)
	{

		if (isset($_SESSION['origine']))
		{
			$origine = $_SESSION['origine'];
		}
		
		if (strstr($origine, "inscription.php") || strstr($origine, "motdepasse_reset.php") || strstr($origine, "login.php"))
			$origine = $url_domaine.$url_site;
		


		$videur->checkLogin(
		$champs['pseudo'],
		$champs['motdepasse'],
		12,
		$origine,
		$url_site.'login.php?msg=faux',
		$champs['memoriser']
		);
	}

//$_SERVER['PHP_SELF'].'?msg=faux'
//si le formulaire n'a pas été validé, ou les valeurs entrées sont fausses
}
else
{

	if (!empty($get['msg']) && $get['msg'] == "faux")
	{
		$verif->setErreur("connexion", "Les valeurs de connexion que vous avez entrées sont fausses.");
	}

}

$nom_page = "login";
$page_titre = "connexion";
$page_description = "Formulaire de connexion pour les membres";
$extra_css = array("formulaires", "login");
include("includes/header.inc.php");


?>




<!-- D?t Contenu -->
<div id="contenu" class="colonne">

<div id="entete_contenu">
<h2>Connexion à La décadanse</h2>
	<div class="spacer"></div>
</div>

<?php

if ($verif->nbErreurs() > 0)
{
	msgErreur("Il y a ".$verif->nbErreurs()." erreur(s)");
}

?>


<form id="ajouter_editer" action="login.php" method="post">
<?php

echo $verif->getHtmlErreur("connexion");

if (isset($_SERVER['HTTP_REFERER']) && strstr($_SERVER['HTTP_REFERER'], $url_site))
{
	if (strstr($_SERVER['HTTP_REFERER'], "login.php") == false)
	{
		$_SESSION['origine'] = $_SERVER['HTTP_REFERER'];
	}
}
else
{
	$_SESSION['origine'] = $url_site;
}

//TEST
//echo "origine : ".$_SESSION['origine'];
//
?>

<fieldset>
<legend class="btn_toggle">Authentification</legend>
<p>
<label for="pseudo" id="login_pseudo">Identifiant</label>
<input type="text" name="pseudo" id="pseudo" value="<?php echo htmlspecialchars($champs['pseudo']) ?>" size="15" title="Veuillez entrer votre identifiant" />
<?php
echo $verif->getHtmlErreur("pseudo");
?>
</p>
<p>
<label for="motdepasse" id="login_motdepasse">Mot de Passe</label>
<input type="password" name="motdepasse" id="motdepasse" value="" size="15" title="Veuillez entrer votre mot de passe" />
<?php

echo $verif->getHtmlErreur("motdepasse");
?>
</p>

<p class="memoriser" id="login_memoriser">
<label for="memoriser">Se souvenir de moi</label>
<input type="checkbox" name="memoriser" id="memoriser" value="1" title="" />
<?php
//echo $verif->getHtmlErreur("memoriser");
?>
</p>

<!--<p style="width:50%;margin-left:10em;">Mot de passe oublié ? <a href="contacteznous.php" >Contactez-nous</a> en nous indiquant si possible votre <b>identifiant</b></p>-->
<p class="mdp_oublie"><a href="motdepasse_demande.php" >Mot de passe oublié ?</a></p>

<p class="piedForm">
<input type="hidden" id="origine" name="origine" value="<?php echo $champs['origine'] ?>" />
<input type="hidden" name="formulaire" value="ok" />
<input type="submit" name="Submit" value="Se connecter" class="submit" />
</p>

</fieldset>


</form>



</div>
<!-- fin  -->
<div id="colonne_gauche" class="colonne">
<?php
include("includes/navigation_calendrier.inc.php");
?>
</div>
<!-- Fin Colonne gauche -->

<div id="colonne_droite" class="colonne">

</div>


<?php
include("includes/footer.inc.php");
?>
