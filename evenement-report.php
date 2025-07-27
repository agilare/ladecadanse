<?php
require_once("app/bootstrap.php");

use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\Logger;
use Ladecadanse\Utils\Mailing;
use Ladecadanse\HtmlShrink;

if (isset($_GET['idE']))
{
    try
    {
        $get['idE'] = Validateur::validateUrlQueryValue($_GET['idE'], "int", 1);
    } catch (Exception)
    {
        header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
        exit;
    }
}
else
{
    header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
    exit;
}

$page_titre = "Signaler une erreur";
$extra_css = ["formulaires", "event/evenement_inc"];
include("_header.inc.php");
?>

<div id="contenu" class="colonne signaler-erreur">


    <?php
    $formTokenName = 'form_token_evenement_report';

    $tab_type_erreur = [
        "info" => "mauvaise information au sujet de l’événement",
        "enlever" => "événement à enlever",
        "autre" => "autre"
    ];

    $verif = new Validateur();

    $champs = ["type_erreur" => '', 'message' => '', 'name' => '', 'email' => ''];
    $action_terminee = false;

    if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok')
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

        if (!empty($_POST['name']))
        {
            exit;
        }

        foreach ($champs as $c => $v)
        {
            $champs[$c] = $_POST[$c];
        }

        $champs['idEvenement'] = $get['idE'];

        $verif = new Validateur();
        $erreurs = [];

        $verif->valider($champs['message'], "message", "texte", 2, 10000, 1);

        if (empty($champs['type_erreur']))
        {
            $verif->setErreur("type_erreur", "Veuillez choisir un type d'erreur");
        }

        $verif->valider($champs['email'], "email", "email", 4, 80, 0);

        if ($verif->nbErreurs() === 0)
        {
            $from = $champs['email'];
            if (isset($_SESSION['user']))
            {
                $from = $_SESSION['Semail'];
            }

            $subject = "Rapport d'erreur sur un événement : " . $tab_type_erreur[$champs['type_erreur']];
            $contenu_message = "Événement : " . $site_full_url . "/event/evenement.php?idE=" . (int) $champs['idEvenement'];
            $contenu_message .= "\n\n";
            $contenu_message .= "Message :\n\n" . $champs['message'] . "\n\n";
            if (isset($_SESSION['user']))
            {
                $contenu_message .= "\n\n" . $_SESSION['user'];
            }

            $mailer = new Mailing();
            if ($mailer->toAdmin($subject, $contenu_message, $from))
            {
                HtmlShrink::msgOk("Merci d'avoir signalé cette erreur, je m'en occupe dès que possible");
                $logger->log('global', 'activity', "[evenement-report] by " . $from . " for /event/evenement.php?idE=" . (int) $champs['idEvenement'], Logger::GRAN_YEAR);
            }

            unset($_POST);
            $action_terminee = true;
        }
    }
} // if POST != ""


    if (!$action_terminee)
    {
        $_SESSION[$formTokenName] = bin2hex(random_bytes(32));
        ?>

        <div id="entete_contenu">
            <h2>Signaler une erreur</h2>
            <div class="spacer"></div>
        </div>

        <?php

        if (isset($get['idE']))
        {
            $req_getEven = $connector->query("SELECT
                  e.genre AS e_genre,
  e.idEvenement AS e_idEvenement,
  e.titre AS e_titre,
  e.statut AS e_statut,
  e.idPersonne AS e_idPersonne,
  e.dateEvenement AS e_dateEvenement,
  e.ref AS e_ref,
  e.flyer AS e_flyer,
  e.image AS e_image,
  e.description AS e_description,
  e.horaire_debut AS e_horaire_debut,
  e.horaire_fin AS e_horaire_fin,
  e.horaire_complement AS e_horaire_complement,
  e.prix AS e_prix,
  e.prelocations AS e_prelocations,
  e.idLieu AS e_idLieu,
  e.idSalle AS e_idSalle,
  e.nomLieu AS e_nomLieu,
  e.adresse AS e_adresse,
  e.quartier AS e_quartier
	  FROM evenement e, localite WHERE e.localite_id=localite.id AND idEvenement =" . (int) $get['idE']);

        if ($affEven = $connector->fetchArray($req_getEven))
            {
                $evenement = $affEven;
                include("event/_evenement.inc.php");
            }
            else
            {
                HtmlShrink::msgErreur("Aucun événement n'est associé à cet id");
                exit;
            } // if fetchArray
        } // if isset idE



        if ($verif->nbErreurs() > 0)
        {
            HtmlShrink::msgErreur("Il y a " . $verif->nbErreurs() . " erreur(s).");
        }
        ?>

    <form method="post" id="ajouter_editer" class="js-submit-freeze-wait" action="<?php echo basename(__FILE__) . "?idE=" . (int) $get['idE']; ?>">

                <input type="hidden" name="<?php echo $formTokenName; ?>" value="<?php echo $_SESSION[$formTokenName]; ?>">
                <div class="mr_as">
                    <label for="name_as">nom</label>
                        <input type="text" name="name">
                    </div>

                <fieldset style="width:100%">

                <legend>Type d'erreur*</legend>
                <ul class="radio_vert" >
                    <?php
                    foreach ($tab_type_erreur as $t => $libelle)
                    {
                        $coche = '';
                        if (strcmp($t, (string) $champs['type_erreur']) == 0)
                        {
                            $coche = 'checked="checked"';
                        }
                        echo '<li><input type="radio" name="type_erreur" value="' . $t . '" ' . $coche . ' id="type_' . $t . '"
	title="" class="radio_vert" /><label for="type_' . $t . '">' . $libelle . '</label></li>';
                    }
                    ?>
                </ul>
                <?php
                echo $verif->getHtmlErreur("type_erreur");
                ?>
            </fieldset>

            <p>
                <label for="message">Description de l’erreur*</label>
                <textarea name="message" id="message" cols="35" rows="8" title=""><?php echo sanitizeForHtml($champs['message']) ?></textarea>
                <?php echo $verif->getHtmlErreur("message"); ?>
            </p>

            <?php
            // email, utile si visiteur non logué
            if (!isset($_SESSION['user']))
            {
                ?>
                <p>
                    <label for="email" id="label_email">Votre e-mail</label>
                            <input name="email" id="email" type="email" size="40" value="<?php echo sanitizeForHtml($champs['email']) ?>"  />
                            <?php echo $verif->getErreur("email"); ?>
                </p>
                <?php
            }
            else
            {
                ?>
                <input name="email" id="email" type="hidden" value=""  />
                <?php
            }
            ?>


            <p class="piedForm">
                <input type="hidden" name="formulaire" value="ok" />
                <input type="submit" value="Envoyer" class="submit submit-big" />
            </p>

        </form>

        <?php
    } // if action_terminee
    ?>
</div>
<!-- fin Evenements -->

<div id="colonne_gauche" class="colonne">
    <?php include("event/_navigation_calendrier.inc.php"); ?>
</div>
<!-- Fin Colonne gauche -->

<?php
include("_footer.inc.php");
?>
