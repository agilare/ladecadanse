<?php
/**
 * Supprime ou dépublie un événement, à la demande des liens « Supprimer » et « Dépublier »
 * (Events dans web/js/global.js).
 *
 * POST seulement, avec le jeton CSRF de la session, que ces liens portent dans data-token.
 * En GET, un simple lien ou une redirection depuis un site tiers suffirait à déclencher
 * l'action au nom de la personne connectée : le cookie de session, SameSite=Lax, accompagne
 * les navigations de premier niveau. Le jeton ferme la voie que Lax laisse ouverte aux
 * requêtes venues du site lui-même.
 */

require_once("../app/bootstrap.php");

use Ladecadanse\Evenement;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Lieu;
use Ladecadanse\Security\SecurityToken;
use Ladecadanse\UserLevel;
use Ladecadanse\Utils\DateHelper;
use Ladecadanse\Utils\QueryParamValidator;
use Ladecadanse\Utils\Text;
use Ladecadanse\Utils\WebLink;

header('X-Robots-Tag: noindex');

// Lu par evenement-edit.php (LieuInfo dans web/js/global.js) pour afficher le popover d'un
// lieu sélectionné : accessible sans connexion, comme ce formulaire lui-même l'est pour
// « Proposer un événement ». GET, et non POST, car sans effet de bord — pas de jeton CSRF à
// exiger ; l'en-tête X-Requested-With écarte au moins la navigation directe.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'lieu-for-event')
{
    if (empty($_SERVER['HTTP_X_REQUESTED_WITH']))
    {
        header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
        die();
    }

    try
    {
        $idLieu = (int) QueryParamValidator::validateUrlQueryValue($_GET['idL'] ?? '', "int", 1);
    }
    catch (Exception)
    {
        header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
        die();
    }

    $lieu = Lieu::getLieu($idLieu);

    if (empty($lieu))
    {
        header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
        die();
    }

    // Même règle que lieu/lieu.php : un lieu dépublié ne reste visible qu'à ses éditeurs,
    // même si le <select> de ce formulaire ne propose que des lieux publiés.
    if ($lieu['statut'] === 'inactif' && !(isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= UserLevel::AUTHOR))
    {
        header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
        die();
    }

    $adresse = HtmlShrink::adresseCompacteSelonContexte($lieu['loc_canton'], $lieu['loc_localite'], $lieu['quartier'], $lieu['adresse']);
    $horaire = trim((string) ($lieu['horaire_general'] ?? ''));

    echo '<p class="lieu-info-popover__nom"><a href="/lieu/lieu.php?idL=' . $idLieu . '" target="_blank" rel="noopener">' . sanitizeForHtml($lieu['nom']) . '</a></p>';
    echo '<p class="lieu-info-popover__adresse">' . sanitizeForHtml($adresse) . '</p>';

    if (!empty($lieu['URL']))
    {
        echo '<p class="lieu-info-popover__url">' . WebLink::html($lieu['URL'], iconeParDefaut: 'fa-globe') . '</p>';
    }

    if ($horaire !== '')
    {
        echo '<p class="lieu-info-popover__horaire">' . Text::lnAndUrlToHtml($horaire) . '</p>';
    }

    die();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
{
    header('Allow: POST');
    header($_SERVER["SERVER_PROTOCOL"] . " 405 Method Not Allowed");
    die();
}

if (!$authorization->checkGroup(UserLevel::ACTOR)) {
	header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
    die();
}

// 400 et non 403, réservé au manque de droits : ce refus-là vient le plus souvent d'une page
// rendue dans une session qui n'a plus cours, et le script propose de la recharger
if (!SecurityToken::check($_POST['token'] ?? '', $_SESSION['token'] ?? ''))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
    die();
}

$post['id'] = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
$post['action'] = strip_tags((string) ($_POST['action'] ?? ''));

if ($post['action'] == 'delete' && !empty($post['id']))
{
    $req_im = $connector->query("SELECT titre, flyer, image, idLieu, genre, dateEvenement, horaire_fin, dateAjout
    FROM evenement WHERE idEvenement=" . (int) $post['id']);

    $val_even = $connector->fetchArray($req_im);

    // Un événement passé est une archive : le supprimer détruit l'historique encore plus
    // sûrement que le recycler. Même règle et même seuil que le verrou d'édition.
    $is_even_editable = !empty($val_even)
        && ($authorization->isPersonneEditor($_SESSION)
            || !DateHelper::isEvenementPast($val_even['dateEvenement'], $val_even['horaire_fin']));

    if ($is_even_editable && (($authorization->isAuthor('evenement', $_SESSION['SidPersonne'], $post['id']) && $_SESSION['Sgroupe'] <= 8) || $_SESSION['Sgroupe'] < 2))
    {
        if (!empty($val_even['flyer']))
        {
            Evenement::rmImageAndItsMiniature($val_even['flyer']);
        }

        if (!empty($val_even['image']))
        {
            Evenement::rmImageAndItsMiniature($val_even['image']);
        }

        if ($connector->query("DELETE FROM evenement WHERE idEvenement=" . (int) $post['id']))
        {
            header('HTTP/1.1 200 OK');
            echo 1;
        }
        else
        {
            header('HTTP/1.1 304 Not Modified');
            echo 0;
        }
    }
    else
    {
        header('HTTP/1.1 403 Forbidden');
        echo 0;
    }
}

if ($post['action'] == 'unpublish' && !empty($post['id']))
{
    $req_im = $connector->query("SELECT titre, flyer, image, idLieu, genre, dateEvenement, idPersonne, dateAjout FROM evenement WHERE idEvenement=" . (int) $post['id']);

    $val_even = $connector->fetchArray($req_im);

    if (!empty($val_even) &&
            (
        (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= UserLevel::AUTHOR || (isset($_SESSION['SidPersonne']) && $_SESSION['SidPersonne'] == $val_even['idPersonne']))
            )
        ||  (isset($_SESSION['Saffiliation_lieu']) && !empty($val_even['idLieu']) && $val_even['idLieu'] == $_SESSION['Saffiliation_lieu'])
         || isset($_SESSION['SidPersonne']) && $authorization->isPersonneInEvenementByOrganisateur($_SESSION['SidPersonne'], $post['id'])
         || isset($_SESSION['SidPersonne']) && $authorization->isPersonneInLieuByOrganisateur($_SESSION['SidPersonne'], $val_even['idLieu'])	))
    {

        if ($connector->query("UPDATE evenement SET statut='inactif' WHERE idEvenement=" . (int) $post['id']))
        {
            header('HTTP/1.1 200 OK');
            echo 1;
        }
        else
        {
            header('HTTP/1.1 304 Not Modified');
            echo 0;
        }
    }
    else
    {
        header('HTTP/1.1 403 Forbidden');
        echo 0;
    }
}
