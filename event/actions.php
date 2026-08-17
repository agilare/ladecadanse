<?php

require_once("../app/bootstrap.php");

use Ladecadanse\Evenement;
use Ladecadanse\UserLevel;
use Ladecadanse\Utils\DateHelper;

header('X-Robots-Tag: noindex');

if (!$authorization->checkGroup(UserLevel::ACTOR)) {
	header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
    die();
}

$get['id'] = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$get['action'] = strip_tags((string) ($_GET['action'] ?? ''));

if ($get['action'] == 'delete' && !empty($get['id']))
{
    $req_im = $connector->query("SELECT titre, flyer, image, idLieu, genre, dateEvenement, horaire_fin, dateAjout
    FROM evenement WHERE idEvenement=" . (int) $get['id']);

    $val_even = $connector->fetchArray($req_im);

    // Un événement passé est une archive : le supprimer détruit l'historique encore plus
    // sûrement que le recycler. Même règle et même seuil que le verrou d'édition.
    $is_even_editable = !empty($val_even)
        && ($authorization->isPersonneEditor($_SESSION)
            || !DateHelper::isEvenementPast($val_even['dateEvenement'], $val_even['horaire_fin']));

    if ($is_even_editable && (($authorization->isAuthor('evenement', $_SESSION['SidPersonne'], $get['id']) && $_SESSION['Sgroupe'] <= 8) || $_SESSION['Sgroupe'] < 2))
    {
        if (!empty($val_even['flyer']))
        {
            Evenement::rmImageAndItsMiniature($val_even['flyer']);
        }

        if (!empty($val_even['image']))
        {
            Evenement::rmImageAndItsMiniature($val_even['image']);
        }

        if ($connector->query("DELETE FROM evenement WHERE idEvenement=" . (int) $get['id']))
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

if ($get['action'] == 'unpublish' && !empty($get['id']))
{
    $req_im = $connector->query("SELECT titre, flyer, image, idLieu, genre, dateEvenement, idPersonne, dateAjout FROM evenement WHERE idEvenement=" . (int) $get['id']);

    $val_even = $connector->fetchArray($req_im);

    if (!empty($val_even) &&
            (
        (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= UserLevel::AUTHOR || (isset($_SESSION['SidPersonne']) && $_SESSION['SidPersonne'] == $val_even['idPersonne']))
            )
        ||  (isset($_SESSION['Saffiliation_lieu']) && !empty($val_even['idLieu']) && $val_even['idLieu'] == $_SESSION['Saffiliation_lieu'])
         || isset($_SESSION['SidPersonne']) && $authorization->isPersonneInEvenementByOrganisateur($_SESSION['SidPersonne'], $get['id'])
         || isset($_SESSION['SidPersonne']) && $authorization->isPersonneInLieuByOrganisateur($_SESSION['SidPersonne'], $val_even['idLieu'])	))
    {

        if ($connector->query("UPDATE evenement SET statut='inactif' WHERE idEvenement=" . (int) $get['id']))
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
