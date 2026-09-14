<?php

require_once("../app/bootstrap.php");

use Ladecadanse\HtmlShrink;
use Ladecadanse\Lieu;
use Ladecadanse\Security\SecurityToken;
use Ladecadanse\UserLevel;
use Ladecadanse\Utils\QueryParamValidator;
use Ladecadanse\Utils\UserHtmlSanitizer;
use Ladecadanse\Utils\Validateur;

if (!$authorization->checkGroup(UserLevel::ACTOR))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
    header("Location: /user/login.php");
    die();
}

// 1sts : intention, 2nds : intention validated
$allowed_actions = ["ajouter", "insert", "editer", "update"];

/*
 * Le type n'a pas de valeur par défaut : une description est un avis signé, une
 * présentation est le lieu parlant en son nom. Ni les mêmes droits, ni la même place
 * dans la fiche — deviner l'un pour l'autre écrirait le texte au mauvais endroit.
 */
$allowed_types = ["description", "presentation"];

/*
 * La colonne `type` est un ENUM sans accent, que la page rendait tel quel : « Ajouter une
 * presentation », « Presentation ajoutée ».
 */
$type_libelles = ['description' => 'description', 'presentation' => 'présentation'];

// null quand la valeur n'existe pas : la page répond alors 400, là où l'exception levée
// par validateUrlQueryValue() faisait un 500 d'une url abîmée ou d'un passage de bot
$action_demandee = QueryParamValidator::enumFromQuery($_GET['action'] ?? null, $allowed_actions, 'ajouter');
$type_demande = QueryParamValidator::enumFromQuery($_GET['type'] ?? null, $allowed_types);

$get = [
    'action' => $action_demandee ?? 'ajouter',
    'type' => $type_demande ?? 'description',
    'idL' => (int) ($_GET['idL'] ?? 0),
    'idP' => (int) ($_GET['idP'] ?? 0),
];

$type_libelle = $type_libelles[$get['type']];

$is_edit_mode = in_array($get['action'], ['editer', 'update'], true);

/*
 * Le formulaire n'est traité que sous les actions qui écrivent. Un POST vers ?action=ajouter
 * ré-affiche donc le formulaire au lieu de tomber dans une branche qui n'enregistre rien.
 */
$is_form_submitted = isset($_POST['form_submitted']) && in_array($get['action'], ['insert', 'update'], true);

/*
 * Auteur du texte : celui que l'url désigne en modification, la personne connectée à
 * l'ajout. Il ne vient plus d'un champ caché du formulaire — c'est de lui que dépend le
 * droit de modifier, et le POST décidait jusqu'ici de la ligne écrite.
 */
$id_auteur = $is_edit_mode ? $get['idP'] : (int) ($_SESSION['SidPersonne'] ?? 0);

/*
 * Bornes du texte, partagées par la validation serveur et le guide affiché sous le champ,
 * qui ne les annonçait pas. Le minimum écarte les textes qui ne disent rien ; le maximum
 * est celui d'un `mediumtext` raisonnable.
 */
$contenu_min = 30;
$contenu_max = 100000;

/*
 * Chaque refus est rendu dans la page du site par _erreur_http.inc.php, comme sur les
 * trois formulaires de fiche. Le type manquant, lui, finissait en page blanche
 * (trigger_error puis exit).
 */
$http_error = null;

if ($action_demandee === null)
{
    $http_error = [400, 'Bad Request', "Cette action n'existe pas"];
}
elseif ($type_demande === null)
{
    $http_error = [400, 'Bad Request', "Cette adresse ne dit pas quel texte rédiger"];
}
elseif ($get['idL'] <= 0)
{
    // le lieu vient de l'url dans les deux modes, la liste déroulante ayant disparu
    $http_error = [400, 'Bad Request', "Aucun lieu n'est désigné"];
}
elseif ($id_auteur <= 0)
{
    $http_error = [400, 'Bad Request', "Aucun auteur n'est désigné"];
}
elseif (!$is_edit_mode && !$authorization->isPersonneAllowedToAddTexteLieu($_SESSION, $get['type'], $get['idL']))
{
    $http_error = [403, 'Forbidden', "Vous ne pouvez pas ajouter de " . $type_libelle . " à ce lieu"];
}
elseif ($is_edit_mode && !$authorization->isPersonneAllowedToEditTexteLieu($_SESSION, $get['type'], $get['idL'], $id_auteur))
{
    $http_error = [403, 'Forbidden', "Vous ne pouvez pas modifier cette " . $type_libelle];
}

if ($http_error !== null)
{
    include("../_erreur_http.inc.php");
    exit;
}

$champs = ['contenu' => ''];
$auteur_pseudo = '';

$stmt = $connectorPdo->prepare("SELECT nom, preposition_nom FROM lieu WHERE idLieu = :idL");
$stmt->execute([':idL' => $get['idL']]);
$lieu = $stmt->fetch(PDO::FETCH_ASSOC);

if ($lieu === false)
{
    $http_error = [404, 'Not Found', "Ce lieu n'existe pas ou plus"];
    include("../_erreur_http.inc.php");
    exit;
}

if ($is_edit_mode)
{
    /*
     * Le texte est relu dans les deux cas, mais il ne remplit le champ qu'à l'affichage :
     * à la soumission, il écraserait la saisie en cours. Sans ce contrôle, un `idP`
     * inconnu rendait un champ vide sous un titre sans texte, et l'UPDATE qui suivait ne
     * touchait aucune ligne en annonçant une réussite.
     *
     * Jointure externe : un texte dont l'auteur a été supprimé reste modifiable.
     */
    $stmt = $connectorPdo->prepare(
        "SELECT dl.contenu, p.pseudo
         FROM descriptionlieu dl
         LEFT JOIN personne p ON dl.idPersonne = p.idPersonne
         WHERE dl.idLieu = :idL AND dl.idPersonne = :idP AND dl.type = :type"
    );
    $stmt->execute([':idL' => $get['idL'], ':idP' => $id_auteur, ':type' => $get['type']]);
    $texte_en_base = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($texte_en_base === false)
    {
        $http_error = [404, 'Not Found', "Ce texte n'existe pas ou plus"];
        include("../_erreur_http.inc.php");
        exit;
    }

    $champs['contenu'] = (string) $texte_en_base['contenu'];
    $auteur_pseudo = (string) ($texte_en_base['pseudo'] ?? '');
}

$verif = new Validateur();
$security_token_mismatch = false;

if ($is_form_submitted)
{
    /*
     * Lecture inconditionnelle du champ : c'est le témoin de soumission qui commande, et
     * non isset($_POST['contenu']) — un champ vidé ne se distinguerait pas d'un premier
     * affichage. is_scalar() écarte un « contenu[]=x » forgé.
     */
    $champs['contenu'] = is_scalar($_POST['contenu'] ?? null) ? trim((string) $_POST['contenu']) : '';

    if (!SecurityToken::check($_POST['token'] ?? '', $_SESSION['token'] ?? ''))
    {
        $security_token_mismatch = true;
    }
    else
    {
        /*
         * Nettoyage avant validation, et non l'inverse : c'est le texte nettoyé qui sera
         * enregistré, et lui seul dont la longueur veut dire quelque chose. Quarante
         * caractères de balises que le sanitizer retire passaient le minimum pour
         * n'enregistrer rien.
         */
        $champs['contenu'] = (new UserHtmlSanitizer())->sanitize($champs['contenu']);

        $verif->valider($champs['contenu'], "contenu", "texte", $contenu_min, $contenu_max, true);

        if ($get['action'] === 'insert')
        {
            /*
             * La clé primaire de `descriptionlieu` est (idLieu, idPersonne), sans le type :
             * une personne ne porte qu'un texte par lieu. Le contrôle ne regardait que les
             * lignes du même type, si bien qu'ajouter une présentation après une description
             * partait en INSERT refusé par la clé — et la page redirigeait quand même.
             */
            $stmt = $connectorPdo->prepare("SELECT type FROM descriptionlieu WHERE idLieu = :idL AND idPersonne = :idP");
            $stmt->execute([':idL' => $get['idL'], ':idP' => $id_auteur]);
            $type_deja_ecrit = $stmt->fetchColumn();

            if ($type_deja_ecrit !== false)
            {
                $verif->setErreur('doublon', "Vous avez déjà écrit une <a href=\"" . basename(__FILE__)
                    . "?action=editer&amp;type=" . sanitizeForHtml((string) $type_deja_ecrit)
                    . "&amp;idL=" . $get['idL'] . "&amp;idP=" . $id_auteur . "\">"
                    . sanitizeForHtml($type_libelles[$type_deja_ecrit] ?? (string) $type_deja_ecrit) . "</a> pour ce lieu");
            }
            elseif ($get['type'] === 'presentation')
            {
                // une seule présentation par lieu, quel qu'en soit l'auteur
                $stmt = $connectorPdo->prepare("SELECT 1 FROM descriptionlieu WHERE idLieu = :idL AND type = 'presentation'");
                $stmt->execute([':idL' => $get['idL']]);

                if ($stmt->fetchColumn() !== false)
                {
                    $verif->setErreur('doublon', "Il y a déjà une présentation pour ce lieu");
                }
            }
        }

        if ($verif->nbErreurs() === 0)
        {
            /*
             * Deux marqueurs pour la même valeur : PDO tourne sans émulation des requêtes
             * préparées, où un marqueur nommé réutilisé rend un HY093.
             */
            $maintenant = date("Y-m-d H:i:s");

            if ($get['action'] === 'insert')
            {
                $stmt = $connectorPdo->prepare(
                    "INSERT INTO descriptionlieu (idLieu, idPersonne, type, contenu, dateAjout, date_derniere_modif)
                     VALUES (:idL, :idP, :type, :contenu, :date_ajout, :date_modif)"
                );
                $stmt->execute([
                    ':idL' => $get['idL'],
                    ':idP' => $id_auteur,
                    ':type' => $get['type'],
                    ':contenu' => $champs['contenu'],
                    ':date_ajout' => $maintenant,
                    ':date_modif' => $maintenant,
                ]);

                $_SESSION['lieu_flash_msg'] = ucfirst($type_libelle) . " ajoutée";
            }
            else
            {
                $stmt = $connectorPdo->prepare(
                    "UPDATE descriptionlieu SET contenu = :contenu, date_derniere_modif = :date_modif
                     WHERE idLieu = :idL AND idPersonne = :idP AND type = :type"
                );
                $stmt->execute([
                    ':contenu' => $champs['contenu'],
                    ':date_modif' => $maintenant,
                    ':idL' => $get['idL'],
                    ':idP' => $id_auteur,
                    ':type' => $get['type'],
                ]);

                $_SESSION['lieu_flash_msg'] = ucfirst($type_libelle) . " modifiée";
            }

            $logger->info('[lieu/text-edit] ' . $get['action'], [
                'type' => $get['type'],
                'idL' => $get['idL'],
                'idP' => $id_auteur,
                'user' => $_SESSION['user'],
            ]);

            header("Location: /lieu/lieu.php?idL=" . $get['idL']);
            die();
        }
    }
}

$form_url_parameters = $is_edit_mode
    ? "update&type=" . $get['type'] . "&idL=" . $get['idL'] . "&idP=" . $id_auteur
    : "insert&type=" . $get['type'] . "&idL=" . $get['idL'];

/*
 * « Ajouter une description au Chat Noir », « Modifier la présentation à l'Usine » :
 * prepositionToPutInSentence() rend l'espace de séparation quand la préposition ne colle
 * pas au nom. sanitizeForHtml() la trime, elle est donc réémise à côté.
 *
 * Un lieu sans préposition prend « pour » : le tiret que rend la fonction par défaut est
 * fait pour une étiquette de liste, pas pour une phrase.
 */
$preposition = trim((string) $lieu['preposition_nom']) === ''
    ? 'pour '
    : Lieu::prepositionToPutInSentence($lieu['preposition_nom']);

$espace_avant_nom = str_ends_with($preposition, ' ') ? ' ' : '';

$page_titre = ($is_edit_mode ? "modifier la " : "ajouter une ") . $type_libelle;
$extra_css = ["formulaires"];
include("../_header.inc.php");
?>

<main id="contenu" class="colonne">

    <header id="entete_contenu">
        <h1><?= $is_edit_mode ? "Modifier la" : "Ajouter une" ?> <?= sanitizeForHtml($type_libelle) ?> <?= sanitizeForHtml($preposition) . $espace_avant_nom ?><a href="/lieu/lieu.php?idL=<?= $get['idL'] ?>"><?= sanitizeForHtml($lieu['nom']) ?></a></h1>
        <div class="spacer"></div>
    </header>

    <?php if ($security_token_mismatch) : ?>
        <?php HtmlShrink::msgErreur("Le système de sécurité du site n'a pu authentifier votre action. Veuillez réafficher ce formulaire et réessayer"); ?>
    <?php elseif ($verif->nbErreurs() > 0) : ?>
        <?php HtmlShrink::msgErreur("Il y a " . $verif->nbErreurs() . " erreur(s)"); ?>
    <?php endif; ?>

    <form method="post" id="ajouter_editer" class="js-submit-freeze-wait" action="<?= basename(__FILE__) ?>?action=<?= sanitizeForHtml($form_url_parameters) ?>">

    <?php if ($get['type'] === 'presentation') : ?>
        <p>Si vous vous occupez de ce lieu, vous pouvez ici le présenter en son nom. Ce texte s'affichera dans sa fiche.</p>
    <?php else : ?>
        <?php /* « signée de son auteur » et non « du vôtre » : la modération reprend aussi
                 les descriptions des autres. */ ?>
        <p>La description s'affiche dans la fiche du lieu, signée du pseudo de son auteur.</p>
    <?php endif; ?>

    <?php /* Un administrateur peut reprendre la description d'un autre : autant qu'il sache
             de qui est le texte qu'il ouvre. */ ?>
    <?php if ($is_edit_mode && $auteur_pseudo !== '' && $id_auteur !== (int) ($_SESSION['SidPersonne'] ?? 0)) : ?>
        <p>Texte écrit par <?= sanitizeForHtml($auteur_pseudo) ?></p>
    <?php endif; ?>

    <p>* indique un champ obligatoire</p>

    <fieldset>

        <?= $verif->getHtmlErreur('doublon') ?>

        <p>
            <?php /* id distinct du name : <main id="contenu"> porte déjà cet identifiant, et
                     le $('#contenu') de web/js/browser.js visait le premier des deux. */ ?>
            <label for="texte">Le texte*</label>
            <textarea name="contenu" id="texte" class="tinymce" cols="45" rows="16"><?= sanitizeForHtml($champs['contenu']) ?></textarea>
            <?= $verif->getHtmlErreur('contenu') ?>
        </p>

        <div class="guideChamp"><?= $contenu_min ?> caractères au minimum. Les mises en forme que l'éditeur ne propose pas — styles collés depuis un traitement de texte, balises exotiques — sont retirées à l'enregistrement.</div>

    </fieldset>

    <p class="piedForm">
        <?php /* Témoin de soumission : le bouton est désactivé par js-submit-freeze-wait, son
                 nom ne part donc pas. Même nom que dans les deux autres formulaires de fiche. */ ?>
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
