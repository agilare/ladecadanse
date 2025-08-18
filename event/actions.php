<?php

require_once("../app/bootstrap.php");

use Ladecadanse\Evenement;
use Ladecadanse\UserLevel;

if (!$videur->checkGroup(UserLevel::ACTOR)) {
	header("Location: index.php"); die();
}

header('X-Robots-Tag: noindex');

$get['id'] = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$get['action'] = strip_tags((string) $_GET['action']);

if ($get['action'] == 'delete' && !empty($get['id']))
{
    $req_im = $connector->query("SELECT titre, flyer, image, idLieu, genre, dateEvenement, dateAjout
    FROM evenement WHERE idEvenement=" . (int) $get['id']);

    $val_even = $connector->fetchArray($req_im);

    if (!empty($val_even) && (($authorization->isAuthor('evenement', $_SESSION['SidPersonne'], $get['id']) && $_SESSION['Sgroupe'] <= 8) || $_SESSION['Sgroupe'] < 2))
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
