<?php

require_once 'app/bootstrap.php';

use Ladecadanse\Evenement;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\Logger;

if (empty($_SERVER['PHP_AUTH_USER']) || empty($_SERVER['PHP_AUTH_PW']) || !($_SERVER['PHP_AUTH_USER'] == LADECADANSE_API_USER && $_SERVER['PHP_AUTH_PW'] == LADECADANSE_API_KEY))
{
    header('WWW-Authenticate: Basic realm="La décadanse"');
    header($_SERVER['SERVER_PROTOCOL'] . ' 401 Unauthorized');
    die('Not authorized');
}

$tab_entity = ['event'];
try
{
    $get['entity'] = Validateur::validateUrlQueryValue($_GET['entity'], 'enum', 1, $tab_entity);
} catch (Exception)
{
    header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
    exit;
}

if (!array_key_exists(trim((string) $_GET['region']), $glo_regions))
{
    echo 'Unknown region';
    header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
    exit;
}

$get['region'] = trim((string) $_GET['region']);

try
{
    $get['date'] = Validateur::validateUrlQueryValue(trim((string) $_GET['date']), 'date', 1);
} catch (Exception)
{
    header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
    exit;
}

if (!preg_match('/^[0-9]{2}:[0-9]{2}:[0-9]{2}$/', trim((string) $_GET['endtime'])))
{
    header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
    exit;
}

$get['endtime'] = trim((string) $_GET['endtime']);

    $eventCategories = [
    'fête',
    'cinéma',
    'théâtre',
    'expos',
    'divers',
];
try
{
    $get['category'] = Validateur::validateUrlQueryValue($_GET['category'], 'enum', 1, $eventCategories);
} catch (Exception)
{
    header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
    exit;
}

$result = [];

if ($get['entity'] == 'event')
{
    // if nighttime : get endtime is > 0h and < 6h
    $datetimeMinEnd = new Datetime($get['date']);
    $datetimeMinEnd->modify('+1 day');

    $events = [];

    $sql = "SELECT e.*, eloc.localite eLocalite, l.nom lNom, l.adresse lAdresse, l.quartier lQuartier, loc.localite lLocalite, l.URL lUrl
	 FROM evenement e
     LEFT JOIN localite eloc ON e.localite_id = eloc.id
     LEFT JOIN lieu l ON e.idLieu = l.idLieu
     LEFT JOIN localite loc ON l.localite_id = loc.id
	 WHERE dateEvenement = '" . $connector->sanitize($get['date']) . "' AND genre = '" . $connector->sanitize($get['category']) . "' AND e.statut NOT IN ('propose') AND e.region IN ('" . $connector->sanitize($get['region']) . "')";
    // noctambus : don't covers 'rf', 'hs'
    if (!empty($get['endtime']))
    {
        $sql .= "AND horaire_fin >= '" . $datetimeMinEnd->format('Y-m-d') . ' ' . $connector->sanitize($get['endtime']) . "' AND horaire_fin < '" . $datetimeMinEnd->format('Y-m-d') . " 06:00:01'";
        // noctambus : covers from 01h to...
    }

    $sql .= ' ORDER BY e.dateAjout DESC';
    // echo $sql;
    $req_even = $connector->query($sql);

    while ($row = $connector->fetchAssoc($req_even))
    {
        // dump($row);
        $event = [
            'idevenement' => $row['idevenement'],
            'statut' => $row['statut'],
            'titre' => $row['titre'],
        ];

        // image : if flyer empty, image; add path
        if (!empty($row['image']))
        {
            $event['image'] = Evenement::getFileHref(Evenement::getFilePath($row['image']));
        }
        elseif (!empty($row['flyer']))
        {
            $event['image'] = Evenement::getFileHref(Evenement::getFilePath($row['flyer']));
        }

        $event['description'] = $row['description'];
        $event['references'] = $row['ref'];

        // TODO (organisateurs)
        // horaire
        $event['horaire'] = [
            'debut' => $row['horaire_debut'],
            'fin' => $row['horaire_fin'],
            'complement' => $row['horaire_complement'],
        ];

        $event['prix'] = $row['prix'];
        $event['prelocations'] = $row['prelocations'];

        // lieu directly from event table
        $event['lieu'] = [
            'nom' => $row['nomLieu'],
            'adresse' => $row['adresse'],
            'quartier' => $row['quartier'],
            'localite' => $row['eLocalite'],
            'url' => $row['urlLieu'],
        ];

        // or from registered lieu
        if (!empty($row['idLieu']))
        {
            $event['lieu'] = [
                'nom' => $row['lNom'],
                'adresse' => $row['lAdresse'],
                'quartier' => $row['lQuartier'],
                'localite' => $row['lLocalite'],
                'url' => $row['lUrl'],
            ];
        }

                $event['created'] = $row['dateAjout'];
        $event['updated'] = $row['date_derniere_modif'];

        $events[] = $event;
    } //end while
    //$events[0]['horaire']['debut'] = "2023-05-21 00:30:00";
    //$events[1]['lieu']['nom'] = "";
    //$events[1]['titre'] = "";

            $result = [
        'date' => $get['date'],
        'events' => $events,
    ];
}//end if

$logger->log('global', 'api', "GET ".count($events)." item(s) for ".$get['entity']." ".$get['category']." in ". $get['region']." the ".$get['date']." until ".$get['endtime']." by user ".LADECADANSE_API_USER, Logger::GRAN_YEAR);

header('Content-Disposition: inline; filename=' . $get['entity'] . '.json');
header('Content-Type: application/json; charset=UTF-8');
echo json_encode($result);
