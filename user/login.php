<?php

require_once("../app/bootstrap.php");

use Ladecadanse\UserLevel;
use Ladecadanse\Utils\QueryParamValidator;
use Ladecadanse\Utils\Validateur;

// =============================================================================
// Traitement
//
// Tout se joue avant l'inclusion de _header.inc.php : le formulaire expiré
// s'annonçait auparavant par un HtmlShrink::msgErreur() émis pendant le
// traitement, donc avant le doctype.
// =============================================================================

if ($authorization->checkGroup(UserLevel::MEMBER))
{
    header("Location: /index.php");
    die();
}

$formTokenName = 'form_token_user_login';

/**
 * Échecs signalés par Sentry, qui redirige vers cette page en nommant la cause
 * dans ?msg. Les clés sont donc aussi la liste blanche du paramètre d'url.
 */
$messages_echec = [
    'faux' => "Votre identifiant (ou votre email) et votre mot de passe ne correspondent pas.",
    'email_ambigu' => "Plusieurs comptes utilisent cet email : il ne suffit pas à vous reconnaître. Connectez-vous avec votre <strong>identifiant</strong>, et écrivez-nous si vous ne vous en souvenez plus.",
];

$champs = ["pseudo" => "", "memoriser" => false];
$motdepasse = "";

$verif = new Validateur();

if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok')
{
    // honeypot : un champ masqué que seuls les robots remplissent
    if (!empty($_POST['login_as']))
    {
        $verif->setErreur("login_as", "Veuillez laisser vide le champ réservé aux robots.");
    }
    // le jeton reçu doit être celui déposé en session à l'affichage du formulaire
    else if (empty($_SESSION[$formTokenName]) || !hash_equals($_SESSION[$formTokenName], (string) ($_POST[$formTokenName] ?? '')))
    {
        $verif->setErreur("formulaire", "Le formulaire a expiré, veuillez le saisir à nouveau.");
    }
    else
    {
        unset($_SESSION[$formTokenName]);

        $champs['pseudo'] = trim((string) ($_POST['pseudo'] ?? ''));
        $champs['memoriser'] = !empty($_POST['memoriser']);
        $motdepasse = (string) ($_POST['motdepasse'] ?? '');

        /*
         * Messages écrits pour cette page plutôt que ceux de Validateur, génériques
         * et anglicisés (« Le texte est trop court : 1, min 2 characters »).
         */
        if ($champs['pseudo'] === "")
        {
            $verif->setErreur("pseudo", "Veuillez saisir votre identifiant ou votre email.");
        }
        else if (mb_strlen($champs['pseudo']) < 2 || mb_strlen($champs['pseudo']) > 100)
        {
            $verif->setErreur("pseudo", "Votre identifiant fait entre 2 et 100 caractères.");
        }

        if ($motdepasse === "")
        {
            $verif->setErreur("motdepasse", "Veuillez saisir votre mot de passe.");
        }
        // borne haute alignée sur les formulaires qui fixent un mot de passe
        // (user/register.php, user-reset2.php) : plus basse, elle rendrait inutilisables
        // des mots de passe que le site a lui-même acceptés
        else if (mb_strlen($motdepasse) < 4 || mb_strlen($motdepasse) > 100)
        {
            $verif->setErreur("motdepasse", "Votre mot de passe fait entre 4 et 100 caractères.");
        }

        if ($verif->nbErreurs() === 0)
        {
            /*
             * checkLogin() redirige dans tous les cas : vers l'accueil si le compte se
             * vérifie, vers cette page avec un ?msg qui dit pourquoi sinon. Rien ne
             * doit donc être rendu ensuite.
             */
            $videur->checkLogin(
                $champs['pseudo'],
                $motdepasse,
                UserLevel::MEMBER,
                "/",
                '/user/login.php?msg=faux',
                $champs['memoriser']
            );

            exit();
        }
    }
}
// un ?msg inconnu (vieille url, passage de bot) laisse simplement le formulaire nu,
// là où la variante bloquante du validateur terminait la page sur une exception
else if (!empty($_GET['msg']) && QueryParamValidator::isAcceptedUrlQueryValue($_GET['msg'], "enum", array_keys($messages_echec)))
{
    $verif->setErreur("connexion", $messages_echec[$_GET['msg']]);
}

// =============================================================================
// Rendu
// =============================================================================

$page_titre = "Connexion";
// user/login.css est chargé automatiquement par _header.inc.php, d'après le nom de la page
$extra_css = ["formulaires"];
include("../_header.inc.php");

// jeton renouvelé à chaque affichage du formulaire
$_SESSION[$formTokenName] = bin2hex(random_bytes(32));

$erreurs = $verif->getErreurs();
?>

<main id="contenu" class="colonne connexion">

    <header id="entete_contenu">
        <h1>Connexion</h1>
        <div class="spacer"></div>
    </header>

    <?php if ($erreurs !== []) : ?>
    <?php // les messages sont écrits ici, jamais repris d'une saisie : ils peuvent porter du HTML ?>
    <div class="msg_erreur" role="alert">
        <?php if (count($erreurs) === 1) : ?>
        <?= current($erreurs) ?>
        <?php else : ?>
        <p class="msg_erreur_titre"><?= $verif->getMsgNbErreurs() ?></p>
        <ul>
            <?php foreach ($erreurs as $message) : ?>
            <li><?= $message ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <form id="ajouter_editer" action="/user/login.php" method="post">

        <input type="text" class="name_as" name="login_as" tabindex="-1" autocomplete="off" aria-hidden="true">
        <input type="hidden" name="<?= $formTokenName ?>" value="<?= $_SESSION[$formTokenName] ?>">

        <fieldset>
            <p>
                <label for="pseudo" id="login_pseudo">Login ou email</label>
                <input type="text" name="pseudo" id="pseudo" value="<?= sanitizeForHtml($champs['pseudo']) ?>" size="30" autocomplete="username" autofocus<?= isset($erreurs['pseudo']) ? ' class="champ_errone" aria-invalid="true"' : '' ?>>
            </p>
            <p>
                <label for="motdepasse" id="login_motdepasse">Mot de passe</label>
                <input type="password" name="motdepasse" id="motdepasse" value="" size="30" autocomplete="current-password"<?= isset($erreurs['motdepasse']) ? ' class="champ_errone" aria-invalid="true"' : '' ?>>
            </p>
            <p class="memoriser" id="login_memoriser">
                <label for="memoriser">Resté connecté-e</label>
                <input type="checkbox" name="memoriser" id="memoriser" value="1"<?= $champs['memoriser'] ? ' checked' : '' ?>>
            </p>

            <p class="liens_form">
                <a href="/user-reset.php">Mot de passe oublié ?</a>
                <a href="/user/register.php">Pas de compte ?</a>
            </p>

            <p class="piedForm">
                <input type="hidden" name="formulaire" value="ok">
                <input type="submit" name="Submit" value="Se connecter" class="submit submit-big">
            </p>

        </fieldset>

    </form>

</main>

<?php
include("../_footer.inc.php");
