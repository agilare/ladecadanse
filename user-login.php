<?php

require_once("app/bootstrap.php");

use Ladecadanse\Utils\Validateur;
use Ladecadanse\HtmlShrink;
use Ladecadanse\UserLevel;

if ($videur->checkGroup(UserLevel::MEMBER)) {
	header("Location: index.php"); die();
}

$tab_messages = array('faux');

/**
* Valeur auto-reçue en cas d'échec dans la vérification du login
*/
if (isset($_GET['msg']))
{
	$get['msg'] = Validateur::validateUrlQueryValue($_GET['msg'], "enum", 1, $tab_messages);
}

$champs = array("pseudo" => "", "motdepasse" => "", "memoriser" => "", "origine" => "");

$pseudo = '';
$motdepasse = '';

$verif = new Validateur();

if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok')
{

	foreach ($champs as $c => $v)
	{
        if (isset($_POST[$c]))
        {
            $champs[$c] = $_POST[$c];
        }
	}


	$verif->valider($champs['pseudo'], "pseudo", "texte", 2, 50, 1);
	$verif->valider($champs['motdepasse'], "motdepasse", "texte", 4, 50, 1);

	if (!empty($champs['memoriser']) && $champs['memoriser'] != 1)
	{
		$verif->setErreur("memoriser", "Valeur fausse");
	}

	if (!empty($_POST['login_as']))
	{
		$verif->setErreur("login_as", "Veuillez laisser ce champ vide");
	}


	//Si le pseudo et le mot de passe sont au bon format
	if ($verif->nbErreurs() == 0)
	{

		$videur->checkLogin(
		$champs['pseudo'],
		$champs['motdepasse'],
		UserLevel::MEMBER,
                "/",
		'/user-login.php?msg=faux',
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
		$verif->setErreur("connexion", "Votre identifiant et/ou votre mot de passe ne sont pas corrects. Veuillez également vérifier que vous avez bien saisi votre <b>identifiant</b> (celui que vous avez choisi à l'inscription), qui est distinct de l'email");
	}

}

$page_titre = "Connexion";
$extra_css = array("formulaires", "login");
include("_header.inc.php");
?>

<div id="contenu" class="colonne">

<div id="entete_contenu">
<h2>Connexion</h2>
	<div class="spacer"></div>
</div>

<?php

if ($verif->nbErreurs() > 0)
{
	HtmlShrink::msgErreur("Il y a ".$verif->nbErreurs()." erreur(s)");
}

?>


<form id="ajouter_editer" action="/user-login.php" method="post">
<?php
echo $verif->getHtmlErreur("connexion");
?>

<fieldset>
<legend class="btn_toggle">Authentification</legend>
<p>
<label for="pseudo" id="login_pseudo">Identifiant</label>
<input type="text" name="pseudo" id="pseudo" value="<?php echo htmlspecialchars($champs['pseudo']) ?>" size="30" />
<div style="margin: 0 1em 1em 26em;font-size: 0.8em;line-height: 1.1em;padding: 0;">Celui que vous avez choisi à l'inscription</div>
<?php
echo $verif->getHtmlErreur("pseudo");
?>
<label for="motdepasse" id="login_motdepasse">Mot de Passe</label>
<input type="password" name="motdepasse" id="motdepasse" value="" size="30" />
<?php
echo $verif->getHtmlErreur("motdepasse");
?>


<p class="memoriser" id="login_memoriser">
<label for="memoriser">Se souvenir de moi</label>
<input type="checkbox" name="memoriser" id="memoriser" value="1" title="" />
</p>

<p class="mdp_oublie"><a href="/user-reset.php" >Mot de passe oublié ?</a></p>
<p class="mdp_oublie"><a href="/user-register.php" >Pas de compte ?</a></p>

<p class="piedForm">
    <input type="hidden" id="origine" name="origine" value="<?php echo $champs['origine'] ?>" />
    <input type="text" class="name_as" name="login_as"></span>
    <input type="hidden" name="formulaire" value="ok" />
    <input type="submit" name="Submit" value="Se connecter" class="submit submit-big" />
</p>

</fieldset>


</form>



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
