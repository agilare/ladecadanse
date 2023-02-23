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
    } catch (Exception $e)
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
$extra_css = array("formulaires", "evenement_inc");
include("_header.inc.php");
?>

<div id="contenu" class="colonne signaler-erreur">


    <?php

    $tab_type_erreur = array(
        "info" => "mauvaise information au sujet de l’événement",
        "enlever" => "événement à enlever",
        "bug" => "un bug",
        "autre" => "autre"
    );

    $verif = new Validateur();

    $champs = array("type_erreur" => '', 'message' => '', 'name' => '', 'email' => '');
    $action_terminee = false;

    if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok')
    {
        foreach ($champs as $c => $v)
        {
            $champs[$c] = $_POST[$c];
        }

        $champs['idEvenement'] = $get['idE'];

        $verif = new Validateur();
        $erreurs = array();

        $verif->valider($champs['message'], "message", "texte", 2, 10000, 1);

        if (empty($champs['type_erreur']))
        {
            $verif->setErreur("type_erreur", "Veuillez choisir un type d'erreur");
        }

        $verif->valider($champs['email'], "email", "email", 4, 80, 0);

        if (empty($champs['name']))
        {
            if ($verif->nbErreurs() === 0)
            {
                $from = $champs['email'];
                if (isset($_SESSION['user']))
                {
                    $from = $_SESSION['Semail'];
                }

                $subject = "Rapport d'erreur sur un événement : " . $tab_type_erreur[$champs['type_erreur']];
                $contenu_message = "Événement : " . $site_full_url . "/evenement.php?idE=" . $champs['idEvenement'];
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
                    $logger->log('global', 'activity', "[evenement-report] by " . $from . " for /evenement.php?idE=" . $champs['idEvenement'], Logger::GRAN_YEAR);
                }

                unset($_POST);
                $action_terminee = true;
            }
        } // if antispam

    } // if POST != ""


    if (!$action_terminee)
    {
        ?>

        <div id="entete_contenu">
            <h2>Signaler une erreur</h2>
            <div class="spacer"></div>
        </div>

        <?php

        if (isset($get['idE']))
        {
            $req_getEven = $connector->query("SELECT idEvenement, idLieu, idSalle, idPersonne, titre, genre, dateEvenement,
	 nomLieu, adresse, quartier, urlLieu, description, flyer, prix, horaire_debut,horaire_fin, horaire_complement, ref, prelocations, statut, localite
	  FROM evenement, localite WHERE evenement.localite_id=localite.id AND idEvenement =" . $get['idE']);

            if ($affEven = $connector->fetchArray($req_getEven))
            {
                $evenement = $affEven;
                include("_evenement.inc.php");
            }
            else
            {
                HtmlShrink::msgErreur("Aucun événement n'est associé à " . $get['idE']);
                exit;
            } // if fetchArray
        } // if isset idE



        if ($verif->nbErreurs() > 0)
        {
            HtmlShrink::msgErreur("Il y a " . $verif->nbErreurs() . " erreur(s).");
        }
        ?>

        <form method="post" id="ajouter_editer" class="submit-freeze-wait" action="<?php echo basename(__FILE__) . "?idE=" . $get['idE']; ?>">

            <fieldset style="width:100%">

                <legend>Type d'erreur*</legend>
                <ul class="radio_vert" >
                    <?php
                    foreach ($tab_type_erreur as $t => $libelle)
                    {
                        $coche = '';
                        if (strcmp($t, $champs['type_erreur']) == 0)
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
                    <input name="email" id="email" type="text" size="40" value="<?php echo sanitizeForHtml($champs['email']) ?>"  />
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

            <div class="mr_as">
                <label for="mr_as">ne pas remplir ce champ</label>
                <input type="text" id="mr_as" name="name">
            </div>

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
    <?php include("_navigation_calendrier.inc.php"); ?>
</div>
<!-- Fin Colonne gauche -->

<?php
include("_footer.inc.php");
?>
