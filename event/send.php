<?php
require_once("../app/bootstrap.php");

use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\Logger;
use Ladecadanse\Utils\Mailing;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Evenement;
use Ladecadanse\Lieu;

if (empty($_GET['idE']) || !is_numeric($_GET['idE']))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
    exit;
}
$get['idE'] = (int) $_GET['idE'];

$tab_action = ['report' => "Signaler une erreur", 'share' => "Envoyer un événement"];
if (empty($_GET['action']) || !in_array($_GET['action'], array_keys($tab_action)))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
    exit;
}
$get['action'] = $_GET['action'];

if ($get['action'] == 'share' && !$videur->checkGroup(12))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
    header("Location: /user-login.php");
    die();
}

// EVENT AND APPENDIXES
$sql_event = "SELECT

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
  e.ref AS e_ref,
  e.horaire_debut AS e_horaire_debut,
  e.horaire_fin AS e_horaire_fin,
  e.horaire_complement AS e_horaire_complement,
  e.prix AS e_prix,
  e.prelocations AS e_prelocations,
  e.idLieu AS e_idLieu,
  e.idSalle AS e_idSalle,
  e.nomLieu AS e_nomLieu,
  e.adresse AS e_adresse,
  e.quartier AS e_quartier,
  loc.localite AS e_localite,
  e.region AS e_region,
  e.urlLieu AS e_urlLieu,
  e.dateAjout AS e_dateAjout,

  l.nom AS l_nom,
  l.determinant AS l_determinant,
  l.adresse AS l_adresse,
  l.quartier AS l_quartier,
  l.lat AS l_lat,
  l.lng AS l_lng,
  l.URL AS l_URL,
  lloc.localite AS lloc_localite,
  l.region AS l_region,

  s.nom AS s_nom

FROM evenement e
JOIN localite loc ON e.localite_id = loc.id
LEFT JOIN lieu l ON e.idLieu = l.idLieu
LEFT JOIN localite lloc ON l.localite_id = lloc.id
LEFT JOIN salle s ON e.idSalle = s.idSalle
WHERE e.idEvenement = :idE";

$stmt = $connectorPdo->prepare($sql_event);
$stmt->execute([':idE' => $get['idE']]);
$tab_even = $stmt->fetch(PDO::FETCH_ASSOC);

if (empty($tab_even))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
    exit;
}

$page_titre = $tab_action[$get['action']];
$extra_css = ["formulaires"];

$formTokenName = 'form_token_evenement_report';

$champs = ['name' => '', 'message' => '', 'email' => $_SESSION['Semail'] ?? '', "email_destinataire" => ''];

$verif = new Validateur();

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
            if (isset($_POST[$c]))
            {
                $champs[$c] = $_POST[$c];
            }
        }
        //dump($champs);

        $verif->valider($champs['message'], "message", "texte", 2, 10000, 1);
        $verif->valider($champs['email'], "email", "email", 4, 80, 0);

        // author & chosen recipient needed
        if ($get['action'] == 'share')
        {
            $verif->valider($champs['email'], "email", "email", 4, 80, 1);
            $verif->valider($champs['email_destinataire'], "email_destinataire", "email", 4, 80, 1);
        }

        if ($verif->nbErreurs() === 0)
        {
            // report
            $to = EMAIL_ADMIN;

            $even_lieu = Evenement::getLieu($tab_even);
            $subject = $translator->get("event-send-{$get['action']}-mail-subject") . " {$tab_even['e_titre']} " . Lieu::prepositionToPutInSentence($even_lieu['determinant'])."{$even_lieu['nom']} {$even_lieu['localite']} le ".date_fr($tab_even['e_dateEvenement'], "annee", "", "", false);

            $body_tpl_parameters = ['idE' => $get['idE'], 'url' => "{$site_full_url}event/evenement.php?idE={$get['idE']}", 'message' => $champs['message']];

            if ($get['action'] === 'share')
            {
                $to = $champs['email_destinataire'];

                $horaire_complet = afficher_debut_fin($tab_even['e_horaire_debut'], $tab_even['e_horaire_fin'], $tab_even['e_dateEvenement']);
                if (!empty($tab_even['e_horaire_complement']))
                {
                    $horaire_complet .= " ".$tab_even['e_horaire_complement'];
                }

                $body_tpl_parameters = [
                    'username' => $_SESSION['user'],
                    'user_email' => $_SESSION['Semail'],
                    'message' => $champs['message'],
                    'titre_complet' => $subject,
                    'description' => $tab_even['e_description'],
                    'horaire_complet' => $horaire_complet,
                    'prix' => $tab_even['e_prix'],
                    'url' => "{$site_full_url}event/evenement.php?idE={$get['idE']}",
                    ];
            }

            $body = $tplEngine->render("event-send-{$get['action']}-mail-body", $body_tpl_parameters);

            $mailer = new Mailing();
            if ($mailer->toUser($to, $subject, $body, ['email' => $champs['email'], 'name' => $_SESSION['user'] ?? '']))
            {
				$_SESSION['evenement-edit_flash_msg'] = $translator->get("event-send-{$get['action']}-success-msg");
                $logger->log('global', 'activity', "[event-send] {$get['action']} by {$champs['email']} to $to of /event/evenement.php?idE=" . (int) $get['idE'], Logger::GRAN_YEAR);
                header("Location: /event/evenement.php?idE=" . (int) $get['idE']);
            }
        }
    }
} // if POST != ""

$_SESSION[$formTokenName] = bin2hex(random_bytes(32));

include("../_header.inc.php");
?>

<main id="contenu" class="colonne signaler-erreur">

    <div id="entete_contenu">
        <h1 style="font-size:1.6em"><?= sanitizeForHtml($tab_action[$get['action']]) ?></h1>
        <div class="spacer"></div>
    </div>

    <div style="width:94%;margin: 0.2em auto 0 auto;">

    <?php if ($verif->nbErreurs() > 0) { HtmlShrink::msgErreur("Il y a " . $verif->nbErreurs() . " erreur(s)."); } ?>

    <?= Ladecadanse\EvenementRenderer::eventShortArticleHtml($tab_even); ?>
    </article>

    <form method="post" id="ajouter_editer" class="js-submit-freeze-wait" action="<?= basename(__FILE__) . "?action=".sanitizeForHtml($get['action'])."&idE=" . (int) $get['idE']; ?>">

        <input type="hidden" name="<?= $formTokenName; ?>" value="<?= sanitizeForHtml($_SESSION[$formTokenName]); ?>">
        <div class="mr_as">
            <label for="name_as">nom</label>
            <input type="text" name="name">
        </div>

        <?php if ($get['action'] == 'share') : ?>
            <p>
                <label for="email_destinataire">Email du destinataire* :</label>
                <input name="email_destinataire" id="email_destinataire" value="<?= sanitizeForHtml($champs['email_destinataire']) ?>" size="40" />
                <?= $verif->getHtmlErreur("email_destinataire"); ?>
            </p>
        <?php endif; ?>

        <p>
            <label for="message">Votre message*</label>
            <textarea name="message" id="message" cols="30" rows="8" style="width:350px"><?= sanitizeForHtml($champs['message']) ?></textarea>
            <?= $verif->getHtmlErreur("message"); ?>
        </p>

        <p>
            <label for="email" id="label_email">Votre e-mail<?php if ($get['action'] == 'share') : ?>*<?php endif; ?></label>
            <input name="email" id="email" type="email" size="40" value="<?= sanitizeForHtml($champs['email']) ?>"  />
            <?= $verif->getErreur("email"); ?>
        </p>

        <p class="piedForm">
            <input type="hidden" name="formulaire" value="ok" />
            <input type="submit" value="Envoyer" class="submit submit-big" />
        </p>

    </form>

    </div>

</main>

<div id="colonne_gauche" class="colonne">
    <?php include("_navigation_calendrier.inc.php"); ?>
</div>

<?php
include("../_footer.inc.php");
?>
