<?php

require_once("../app/bootstrap.php");

use Ladecadanse\Security\SecurityToken;
use Ladecadanse\SalleEdition;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\HtmlShrink;
use Ladecadanse\UserLevel;

if (!$videur->checkGroup(UserLevel::ACTOR)) {
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
    header("Location: /user-login.php");
    die();
}

$tab_actions = ["ajouter", "insert", "editer", "update"];
$get = [
    'action' => Validateur::validateUrlQueryValue($_GET['action'] ?? 'ajouter', "enum", 'ajouter', $tab_actions),
    'idS' => (int)($_GET['idS'] ?? 0),
    'idL' => (int)($_GET['idL'] ?? 0),
];

$isAddMode = in_array($get['action'], ['ajouter', 'insert']);
$isEditMode = in_array($get['action'], ['editer', 'update']);

if ($isEditMode && $_SESSION['Sgroupe'] > UserLevel::ADMIN) {
    HtmlShrink::msgErreur("Vous n'avez pas les droits pour éditer cette salle");
    exit;
}

$salleForm = new SalleEdition();
$salleForm->setAction($get['action']);
$salleForm->setIdPersonne($_SESSION['SidPersonne']);
$salleForm->setIdSalle($get['idS'] ?: null);

if ($get['action'] === 'editer' && $get['idS'] > 0) {
    $salleForm->loadValeurs($get['idS']);
} elseif ($get['idL'] > 0) {
    $salleForm->setValeur('idLieu', $get['idL']);
}

if (($_POST['formulaire'] ?? '') === 'ok') {
    if (!SecurityToken::check($_POST['token'] ?? '', $_SESSION['token'] ?? '')) {
        echo "Le système de sécurité du site n'a pu authentifier votre action. Veuillez réafficher ce formulaire et réessayer";
        exit;
    }

    if ($salleForm->traitement($_POST, [])) {
        $_SESSION['lieu_flash_msg'] = $salleForm->getMessage();
        header("Location: /lieu/lieu.php?idL=" . (int)$salleForm->getValeur('idLieu'));
        die();
    }

    if (!$salleForm->hasErrors()) {
        HtmlShrink::msgErreur("La requête a échoué");
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

<?php if ($salleForm->hasErrors()): ?>
    <?php HtmlShrink::msgErreur("Il y a " . $salleForm->getErrorCount() . " erreur(s)."); ?>
<?php endif; ?>

<form method="post" id="ajouter_editer" enctype="multipart/form-data" class="js-submit-freeze-wait" action="<?= basename(__FILE__) ?>?action=<?= $act ?>">

<p>* indique un champ obligatoire</p>

<fieldset>
<legend>Salle</legend>

<p>
    <label for="idLieu">Lieu* :</label>
    <select name="idLieu" id="idLieu" class="js-select2-options-with-style" data-placeholder="">
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
