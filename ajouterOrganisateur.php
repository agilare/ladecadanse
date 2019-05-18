<?php
/**
 *
 * @category   modification d'une table de la base
 * @see organisateur.php
 * @author     Michel Gaudry <michel@ladecadanse.ch>
 */

if (is_file("config/reglages.php"))
{
	require_once("config/reglages.php");
}

require_once($rep_librairies."Sentry.php");
$videur = new Sentry();

if (!$videur->checkGroup(8))
{
	header("Location: index.php"); die();
}

require_once($rep_librairies."EditionOrganisateur.class.php");


$page_titre = "ajouter/éditer un organisateur";
$page_description = "ajouter/éditer un organisateur";
$extra_css = array("formulaires", "ajouterOrganisateur_formulaire", "organisateur_inc");
$extra_js = array("zebra_datepicker", "jquery.shiftcheckbox");


/*
* action choisie, ID si édition, val pour (dés)activer l'événement
* action "ajouter" par défaut
*/
$tab_actions = array("ajouter", "insert", "editer", "update");
$get['action'] = "ajouter";
$get['idO'] = "idO";
if (isset($_GET['action']))
{
	$get['action'] = verif_get($_GET['action'], "enum", "ajouter", $actions);
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
	if (!estAuteur($_SESSION['SidPersonne'], $get['idO'], "organisateur") && $_SESSION['Sgroupe'] > 8)
	{
		msgErreur("Vous ne pouvez pas modifier cet organisateur");
		exit;
	}
}


$champs = array('statut' => '', 'nom' => '', 'adresse' => '',  'telephone' => '', 'URL' => '',
 'email' => '', 'presentation' => '',  "date_ajout" => "");
$fichiers = array('logo' => '', 'photo' => '');
$supprimer = array('logo' => '', 'photo' => '');

$afficher_form = true;
$message_ok = '';
$form = new EditionOrganisateur('form', $champs, $fichiers, $get);

//TEST
//printr($_POST);
//

$form->setAction($get['action']);
if (isset($_POST['formulaire']) && $_POST['formulaire'] == 'ok')
{
	if ($form->traitement($_POST, $_FILES))
	{
		$_SESSION['organisateur_flash_msg'] = $form->getMessage();
        header("Location: organisateur.php?idO=".$form->id); die();
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

	if (estAuteur($_SESSION["SidPersonne"], $get['idO'], "organisateur") || $_SESSION['Sgroupe'] <= 8)
	{
		//Menu d'actions
		if ($_SESSION['Sgroupe'] < 2)
		{
			$titre_actions = '<ul class="entete_contenu_menu">';
			$titre_actions .= "<li class=\"action_supprimer\">
			<a href=\"".$url_site."supprimer.php?type=organisateur&amp;id=".$get['idO']."\">Supprimer</a></li>";
			$titre_actions .= '</ul>';
		}

	}
	else
	{
		msgErreur("Vous ne pouvez pas éditer cet élément");
		exit;
	}
}
else
{
	$act = 'ajouter';
	$titre_form = "Ajouter";
}

include("includes/header.inc.php");
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

<!-- FORMULAIRE POUR UN LIEU -->

<form  method="post" enctype="multipart/form-data" id="ajouter_editer" class="submit-freeze-wait" action="<?php echo basename(__FILE__)."?action=".$act; ?>">

<p>* indique un champ obligatoire</p>


<fieldset>
<input type="hidden" name="date_ajout" value="<?php echo $form->getValeur('date_ajout') ?>" />
<input type="hidden" name="idOrganisateur" value="<?php echo $get['idO'] ?>" />
<legend>Infos pratiques</legend>

<!-- Nom (text) -->
<p>
<label for="nom">Nom* :</label>
<input type="text" name="nom" id="nom" size="50" maxlength="80" value="<?php echo $form->getValeur('nom') ?>" required />
<?php
echo $form->getHtmlErreur("nom");
echo $form->getHtmlErreur("nom_existant");
?>
</p>


<!-- Adresse (text) -->
<p>
<label for="adresse">Adresse :</label>
<input type="text" name="adresse" id="adresse" size="50" maxlength="80" value="<?php echo $form->getValeur('adresse') ?>" />
<?php
echo $form->getHtmlErreur("adresse");
?>
</p>

<div class="spacer"></div>
<!-- Telephone (text) -->
<p>
<label for="telephone">Téléphone :</label>
<input type="text" name="telephone" id="telephone" size="20" maxlength="40"
title="Numéro de téléphone" value="<?php echo $form->getValeur('telephone') ?>" onblur="validerTelephone('telephone', 'false');" />
<?php
echo $form->getHtmlErreur("telephone");
?>
</p>

<!-- URL (text) -->
<p>
<label for="URL">Site web :</label>
<input type="text" name="URL" id="URL" size="50" maxlength="80"
value="<?php echo $form->getValeur('URL') ?>"  onblur="validerURL('URL', 'false');" />
<?php
echo $form->getHtmlErreur("URL");
?>
</p>

<p>
<label for="email">Email :</label>
<input type="text" name="email" id="email" size="40" maxlength="40"
 value="<?php echo $form->getValeur('email') ?>" onblur="validerEmail('email', 'false');" />
<?php
echo $form->getHtmlErreur("email");
?>
</p>
<p>
    <label for="presentation">Présentation :</label><br><br>
<textarea name="presentation" id="presentation" class="tinymce" rows="16" cols="50">
<?php echo $form->getValeur('presentation') ?></textarea>
<?php
echo $form->getHtmlErreur("presentation");
?>
</p>
</fieldset>


<fieldset>
<legend>Images</legend>
<input type="hidden" name="MAX_FILE_SIZE" value="2097152" /> <!-- 2 Mo -->
<div class="guideForm">Formats JPEG, PNG ou GIF, max. 2 Mo</div>
<!-- Logo (file) -->
<p>
<label for="Logo">Logo</label>
<input type="file" name="logo" id="Logo" tabindex="18" title="Logo qui s'affichera à gauche du titre" size="25" />

<?php
echo $form->getHtmlErreur("logo");


if (isset($get['idO']) && $form->getValeur('logo') != '' && $form->getErreur("logo") == '')
{

	$imgInfo = getimagesize($rep_images_organisateurs.$form->getValeur('logo'));

	$lien_popup = lien_popup($url_images_organisateurs.$form->getValeur('logo')."?".filemtime($rep_images_organisateurs.$form->getValeur('logo')), "Logo", $imgInfo[0]+20, $imgInfo[1]+20,
	"<img src=\"".$url_images_organisateurs."s_".$form->getValeur('logo')."?".filemtime($rep_images_organisateurs.$form->getValeur('logo'))."\" alt=\"Logo pour ".securise_string($form->getValeur('nom'))."\" />"
	);
	$checked = '';
	$tab_sup = $form->getSupprimer();
	if (in_array('logo', $tab_sup) && $form->getNbErreurs() > 0)
	{
		$checked = ' checked="checked"';
	}
	?>

	<input type="hidden" name="logo_existant" value="<?php echo $form->getValeur('logo'); ?>" />
	<div class="supImg">
	<?php echo $lien_popup; ?>
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
<input type="file" name="photo" id="photo" tabindex="16" title="Photo qui s'affichera en haut à droite" size="25" />


<?php
echo $form->getHtmlErreur("photo");

//affichage de l'image existante
if (isset($get['idO']) && $form->getValeur('photo') != '' && $form->getErreur("photo") == '')
{
	$imgInfo = getimagesize($rep_images_organisateurs.$form->getValeur('photo'));

	$lien_popup = lien_popup($url_images_organisateurs.$form->getValeur('photo')."?".filemtime($rep_images_organisateurs.$form->getValeur('photo')), "Photo", $imgInfo[0]+20, $imgInfo[1]+20,
	"<img src=\"".$url_images_organisateurs."s_".$form->getValeur('photo')."?".filemtime($rep_images_organisateurs.$form->getValeur('photo'))."\" alt=\"photo pour ".securise_string($form->getValeur('nom'))."\" />"
	);
	$checked = '';
	$tab_sup = $form->getSupprimer();
	if (in_array('photo', $tab_sup) && $form->getNbErreurs() > 0)
	{
		$checked = ' checked="checked"';
	}
	?>

	<input type="hidden" name="photo_existant" value="<?php echo $form->getValeur('photo'); ?>" />
	<div class="supImg">
	<?php echo $lien_popup; ?>
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
((estAuteur($_SESSION['SidPersonne'], $get['idO'], "organisateur") && $_SESSION['Sgroupe'] < 6) || $_SESSION['Sgroupe'] <= 4))
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
<input type="submit" value="Enregistrer" tabindex="20" title="Enregistrer le lieu" class="submit" />
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
