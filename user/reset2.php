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
    /*
     * Comptes que cette demande permet de réinitialiser. La liste est établie une fois,
     * avant le traitement, et sert aussi bien à l'affichage qu'au contrôle de ce qui est
     * posté : les deux ne peuvent donc plus diverger, ce qui laissait auparavant exiger un
     * choix de compte sans qu'aucun choix ne soit proposé.
     *
     * Le filtre sur statut suit celui de Sentry, qui n'accepte au login que les comptes
     * actifs : changer le mot de passe d'un autre laisserait la personne bloquée ensuite.
     */
    if (!empty($tab_temp['idPersonne']))
    {
        // demande faite avec un pseudo, qui ne désigne qu'un compte
        $stmt = $connectorPdo->prepare("SELECT idPersonne, pseudo FROM personne
            WHERE idPersonne = :idPersonne AND statut = 'actif'");
        $stmt->execute([':idPersonne' => (int) $tab_temp['idPersonne']]);
        $tab_comptes = $stmt->fetchAll();
    }
    else if (!empty($tab_temp['email']))
    {
        // demande faite avec une adresse, que plusieurs comptes peuvent partager
        $stmt = $connectorPdo->prepare("SELECT idPersonne, pseudo FROM personne
            WHERE email = :email AND statut = 'actif'");
        $stmt->execute([':email' => $tab_temp['email']]);
        $tab_comptes = $stmt->fetchAll();
    }

    if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok' && $tab_comptes !== [])
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

            /*
             * Le compte à modifier se déduit de la demande, pas du formulaire : un candidat
             * unique s'impose de lui-même, et il n'y a de choix à faire — donc d'erreur
             * possible — que si plusieurs comptes partagent l'adresse fournie.
             */
            $idPersonne = null;

            if (count($tab_comptes) === 1)
            {
                $idPersonne = (int) $tab_comptes[0]['idPersonne'];
            }
            else
            {
                foreach ($tab_comptes as $c)
                {
                    if ((string) $c['idPersonne'] === $champs['idPersonne'])
                    {
                        $idPersonne = (int) $c['idPersonne'];
                    }
                }

                if ($idPersonne === null)
                {
                    $verif->setErreur("compte", $champs['idPersonne'] === ''
                        ? "Veuillez choisir le compte dont vous voulez changer le mot de passe."
                        : "Ce compte ne correspond pas à votre demande.");
                }
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

    <?php elseif ($tab_comptes === []) : ?>

    <?php // le compte a été désactivé entre la demande et le clic : afficher le formulaire
          // n'aurait mené qu'à une impasse, faute de compte à modifier ?>
    <div class="msg_erreur" role="alert">
        Aucun compte actif ne correspond à cette demande. Si votre compte a été désactivé,
        écrivez-nous à <a href="mailto:info@ladecadanse.ch">info@ladecadanse.ch</a>.
    </div>

    <?php else : ?>

    <form method="post" id="ajouter_editer" class="js-submit-freeze-wait" action="/user/reset2.php?token=<?= sanitizeForHtml($token) ?>">

        <input type="text" class="name_as" name="as_nom" tabindex="-1" autocomplete="off" aria-hidden="true">

        <fieldset>

            <?php // un compte unique n'est pas demandé au formulaire : le traitement le déduit
                  // de la demande, un champ caché ne ferait qu'offrir prise à une modification ?>
            <?php if (count($tab_comptes) > 1) : ?>
            <p>
                <label for="idPersonne">Lequel de vos comptes ?</label>
                <select name="idPersonne" id="idPersonne">
                    <?php foreach ($tab_comptes as $c) : ?>
                    <option value="<?= (int) $c['idPersonne'] ?>"<?= isset($_POST['idPersonne']) && $c['idPersonne'] == $_POST['idPersonne'] ? ' selected' : '' ?>><?= sanitizeForHtml($c['pseudo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
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
