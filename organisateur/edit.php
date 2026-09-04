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

// 1sts : intention, 2nds : intention validated
$allowed_actions = ["ajouter", "insert", "editer", "update"];
$get = [
    'action' => QueryParamValidator::validateUrlQueryValue($_GET['action'] ?? 'ajouter', "enum", 'ajouter', $allowed_actions),
    'idO' => (int) ($_GET['idO'] ?? 0),
];

$is_edit_mode = in_array($get['action'], ['editer', 'update'], true);

/*
 * Les deux refus se rendent dans la page du site, avec le statut HTTP qui va avec :
 * un message HTML nu au-dessus d'une page vide n'offrait ni retour à l'accueil, ni
 * au client le moyen de distinguer un refus d'une réponse normale.
 */
$http_error = null;

if ($is_edit_mode && $get['idO'] <= 0)
{
    $http_error = [400, 'Bad Request', "Aucun organisateur n'est désigné"];
}
elseif ($is_edit_mode && !$authorization->isPersonneAllowedToEditOrganisateur($_SESSION, $get['idO']))
{
    $http_error = [403, 'Forbidden', "Vous ne pouvez pas modifier cet organisateur"];
}

// Publier ou dépublier une fiche reste une décision de modération
$can_change_status = $_SESSION['Sgroupe'] <= UserLevel::ADMIN;
$is_form_submitted = isset($_POST['form_submitted']);

$organisateur_form = new OrganisateurEdition();
$organisateur_form->setAction($get['action']);
$organisateur_form->setAuthorId((int) ($_SESSION['SidPersonne'] ?? 0));
$organisateur_form->setStatusEditable($can_change_status);

if ($http_error === null && $is_edit_mode)
{
    $organisateur_form->setIdOrganisateur($get['idO']);

    /*
     * À l'affichage la fiche remplit le formulaire ; à la soumission on vérifie seulement
     * qu'elle existe encore, la recharger écraserait la saisie en cours. Sans ce contrôle,
     * un identifiant inconnu rendait un formulaire vide sous un titre sans nom, et l'UPDATE
     * qui suivait ne touchait aucune ligne en annonçant une réussite.
     */
    $fiche_existe = $is_form_submitted
        ? $organisateur_form->ficheExiste()
        : $organisateur_form->loadValeurs($get['idO']);

    if (!$fiche_existe)
    {
        $http_error = [404, 'Not Found', "Cet organisateur n'existe pas ou plus"];
    }
}

if ($http_error !== null)
{
    [$status_code, $status_reason, $error_message] = $http_error;

    header($_SERVER["SERVER_PROTOCOL"] . " $status_code $status_reason");
    $page_titre = "erreur $status_code";
    include("../_header.inc.php");
    HtmlShrink::msgErreur($error_message);
    include("../_footer.inc.php");
    exit;
}

$token_error = false;
if ($is_form_submitted)
{
    if (!SecurityToken::check($_POST['token'] ?? '', $_SESSION['token'] ?? ''))
    {
        $token_error = true;
    }
    elseif ($organisateur_form->traitement($_POST, $_FILES))
    {
        $_SESSION['organisateur_flash_msg'] = $organisateur_form->getMessage();
        header("Location: /organisateur/organisateur.php?idO=" . $organisateur_form->getIdOrganisateur());
        die();
    }
    elseif (!$organisateur_form->hasErrors())
    {
        // La saisie est valide et l'enregistrement a pourtant échoué : la base est
        // hors d'état, ce dont l'auteur du formulaire ne peut rien faire.
        throw new RuntimeException("L'enregistrement de l'organisateur a échoué sans erreur de validation");
    }
}

$form_url_parameters = $is_edit_mode ? "update&idO=" . $get['idO'] : "insert";

$page_titre = $is_edit_mode ? "modifier un organisateur" : "ajouter un organisateur";
$extra_css = ["formulaires"];
include("../_header.inc.php");
?>

<main id="contenu" class="colonne">

    <header id="entete_contenu">
        <h1>
            <?php if ($is_edit_mode) : ?>
                Modifier l'organisateur
                <a href="/organisateur/organisateur.php?idO=<?= $get['idO'] ?>"><?= sanitizeForHtml($organisateur_form->getStoredName()) ?></a>
            <?php else : ?>
                Ajouter un organisateur
            <?php endif; ?>
        </h1>
        <div class="spacer"></div>
    </header>

    <?php if ($token_error) : ?>
        <?php HtmlShrink::msgErreur("Le système de sécurité du site n'a pu authentifier votre action. Veuillez réafficher ce formulaire et réessayer"); ?>
    <?php elseif ($organisateur_form->hasErrors()) : ?>
        <?php HtmlShrink::msgErreur("Il y a " . $organisateur_form->getErrorCount() . " erreur(s)"); ?>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="ajouter_editer" class="js-submit-freeze-wait" action="<?= basename(__FILE__) ?>?action=<?= sanitizeForHtml($form_url_parameters) ?>">

    <p>* indique un champ obligatoire</p>

    <fieldset>
        <legend>Identité</legend>

        <input type="hidden" name="MAX_FILE_SIZE" value="<?= UPLOAD_MAX_FILESIZE ?>" />

        <p>
            <label for="nom">Nom*</label>
            <input type="text" name="nom" id="nom" size="50" maxlength="<?= Organisateur::FIELDS['nom']['max'] ?>" value="<?= sanitizeForHtml($organisateur_form->getValeur('nom')) ?>" required />
            <?= $organisateur_form->getHtmlErreur("nom") ?>
        </p>

        <?php
        $image_form = $organisateur_form;
        $image_entity = Organisateur::class;
        $image_field = 'logo';
        $image_label = 'Logo';
        $image_title = "Logo qui s'affichera à droite du titre";
        include("../_champ_image.inc.php");
        ?>
    </fieldset>

    <fieldset>
        <legend>Infos pratiques</legend>

        <p>
            <label for="adresse">Adresse</label>
            <input type="text" name="adresse" id="adresse" size="50" maxlength="<?= Organisateur::FIELDS['adresse']['max'] ?>" value="<?= sanitizeForHtml($organisateur_form->getValeur('adresse')) ?>" />
            <?= $organisateur_form->getHtmlErreur("adresse") ?>
        </p>

        <p>
            <label for="URL">Site web</label>
            <input type="url" name="URL" id="URL" size="50" maxlength="<?= Organisateur::FIELDS['URL']['max'] ?>" value="<?= sanitizeForHtml($organisateur_form->getValeur('URL')) ?>" />
            <?= $organisateur_form->getHtmlErreur("URL") ?>
        </p>

        <p>
            <label for="email">Email</label>
            <input type="email" name="email" id="email" size="40" maxlength="<?= Organisateur::FIELDS['email']['max'] ?>" value="<?= sanitizeForHtml($organisateur_form->getValeur('email')) ?>" />
            <?= $organisateur_form->getHtmlErreur("email") ?>
        </p>
    </fieldset>

    <fieldset>
        <legend>Présentation</legend>

        <p>
            <label for="presentation">Texte</label>
            <?php /* TinyMCE remplace le textarea par un bloc à lui, dont la police revient à 16px :
                     une marge en em posée dessus ne vaudrait pas celle des autres champs. D'où ce
                     conteneur, qui garde la taille de police du formulaire — voir edit.css */ ?>
            <?php /* pas de maxlength ici : TinyMCE remplace le textarea, l'attribut ne
                     s'appliquerait donc à rien, et le HTML qu'il produit n'a pas la longueur
                     du texte saisi. Seule Organisateur::FIELDS['presentation'] tranche. */ ?>
            <span class="champ-riche">
                <textarea name="presentation" id="presentation" class="tinymce" rows="10" cols="50"><?= sanitizeForHtml($organisateur_form->getValeur('presentation')) ?></textarea>
            </span>
            <?= $organisateur_form->getHtmlErreur("presentation") ?>
        </p>
    </fieldset>

    <fieldset>
        <legend>Photo</legend>

        <?php
        $image_form = $organisateur_form;
        $image_entity = Organisateur::class;
        $image_field = 'photo';
        $image_label = 'Photo';
        $image_title = "Photo qui s'affichera en haut à droite";
        include("../_champ_image.inc.php");
        ?>
    </fieldset>

    <?php if ($can_change_status) : ?>
    <fieldset>
        <legend>Statut</legend>

        <ul class="radio mobile-vertical">
            <?php foreach (Organisateur::STATUTS as $status_value => $status_label) : ?>
                <li class="listehoriz">
                    <input type="radio" name="statut" value="<?= $status_value ?>" id="statut_<?= $status_value ?>" class="radio_horiz"
                        <?= $organisateur_form->getValeur('statut') === $status_value ? 'checked="checked"' : '' ?> />
                    <label class="continu" for="statut_<?= $status_value ?>"><?= $status_label ?></label>
                </li>
            <?php endforeach; ?>
        </ul>
        <?= $organisateur_form->getHtmlErreur("statut") ?>
    </fieldset>
    <?php endif; ?>

    <p class="piedForm">
        <?php /* Témoin de soumission : le bouton est désactivé par js-submit-freeze-wait, son
                 nom ne part donc pas. Les autres formulaires du site le nomment « formulaire ». */ ?>
        <input type="hidden" name="form_submitted" value="1" />
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
