<?php

require_once("../app/bootstrap.php");

use Ladecadanse\Security\SecurityToken;
use Ladecadanse\SalleEdition;
use Ladecadanse\Utils\QueryParamValidator;
use Ladecadanse\HtmlShrink;
use Ladecadanse\UserLevel;

if (!$authorization->checkGroup(UserLevel::ACTOR)) {
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
    header("Location: /user/login.php");
    die();
}

$allowed_actions = ["ajouter", "insert", "editer", "update"];

// null quand l'action n'existe pas : la page répond alors 400, là où l'exception levée
// par validateUrlQueryValue() faisait un 500 d'une url abîmée ou d'un passage de bot
$action_demandee = QueryParamValidator::enumFromQuery($_GET['action'] ?? null, $allowed_actions, 'ajouter');

$get = [
    'action' => $action_demandee ?? 'ajouter',
    'idS' => (int)($_GET['idS'] ?? 0),
    'idL' => (int)($_GET['idL'] ?? 0),
];

$isEditMode = in_array($get['action'], ['editer', 'update'], true);

/*
 * Chaque refus est rendu dans la page du site par _erreur_http.inc.php, comme sur les
 * deux formulaires de fiche. Il sortait ici en message nu au-dessus d'une page vide, et
 * avec un statut 200 qui annonçait au client une réponse normale.
 */
$http_error = null;

if ($action_demandee === null) {
    $http_error = [400, 'Bad Request', "Cette action n'existe pas"];
} elseif ($isEditMode && $get['idS'] <= 0) {
    $http_error = [400, 'Bad Request', "Aucune salle n'est désignée"];
} elseif ($isEditMode && $_SESSION['Sgroupe'] > UserLevel::ADMIN) {
    $http_error = [403, 'Forbidden', "Vous n'avez pas les droits pour éditer cette salle"];
}

if ($http_error !== null) {
    include("../_erreur_http.inc.php");
    exit;
}

$salleForm = new SalleEdition();
$salleForm->setAction($get['action']);
$salleForm->setIdPersonne($_SESSION['SidPersonne']);
$salleForm->setIdSalle($get['idS'] ?: null);

$is_form_submitted = ($_POST['formulaire'] ?? '') === 'ok';

if ($isEditMode) {
    /*
     * Au premier affichage la salle remplit le formulaire ; à la soumission on ne relit
     * que l'état de référence, la recharger écraserait la saisie en cours. Le retour
     * était ignoré des deux côtés : un identifiant inconnu rendait un formulaire vide
     * sous le titre « Modifier une salle », et l'UPDATE qui suivait ne touchait aucune
     * ligne en annonçant une réussite.
     */
    $salle_exists = $is_form_submitted
        ? $salleForm->refreshStoredValues()
        : $salleForm->loadValues($get['idS']);

    if (!$salle_exists) {
        $http_error = [404, 'Not Found', "Cette salle n'existe pas ou plus"];
        include("../_erreur_http.inc.php");
        exit;
    }
} elseif ($get['idL'] > 0) {
    $salleForm->setValeur('idLieu', $get['idL']);
}

$security_token_mismatch = false;
if ($is_form_submitted) {
    if (!SecurityToken::check($_POST['token'] ?? '', $_SESSION['token'] ?? '')) {
        $security_token_mismatch = true;
    } else {
        if ($salleForm->processSubmission($_POST, [])) {
            $_SESSION['lieu_flash_msg'] = $salleForm->getResultMessage();
            header("Location: /lieu/lieu.php?idL=" . (int)$salleForm->getValeur('idLieu'));
            die();
        }

        if (!$salleForm->hasErrors()) {
            /*
             * La saisie est valide et l'enregistrement a pourtant échoué : la base est
             * hors d'état, ce dont l'auteur du formulaire ne peut rien faire. Le message
             * qui sortait ici partait avant le doctype, l'en-tête n'étant inclus que
             * plus bas — les deux formulaires de fiche lèvent la même exception.
             */
            throw new RuntimeException("L'enregistrement de la salle a échoué sans erreur de validation");
        }
    }
}

$titre_form = $isEditMode ? "Modifier une salle" : "Ajouter une salle à un lieu";
$act = $isEditMode ? "update&idS={$get['idS']}" : "insert";
$lieux = $salleForm->getLieux();

$page_titre = "ajouter/modifier une salle";
$extra_css = ["formulaires"];
include("../_header.inc.php");
?>

<main id="contenu" class="colonne">

<header id="entete_contenu">
    <h1><?= sanitizeForHtml($titre_form) ?></h1>
    <div class="spacer"></div>
</header>

<?php if ($security_token_mismatch): ?>
    <?php HtmlShrink::msgErreur("Le système de sécurité du site n'a pu authentifier votre action. Veuillez réafficher ce formulaire et réessayer."); ?>
<?php elseif ($salleForm->hasErrors()): ?>
    <?php HtmlShrink::msgErreur("Il y a " . $salleForm->getErrorCount() . " erreur(s)."); ?>
<?php endif; ?>

    <form method="post" id="ajouter_editer" enctype="multipart/form-data" class="js-submit-freeze-wait" action="<?= basename(__FILE__) ?>?action=<?= sanitizeForHtml($act) ?>">

<p>* indique un champ obligatoire</p>

<fieldset>
<legend>Salle</legend>

<p>
    <label for="idLieu">Lieu* :</label>
    <?php /* Le lieu se choisit à la création et ne bouge plus : update() ne l'a jamais écrit,
             et le déplacer laisserait derrière lui les événements qui citent la salle avec
             l'ancien idLieu. Le select se contentait de le laisser croire. */ ?>
    <select name="idLieu" id="idLieu" class="js-select2-options-with-style" data-placeholder="" <?= $isEditMode ? 'disabled' : '' ?>>
        <option value=""></option>
        <?php foreach ($lieux as $lieu): ?>
            <option value="<?= $lieu['idLieu'] ?>"<?= $lieu['idLieu'] == $salleForm->getValeur('idLieu') ? ' selected' : '' ?>>
                <?= sanitizeForHtml($lieu['nom']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?= $salleForm->getValidationError('idLieu') ?>
</p>

<p>
    <label for="nom">Nom* :</label>
    <input name="nom" id="nom" type="text" size="30" value="<?= sanitizeForHtml($salleForm->getValeur('nom')) ?>">
    <?= $salleForm->getValidationError('nom') ?>
</p>

<p>
    <label for="emplacement">Emplacement :</label>
    <input name="emplacement" id="emplacement" type="text" size="30" value="<?= sanitizeForHtml($salleForm->getValeur('emplacement')) ?>">
    <?= $salleForm->getValidationError('emplacement') ?>
</p>

</fieldset>

<p class="piedForm">
    <input type="hidden" name="formulaire" value="ok">
    <input type="hidden" name="token" value="<?= SecurityToken::getToken() ?>">
    <input type="submit" value="Enregistrer" class="submit submit-big">
</p>

</form>

</main>

<div id="colonne_gauche" class="colonne">
<?php include("../event/_navigation_calendrier.inc.php"); ?>
</div>

<div id="colonne_droite" class="colonne">
</div>

<?php
include("../_footer.inc.php");
