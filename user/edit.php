<?php
require_once("../app/bootstrap.php");

use Ladecadanse\UserLevel;
use Ladecadanse\Utils\PostedFields;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Security\SecurityToken;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Personne;

/*
 * Le compte lui-même : adresse, et pour le superadmin nom d'utilisateur, groupe et statut. Le
 * mot de passe se change dans user/pwd-edit.php (#249), le profil d'organisateur d'événements
 * dans user/contributor-edit.php (#288) ; les comptes se créent par user/register.php.
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

/*
 * Le paramètre `action` des liens existants (« editer ») n'a plus de rôle, il est simplement ignoré.
 */
$get = [
    'idP' => (int) ($_GET['idP'] ?? 0),
];

$is_superadmin = (int) $_SESSION['Sgroupe'] === UserLevel::SUPERADMIN;

// Chaque refus est rendu dans la page du site par _erreur_http.inc.php
$http_error = null;

if ($get['idP'] <= 0)
{
    $http_error = [400, 'Bad Request', "Aucun compte n'est désigné"];
}
elseif (!$is_superadmin && (int) $_SESSION['SidPersonne'] !== $get['idP'])
{
    $http_error = [403, 'Forbidden', "Vous n'avez pas les droit pour effectuer cette action"];
}

if ($http_error !== null)
{
    include("../_erreur_http.inc.php");
    exit;
}

/*
 * Le compte modifié, relu à l'affichage comme à la soumission. Sans ce contrôle, un
 * identifiant inconnu rendait un formulaire vide, et l'UPDATE qui suivait ne touchait
 * aucune ligne en annonçant une réussite.
 */
$stmt = $connectorPdo->prepare("SELECT idPersonne, pseudo, email, groupe, statut FROM personne WHERE idPersonne = ?");
$stmt->execute([$get['idP']]);
$stored_personne = $stmt->fetch(PDO::FETCH_ASSOC);

if ($stored_personne === false)
{
    $http_error = [404, 'Not Found', "Cet utilisateur n'existe pas ou plus"];
    include("../_erreur_http.inc.php");
    exit;
}

// Dans l'ordre du formulaire ; ce sont aussi les seules colonnes que l'UPDATE écrit
$champs = [
    "statut" => (string) $stored_personne['statut'],
    "pseudo" => (string) $stored_personne['pseudo'],
    "email" => (string) $stored_personne['email'],
    "groupe" => (int) $stored_personne['groupe'],
];

$user_levels = UserLevel::getConstants();

$verif = new Validateur();


// =============================================================================
// Traitement
//
// Trois temps : lire la saisie et la normaliser, la valider, puis l'enregistrer. Tout se
// joue avant l'inclusion de _header.inc.php : un enregistrement réussi se termine par une
// redirection vers la fiche du profil (POST/Redirect/GET).
// =============================================================================

if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok')
{
    // -------------------------------------------------------------------------
    // 1. Lecture de la saisie
    //
    // Ce que le compte ne peut pas changer garde sa valeur en base : la validation et
    // l'enregistrement ne voient plus que ce qui doit être écrit.
    // -------------------------------------------------------------------------

    // l'adresse pour tous ; nom, groupe et statut pour le superadmin seul, quoi que porte le POST
    $modifiables = $is_superadmin ? ['email', 'pseudo', 'groupe', 'statut'] : ['email'];

    // un champ absent ou forgé en tableau garde sa valeur en base
    $champs = array_merge($champs, PostedFields::scalars($_POST, $modifiables));

    /*
     * Une valeur héritée hors de la liste (le groupe 10, le statut « demande ») est rendue cochée
     * mais désactivée : le navigateur ne la poste pas, et la valeur en base est gardée.
     */

    // -------------------------------------------------------------------------
    // 2. Validation
    // -------------------------------------------------------------------------

    // Longueurs et obligation viennent de Personne::FIELDS, dont le formulaire tire aussi ses
    // maxlength
    foreach (['pseudo', 'email'] as $field)
    {
        // Le pseudo seulement s'il change : un pseudo caduque (hors des règles) bloquerait sinon l'enregistrement de l'adresse
        if ($field === 'pseudo' && $champs['pseudo'] === (string) $stored_personne['pseudo'])
        {
            continue;
        }

        $rule = Personne::FIELDS[$field];
        $verif->valider($champs[$field], $field, $rule['type'], $rule['min'], $rule['max'], $rule['required']);
    }

    // Seule la valeur déjà en base (ancien compte) échappe à la liste des choix permis
    if (!in_array((string) $champs['groupe'], array_map('strval', array_merge(array_values($user_levels), [$stored_personne['groupe']])), true))
    {
        $verif->setErreur("groupe", "Choisissez un groupe parmi ceux proposés");
    }

    if (!in_array($champs['statut'], array_merge(Personne::$statuts, [$stored_personne['statut']]), true))
    {
        $verif->setErreur("statut", "Choisissez un statut parmi ceux proposés");
    }

    /*
     * Nom d'utilisateur changé par le superadmin : contrôlé seulement s'il change. Des comptes
     * anciens portent un nom partagé ou en forme d'adresse, et les refuser tels quels les
     * empêcherait d'enregistrer quoi que ce soit.
     */
    if ($champs['pseudo'] !== '' && $champs['pseudo'] !== (string) $stored_personne['pseudo'])
    {
        // vide, il est partagé par tous les comptes qui n'en ont pas : seul un nom renseigné est unique
        if (Personne::pseudoExists((string) $champs['pseudo'], $get['idP']))
        {
            // messages rendus tels quels par getHtmlErreur() et getErreur() : la saisie s'y échappe
            $verif->setErreur("pseudoIdentique", "Un membre ".sanitizeForHtml($champs['pseudo'])." existe déjà dans la base.");
        }

        /*
         * Un nom d'utilisateur en forme d'adresse rendrait ambiguë la saisie de connexion :
         * Sentry::checkLogin() cherche alors « pseudo OU email », et deux comptes peuvent
         * répondre — dont un sans pseudo, qui n'a plus alors aucun moyen de se connecter.
         */
        if (filter_var((string) $champs['pseudo'], FILTER_VALIDATE_EMAIL) !== false)
        {
            $verif->setErreur("pseudo", "Le nom d'utilisateur ne peut pas être une adresse e-mail.");
        }
    }

    /*
     * Valider l'unicité de l'adresse email seulement si elle change : un compte dont l'adresse est déjà
     * partagée — il en reste des centaines en production — ne pourrait sinon plus rien
     * enregistrer.
     */
    if (mb_strtolower(trim((string) $champs['email'])) !== mb_strtolower((string) $stored_personne['email'])
        && Personne::emailExists((string) $champs['email'], $get['idP']))
    {
        $verif->setErreur("emailIdentique", "Cette adresse e-mail est déjà celle d'un autre compte.");
    }

    // La clé est "global", rendue sous le décompte des erreurs pour tous les niveaux
    if (!SecurityToken::check($_POST['token'] ?? '', $_SESSION['token'] ?? ''))
    {
        $verif->setErreur("global", "Le système de sécurité du site n'a pu authentifier votre action. Veuillez réafficher ce formulaire et réessayer");
    }

    // -------------------------------------------------------------------------
    // 3. Enregistrement, si la saisie est valide
    // -------------------------------------------------------------------------

    if ($verif->nbErreurs() === 0)
    {
        $stmt = $connectorPdo->prepare("UPDATE personne
            SET statut = :statut, pseudo = :pseudo, email = :email, groupe = :groupe, date_derniere_modif = :modif
            WHERE idPersonne = :idP");
        $stmt->execute([
            'statut' => (string) $champs['statut'],
            'pseudo' => (string) $champs['pseudo'],
            'email' => (string) $champs['email'],
            'groupe' => (int) $champs['groupe'],
            'modif' => date("Y-m-d H:i:s"),
            'idP' => $get['idP'],
        ]);

        // MAJ des infos perso de session si la personne s'autoédite
        if ((int) $_SESSION['SidPersonne'] === $get['idP'])
        {
            $_SESSION["user"] = $champs['pseudo'];
            $_SESSION['Sgroupe'] = (int) $champs['groupe'];
            $message_succes = "Votre profil a été modifié";
        }
        else
        {
            $message_succes = "Le profil a été modifié";
        }

        $logger->info('[user-edit] user updated', ['pseudo' => $champs['pseudo'], 'idP' => $get['idP'], 'by' => (int) $_SESSION['SidPersonne']]);

        $_SESSION['user_flash_msg'] = $message_succes;
        header("Location: /user/dashboard.php?idP=" . $get['idP']);
        die();
    }
}


// =============================================================================
// Préparation du rendu
// =============================================================================

/*
 * Choix rendus en boutons radio : les valeurs permises, plus celle du compte si elle n'en fait pas
 * partie (groupe 10 hérité, statut « demande »), cochée et désactivée.
 */
$groupe_choices = [];

foreach ($user_levels as $nom => $id)
{
    $groupe_choices[(int) $id] = ['label' => $id . ' : ' . $nom, 'locked' => false];
}

if (!isset($groupe_choices[(int) $stored_personne['groupe']]))
{
    $groupe_choices[(int) $stored_personne['groupe']] = ['label' => $stored_personne['groupe'] . ' : ancien niveau', 'locked' => true];
}

$statut_choices = Personne::$statuts;

if (!in_array($stored_personne['statut'], $statut_choices, true))
{
    $statut_choices[] = (string) $stored_personne['statut'];
}

// les pages voisines, selon la règle d'accès de chacune
$can_edit_password = $is_superadmin || (int) $_SESSION['SidPersonne'] === $get['idP'];
$is_contributor_account = (int) $stored_personne['groupe'] <= UserLevel::ACTOR;

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
    if ($verif->nbErreurs() > 0)
    {
        HtmlShrink::msgErreur("Il y a ".$verif->nbErreurs()." erreur(s)");
        // les erreurs qu'aucun champ ne porte, comme le refus du jeton CSRF
        echo $verif->getHtmlErreur("global");
    }
    ?>


    <form method="post" id="ajouter_editer" class="js-submit-freeze-wait"
          action="<?= basename(__FILE__) . "?idP=" . $get['idP'] ?>" >

        <p>* indique un champ obligatoire</p>

        <?php if ($is_superadmin) : ?>

            <fieldset>
                <legend>Statut</legend>
                <ul class="radio">
                <?php foreach ($statut_choices as $s) : ?>
                    <li class="listehoriz">
                        <input type="radio" name="statut" value="<?= sanitizeForHtml($s) ?>"
                            <?php if ($s == $champs['statut']) : ?> checked="checked" <?php endif; ?>
                            <?php if (!in_array($s, Personne::$statuts, true)) : ?> disabled="disabled" <?php endif; ?>
                            id="statut_<?= sanitizeForHtml($s) ?>" class="radio_horiz" />
                        <label for="statut_<?= sanitizeForHtml($s) ?>" class="continu"><?= sanitizeForHtml($s) ?></label>
                    </li>
                <?php endforeach ?>
                </ul>
                <?= $verif->getHtmlErreur("statut") ?>
            </fieldset>

        <?php endif; ?>

        <fieldset>

            <legend>Compte</legend>
            <p>
                <label for="pseudo">Nom d'utilisateur</label>
                <?php if ($is_superadmin) : ?>
                    <input type="text" name="pseudo" id="pseudo" size="30" maxlength="<?= Personne::FIELDS['pseudo']['max'] ?>" value="<?= sanitizeForHtml($champs['pseudo']) ?>" />
                    <?= $verif->getHtmlErreur('pseudo') ?>
                    <?= $verif->getHtmlErreur("pseudoIdentique") ?>
                <?php elseif (trim((string) $champs['pseudo']) !== '') : ?>
                    <input type="text" name="pseudo" id="pseudo" size="30" maxlength="<?= Personne::FIELDS['pseudo']['max'] ?>" value="<?= sanitizeForHtml($champs['pseudo']) ?>" readonly />
                <?php else : ?>
                    <?php /* Ni champ ni valeur : ce compte n'a pas de nom d'utilisateur */ ?>
                    <span class="sans-valeur">aucun</span>
                <?php endif; ?>
            </p>
            <div class="guideChamp">
                <?php if (trim((string) $champs['pseudo']) !== '' || $is_superadmin) : ?>
                    Permet de se connecter, comme l'e-mail, et peut signer les événements ajoutés
                <?php else : ?>
                    C'est votre e-mail qui vous identifie
                <?php endif; ?>
            </div>

            <p>
                <label for="email">E-mail*</label>
                <input type="email" name="email" id="email" size="40" maxlength="<?= Personne::FIELDS['email']['max'] ?>" value="<?= sanitizeForHtml(stripslashes((string) $champs['email'])) ?>" required />
                <?= $verif->getHtmlErreur("email") ?>
                <?= $verif->getErreur("emailIdentique") ?>
            </p>
            <div class="guideChamp">Pour se connecter et recevoir un lien en cas de mot de passe oublié</div>

            <?php if ($is_superadmin) : ?>
                <?php /* sa légende tient la place d'un libellé */ ?>
                <fieldset class="groupe-radios">
                    <legend>Groupe*</legend>
                    <ul class="radio mobile-vertical">
                    <?php foreach ($groupe_choices as $id => $choice) : ?>
                        <li class="listehoriz">
                            <input type="radio" name="groupe" value="<?= $id ?>" id="groupe_<?= $id ?>" class="radio_horiz"
                                <?php if ($id == $champs['groupe']) : ?> checked="checked" <?php endif; ?>
                                <?php if ($choice['locked']) : ?> disabled="disabled" <?php endif; ?> />
                            <label for="groupe_<?= $id ?>" class="continu"><?= sanitizeForHtml($choice['label']) ?></label>
                        </li>
                    <?php endforeach ?>
                    </ul>
                    <?= $verif->getHtmlErreur("groupe") ?>
                </fieldset>
            <?php endif; ?>

            <?php // les deux autres formulaires du compte ?>
            <ul class="pages-voisines">
                <?php if ($can_edit_password) : ?>
                <li><a href="/user/pwd-edit.php?idP=<?= $get['idP'] ?>">Modifier le mot de passe</a></li>
                <?php endif; ?>
                <?php if ($is_contributor_account) : ?>
                <li><a href="/user/contributor-edit.php?idP=<?= $get['idP'] ?>">Modifier le profil d'organisateur d'événements</a></li>
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
