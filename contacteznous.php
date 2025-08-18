<?php
require_once("app/bootstrap.php");

use Ladecadanse\Utils\Validateur;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Utils\Mailing;

$page_titre = "Contact";
$page_description = "Formulaire pour envoyer un email au webmaster de La décadanse";
$extra_css = ["formulaires"];

include("_header.inc.php");
?>

<main id="contenu" class="colonne">

    <header id="entete_contenu">
        <h1 style="font-size:1.6em">Contact</h1>
        <div class="spacer"></div>
    </header>

    <?php
    $formTokenName = 'form_token_contacteznous';

    $verif = new Validateur();

    $champs = ["email" => "", "auteur" => "", "affiliation" => "", "sujet" => "", "contenu" => ""];

    $action_terminee = false;

    if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok' && empty($_POST['as_nom']))
    {
        // check token received == token initially set in form registered in session
        if (!isset($_SESSION[$formTokenName]) || $_POST[$formTokenName] !== $_SESSION[$formTokenName])
        {
            HtmlShrink::msgErreur("Désolé, le formulaire est expiré, veuillez le saisir à nouveau");
            //die('Le formulaire est expiré; <a href="' . $_SERVER['PHP_SELF'] . '">Recharger la page');
        }
        else
        {
            unset($_SESSION[$formTokenName]);

            foreach ($champs as $c => $v)
            {
                $champs[$c] = $_POST[$c];
            }

            $verif = new Validateur();
            $erreurs = [];

            $verif->valider($champs['email'], "email", "email", 4, 80, 1);
            $verif->valider($champs['auteur'], "auteur", "texte", 2, 80, 1);
            $verif->valider($champs['affiliation'], "affiliation", "texte", 2, 80, 0);
            $verif->valider($champs['sujet'], "sujet", "texte", 2, 80, 1);
            $verif->valider($champs['contenu'], "contenu", "texte", 8, 10000, 1);

            if (!empty($_POST['name_as']))
            {
                $verif->setErreur("name_as", "Veuillez laisser ce champ vide");
            }

            if ($verif->nbErreurs() == 0)
            {
                $mailer = new Mailing();
                if ($mailer->toAdmin($champs['sujet'], "Affiliation : " . $champs['affiliation'] . "\n\n" . $champs['contenu'], $champs['email']))
                {
                    HtmlShrink::msgOk('Merci, votre message a été envoyé. Je vous répondrai dans les prochains jours');
                }
                $action_terminee = true;
                unset($_POST);
            }
        } // form_token check
    } //POST

    if (!$action_terminee)
    {
        $_SESSION[$formTokenName] = bin2hex(random_bytes(32));

        if ($verif->nbErreurs() > 0)
        {
            HtmlShrink::msgErreur($verif->getMsgNbErreurs());
        }
        ?>
        <div style="width:94%;margin: 0.2em auto 0 auto;color: #5E5E5F;line-height: 1.4em;">

            <p>Pour nous communiquer vos événements, merci de passer par la page <strong><a href="/articles/annoncerEvenement.php">Annoncer&nbsp;un&nbsp;événement</a></strong></p>

            <h2 style="font-size:1.4em">E-mail</h2>

            <p id="email-info">
                <span id="contacteznous-email-info"><noscript>JS is required to view this address.</noscript></span>
            </p>


        <h3 style="font-size:1.2em">Formulaire</h3>
        </div>
        <form method="post" id="ajouter_editer" class="js-submit-freeze-wait" enctype="multipart/form-data" action="<?= basename(__FILE__) ?>">

            <p>* indique un champ obligatoire</p>

            <input type="hidden" name="<?= $formTokenName; ?>" value="<?= $_SESSION[$formTokenName]; ?>">

            <span class="mr_as">
                <label for="mr_as">Ne pas remplir ce champ</label><input name="as_nom" id="as_nom" type="text">
            </span>

            <fieldset>
                <legend>Vos coordonnées</legend>

                <!-- Email obligatoire (text) -->
                <p>
                    <label for="email" id="label_email">E-mail* </label>
                    <input name="email" id="email" type="text" size="40" title="email expéditeur" tabindex="1" value="<?= sanitizeForHtml($champs['email']) ?>" />
                    <?= $verif->getErreur("email"); ?>
                </p>
                <div class="guideChamp">Votre adresse e-mail restera confidentielle.</div>

                <!-- Nom obligatoire (text) -->
                <p>
                    <label for="auteur" id="label_nom">Prénom/Nom* </label>
                    <input name="auteur" id="auteur" type="text" size="30" title="auteur" tabindex="2" value="<?= sanitizeForHtml($champs['auteur']) ?>" />
                    <?= $verif->getErreur("auteur"); ?>
                </p>

                <!-- Affiliation (text) -->
                <p>
                    <label for="affiliation" id="label_affiliation">Affiliation </label>
                    <input name="affiliation" id="affiliation" type="text" size="30" tabindex="3" value="<?= sanitizeForHtml($champs['affiliation']) ?>" />
                    <?= $verif->getErreur("affiliation"); ?>
                </p>
                <div class="guideChamp">Vous pouvez indiquer ici à quel groupe, assoc, etc. vous appartenez.</div>
            </fieldset>

            <fieldset>
                <legend>Message</legend>

                <p>
                    <label for="sujet" id="label_sujet">Sujet* </label>
                    <input name="sujet" id="sujet" type="text" size="38" maxlength="120"  tabindex="4" value="<?= sanitizeForHtml($champs['sujet']) ?>" />
                    <?= $verif->getErreur("sujet"); ?>
                </p>

                <p>
                    <label for="contenu" id="label_contenu">Contenu* </label><textarea name="contenu" id="message" rows="14" tabindex="5"><?= sanitizeForHtml($champs['contenu']) ?></textarea>
                    <?= $verif->getErreur("contenu"); ?>
                </p>

            </fieldset>

            <p class="piedForm">
                <input type="hidden" name="formulaire" value="ok" />
                <input type="text" name="name_as" value="" class="name_as"  />
                <input type="submit" value="Envoyer" class="submit submit-big" />
                <div class="spacer"><!-- --></div>
            </p>

        </form>
    <?php
    } // if action_terminee
    ?>
</main>
<!-- fin contenu -->

<div id="colonne_gauche" class="colonne">
    <?php include("event/_navigation_calendrier.inc.php"); ?>
</div>

<div id="colonne_droite" class="colonne">
</div>

<?php
include("_footer.inc.php");
?>
