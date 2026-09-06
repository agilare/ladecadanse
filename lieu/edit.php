<?php

require_once("../app/bootstrap.php");

use Ladecadanse\HtmlShrink;
use Ladecadanse\Lieu;
use Ladecadanse\LieuEdition;
use Ladecadanse\Localite;
use Ladecadanse\Organisateur;
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

// null quand l'action n'existe pas : la page répond alors 400, là où l'exception levée
// par validateUrlQueryValue() faisait un 500 d'une url abîmée ou d'un passage de bot
$action_demandee = QueryParamValidator::enumFromQuery($_GET['action'] ?? null, $allowed_actions, 'ajouter');

$get = [
    'action' => $action_demandee ?? 'ajouter',
    'idL' => (int) ($_GET['idL'] ?? 0),
];

// Reste dans la page : les refus ci-dessous s'en servent avant que LieuEdition n'existe,
// et l'instancier plus tôt reviendrait à construire un formulaire pour le jeter aussitôt
$is_edit_mode = in_array($get['action'], ['editer', 'update'], true);

// Chaque refus est rendu dans la page du site par _erreur_http.inc.php
$http_error = null;

if ($action_demandee === null)
{
    $http_error = [400, 'Bad Request', "Cette action n'existe pas"];
}
elseif ($is_edit_mode && $get['idL'] <= 0)
{
    $http_error = [400, 'Bad Request', "Aucun lieu n'est désigné"];
}
elseif ($is_edit_mode && !$authorization->isPersonneAllowedToEditLieu($_SESSION, $get['idL']))
{
    $http_error = [403, 'Forbidden', "Vous ne pouvez pas modifier ce lieu"];
}
elseif (!$is_edit_mode && !$authorization->isPersonneAllowedToAddLieu($_SESSION))
{
    $http_error = [403, 'Forbidden', "Vous ne pouvez pas ajouter de lieu"];
}

if ($http_error !== null)
{
    include("../_erreur_http.inc.php");
    exit;
}

// Publier ou dépublier une fiche reste une décision de modération
// TODO: domain, mv into LieuEdition ? looks like AuthorId; create a class for current person editing ? A LieuEdition->CurrentUserEditing->canChangeStatus is clearer than $can_change_status alone (would replace setStatusEditable)
$can_change_status = $_SESSION['Sgroupe'] <= UserLevel::ADMIN;

/*
 * Le nom, la préposition, les catégories et les organisateurs engagent tous les
 * événements qui se déroulent dans le lieu : les autres niveaux les voient sans pouvoir
 * y toucher, et LieuEdition reprend en base ce qu'ils n'ont pas le droit de poster.
 */
// TODO: cf previous remark
$can_edit_editor_fields = $authorization->isPersonneEditor($_SESSION);

$lieu_form = new LieuEdition();
$lieu_form->setAction($get['action']);
$lieu_form->setAuthorId((int) ($_SESSION['SidPersonne'] ?? 0));
$lieu_form->setStatusEditable($can_change_status);
$lieu_form->setCanEditEditorFields($can_edit_editor_fields);

$is_form_submitted = isset($_POST['form_submitted']);

if ($is_edit_mode)
{
    // l'identifiant vient de l'url, dont le droit vient d'être vérifié ; il arrivait d'un
    // champ caché, ce qui laissait modifier n'importe quel autre lieu
    $lieu_form->setIdLieu($get['idL']);

    /*
     * Au premier affichage la fiche remplit le formulaire ; à la soumission on ne relit
     * que l'état de référence, la recharger écraserait la saisie en cours. Sans ce
     * contrôle, un identifiant inconnu rendait un formulaire vide sous un titre sans nom,
     * et l'UPDATE qui suivait ne touchait aucune ligne en annonçant une réussite.
     */
    $lieu_exists = $is_form_submitted
        ? $lieu_form->refreshStoredValues()
        : $lieu_form->loadValues($get['idL']);

    if (!$lieu_exists)
    {
        $http_error = [404, 'Not Found', "Ce lieu n'existe pas ou plus"];
        include("../_erreur_http.inc.php");
        exit;
    }
}

$security_token_mismatch = false;
if ($is_form_submitted)
{
    if (!SecurityToken::check($_POST['token'] ?? '', $_SESSION['token'] ?? ''))
    {
        $security_token_mismatch = true;
    }
    elseif ($lieu_form->processSubmission($_POST, $_FILES))
    {
        $_SESSION['lieu_flash_msg'] = $lieu_form->getResultMessage();
        header("Location: /lieu/lieu.php?idL=" . $lieu_form->getIdLieu());
        die();
    }
    elseif (!$lieu_form->hasErrors())
    {
        /*
         * Le seul cas restant : la saisie est valide et l'enregistrement a pourtant
         * échoué, donc la base est hors d'état. Sans ce test, la page se contenterait de
         * réafficher le formulaire sans un seul message — l'auteur croirait à une erreur
         * de saisie qu'il ne trouverait nulle part.
         */
        throw new RuntimeException("L'enregistrement du lieu a échoué sans erreur de validation");
    }
}

$coordinates = $lieu_form->getCoordinates();

$page_titre = $is_edit_mode ? "modifier un lieu" : "ajouter un lieu";
$extra_css = ["formulaires"];
include("../_header.inc.php");
?>

<main id="contenu" class="colonne">

    <header id="entete_contenu">
        <h1>
            <?php if ($is_edit_mode) : ?>
                Modifier le lieu
                <a href="/lieu/lieu.php?idL=<?= $get['idL'] ?>"><?= sanitizeForHtml($lieu_form->getStoredName()) ?></a>
            <?php else : ?>
                Ajouter un lieu
            <?php endif; ?>
        </h1>
        <div class="spacer"></div>
    </header>

    <?php if ($security_token_mismatch) : ?>
        <?php HtmlShrink::msgErreur("Le système de sécurité du site n'a pu authentifier votre action. Veuillez réafficher ce formulaire et réessayer"); ?>
    <?php elseif ($lieu_form->hasErrors()) : ?>
        <?php HtmlShrink::msgErreur("Il y a " . $lieu_form->getErrorCount() . " erreur(s)"); ?>
    <?php endif; ?>

    <!-- TODO: ajouter_editer -> app_form (or edit_form if there is a distinctiveness of edit forms) would be clearer but needs a big renaming accross files -->
    <form method="post" enctype="multipart/form-data" id="ajouter_editer" class="js-submit-freeze-wait" action="<?= basename(__FILE__) ?>?action=<?= $is_edit_mode ? "update&amp;idL=" . (int) $get['idL'] : "insert" ?>">


    <?php if (!$can_edit_editor_fields) : ?>
        <p>Si vous souhaitez modifier le nom du lieu, ses catégories ou ses organisateurs, merci de nous <a href="/misc/contacteznous.php">contacter</a></p>
    <?php endif; ?>

    <p>* indique un champ obligatoire</p>

    <fieldset>
        <legend>Identité</legend>

        <!-- TODO: UPLOAD_MAX_FILESIZE could be moved in a class related to files, uploads ? -->
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= UPLOAD_MAX_FILESIZE ?>" />

        <?php /* Les champs réservés aux éditeurs sont rendus en lecture seule aux autres
                 niveaux, et non plus en champs cachés : LieuEdition reprend de toute façon
                 leur valeur enregistrée, un POST forgé n'y changerait rien. */ ?>
        <p>
            <label for="nom">Nom du lieu*</label>
            <input type="text" name="nom" id="nom" size="40" maxlength="<?= Lieu::FIELDS['nom']['max'] ?>" value="<?= sanitizeForHtml($lieu_form->getValeur('nom')) ?>" required
                <?= $can_edit_editor_fields ? '' : 'readonly class="read-only"' ?> />
            <?= $lieu_form->getHtmlErreur("nom") ?>
        </p>

        <p>
            <label for="preposition_nom">Préposition du nom</label>
            <input type="text" name="preposition_nom" id="preposition_nom" size="12" maxlength="<?= Lieu::FIELDS['preposition_nom']['max'] ?>"
                title="« au », « chez », « à l’ »… tel que le nom du lieu se dit dans une phrase"
                value="<?= sanitizeForHtml($lieu_form->getValeur('preposition_nom')) ?>"
                <?= $can_edit_editor_fields ? '' : 'readonly class="read-only"' ?> />
            <?= $lieu_form->getHtmlErreur("preposition_nom") ?>
        </p>

        <p>
            <label for="categories">Catégorie(s)*</label>
            <?php /* la largeur des trois listes vient de web/css/lieu/edit.css, qui les aligne
                     sur les champs longs ; elle était posée ici en style en ligne, que Select2
                     recopie sur le conteneur qu'il substitue au <select> */ ?>
            <select name="categories[]" id="categories" class="js-select2-options-with-style" multiple
                data-placeholder="Choisissez une ou plusieurs catégories" <?= $can_edit_editor_fields ? '' : 'disabled' ?>>
                <!-- TODO: introduce a LieuRenderer -->
                <?= Lieu::getCategoriesOptionsHtml($lieu_form->getCategories()) ?>
            </select>
            <?= $lieu_form->getHtmlErreur("categories") ?>
        </p>

        <?php
        // TODO: introduce a "widget" or "component" ImageFormHtmlComponent or ImageHtmlComponent (belonging to FicheEdition) class ?
        $image_form = $lieu_form;
        $image_entity = Lieu::class;
        $image_field = 'logo';
        $image_label = 'Logo';
        $image_title = "Logo du lieu qui s'affichera à gauche du titre";
        include("../_champ_image.inc.php");
        ?>
    </fieldset>

    <fieldset>
        <legend>Infos pratiques</legend>

        <p>
            <label for="adresse">Adresse*</label>
            <input type="text" name="adresse" id="adresse" size="50" maxlength="<?= Lieu::FIELDS['adresse']['max'] ?>" title="numéro et rue" value="<?= sanitizeForHtml($lieu_form->getValeur('adresse')) ?>" required />
            <?= $lieu_form->getHtmlErreur("adresse") ?>
        </p>

        <p>
            <?php /* « Localité » tout court attendait la disparition de la colonne `quartier`,
                     que les données ne permettent pas : 47 lieux sur 109 en portent un, et 355
                     événements avec eux. Le select propose donc toujours les quartiers de Genève,
                     et le libellé le dit — comme dans les deux autres formulaires. */ ?>
            <label for="localite">Localité/quartier*</label>
            <select name="localite_id" id="localite" class="js-select2-options-with-style" required data-placeholder="Tapez le nom...">
                <?php
                // Les localités fribourgeoises ne sont plus proposées à l'ajout, mais restent
                // affichables en édition pour ne pas vider le select d'un lieu déjà rattaché à l'une
                // d'elles. France et « Autre » sont des localités comme les autres depuis la 3.12.0.
                echo Localite::getOptionsHtml($lieu_form->getValeur('localite_id'), $lieu_form->getValeur('quartier'), !$is_edit_mode);
                ?>
            </select>
            <?= $lieu_form->getHtmlErreur("localite_id") ?>
            <?= Localite::getAideChoixHtml() ?>
        </p>

        <p>
            <label for="lat">Latitude</label>
            <input type="text" name="lat" id="lat" size="14" maxlength="12" inputmode="decimal" placeholder="46.2043907" title="Latitude du lieu, en degrés décimaux" value="<?= sanitizeForHtml($coordinates->latInput()) ?>" />
            <?= $lieu_form->getHtmlErreur("lat") ?>
        </p>

        <p>
            <label for="lng">Longitude</label>
            <input type="text" name="lng" id="lng" size="14" maxlength="12" inputmode="decimal" placeholder="6.1431577" title="Longitude du lieu, en degrés décimaux" value="<?= sanitizeForHtml($coordinates->lngInput()) ?>" />
            <?= $lieu_form->getHtmlErreur("lng") ?>
        </p>
        <div class="guideChamp">Coordonnées qui permettent d’afficher le plan du lieu. Pour les obtenir : sur <a href="https://www.openstreetmap.org" rel="external" target="_blank">openstreetmap.org</a>, faites un clic droit sur l’emplacement du lieu puis choisissez « Afficher l’adresse » ; les deux nombres apparaissent en haut à gauche. Laissez les deux champs vides si vous ne les connaissez pas.</div>

        <p>
            <label for="horaire_general">Jours et heures d’ouverture habituels</label>
            <textarea name="horaire_general" id="horaire_general" cols="50" rows="4"><?= sanitizeForHtml($lieu_form->getValeur('horaire_general')) ?></textarea>
            <?= $lieu_form->getHtmlErreur("horaire_general") ?>
        </p>

        <p>
            <label for="URL">Site web</label>
            <input type="url" name="URL" id="URL" size="50" maxlength="<?= Lieu::FIELDS['URL']['max'] ?>" title="Page web principale" value="<?= sanitizeForHtml($lieu_form->getValeur('URL')) ?>" />
            <?= $lieu_form->getHtmlErreur("URL") ?>
        </p>
    </fieldset>

    <fieldset>
        <legend>Relations</legend>

        <p>
            <label for="organisateurs">Organisateur(s)</label>
            <?php /* Les <option> viennent d'Organisateur::getOptionsHtml(), comme dans les
                     formulaires d'événement : la requête et la boucle qui les construisaient
                     ici en étaient une copie, restée en arrière. */ ?>
            <select name="organisateurs[]" id="organisateurs" data-placeholder="Tapez le nom de l'organisateur"
                class="js-select2-options-with-complement" multiple
                title="Un organisateur dans la base de données de La décadanse" <?= $can_edit_editor_fields ? '' : 'disabled' ?>>
                <?= Organisateur::getOptionsHtml($lieu_form->getOrganisateurs()) ?>
            </select>
            <div class="guideChamp">Les personnes membres de ces organisateurs pourront modifier ce lieu ainsi que tous les événements s’y déroulant</div>
        </p>
    </fieldset>

    <fieldset>
        <legend>Photo</legend>

        <?php
        // TODO: cf. remark for logo
        $image_form = $lieu_form;
        $image_entity = Lieu::class;
        $image_field = 'photo1';
        $image_label = 'Photo';
        $image_title = "Photo qui s'affichera en haut à droite";
        include("../_champ_image.inc.php");
        ?>
    </fieldset>

    <?php if ($can_change_status) : ?>
    <fieldset>
        <legend>Statut</legend>

        <ul class="radio mobile-vertical">
            <?php foreach (Lieu::STATUTS as $status_value => $status_label) : ?>
                <li class="listehoriz">
                    <input type="radio" name="statut" value="<?= $status_value ?>" id="statut_<?= $status_value ?>" class="radio_horiz"
                        <?= $lieu_form->getValeur('statut') === $status_value ? 'checked="checked"' : '' ?> />
                    <label class="continu" for="statut_<?= $status_value ?>"><?= $status_label ?></label>
                </li>
            <?php endforeach; ?>
        </ul>
        <?= $lieu_form->getHtmlErreur("statut") ?>
    </fieldset>
    <?php endif; ?>

    <p class="piedForm">
        <?php /* Témoin de soumission, et non détection du bouton lui-même : js-submit-freeze-wait
                 le désactive dès le premier clic, un contrôle désactivé ne poste pas son nom, et
                 la page ne verrait donc jamais le formulaire arriver. Une soumission au clavier
                 (Entrée dans un champ) ne poste pas non plus le bouton. Les autres formulaires du
                 site nomment ce témoin « formulaire ». */ ?>
        <input type="hidden" name="form_submitted" value="1" />
        <input type="hidden" name="token" value="<?= SecurityToken::getToken() ?>" />
        <input type="submit" value="Enregistrer" title="Enregistrer le lieu" class="submit submit-big" />
    </p>

    </form>

</main>

<div id="colonne_gauche" class="colonne">
</div>

<div id="colonne_droite" class="colonne">
</div>

<?php
include("../_footer.inc.php");
