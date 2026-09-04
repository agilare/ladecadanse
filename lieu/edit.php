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

$is_edit_mode = in_array($get['action'], ['editer', 'update'], true);

/*
 * Les refus se rendent dans la page du site, avec le statut HTTP qui va avec : un message
 * HTML nu au-dessus d'une page vide n'offrait ni retour à l'accueil, ni au client le
 * moyen de distinguer un refus d'une réponse normale.
 */
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

// Publier ou dépublier une fiche reste une décision de modération
$can_change_status = $_SESSION['Sgroupe'] <= UserLevel::ADMIN;

/*
 * Le nom, la préposition, les catégories et les organisateurs engagent tous les
 * événements qui se déroulent dans le lieu : les autres niveaux les voient sans pouvoir
 * y toucher, et LieuEdition reprend en base ce qu'ils n'ont pas le droit de poster.
 */
$can_edit_editor_fields = $authorization->isPersonneEditor($_SESSION);

$is_form_submitted = isset($_POST['form_submitted']);

$lieu_form = new LieuEdition();
$lieu_form->setAction($get['action']);
$lieu_form->setAuthorId((int) ($_SESSION['SidPersonne'] ?? 0));
$lieu_form->setStatusEditable($can_change_status);
$lieu_form->setEditorFieldsEditable($can_edit_editor_fields);

if ($http_error === null && $is_edit_mode)
{
    /*
     * L'identifiant vient de l'url, dont l'autorisation a déjà été vérifiée, et non plus
     * d'un champ caché du formulaire : le POST décidait jusqu'ici du lieu à écrire, si
     * bien qu'un utilisateur autorisé sur un lieu pouvait en modifier n'importe quel autre.
     */
    $lieu_form->setIdLieu($get['idL']);

    /*
     * À l'affichage la fiche remplit le formulaire ; à la soumission on vérifie seulement
     * qu'elle existe encore, la recharger écraserait la saisie en cours. Sans ce contrôle,
     * un identifiant inconnu rendait un formulaire vide sous un titre sans nom, et l'UPDATE
     * qui suivait ne touchait aucune ligne en annonçant une réussite.
     */
    $fiche_existe = $is_form_submitted
        ? $lieu_form->ficheExiste()
        : $lieu_form->loadValeurs($get['idL']);

    if (!$fiche_existe)
    {
        $http_error = [404, 'Not Found', "Ce lieu n'existe pas ou plus"];
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
    elseif ($lieu_form->traitement($_POST, $_FILES))
    {
        $_SESSION['lieu_flash_msg'] = $lieu_form->getMessage();
        header("Location: /lieu/lieu.php?idL=" . $lieu_form->getIdLieu());
        die();
    }
    elseif (!$lieu_form->hasErrors())
    {
        // La saisie est valide et l'enregistrement a pourtant échoué : la base est
        // hors d'état, ce dont l'auteur du formulaire ne peut rien faire.
        throw new RuntimeException("L'enregistrement du lieu a échoué sans erreur de validation");
    }
}

$form_url_parameters = $is_edit_mode ? "update&idL=" . $get['idL'] : "insert";
$coordonnees = $lieu_form->getCoordonnees();

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

    <?php if ($token_error) : ?>
        <?php HtmlShrink::msgErreur("Le système de sécurité du site n'a pu authentifier votre action. Veuillez réafficher ce formulaire et réessayer"); ?>
    <?php elseif ($lieu_form->hasErrors()) : ?>
        <?php HtmlShrink::msgErreur("Il y a " . $lieu_form->getErrorCount() . " erreur(s)"); ?>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="ajouter_editer" class="js-submit-freeze-wait" action="<?= basename(__FILE__) ?>?action=<?= sanitizeForHtml($form_url_parameters) ?>">

    <p>* indique un champ obligatoire</p>

    <?php if (!$can_edit_editor_fields) : ?>
        <p>Si vous souhaitez modifier le nom du lieu, ses catégories ou ses organisateurs, merci de nous <a href="/misc/contacteznous.php">contacter</a></p>
    <?php endif; ?>

    <fieldset>
        <legend>Identité</legend>

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
                <?= Lieu::getCategoriesOptionsHtml($lieu_form->getCategories()) ?>
            </select>
            <?= $lieu_form->getHtmlErreur("categories") ?>
        </p>

        <?php
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
            <input type="text" name="lat" id="lat" size="14" maxlength="12" inputmode="decimal" placeholder="46.2043907" title="Latitude du lieu, en degrés décimaux" value="<?= sanitizeForHtml($coordonnees->latSaisie()) ?>" />
            <?= $lieu_form->getHtmlErreur("lat") ?>
        </p>

        <p>
            <label for="lng">Longitude</label>
            <input type="text" name="lng" id="lng" size="14" maxlength="12" inputmode="decimal" placeholder="6.1431577" title="Longitude du lieu, en degrés décimaux" value="<?= sanitizeForHtml($coordonnees->lngSaisie()) ?>" />
            <?= $lieu_form->getHtmlErreur("lng") ?>
        </p>
        <div class="guideChamp">Coordonnées qui permettent d’afficher le plan du lieu. Pour les obtenir : sur <a href="https://www.openstreetmap.org" rel="external" target="_blank">openstreetmap.org</a>, faites un clic droit sur l’emplacement du lieu puis choisissez « Afficher l’adresse » ; les deux nombres apparaissent en haut à gauche. Laissez les deux champs vides si vous ne les connaissez pas.</div>

        <p>
            <label for="horaire_general">Jours et heures d’ouverture habituels</label>
            <textarea name="horaire_general" id="horaire_general" cols="50" rows="3" title="Quels sont les horaires typiques d'une soirée ?"><?= sanitizeForHtml($lieu_form->getValeur('horaire_general')) ?></textarea>
            <?= $lieu_form->getHtmlErreur("horaire_general") ?>
        </p>

        <p>
            <label for="URL">Site web</label>
            <input type="url" name="URL" id="URL" size="50" maxlength="<?= Lieu::FIELDS['URL']['max'] ?>" title="Page web du lieu" value="<?= sanitizeForHtml($lieu_form->getValeur('URL')) ?>" />
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
            <select name="organisateurs[]" id="organisateurs" data-placeholder="Choisissez un ou plusieurs organisateurs"
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
        <?php /* Témoin de soumission : le bouton est désactivé par js-submit-freeze-wait, son
                 nom ne part donc pas. Les autres formulaires du site le nomment « formulaire ». */ ?>
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
