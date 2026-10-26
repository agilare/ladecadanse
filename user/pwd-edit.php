<?php

require_once("../app/bootstrap.php");

use Ladecadanse\Personne;
use Ladecadanse\Security\SecurityToken;
use Ladecadanse\Security\Sentry;
use Ladecadanse\UserLevel;
use Ladecadanse\Utils\PasswordPolicy;
use Ladecadanse\Utils\Validateur;

/*
 * Changement de mot de passe d'un compte (#249), sorti du formulaire de compte : sa propre
 * vérification, sa propre validation, sa propre ligne de journal, et un balisage que les
 * gestionnaires de mots de passe reconnaissent comme un formulaire de changement.
 */

// =============================================================================
// Accès
// =============================================================================

if (!$authorization->checkGroup(UserLevel::MEMBER))
{
    header("Location: /user/login.php");
    die();
}

$idP = (int) ($_GET['idP'] ?? 0);
$is_own_account = (int) $_SESSION['SidPersonne'] === $idP;

// Chaque refus est rendu dans la page du site par _erreur_http.inc.php
$http_error = null;

if ($idP <= 0)
{
    $http_error = [400, 'Bad Request', "Aucun compte n'est désigné"];
}
// le compte d'un autre : le superadmin seul, comme pour user/edit.php
elseif (!$is_own_account && (int) $_SESSION['Sgroupe'] !== UserLevel::SUPERADMIN)
{
    $http_error = [403, 'Forbidden', "Vous ne pouvez pas modifier le mot de passe de ce compte"];
}

if ($http_error === null)
{
    $stmt = $connectorPdo->prepare("SELECT pseudo, email, mot_de_passe, gds FROM personne WHERE idPersonne = ?");
    $stmt->execute([$idP]);
    $compte = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($compte === false)
    {
        $http_error = [404, 'Not Found', "Cet utilisateur n'existe pas ou plus"];
    }
}

if ($http_error !== null)
{
    include("../_erreur_http.inc.php");
    exit;
}

// =============================================================================
// Traitement
// =============================================================================

$verif = new Validateur();

if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok')
{
    // le mot de passe n'est pas trimé : une espace initiale ou finale en fait partie
    $actuel = isset($_POST['motdepasse_actuel']) && is_scalar($_POST['motdepasse_actuel']) ? (string) $_POST['motdepasse_actuel'] : '';
    $nouveau = isset($_POST['motdepasse']) && is_scalar($_POST['motdepasse']) ? (string) $_POST['motdepasse'] : '';

    if (!SecurityToken::check($_POST['token'] ?? '', $_SESSION['token'] ?? ''))
    {
        $verif->setErreur("global", "Le système de sécurité du site n'a pu authentifier votre action. Veuillez réafficher ce formulaire et réessayer");
    }

    /*
     * Son propre mot de passe se change en donnant l'actuel, superadmin compris : une session
     * laissée ouverte ne doit pas suffire à prendre le compte. Double vérification, comme
     * Sentry::checkLogin() : password_hash(), puis le sha1 salé historique.
     */
    if ($is_own_account)
    {
        if ($actuel === '')
        {
            $verif->setErreur("motdepasse_actuel", "Veuillez saisir votre mot de passe actuel.");
        }
        elseif (!password_verify($actuel, (string) $compte['mot_de_passe'])
            && !hash_equals((string) $compte['mot_de_passe'], sha1($compte['gds'] . sha1($actuel))))
        {
            $verif->setErreur("motdepasse_actuel", "Le mot de passe actuel est faux.");
        }
    }

    // un seul champ, comme à l'inscription et à la réinitialisation : le bouton « Afficher »
    // remplace la confirmation
    foreach (PasswordPolicy::erreurs($nouveau) as $champ => $message)
    {
        $verif->setErreur($champ, $message);
    }

    if ($verif->nbErreurs() === 0)
    {
        $hash = password_hash($nouveau, PASSWORD_DEFAULT);

        // gds : sel du stockage historique (sha1), vidé partout où le mot de passe change
        $stmt = $connectorPdo->prepare("UPDATE personne
            SET mot_de_passe = :hash, gds = '', password_updated = NOW(), date_derniere_modif = NOW()
            WHERE idPersonne = :idP");
        $stmt->execute([':hash' => $hash, ':idP' => $idP]);

        /*
         * Les autres sessions du compte ne correspondent plus à l'empreinte du mot de passe et
         * se ferment à leur requête suivante, y compris quand le superadmin change celui d'un
         * autre ; les appareils mémorisés sont oubliés. La session en cours, elle, reprend la
         * nouvelle empreinte, sans quoi Sentry::checkSession() la fermerait aussi.
         */
        $videur->revokeRememberedDevices($idP);

        if ($is_own_account)
        {
            $_SESSION['pass_fingerprint'] = Sentry::passFingerprint($hash);
        }

        $logger->info('[user-pwd-edit] password updated', ['idP' => $idP, 'by' => (int) $_SESSION['SidPersonne']]);

        $_SESSION['user_flash_msg'] = "Le mot de passe a été modifié";
        header("Location: /user/dashboard.php?idP=" . $idP);
        die();
    }
}

// =============================================================================
// Rendu
// =============================================================================

$page_titre = "Modifier le mot de passe";
$extra_css = ["formulaires", "compte"];
include("../_header.inc.php");

$erreurs = $verif->getErreurs();
?>

<main id="contenu" class="colonne compte libelles_dessus">

    <header id="entete_contenu">
        <h1>Modifier le mot de passe</h1>
        <div class="spacer"></div>
    </header>

    <?php if (!$is_own_account) : ?>
    <p class="intro_form">Compte de <strong><?= sanitizeForHtml(Personne::displayName($compte['pseudo'], $compte['email'])) ?></strong></p>
    <?php endif; ?>

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

    <form method="post" id="ajouter_editer" class="js-submit-freeze-wait" action="/user/pwd-edit.php?idP=<?= $idP ?>">

        <fieldset>

            <?php /* Sans ce champ, Firefox et Chrome ne reconnaissent pas un formulaire de
                     changement et ne proposent pas de mettre à jour le mot de passe enregistré.
                     Il porte ce qui sert à se connecter : le nom d'utilisateur, ou l'adresse. */ ?>
            <input type="text" name="username" autocomplete="username" value="<?= sanitizeForHtml(trim((string) $compte['pseudo']) !== '' ? $compte['pseudo'] : $compte['email']) ?>" hidden>

            <?php if ($is_own_account) : ?>
            <p>
                <label for="motdepasse_actuel">Mot de passe actuel</label>
                <span class="password-field">
                    <input type="password" name="motdepasse_actuel" id="motdepasse_actuel" size="20" value="" autocomplete="current-password" required<?= isset($erreurs['motdepasse_actuel']) ? ' class="champ_errone" aria-invalid="true"' : '' ?>>
                    <button type="button" class="js-toggle-password" data-target="motdepasse_actuel" aria-pressed="false" aria-label="Afficher le mot de passe" title="Afficher le mot de passe"><i class="fa fa-eye" aria-hidden="true"></i></button>
                </span>
            </p>
            <?php endif; ?>

            <?php /* Un seul champ, avec de quoi relire sa saisie : la confirmation ne
                     protégeait que de la frappe en aveugle, et doublait l'effort. */ ?>
            <p>
                <label for="motdepasse">Nouveau mot de passe</label>
                <span class="password-field">
                    <input type="password" name="motdepasse" id="motdepasse" size="20" minlength="<?= PasswordPolicy::LONGUEUR_MIN ?>" maxlength="<?= PasswordPolicy::LONGUEUR_MAX ?>" value="" autocomplete="new-password" required<?= isset($erreurs['motdepasse']) ? ' class="champ_errone" aria-invalid="true"' : '' ?>>
                    <button type="button" class="js-toggle-password" data-target="motdepasse" aria-pressed="false" aria-label="Afficher le mot de passe" title="Afficher le mot de passe"><i class="fa fa-eye" aria-hidden="true"></i></button>
                </span>
            </p>

            <div class="guideChamp">Au minimum <?= PasswordPolicy::LONGUEUR_MIN ?> caractères. Une phrase dont vous vous souvenez vaut mieux qu'un mot compliqué. Les autres appareils connectés à ce compte seront déconnectés.</div>

            <p class="piedForm">
                <input type="hidden" name="formulaire" value="ok">
                <input type="hidden" name="token" value="<?= SecurityToken::getToken() ?>">
                <input type="submit" value="Enregistrer" class="submit submit-big">
            </p>

        </fieldset>

    </form>

</main>

<?php
include("../_footer.inc.php");
?>
