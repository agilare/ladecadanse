<?php
require_once("../app/bootstrap.php");

use Ladecadanse\HtmlShrink;
use Ladecadanse\Personne;
use Ladecadanse\Security\SecurityToken;
use Ladecadanse\UserEdition;
use Ladecadanse\UserLevel;

/*
 * Le compte lui-même : adresse, et pour le superadmin nom d'utilisateur, groupe et statut. Le
 * traitement vit dans UserEdition (#289) ; le mot de passe se change dans user/pwd-edit.php (#249),
 * le profil d'organisateur d'événements dans user/contributor-edit.php (#288) ; les comptes se
 * créent par user/register.php.
 */

// =============================================================================
// Accès
// =============================================================================

/*
 * Tout compte modifie le sien, MEMBER (12) compris. Sans session, direction la page de
 * connexion : un 403 envoyé avant n'y changeait rien, l'en-tête Location le remplaçant
 * d'office par un 302.
 */
if (!$authorization->checkGroup(UserLevel::MEMBER)) {
    header("Location: /user/login.php");
    die();
}

// Le paramètre `action` des liens existants (« editer ») n'a plus de rôle, il est simplement ignoré
$idP = (int) ($_GET['idP'] ?? 0);
$is_superadmin = (int) $_SESSION['Sgroupe'] === UserLevel::SUPERADMIN;
$is_own_account = (int) $_SESSION['SidPersonne'] === $idP;

$user_form = new UserEdition();
$user_form->setSuperadmin($is_superadmin);

// Chaque refus est rendu dans la page du site par _erreur_http.inc.php
$http_error = null;

if ($idP <= 0)
{
    $http_error = [400, 'Bad Request', "Aucun compte n'est désigné"];
}
elseif (!$is_superadmin && !$is_own_account)
{
    $http_error = [403, 'Forbidden', "Vous n'avez pas les droit pour effectuer cette action"];
}
elseif (!$user_form->loadValues($idP))
{
    $http_error = [404, 'Not Found', "Cet utilisateur n'existe pas ou plus"];
}

if ($http_error !== null)
{
    include("../_erreur_http.inc.php");
    exit;
}

// =============================================================================
// Traitement
//
// Tout se joue avant l'inclusion de _header.inc.php : un enregistrement réussi se termine par
// une redirection vers la fiche du profil (POST/Redirect/GET).
// =============================================================================

if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok')
{
    // La clé est "global", rendue sous le décompte des erreurs pour tous les niveaux
    if (!SecurityToken::check($_POST['token'] ?? '', $_SESSION['token'] ?? ''))
    {
        $user_form->setErreur("global", "Le système de sécurité du site n'a pu authentifier votre action. Veuillez réafficher ce formulaire et réessayer");
    }
    elseif ($user_form->processSubmission($_POST, $_FILES))
    {
        // MAJ des infos perso de session si la personne s'autoédite
        if ($is_own_account)
        {
            $_SESSION["user"] = (string) $user_form->getValeur('pseudo');
            $_SESSION['Sgroupe'] = (int) $user_form->getValeur('groupe');
        }

        $logger->info('[user-edit] user updated', ['pseudo' => $user_form->getValeur('pseudo'), 'idP' => $idP, 'by' => (int) $_SESSION['SidPersonne']]);

        $_SESSION['user_flash_msg'] = $is_own_account ? "Votre profil a été modifié" : "Le profil a été modifié";
        header("Location: /user/dashboard.php?idP=" . $idP);
        die();
    }
}

// =============================================================================
// Préparation du rendu
// =============================================================================

$pseudo = (string) $user_form->getValeur('pseudo');

// les pages voisines, selon la règle d'accès de chacune
$can_edit_password = $is_superadmin || $is_own_account;
$is_contributor_account = $user_form->getStored()['groupe'] <= UserLevel::ACTOR;

// =============================================================================
// Rendu
// =============================================================================

$page_titre = "modifier un compte";
$extra_css = ["formulaires"];
include("../_header.inc.php");
?>

<main id="contenu" class="colonne user-edit">

    <header id="entete_contenu">
        <h1>Modification du compte</h1>
        <div class="spacer"></div>
    </header>

    <?php
    if ($user_form->hasErrors())
    {
        HtmlShrink::msgErreur("Il y a " . $user_form->getErrorCount() . " erreur(s)");
        // les erreurs qu'aucun champ ne porte, comme le refus du jeton CSRF
        echo $user_form->getHtmlErreur("global");
    }
    ?>


    <form method="post" id="ajouter_editer" class="js-submit-freeze-wait"
          action="<?= basename(__FILE__) . "?idP=" . $idP ?>" >

        <p>* indique un champ obligatoire</p>

        <?php // un seul groupe de champs : la page n'a plus que le compte, le titre suffit à le nommer ?>
        <fieldset>

            <p>
                <label for="pseudo">Nom d'utilisateur</label>
                <?php if ($is_superadmin) : ?>
                    <input type="text" name="pseudo" id="pseudo" size="30" maxlength="<?= Personne::FIELDS['pseudo']['max'] ?>" value="<?= sanitizeForHtml($pseudo) ?>" />
                    <?= $user_form->getHtmlErreur('pseudo') ?>
                    <?= $user_form->getHtmlErreur("pseudoIdentique") ?>
                <?php elseif (trim($pseudo) !== '') : ?>
                    <input type="text" name="pseudo" id="pseudo" size="30" maxlength="<?= Personne::FIELDS['pseudo']['max'] ?>" value="<?= sanitizeForHtml($pseudo) ?>" readonly />
                <?php else : ?>
                    <?php /* Ni champ ni valeur : ce compte n'a pas de nom d'utilisateur */ ?>
                    <span class="sans-valeur">aucun</span>
                <?php endif; ?>
            </p>
            <div class="guideChamp">
                <?php if (trim($pseudo) !== '' || $is_superadmin) : ?>
                    Permet de se connecter, comme l'e-mail, et peut signer les événements ajoutés
                <?php else : ?>
                    C'est votre e-mail qui vous identifie
                <?php endif; ?>
            </div>

            <p>
                <label for="email">E-mail*</label>
                <input type="email" name="email" id="email" size="40" maxlength="<?= Personne::FIELDS['email']['max'] ?>" value="<?= sanitizeForHtml(stripslashes((string) $user_form->getValeur('email'))) ?>" required />
                <?= $user_form->getHtmlErreur("email") ?>
                <?= $user_form->getErreur("emailIdentique") ?>
            </p>
            <div class="guideChamp">Pour se connecter et recevoir un lien en cas de mot de passe oublié</div>

            <?php if ($is_superadmin) : ?>
                <?php /* sa légende tient la place d'un libellé ; un niveau par ligne, chacun avec
                         ce qu'il permet, pour décider en connaissance de cause */ ?>
                <fieldset class="groupe-radios">
                    <legend>Groupe*</legend>
                    <ul class="radio vertical">
                    <?php foreach ($user_form->getGroupChoices() as $id => $choice) : ?>
                        <li>
                            <input type="radio" name="groupe" value="<?= $id ?>" id="groupe_<?= $id ?>" class="radio_horiz" aria-describedby="groupe_<?= $id ?>_aide"
                                <?php if ($id == $user_form->getValeur('groupe')) : ?> checked="checked" <?php endif; ?>
                                <?php if ($choice['locked']) : ?> disabled="disabled" <?php endif; ?> />
                            <label for="groupe_<?= $id ?>" class="continu"><?= sanitizeForHtml($choice['label']) ?></label>
                            <div class="aide-choix" id="groupe_<?= $id ?>_aide"><?= sanitizeForHtml($choice['description']) ?></div>
                        </li>
                    <?php endforeach ?>
                    </ul>
                    <?= $user_form->getHtmlErreur("groupe") ?>
                </fieldset>

                <fieldset class="groupe-radios">
                    <legend>Statut</legend>
                    <ul class="radio">
                    <?php foreach ($user_form->getStatusChoices() as $s) : ?>
                        <li>
                            <input type="radio" name="statut" value="<?= sanitizeForHtml($s) ?>"
                                <?php if ($s == $user_form->getValeur('statut')) : ?> checked="checked" <?php endif; ?>
                                <?php if (!in_array($s, Personne::$statuts, true)) : ?> disabled="disabled" <?php endif; ?>
                                id="statut_<?= sanitizeForHtml($s) ?>" class="radio_horiz" />
                            <label for="statut_<?= sanitizeForHtml($s) ?>" class="continu"><?= sanitizeForHtml($s) ?></label>
                        </li>
                    <?php endforeach ?>
                    </ul>
                    <?= $user_form->getHtmlErreur("statut") ?>
                </fieldset>
            <?php endif; ?>

            <?php // les deux autres formulaires du compte ?>
            <ul class="pages-voisines">
                <?php if ($can_edit_password) : ?>
                <li><a href="/user/pwd-edit.php?idP=<?= $idP ?>">Modifier le mot de passe</a></li>
                <?php endif; ?>
                <?php if ($is_contributor_account) : ?>
                <li><a href="/user/contributor-edit.php?idP=<?= $idP ?>">Modifier le profil d'organisateur d'événements</a></li>
                <?php endif; ?>
            </ul>
        </fieldset>

        <p class="piedForm">
            <input type="hidden" name="formulaire" value="ok" />
            <input type="hidden" name="token" value="<?= SecurityToken::getToken(); ?>" />
            <input type="submit" value="Enregistrer" class="submit submit-big" />
        </p>

    </form>

</main>

<div id="colonne_gauche" class="colonne"></div>

<?php
include("../_footer.inc.php");
?>
