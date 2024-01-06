<?php
require_once("app/bootstrap.php");

use Ladecadanse\Utils\Validateur;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Utils\Mailing;

$page_titre = "Contact";
$page_description = "Formulaire pour envoyer un email au webmaster de La décadanse : proposer un événement, poser une question, etc.";
$extra_css = array("formulaires", "contacteznous");

include("_header.inc.php");
?>

<div id="contenu" class="colonne contacteznous">

    <div id="entete_contenu">
        <h2>Contact</h2>
        <div class="spacer"></div>
    </div>

    <?php
    $verif = new Validateur();

    $champs = array("email" => "", "auteur" => "", "affiliation" => "", "sujet" => "", "contenu" => "");

    $action_terminee = false;

    if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok' && empty($_POST['as_nom']))
    {
        foreach ($champs as $c => $v)
        {
            $champs[$c] = $_POST[$c];
        }

        $verif = new Validateur();
        $erreurs = array();

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
    } //POST


    if (!$action_terminee)
    {

        if ($verif->nbErreurs() > 0)
        {
            HtmlShrink::msgErreur($verif->getMsgNbErreurs());
        }
        ?>

    <div style="margin:1em 0 1em 1em">
            <p>Pour nous communiquer vos événements, merci de passer par la page <strong><a href="/articles/annoncerEvenement.php">Annoncer&nbsp;un&nbsp;événement</a></strong></p>
        </div>

    <h3>E-mail</h3>

            <div style="margin:1em 0 0em 2em">
                <p id="email-info">
                    <span id="email-info"><noscript>JS is required to view this address.</noscript></span>
                </p>
            </div>

            <?php
            if (1)
            {
                ?>
                <h3>Formulaire</h3>
                <form method="post" id="ajouter_editer"  class="js-submit-freeze-wait" enctype="multipart/form-data" action="<?php echo basename(__FILE__) ?>">
                    <p>* indique un champ obligatoire</p>
                    <span class="mr_as">
                        <label for="mr_as">Ne pas remplir ce champ</label><input name="as_nom" id="as_nom" type="text">
                    </span>

                        <fieldset>
                            <legend>Vos coordonnées</legend>

                            <!-- Email obligatoire (text) -->
                            <p>
                                <label for="email" id="label_email">E-mail* </label>
                                <input name="email" id="email" type="text" size="40" title="email expéditeur" tabindex="1" value="<?php echo sanitizeForHtml($champs['email']) ?>"  onblur="validerEmail('email', 'false');" />
                                <?php echo $verif->getErreur("email"); ?>
                            </p>
                            <div class="guideChamp">Votre adresse e-mail restera confidentielle.</div>

                            <!-- Nom obligatoire (text) -->
                            <p>
                                <label for="auteur" id="label_nom">Prénom/Nom* </label>
                                <input name="auteur" id="auteur" type="text" size="30" title="auteur" tabindex="2" value="<?php echo sanitizeForHtml($champs['auteur']) ?>" />
                                <?php echo $verif->getErreur("auteur"); ?>
                            </p>


                            <!-- Affiliation (text) -->
                            <p>
                                <label for="affiliation" id="label_affiliation">Affiliation </label>
                                <input name="affiliation" id="affiliation" type="text" size="30" tabindex="3" value="<?php echo sanitizeForHtml($champs['affiliation']) ?>" />
                                <?php echo $verif->getErreur("affiliation"); ?>
                            </p>
                            <div class="guideChamp">Vous pouvez indiquer ici à quel groupe, assoc, etc. vous appartenez.</div>

                        </fieldset>

                        <fieldset>

                            <!-- Sujet obligatoire (text) -->
                            <legend>Message</legend>

                            <p>
                                <label for="sujet" id="label_sujet">Sujet* </label>
                                <input name="sujet" id="sujet" type="text" size="45" maxlength="100"  tabindex="4" value="<?php echo sanitizeForHtml($champs['sujet']) ?>" />
                                <?php echo $verif->getErreur("sujet"); ?>
                            </p>


                            <!-- Contenu obligatoire (textarea) -->
                            <p>
                                <label for="contenu" id="label_contenu">Contenu* </label><textarea name="contenu" id="message" rows="14" title="" tabindex="5"><?php echo sanitizeForHtml($champs['contenu']) ?></textarea>
                                <?php echo $verif->getErreur("contenu"); ?>
                            </p>

                        </fieldset>

                        <p class="piedForm">
                            <input type="hidden" name="formulaire" value="ok" />
                            <input type="text" name="name_as" value="" class="name_as" id="name_as" />
                            <input type="submit" value="Envoyer" class="submit submit-big" />
                        <div class="spacer"><!-- --></div>
                    </p>

                    </form>
                <?php } // if 0   ?>
                <?php
            } // if action_terminee
            ?>

</div>
<!-- fin contenu -->

<div id="colonne_gauche" class="colonne">

    <?php include("_navigation_calendrier.inc.php"); ?>
</div>
<!-- Fin Colonnegauche -->

<div id="colonne_droite" class="colonne">
</div>

<?php
include("_footer.inc.php");
?>
