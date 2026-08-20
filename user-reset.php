<?php

require_once("app/bootstrap.php");

use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\Mailing;
use Ladecadanse\HtmlShrink;
use Ladecadanse\UserLevel;

if ($authorization->checkGroup(UserLevel::MEMBER)) {
	header("Location: index.php"); die();
}

$page_titre = "Mot de passe oublié";
$extra_css = ["formulaires"];
include("_header.inc.php");
?>

<main id="contenu" class="colonne">

    <header id="entete_contenu">
        <h1>Mot de passe oublié</h1>
        <div class="spacer"></div>
    </header>


<?php
$termine = false;
$champs = ["login_ou_email" => ""];
$formTokenName = 'form_token_user_reset';

$verif = new Validateur();

if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok' && empty($_POST['name_as']))
{
    // check token received == token initially set in form registered in session
    if (!isset($_SESSION[$formTokenName]) || ($_POST[$formTokenName] ?? '') !== $_SESSION[$formTokenName])
    {
        HtmlShrink::msgErreur("Désolé, le formulaire est expiré, veuillez le saisir à nouveau");
    }
    else
    {
        unset($_SESSION[$formTokenName]);

        // is_scalar : le champ du formulaire est une valeur simple, un POST tableau ne
        // doit pas se retrouver dans une validation (cf. user-register.php)
        $saisie = $_POST['login_ou_email'] ?? '';
        $champs['login_ou_email'] = is_scalar($saisie) ? trim((string) $saisie) : '';

        $verif->valider($champs['login_ou_email'], "login_ou_email", "texte", 2, 100, 1);

        //Si le pseudo et le mot de passe sont au bon format
        if ($verif->nbErreurs() == 0)
        {
            // la demande retient soit l'idPersonne (saisie d'un pseudo, qui est unique),
            // soit l'e-mail (qui peut désigner plusieurs comptes, départagés en page 2) ;
            // l'autre colonne reste NULL
            $idPersonne = null;
            $user_email = null;
            $email_for_recipient = '';

            //trouver user selon pseudo
            $stmt = $connectorPdo->prepare("SELECT idPersonne, email FROM personne WHERE pseudo = :pseudo");
            $stmt->execute([':pseudo' => $champs['login_ou_email']]);
            $tab_pers = $stmt->fetch();

            if ($tab_pers !== false)
            {
                $idPersonne = (int) $tab_pers['idPersonne'];
                $email_for_recipient = $tab_pers['email'];
                $source_for_hash = $tab_pers['idPersonne'];
            }

            //trouver user selon email ; l'emporte sur le pseudo si les deux correspondent
            $stmt = $connectorPdo->prepare("SELECT idPersonne, email FROM personne WHERE email = :email");
            $stmt->execute([':email' => $champs['login_ou_email']]);
            $tab_pers = $stmt->fetch();

            if ($tab_pers !== false)
            {
                $idPersonne = null;
                $user_email = $champs['login_ou_email'];
                $email_for_recipient = $tab_pers['email'];
                $source_for_hash = $champs['login_ou_email'];
            }

            if ($email_for_recipient != '')
            {
                $salt = "ciek48";

                // Create the unique user password reset key
                $token = hash('sha256', $salt . random_int(0, 1000) . $source_for_hash);

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
                    $email_for_recipient,
                    "Votre demande pour un nouveau mot de passe sur La décadanse",
                    $tplEngine->render("user-reset-mail-body", [
                        'reset_url' => $site_full_url . "user-reset2.php?token=" . $token,
                    ])
                );

                $logger->info('[user-reset] request', ['email' => $email_for_recipient, 'idP' => $idPersonne]);
            }
            else
            {
                $logger->warning('[user-reset] request failed, user not found', ['login_ou_email' => $champs['login_ou_email']]);
            }

            HtmlShrink::msgOk("Si l'identifiant ou email que vous avez fourni existe comme compte sur La décadanse, un email contenant un lien pour réinitialiser le mot de passe vous a été envoyé");

            $termine = true;
        }
    }
}

if (!$termine)
{
    $_SESSION[$formTokenName] = bin2hex(random_bytes(32));

    if ($verif->nbErreurs() > 0)
    {
        HtmlShrink::msgErreur("Il y a " . $verif->nbErreurs() . " erreur(s)");
    }
    ?>

    <form id="ajouter_editer" class="js-submit-freeze-wait" action="" method="post">

        <span class="mr_as"><label for="mr_as">Ne pas remplir ce champ</label><input type="text" name="name_as"></span>

        <input type="hidden" name="<?= $formTokenName ?>" value="<?= $_SESSION[$formTokenName] ?>">
        <p>Un email comportant un lien vous permettant de choisir un nouveau mot de passe vous sera envoyé</p>
        <p>
            <label for="login_ou_email" id="login_pseudo">Identifiant ou e-mail de votre compte</label>
            <input type="text" name="login_ou_email" id="login_ou_email" value="" size="35" maxlength="100" autofocus required />
            <?= $verif->getHtmlErreur("login_ou_email") ?>
        </p>

        <p class="piedForm">
            <input type="hidden" name="formulaire" value="ok" />
            <input type="submit" name="Submit" value="Envoyer la demande" class="submit" />
        </p>
    </form>

<?php } ?>

</main>

<div id="colonne_gauche" class="colonne">
</div>

<div id="colonne_droite" class="colonne">
</div>

<?php
include("_footer.inc.php");
?>
