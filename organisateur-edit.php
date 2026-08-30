<?php

require_once("app/bootstrap.php");

use Ladecadanse\Organisateur;
use Ladecadanse\Security\SecurityToken;
use Ladecadanse\OrganisateurEdition;
use Ladecadanse\Utils\QueryParamValidator;
use Ladecadanse\HtmlShrink;

if (!$authorization->checkGroup(8))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
	header("Location: /user/login.php");
    die();
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
	$get['action'] = QueryParamValidator::validateUrlQueryValue($_GET['action'], "enum", "ajouter", $actions);
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
        header("Location: /organisateur/organisateur.php?idO=".$form->id); die();
	}
}
else if ($get['action'] == 'editer')
{
  	$form->loadValues($get['idO']);
}

$titre_form = "";

//menu d'actions (activation et suppression)  pour l'auteur > 6 ou l'admin
if (($get['action'] == 'editer' || $get['action'] == 'update'))
{
	$act = "editer&idO=".$get['idO'];
	$titre_form = "Modifier";
	$nom_submit = "Modifier";

	if ($authorization->isAuthor("organisateur", $_SESSION["SidPersonne"], $get['idO']) || $_SESSION['Sgroupe'] <= 8)
    {
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

<main id="contenu" class="colonne">

    <header id="entete_contenu">
        <h1><?= $titre_form; ?> un organisateur</h1>
        <div class="spacer"></div>
    </header>

    <?php if ($afficher_form) { ?>

    <form  method="post" enctype="multipart/form-data" id="ajouter_editer" class="js-submit-freeze-wait" action="<?= basename(__FILE__)."?action=". sanitizeForHtml($act); ?>">

    <p>* indique un champ obligatoire</p>

    <fieldset>

        <input type="hidden" name="date_ajout" value="<?= $form->getValeur('date_ajout') ?>" />
        <input type="hidden" name="idOrganisateur" value="<?= (int)$get['idO'] ?>" />
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= UPLOAD_MAX_FILESIZE ?>" />

        <legend>Identité</legend>

        <p>
            <label for="nom">Nom* :</label>
            <input type="text" name="nom" id="nom" size="50" maxlength="80" value="<?= sanitizeForHtml($form->getValeur('nom')) ?>" required />
            <?php
            echo $form->getHtmlErreur("nom");
            echo $form->getHtmlErreur("nom_existant");
            ?>
        </p>



        <label for="Logo">Logo<div class="guideForm">Formats JPEG, PNG, GIF ou WebP, max. 5 Mo</div></label>
        <input type="file" name="logo" id="Logo" class="js-file-upload-size-max" tabindex="18" title="Logo qui s'affichera à gauche du titre" size="25" accept="image/jpeg,image/pjpeg,image/png,image/x-png,image/gif,image/webp" />
        <?= $form->getHtmlErreur("logo") ?>

        <?php

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

            <input type="hidden" name="logo_existant" value="<?= $form->getValeur('logo'); ?>" />
            <div class="supImg">
                <?= "<img src=\"" . $assets->get(Organisateur::getAssetPath(Organisateur::getFilePath($form->getValeur('logo')))) . "\" alt=\"Logo pour " . sanitizeForHtml($form->getValeur('nom')) . "\" />"; ?>
                <div>
                    <label for="supprimer_logo" class="continu">Supprimer</label>
                    <input type="checkbox" name="supprimer[]" id="supprimer_logo" value="logo" class="checkbox" <?= $checked; ?> />
                </div>
            </div>

            <?php
        }
        ?>
    </fieldset>

    <legend>Infos pratiques</legend>
        <p>
            <label for="adresse">Adresse :</label>
            <input type="text" name="adresse" id="adresse" size="50" maxlength="80" value="<?= sanitizeForHtml($form->getValeur('adresse')) ?>" />
            <?= $form->getHtmlErreur("adresse") ?>
        </p>

        <div class="spacer"></div>

        <p>
            <label for="URL">Site web :</label>
            <input type="url" name="URL" id="URL" size="50" maxlength="100" value="<?= sanitizeForHtml($form->getValeur('URL')) ?>" />
            <?= $form->getHtmlErreur("URL") ?>
        </p>

        <p>
            <label for="email">Email :</label>
            <input type="email" name="email" id="email" size="40" maxlength="100" value="<?= sanitizeForHtml($form->getValeur('email')) ?>" />
            <?= $form->getHtmlErreur("email") ?>
        </p>

        <p>
            <label for="presentation">Présentation :</label><br><br>
            <textarea name="presentation" id="presentation" class="tinymce" rows="12" cols="50"><?= sanitizeForHtml($form->getValeur('presentation')) ?></textarea>
            <?= $form->getHtmlErreur("presentation"); ?>
        </p>

    </fieldset>

    <fieldset>

        <legend>Photo</legend>


        <label for="photo">Envoyer</label>
        <input type="file" name="photo" id="photo" class="js-file-upload-size-max" tabindex="16" title="Photo qui s'affichera en haut à droite" size="25" accept="image/jpeg,image/pjpeg,image/png,image/x-png,image/gif,image/webp" />
        <?= $form->getHtmlErreur("photo") ?>

        <?php
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

            <input type="hidden" name="photo_existant" value="<?= $form->getValeur('photo'); ?>" />
            <div class="supImg">
                <?= "<img src=\"" . $assets->get(Organisateur::getAssetPath(Organisateur::getFilePath($form->getValeur('photo')))) . "\" alt=\"photo pour " . sanitizeForHtml($form->getValeur('nom')) . "\" />"; ?>
                <div>
                    <label for="supprimer_photo" class="continu">Supprimer</label>
                    <input type="checkbox" name="supprimer[]" id="supprimer_photo" value="photo" class="checkbox" <?= $checked; ?> />
                </div>
            </div>
        <?php } ?>

    </fieldset>

    <fieldset>
        <?php
        if (($get['action'] == 'editer' || $get['action'] == 'update') &&
            (($authorization->isAuthor("organisateur", $_SESSION['SidPersonne'], $get['idO']) && $_SESSION['Sgroupe'] < 6) || $_SESSION['Sgroupe'] <= 4)
            )
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
        <?= $form->getHtmlErreur("statut") ?>

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
        <input type="hidden" name="token" value="<?= SecurityToken::getToken(); ?>" />
        <input type="submit" value="Enregistrer" tabindex="20" class="submit submit-big" />
    </p>

</form>

<?php
} // if action_terminee
?>

</main>

<div id="colonne_gauche" class="colonne">
</div>

<div id="colonne_droite" class="colonne">
</div>

<?php
include("_footer.inc.php");
?>
