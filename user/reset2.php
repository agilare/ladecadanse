<?php

require_once("../app/bootstrap.php");

use Ladecadanse\UserLevel;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\PasswordPolicy;
use Ladecadanse\Utils\QueryParamValidator;

// =============================================================================
// Traitement
//
// Tout se joue avant l'inclusion de _header.inc.php, comme dans user/login.php :
// les messages émis pendant le traitement sortaient auparavant avant le doctype,
// et un jeton mal formé terminait la page sur une exception en plein HTML.
// =============================================================================

if ($authorization->checkGroup(UserLevel::MEMBER))
{
    header("Location: /index.php");
    die();
}

$champs = [
    "idPersonne" => '',
    "motdepasse" => '',
    "motdepasse2" => '',
];

$action_terminee = false;
$tab_temp = false;
$tab_comptes = [];

$verif = new Validateur();

/*
 * Un jeton absent ou mal formé (lien coupé par un client de messagerie, url bricolée)
 * vaut une demande invalide, pas une exception : le validateur lève, on l'attrape.
 */
$token = '';

try
{
    $token = (string) QueryParamValidator::validateUrlQueryValue($_GET['token'] ?? '', "alpha_numeric", 1);
}
catch (Exception)
{
    $token = '';
}

if ($token !== '')
{
    // vérification en tous temps; un seul enregistrement accepté
    $stmt = $connectorPdo->prepare("SELECT idPersonne, email FROM user_reset_requests
        WHERE token = :token AND expiration > NOW()");
    $stmt->execute([':token' => $token]);
    $demandes = $stmt->fetchAll();

    if (count($demandes) === 1)
    {
        $tab_temp = $demandes[0];
    }
}

if ($tab_temp !== false)
{
    if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok')
    {
        // honeypot : un champ masqué que seuls les robots remplissent
        if (!empty($_POST['as_nom']))
        {
            $verif->setErreur("as_nom", "Veuillez laisser vide le champ réservé aux robots.");
        }
        else
        {
            foreach ($champs as $c => $v)
            {
                // is_scalar : les champs du formulaire sont tous des valeurs simples,
                // un POST tableau ne doit pas se retrouver dans une validation
                if (isset($_POST[$c]) && is_scalar($_POST[$c]))
                {
                    $champs[$c] = (string) $_POST[$c];
                }
            }

            if (empty($tab_temp['idPersonne']) && empty($champs['idPersonne']))
            {
                $verif->setErreur("compte", "Veuillez choisir un compte.");
            }

            $tab_auth = null;

            // si l'email avait été fourni, vérification qu'il correspond bien au compte choisi
            if (!empty($tab_temp['email']) && !empty($champs['idPersonne']))
            {
                // retrouver user dans personne à partir de la demande ; si le demandeur a choisi un
                // compte parmi plusieurs qui ont son email, l'id doit correspondre à cet email
                $stmt = $connectorPdo->prepare("SELECT pseudo, idPersonne FROM personne
                    WHERE email = :email AND idPersonne = :idPersonne");
                $stmt->execute([':email' => $tab_temp['email'], ':idPersonne' => (int) $champs['idPersonne']]);
                $comptes_auth = $stmt->fetchAll();

                // on doit obtenir un seul compte
                if (count($comptes_auth) !== 1)
                {
                    $verif->setErreur("auth", "Vous n'êtes pas autorisé à modifier ce compte.");
                }
                else
                {
                    // un seul row donc
                    $tab_auth = $comptes_auth[0];
                }
            }

            $idPersonne = '';

            if (!empty($tab_temp['idPersonne']))
            {
                $idPersonne = $tab_temp['idPersonne'];
            }
            else if (!empty($tab_temp['email']))
            {
                $idPersonne = $tab_auth['idPersonne'] ?? '';
            }
            else
            {
                $verif->setErreur("utilisateur", "L'utilisateur n'a pu être retrouvé.");
            }

            foreach (PasswordPolicy::erreurs($champs['motdepasse'], $champs['motdepasse2']) as $champ => $message)
            {
                $verif->setErreur($champ, $message);
            }

            if ($verif->nbErreurs() === 0)
            {
                try
                {
                    // gds : sel du stockage historique (sha1), vidé partout où le mot de passe change
                    $stmt = $connectorPdo->prepare("UPDATE personne
                        SET mot_de_passe = :hash, gds = '', date_derniere_modif = NOW()
                        WHERE idPersonne = :idPersonne");
                    $stmt->execute([
                        ':hash' => password_hash($champs['motdepasse'], PASSWORD_DEFAULT),
                        ':idPersonne' => (int) $idPersonne,
                    ]);

                    $stmt = $connectorPdo->prepare("DELETE FROM user_reset_requests WHERE token = :token");
                    $stmt->execute([':token' => $token]);

                    $action_terminee = true;

                    $logger->info('[user-reset2] password reset success', ['idP' => $idPersonne]);
                }
                // PDO est en ERRMODE_EXCEPTION : sans ce catch, un échec d'écriture n'afficherait
                // plus rien à l'utilisateur
                catch (PDOException $e)
                {
                    $verif->setErreur("enregistrement", "La mise à jour a échoué, veuillez contacter le webmaster.");

                    $logger->error('[user-reset2] password reset failed', ['idP' => $idPersonne, 'exception' => $e->getMessage()]);
                }
            }
        }
    }

    // si l'email avait été fourni, retrouve le(s) compte(s) associé(s)
    if (empty($tab_temp['idPersonne']) && !empty($tab_temp['email']))
    {
        // statut : 'actif' et non '!= inactif', puisque Sentry n'accepte au login que les comptes actifs
        $stmt = $connectorPdo->prepare("SELECT idPersonne, pseudo FROM personne
            WHERE email = :email AND statut = 'actif'");
        $stmt->execute([':email' => $tab_temp['email']]);
        $tab_comptes = $stmt->fetchAll();
    }
}

// =============================================================================
// Rendu
// =============================================================================

$page_titre = "Réinitialisation du mot de passe";
$page_description = "";
$extra_css = ["formulaires", "compte"];
include("../_header.inc.php");

$erreurs = $verif->getErreurs();
?>

<main id="contenu" class="colonne compte libelles_dessus">

    <header id="entete_contenu">
        <h1>Réinitialisation du mot de passe</h1>
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

    <?php if ($tab_temp === false) : ?>

    <div class="msg_erreur" role="alert">
        Cette demande n'est plus valable. Les liens de réinitialisation expirent au bout de 24 h :
        veuillez <a href="/user/reset.php">en demander un nouveau</a>.
    </div>

    <?php elseif ($action_terminee) : ?>

    <div class="msg_ok" role="status">
        Le mot de passe a été mis à jour, vous pouvez maintenant vous
        <a href="/user/login.php">connecter</a> avec ce nouveau mot de passe
    </div>

    <?php else : ?>

    <form method="post" id="ajouter_editer" class="js-submit-freeze-wait" action="/user/reset2.php?token=<?= sanitizeForHtml($token) ?>">

        <input type="text" class="name_as" name="as_nom" tabindex="-1" autocomplete="off" aria-hidden="true">

        <fieldset>

            <?php if (count($tab_comptes) > 1) : ?>
            <p>
                <label for="idPersonne">Lequel de vos comptes ?</label>
                <select name="idPersonne" id="idPersonne">
                    <?php foreach ($tab_comptes as $c) : ?>
                    <option value="<?= (int) $c['idPersonne'] ?>"<?= isset($_POST['idPersonne']) && $c['idPersonne'] == $_POST['idPersonne'] ? ' selected' : '' ?>><?= sanitizeForHtml($c['pseudo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <?php elseif (count($tab_comptes) === 1) : ?>
            <input type="hidden" name="idPersonne" value="<?= (int) $tab_comptes[0]['idPersonne'] ?>">
            <?php endif; ?>

            <p>
                <label for="motdepasse">Nouveau mot de passe</label>
                <input type="password" name="motdepasse" id="motdepasse" size="20" minlength="<?= PasswordPolicy::LONGUEUR_MIN ?>" maxlength="<?= PasswordPolicy::LONGUEUR_MAX ?>" value="" autocomplete="new-password" required<?= isset($erreurs['motdepasse']) ? ' class="champ_errone" aria-invalid="true"' : '' ?>>
            </p>

            <p>
                <label for="motdepasse2">Confirmer le nouveau mot de passe</label>
                <input type="password" name="motdepasse2" id="motdepasse2" size="20" minlength="<?= PasswordPolicy::LONGUEUR_MIN ?>" maxlength="<?= PasswordPolicy::LONGUEUR_MAX ?>" value="" autocomplete="new-password" required<?= isset($erreurs['motdepasse_inegaux']) ? ' class="champ_errone" aria-invalid="true"' : '' ?>>
            </p>

            <div class="guideChamp">Le mot de passe doit faire au minimum <?= PasswordPolicy::LONGUEUR_MIN ?> caractères et comporter au moins un chiffre</div>

            <p class="piedForm">
                <input type="hidden" name="formulaire" value="ok">
                <input type="submit" value="Envoyer" class="submit submit-big">
            </p>

        </fieldset>

    </form>

    <?php endif; ?>

</main>

<?php
include("../_footer.inc.php");
?>
