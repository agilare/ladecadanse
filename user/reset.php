<?php

require_once("../app/bootstrap.php");

use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\Mailing;
use Ladecadanse\UserLevel;

// =============================================================================
// Traitement
//
// Tout se joue avant l'inclusion de _header.inc.php, comme dans user/login.php :
// les messages émis pendant le traitement sortaient auparavant avant le doctype.
// =============================================================================

if ($authorization->checkGroup(UserLevel::MEMBER))
{
    header("Location: /index.php");
    die();
}

$formTokenName = 'form_token_user_reset';

$champs = ["login_ou_email" => ""];

$demande_traitee = false;

$verif = new Validateur();

if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok')
{
    // honeypot : un champ masqué que seuls les robots remplissent
    if (!empty($_POST['name_as']))
    {
        $verif->setErreur("name_as", "Veuillez laisser vide le champ réservé aux robots.");
    }
    // le jeton reçu doit être celui déposé en session à l'affichage du formulaire
    else if (empty($_SESSION[$formTokenName]) || !hash_equals($_SESSION[$formTokenName], (string) ($_POST[$formTokenName] ?? '')))
    {
        $verif->setErreur("formulaire", "Le formulaire a expiré, veuillez le saisir à nouveau.");
    }
    else
    {
        unset($_SESSION[$formTokenName]);

        // is_scalar : le champ du formulaire est une valeur simple, un POST tableau ne
        // doit pas se retrouver dans une validation (cf. user/register.php)
        $saisie = $_POST['login_ou_email'] ?? '';
        $champs['login_ou_email'] = is_scalar($saisie) ? trim((string) $saisie) : '';

        /*
         * Messages écrits pour cette page plutôt que ceux de Validateur, génériques
         * et anglicisés (« Le texte est trop court : 1, min 2 characters »).
         */
        if ($champs['login_ou_email'] === "")
        {
            $verif->setErreur("login_ou_email", "Veuillez saisir votre identifiant ou votre email.");
        }
        else if (mb_strlen($champs['login_ou_email']) < 2 || mb_strlen($champs['login_ou_email']) > 100)
        {
            $verif->setErreur("login_ou_email", "Votre identifiant doit faire entre 2 et 100 caractères.");
        }

        if ($verif->nbErreurs() === 0)
        {
            /**
             * Compte correspondant à une des deux colonnes d'identification.
             *
             * Le filtre sur statut suit celui de Sentry, qui n'accepte au login que les
             * comptes actifs : sans lui, un compte désactivé recevait un lien de
             * réinitialisation qui ne pouvait mener nulle part, et la page 2 finissait en
             * impasse faute de compte à proposer.
             *
             * @param string $colonne Nom de colonne littéral (jamais une saisie)
             * @return array<string, mixed>|false
             */
            $chercherCompte = function (string $colonne, string $valeur) use ($connectorPdo): array|false
            {
                $stmt = $connectorPdo->prepare("SELECT idPersonne, email FROM personne
                    WHERE $colonne = :valeur AND statut = 'actif'");
                $stmt->execute([':valeur' => $valeur]);

                return $stmt->fetch();
            };

            /*
             * La demande retient soit l'idPersonne, soit l'e-mail, l'autre colonne restant
             * NULL. Le pseudo est unique, mais une adresse peut désigner plusieurs comptes :
             * une saisie qui correspond à un e-mail l'emporte donc et se retient telle
             * quelle, à charge pour la page 2 de faire départager les comptes.
             */
            $idPersonne = null;
            $user_email = null;
            $email_destinataire = '';
            $graine_du_jeton = '';

            if (($compte = $chercherCompte('pseudo', $champs['login_ou_email'])) !== false)
            {
                $idPersonne = (int) $compte['idPersonne'];
                $email_destinataire = $compte['email'];
                $graine_du_jeton = (string) $compte['idPersonne'];
            }

            if (($compte = $chercherCompte('email', $champs['login_ou_email'])) !== false)
            {
                $idPersonne = null;
                $user_email = $champs['login_ou_email'];
                $email_destinataire = $compte['email'];
                $graine_du_jeton = $champs['login_ou_email'];
            }

            if ($email_destinataire !== '')
            {
                $salt = "ciek48";

                // Create the unique user password reset key
                $token = hash('sha256', $salt . random_int(0, 1000) . $graine_du_jeton);

                // création de demande avec nouveau token
                $stmt = $connectorPdo->prepare("
                    INSERT INTO user_reset_requests (idPersonne, email, token, expiration)
                    VALUES (:idPersonne, :email, :token, NOW() + INTERVAL 1 DAY)");
                $stmt->execute([
                    ':idPersonne' => $idPersonne,
                    ':email' => $user_email,
                    ':token' => $token,
                ]);

                $mailer = new Mailing();
                $mailer->toUser(
                    $email_destinataire,
                    "Votre demande pour un nouveau mot de passe sur La décadanse",
                    $tplEngine->render("user-reset-mail-body", [
                        'reset_url' => $site_full_url . "user/reset2.php?token=" . $token,
                    ])
                );

                $logger->info('[user-reset] request', ['email' => $email_destinataire, 'idP' => $idPersonne]);
            }
            else
            {
                $logger->warning('[user-reset] request failed, user not found', ['login_ou_email' => $champs['login_ou_email']]);
            }

            // le message ne dit pas si le compte existe : il serait sinon un oracle
            // permettant d'énumérer les identifiants et les adresses du site
            $demande_traitee = true;
        }
    }
}

// =============================================================================
// Rendu
// =============================================================================

$page_titre = "Mot de passe oublié";
$extra_css = ["formulaires", "compte"];
include("../_header.inc.php");

$erreurs = $verif->getErreurs();
?>

<main id="contenu" class="colonne compte libelles_dessus">

    <header id="entete_contenu">
        <h1>Mot de passe oublié</h1>
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

    <?php if ($demande_traitee) : ?>

    <div class="msg_ok" role="status">
        Si l'identifiant ou email que vous avez fourni existe comme compte sur La décadanse,
        un email pour créer votre nouveau mot de passe vous a été envoyé
    </div>

    <?php else :

    // jeton renouvelé à chaque affichage du formulaire
    $_SESSION[$formTokenName] = bin2hex(random_bytes(32));
    ?>

    <p class="intro_form">Un email comportant un lien sécurisé pour créer votre nouveau mot de passe
        vous sera envoyé ; vous pourrez ensuite vous connecter avec celui-ci</p>

    <form id="ajouter_editer" class="js-submit-freeze-wait" action="/user/reset.php" method="post">

        <input type="text" class="name_as" name="name_as" tabindex="-1" autocomplete="off" aria-hidden="true">
        <input type="hidden" name="<?= $formTokenName ?>" value="<?= $_SESSION[$formTokenName] ?>">

        <fieldset>
            <p>
                <label for="login_ou_email">Identifiant ou e-mail de votre compte</label>
                <input type="text" name="login_ou_email" id="login_ou_email" value="" size="35" maxlength="100" autocomplete="username" autofocus required<?= isset($erreurs['login_ou_email']) ? ' class="champ_errone" aria-invalid="true"' : '' ?>>
            </p>

            <p class="liens_form">
                <a href="/user/login.php">Retour à la connexion</a>
            </p>

            <p class="piedForm">
                <input type="hidden" name="formulaire" value="ok">
                <input type="submit" name="Submit" value="Envoyer la demande" class="submit submit-big">
            </p>
        </fieldset>

    </form>

    <?php endif; ?>

</main>

<?php
include("../_footer.inc.php");
?>
