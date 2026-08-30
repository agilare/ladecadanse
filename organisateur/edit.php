<?php

require_once("../app/bootstrap.php");

use Ladecadanse\HtmlShrink;
use Ladecadanse\Organisateur;
use Ladecadanse\OrganisateurEdition;
use Ladecadanse\Security\SecurityToken;
use Ladecadanse\UserLevel;
use Ladecadanse\Utils\QueryParamValidator;

if (!$authorization->checkGroup(UserLevel::ACTOR))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
    header("Location: /user/login.php");
    die();
}

$tab_actions = ["ajouter", "insert", "editer", "update"];
$get = [
    'action' => QueryParamValidator::validateUrlQueryValue($_GET['action'] ?? 'ajouter', "enum", 'ajouter', $tab_actions),
    'idO' => (int) ($_GET['idO'] ?? 0),
];

$isEditMode = in_array($get['action'], ['editer', 'update'], true);

if ($isEditMode && $get['idO'] <= 0)
{
    HtmlShrink::msgErreur("Aucun organisateur n'est désigné");
    exit;
}

/*
 * Qui peut modifier la fiche : les mêmes que ceux à qui organisateur.php propose
 * le lien « Modifier cet organisateur », plus son auteur. Le contrôle admettait
 * jusqu'ici tout compte de niveau ACTOR, c'est-à-dire n'importe quel organisateur
 * sur la fiche de n'importe quel autre.
 */
$idPersonne = (int) ($_SESSION['SidPersonne'] ?? 0);
$peutEditer = $_SESSION['Sgroupe'] <= UserLevel::AUTHOR
    || ($_SESSION['Sgroupe'] <= UserLevel::ACTOR && $authorization->isPersonneInOrganisateur($idPersonne, $get['idO']))
    || $authorization->isAuthor("organisateur", $idPersonne, $get['idO']);

if ($isEditMode && !$peutEditer)
{
    HtmlShrink::msgErreur("Vous ne pouvez pas modifier cet organisateur");
    exit;
}

// Publier ou dépublier une fiche reste une décision de modération
$peutChangerStatut = $_SESSION['Sgroupe'] <= UserLevel::ADMIN;

$form = new OrganisateurEdition();
$form->setAction($get['action']);
$form->setIdPersonne($idPersonne);
$form->setStatutModifiable($peutChangerStatut);

if ($isEditMode)
{
    $form->setId($get['idO']);
}

$tokenError = false;
if (($_POST['formulaire'] ?? '') === 'ok')
{
    if (!SecurityToken::check($_POST['token'] ?? '', $_SESSION['token'] ?? ''))
    {
        $tokenError = true;
    }
    elseif ($form->traitement($_POST, $_FILES))
    {
        $_SESSION['organisateur_flash_msg'] = $form->getMessage();
        header("Location: /organisateur/organisateur.php?idO=" . (int) $form->id);
        die();
    }
    elseif (!$form->hasErrors())
    {
        HtmlShrink::msgErreur("La requête a échoué");
    }
}
elseif ($isEditMode)
{
    $form->loadValeurs($get['idO']);
}

$act = $isEditMode ? "update&idO=" . $get['idO'] : "insert";

$page_titre = $isEditMode ? "modifier un organisateur" : "ajouter un organisateur";
$extra_css = ["formulaires"];
include("../_header.inc.php");
?>

<main id="contenu" class="colonne">

    <header id="entete_contenu">
        <h1>
            <?php if ($isEditMode) : ?>
                Modifier l'organisateur
                <a href="/organisateur/organisateur.php?idO=<?= $get['idO'] ?>"><?= sanitizeForHtml($form->getNomEnBase()) ?></a>
            <?php else : ?>
                Ajouter un organisateur
            <?php endif; ?>
        </h1>
        <div class="spacer"></div>
    </header>

    <?php if ($tokenError) : ?>
        <?php HtmlShrink::msgErreur("Le système de sécurité du site n'a pu authentifier votre action. Veuillez réafficher ce formulaire et réessayer."); ?>
    <?php elseif ($form->hasErrors()) : ?>
        <?php HtmlShrink::msgErreur("Il y a " . $form->getErrorCount() . " erreur(s)."); ?>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="ajouter_editer" class="js-submit-freeze-wait" action="<?= basename(__FILE__) ?>?action=<?= sanitizeForHtml($act) ?>">

    <p>* indique un champ obligatoire</p>

    <fieldset>
        <legend>Identité</legend>

        <input type="hidden" name="MAX_FILE_SIZE" value="<?= UPLOAD_MAX_FILESIZE ?>" />

        <p>
            <label for="nom">Nom* :</label>
            <input type="text" name="nom" id="nom" size="50" maxlength="80" value="<?= sanitizeForHtml($form->getValeur('nom')) ?>" required />
            <?= $form->getHtmlErreur("nom") ?>
        </p>

        <?php
        $champImage = 'logo';
        $libelleImage = 'Logo';
        $titreImage = "Logo qui s'affichera à gauche du titre";
        include("_champ_image.inc.php");
        ?>
    </fieldset>

    <fieldset>
        <legend>Infos pratiques</legend>

        <p>
            <label for="adresse">Adresse :</label>
            <input type="text" name="adresse" id="adresse" size="50" maxlength="80" value="<?= sanitizeForHtml($form->getValeur('adresse')) ?>" />
            <?= $form->getHtmlErreur("adresse") ?>
        </p>

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
    </fieldset>

    <fieldset>
        <legend>Présentation</legend>

        <p>
            <label for="presentation">Texte :</label>
            <textarea name="presentation" id="presentation" class="tinymce" rows="12" cols="50"><?= sanitizeForHtml($form->getValeur('presentation')) ?></textarea>
            <?= $form->getHtmlErreur("presentation") ?>
        </p>
    </fieldset>

    <fieldset>
        <legend>Photo</legend>

        <?php
        $champImage = 'photo';
        $libelleImage = 'Photo';
        $titreImage = "Photo qui s'affichera en haut à droite";
        include("_champ_image.inc.php");
        ?>
    </fieldset>

    <?php if ($peutChangerStatut) : ?>
    <fieldset>
        <legend>Statut</legend>

        <ul class="radio mobile-vertical">
            <?php foreach (Organisateur::STATUTS as $valeur => $libelle) : ?>
                <li class="listehoriz">
                    <input type="radio" name="statut" value="<?= $valeur ?>" id="statut_<?= $valeur ?>" class="radio_horiz"
                        <?= $form->getValeur('statut') === $valeur ? 'checked="checked"' : '' ?> />
                    <label class="continu" for="statut_<?= $valeur ?>"><?= $libelle ?></label>
                </li>
            <?php endforeach; ?>
        </ul>
        <?= $form->getHtmlErreur("statut") ?>
    </fieldset>
    <?php endif; ?>

    <p class="piedForm">
        <input type="hidden" name="formulaire" value="ok" />
        <input type="hidden" name="token" value="<?= SecurityToken::getToken() ?>" />
        <input type="submit" value="Enregistrer" class="submit submit-big" />
    </p>

    </form>

</main>

<div id="colonne_gauche" class="colonne">
</div>

<div id="colonne_droite" class="colonne">
</div>

<?php
include("../_footer.inc.php");
