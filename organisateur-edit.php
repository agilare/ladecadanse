<?php

require_once("app/bootstrap.php");

use Ladecadanse\Security\SecurityToken;
use Ladecadanse\OrganisateurEdition;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\HtmlShrink;

if (!$videur->checkGroup(8))
{
	header("Location: index.php"); die();
}

$page_titre = "ajouter/éditer un organisateur";
$extra_css = ["formulaires"];

/*
* action choisie, ID si édition, val pour (dés)activer l'événement
* action "ajouter" par défaut
*/
$tab_actions = ["ajouter", "insert", "editer", "update"];
$get['action'] = "ajouter";
$get['idO'] = "idO";
if (isset($_GET['action']))
{
	$get['action'] = Validateur::validateUrlQueryValue($_GET['action'], "enum", "ajouter", $actions);
}

if (isset($_GET['idO']))
{
	$get['idO'] = (int)$_GET['idO'];
}

/* VERIFICATION POUR MODIFICATION
* Si ce n'est pas un ajout et que la personne n'est pas l'auteur ni admin ou 'auteur'
*/
if ($get['action'] != "ajouter" && $get['action'] != "insert")
{
	if (!$authorization->isAuthor("organisateur", $_SESSION['SidPersonne'], $get['idO']) && $_SESSION['Sgroupe'] > 8)
    {
		HtmlShrink::msgErreur("Vous ne pouvez pas modifier cet organisateur");
		exit;
	}
}


$champs = ['statut' => '', 'nom' => '', 'adresse' => '', 'URL' => '', 'email' => '', 'presentation' => '', "date_ajout" => ""];
$fichiers = ['logo' => '', 'photo' => ''];
$supprimer = ['logo' => '', 'photo' => ''];

$afficher_form = true;
$message_ok = '';
$form = new OrganisateurEdition('form', $champs, $fichiers);

//TEST
//printr($_POST);
//

$form->setAction($get['action']);
if (isset($_POST['formulaire']) && $_POST['formulaire'] == 'ok')
{
    if (!SecurityToken::check($_POST['token'], $_SESSION['token']))
    {
        echo "Le système de sécurité du site n'a pu authentifier votre action. Veuillez réafficher ce formulaire et réessayer";
        exit;
    }

	if ($form->traitement($_POST, $_FILES))
	{
		$_SESSION['organisateur_flash_msg'] = $form->getMessage();
        header("Location: /organisateur.php?idO=".$form->id); die();
	}
}
else if ($get['action'] == 'editer')
{
  	$form->loadValues($get['idO']);
}

$titre_form = "";
$titre_actions = '';

//menu d'actions (activation et suppression)  pour l'auteur > 6 ou l'admin
if (($get['action'] == 'editer' || $get['action'] == 'update'))
{
	$act = "editer&amp;idO=".$get['idO'];
	$titre_form = "Modifier";
	$nom_submit = "Modifier";

	if ($authorization->isAuthor("organisateur", $_SESSION["SidPersonne"], $get['idO']) || $_SESSION['Sgroupe'] <= 8)
    {
		//Menu d'actions
		if ($_SESSION['Sgroupe'] < 2)
		{
			$titre_actions = '<ul class="entete_contenu_menu">';
			$titre_actions .= "<li class=\"action_supprimer\">
			<a href=\"/multi-suppr.php?type=organisateur&amp;id=".(int)$get['idO']."&token=".SecurityToken::getToken()."\">Supprimer</a></li>";
			$titre_actions .= '</ul>';
		}

	}
	else
	{
		HtmlShrink::msgErreur("Vous ne pouvez pas éditer cet élément");
		exit;
	}
}
else
{
	$act = 'ajouter';
	$titre_form = "Ajouter";
}

include("_header.inc.php");
?>

<div id="contenu" class="colonne">
<div id="entete_contenu">
<h2><?php echo $titre_form; ?> un organisateur</h2>
<?php echo $titre_actions; ?>
<div class="spacer"></div>
</div>
<?php
if ($afficher_form)
{


?>

        <form  method="post" enctype="multipart/form-data" id="ajouter_editer" class="js-submit-freeze-wait" action="<?php echo basename(__FILE__)."?action=".$act; ?>">

<p>* indique un champ obligatoire</p>


<fieldset>
<input type="hidden" name="date_ajout" value="<?php echo $form->getValeur('date_ajout') ?>" />
<input type="hidden" name="idOrganisateur" value="<?php echo (int)$get['idO'] ?>" />
<legend>Infos pratiques</legend>

<!-- Nom (text) -->
<p>
<label for="nom">Nom* :</label>
    <input type="text" name="nom" id="nom" size="50" maxlength="80" value="<?php echo sanitizeForHtml($form->getValeur('nom')) ?>" required />
    <?php
echo $form->getHtmlErreur("nom");
echo $form->getHtmlErreur("nom_existant");
?>
</p>


<!-- Adresse (text) -->
<p>
<label for="adresse">Adresse :</label>
    <input type="text" name="adresse" id="adresse" size="50" maxlength="80" value="<?php echo sanitizeForHtml($form->getValeur('adresse')) ?>" />
    <?php
echo $form->getHtmlErreur("adresse");
?>
</p>

    <div class="spacer"></div>

<!-- URL (text) -->
<p>
<label for="URL">Site web :</label>
    <input type="text" name="URL" id="URL" size="50" maxlength="80" value="<?php echo sanitizeForHtml($form->getValeur('URL')) ?>" />
    <?php
echo $form->getHtmlErreur("URL");
?>
</p>

<p>
<label for="email">Email :</label>
<input type="text" name="email" id="email" size="40" maxlength="40"
       value="<?php echo sanitizeForHtml($form->getValeur('email')) ?>" />
    <?php
echo $form->getHtmlErreur("email");
?>
</p>
<p>
    <label for="presentation">Présentation :</label><br><br>
<textarea name="presentation" id="presentation" class="tinymce" rows="16" cols="50">
        <?php echo sanitizeForHtml($form->getValeur('presentation')) ?></textarea>
    <?php
echo $form->getHtmlErreur("presentation");
?>
</p>
</fieldset>


<fieldset>
<legend>Images</legend>
<input type="hidden" name="<?php echo UPLOAD_MAX_FILESIZE ?>" value="2097152" /> <!-- 2 Mo -->
<div class="guideForm">Formats JPEG, PNG ou GIF, max. 2 Mo</div>
<!-- Logo (file) -->
<p>
<label for="Logo">Logo</label>
<input type="file" name="logo" id="Logo" class="js-file-upload-size-max" tabindex="18" title="Logo qui s'affichera à gauche du titre" size="25" />

<?php
echo $form->getHtmlErreur("logo");


if (isset($get['idO']) && $form->getValeur('logo') != '' && $form->getErreur("logo") == '')
{

	$imgInfo = getimagesize($rep_uploads_organisateurs.$form->getValeur('logo'));

	$checked = '';
	$tab_sup = $form->getSupprimer();
	if (in_array('logo', $tab_sup) && $form->getNbErreurs() > 0)
	{
		$checked = ' checked="checked"';
	}
	?>

	<input type="hidden" name="logo_existant" value="<?php echo $form->getValeur('logo'); ?>" />
	<div class="supImg">
                <?php echo "<img src=\"" . $url_uploads_organisateurs . "s_" . $form->getValeur('logo') . "?" . filemtime($rep_uploads_organisateurs . $form->getValeur('logo')) . "\" alt=\"Logo pour " . sanitizeForHtml($form->getValeur('nom')) . "\" />"; ?>
                <div>
		<label for="supprimer_logo" class="continu">Supprimer</label>
		<input type="checkbox" name="supprimer[]" id="supprimer_logo" value="logo" class="checkbox" <?php echo $checked; ?> />

		</div>
	</div>

	<?php
}
?>
</p>


<!-- photo (file) -->
<p>
<label for="photo">Photo</label>
<input type="file" name="photo" id="photo" class="js-file-upload-size-max" tabindex="16" title="Photo qui s'affichera en haut à droite" size="25" />


<?php
echo $form->getHtmlErreur("photo");

//affichage de l'image existante
if (isset($get['idO']) && $form->getValeur('photo') != '' && $form->getErreur("photo") == '')
{
	$imgInfo = getimagesize($rep_uploads_organisateurs . $form->getValeur('photo'));
        $checked = '';
	$tab_sup = $form->getSupprimer();
	if (in_array('photo', $tab_sup) && $form->getNbErreurs() > 0)
	{
		$checked = ' checked="checked"';
	}
	?>

	<input type="hidden" name="photo_existant" value="<?php echo $form->getValeur('photo'); ?>" />
	<div class="supImg">
                <?php echo "<img src=\"" . $url_uploads_organisateurs . "s_" . $form->getValeur('photo') . "?" . filemtime($rep_uploads_organisateurs . $form->getValeur('photo')) . "\" alt=\"photo pour " . sanitizeForHtml($form->getValeur('nom')) . "\" />"; ?>
                <div>
		<label for="supprimer_photo" class="continu">Supprimer</label>
		<input type="checkbox" name="supprimer[]" id="supprimer_photo" value="photo" class="checkbox" <?php echo $checked; ?> />

		</div>
	</div>

	<?php
}
?>
</p>


</fieldset>


<fieldset>
<?php

//menu d'actions (activation et suppression)  pour l'auteur > 6 ou l'admin
if (($get['action'] == 'editer' || $get['action'] == 'update') &&
        (($authorization->isAuthor("organisateur", $_SESSION['SidPersonne'], $get['idO']) && $_SESSION['Sgroupe'] < 6) || $_SESSION['Sgroupe'] <= 4))
    {
?>


<legend>Statut</legend>
<ul class="radio">
<?php
foreach ($statuts_lieu as $s)
{
	$coche = '';
	$statut = $form->getValeur('statut');

	if ($s == $statut)
	{
		$coche = 'checked="checked"';
	}
	echo '<li class="listehoriz"><input type="radio" name="statut" value="'.$s.'" '.$coche.' id="genre_'.$s.'"  class="radio_horiz" /><label class="continu" for="genre_'.$s.'">'.$s.'</label></li>';
}
?>
</ul>
<?php
echo $form->getHtmlErreur("statut");
?>

<?php
}
else
{
?>

<input type="hidden" name="statut" value="actif" id="statut_actif" title="statut" />

<?php
}
?>
</fieldset>

<p class="piedForm">
<input type="hidden" name="formulaire" value="ok" />
<input type="hidden" name="token" value="<?php echo SecurityToken::getToken(); ?>" />
<input type="submit" value="Enregistrer" tabindex="20" class="submit submit-big" />
</p>
</form>

<?php
} // if action_terminee
?>


</div>
<!-- fin contenu  -->

<div id="colonne_gauche" class="colonne">

<?php include("event/_navigation_calendrier.inc.php"); ?>
</div>
<!-- Fin Colonne gauche -->

<div id="colonne_droite" class="colonne">
</div>

<?php
include("_footer.inc.php");
?>
