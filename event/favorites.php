<?php

require_once("../app/bootstrap.php");

use Ladecadanse\EvenementRenderer;

header('X-Robots-Tag: noindex');
header('Content-Type: application/json; charset=utf-8');

$get['action'] = strip_tags((string) ($_GET['action'] ?? ''));

// "events" action is available to everyone (guests need it for the favoris page)
if ($get['action'] === 'events')
{
    $input = json_decode(file_get_contents('php://input'), true);
    $ids = $input['ids'] ?? [];
    $view = $input['view'] ?? 'avenir';
    $pageNum = max(1, (int) ($input['page'] ?? 1));
    $perPage = 50;

    if (!in_array($view, ['avenir', 'passes']))
    {
        $view = 'avenir';
    }

    if (!is_array($ids) || empty($ids))
    {
        echo json_encode(['html' => '', 'count' => 0, 'totalCount' => 0, 'totalPages' => 0, 'page' => 1]);
        exit;
    }

    $ids = array_map('intval', array_slice($ids, 0, 200));
    $ids = array_filter($ids, function ($id) { return $id > 0; });
    $ids = array_values($ids);

    if (empty($ids))
    {
        echo json_encode(['html' => '', 'count' => 0, 'totalCount' => 0, 'totalPages' => 0, 'page' => 1]);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $dateOp = ($view === 'passes') ? '<' : '>=';

    $selectFields = "
        e.idEvenement AS e_idEvenement,
        e.titre AS e_titre,
        e.statut AS e_statut,
        e.idPersonne AS e_idPersonne,
        e.dateEvenement AS e_dateEvenement,
        e.genre AS e_genre,
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
        e.quartier AS e_quartier,
        loc.localite AS e_localite,
        e.region AS e_region,
        e.urlLieu AS e_urlLieu,
        l.nom AS l_nom,
        l.adresse AS l_adresse,
        l.quartier AS l_quartier,
        l.URL AS l_URL,
        lloc.localite AS lloc_localite,
        l.region AS l_region,
        s.nom AS s_nom";

    $fromJoins = "
    FROM evenement e
    JOIN localite loc ON e.localite_id = loc.id
    LEFT JOIN lieu l ON e.idLieu = l.idLieu
    LEFT JOIN localite lloc ON l.localite_id = lloc.id
    LEFT JOIN salle s ON e.idSalle = s.idSalle";

    $whereClause = "WHERE e.idEvenement IN (" . $placeholders . ") AND e.statut = 'actif' AND e.dateEvenement " . $dateOp . " ?";
    $params = array_merge($ids, [$glo_auj_6h]);

    $totalCount = 0;
    $totalPages = 1;

    if ($view === 'passes')
    {
        $countSql = "SELECT COUNT(*) AS total " . $fromJoins . " " . $whereClause;
        $countStmt = $connectorPdo->prepare($countSql);
        $countStmt->execute($params);
        $totalCount = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($totalCount / $perPage));
        $pageNum = min($pageNum, $totalPages);
        $offset = ($pageNum - 1) * $perPage;

        $sql = "SELECT " . $selectFields . $fromJoins . " " . $whereClause . " ORDER BY e.dateEvenement DESC, e.horaire_debut ASC LIMIT ? OFFSET ?";
        $stmt = $connectorPdo->prepare($sql);
        $stmt->execute(array_merge($params, [$perPage, $offset]));
    }
    else
    {
        $sql = "SELECT " . $selectFields . $fromJoins . " " . $whereClause . " ORDER BY e.dateEvenement ASC, e.horaire_debut ASC LIMIT 200";
        $stmt = $connectorPdo->prepare($sql);
        $stmt->execute($params);
    }

    $Mois = ["", "janvier", "février", "mars", "avril", "mai", "juin", "juillet", "août", "septembre", "octobre", "novembre", "décembre"];

    $html = '';
    $count = 0;
    $lastDate = null;
    $lastMonth = null;
    $months = [];

    while ($tab_even = $stmt->fetch())
    {
        $date = $tab_even['e_dateEvenement'];
        $monthKey = substr($date, 0, 7);

        if ($monthKey !== $lastMonth)
        {
            $lastMonth = $monthKey;
            $m = (int) substr($date, 5, 2);
            $y = substr($date, 0, 4);
            $months[] = ['key' => $monthKey, 'label' => ucfirst($Mois[$m]) . ' ' . $y];
            $html .= '<header class="genre-titre" id="favoris-mois-' . $monthKey . '"><h2>' . ucfirst($Mois[$m]) . ' ' . $y . '</h2><div class="spacer"></div></header>';
        }

        if ($date !== $lastDate)
        {
            $lastDate = $date;
            $html .= '<div><p class="rappel_date">' . ucfirst(date_fr($date)) . '</p></div>';
        }

        $html .= EvenementRenderer::eventShortArticleHtml($tab_even);
        $html .= '<footer class="edition"><ul class="menu_action">'
            . '<li><a href="/event/send.php?action=report&idE=' . (int) $tab_even['e_idEvenement'] . '" class="signaler" title="Signaler une erreur"><i class="fa fa-flag-o fa-lg"></i></a></li>'
            . '<li><a href="/event/to-ics.php?idE=' . (int) $tab_even['e_idEvenement'] . '" class="ical" title="Exporter au format iCalendar dans votre agenda"><i class="fa fa-calendar-plus-o fa-lg"></i></a></li>'
            . '<li><a href="#" class="js-favorite-toggle favorite-btn is-favorite" data-event-id="' . (int) $tab_even['e_idEvenement'] . '" title="Retirer des favoris"><i class="fa fa-heart fa-lg"></i></a></li>'
            . '</ul><div class="spacer"></div></footer></article>';
        $count++;
    }

    $result = ['html' => $html, 'count' => $count, 'months' => $months];
    if ($view === 'passes')
    {
        $result['totalCount'] = $totalCount;
        $result['totalPages'] = $totalPages;
        $result['page'] = $pageNum;
    }
    else
    {
        $result['totalCount'] = $count;
        $result['totalPages'] = 1;
        $result['page'] = 1;
    }
    echo json_encode($result);
    exit;
}

// All other actions require authentication
if (empty($_SESSION['logged']))
{
    http_response_code(401);
    echo json_encode(['error' => 'not_authenticated']);
    exit;
}

$idPersonne = (int) $_SESSION['SidPersonne'];

if ($get['action'] === 'toggle' && $_SERVER['REQUEST_METHOD'] === 'POST')
{
    $input = json_decode(file_get_contents('php://input'), true);
    $idE = (int) ($input['idE'] ?? 0);
    if ($idE <= 0)
    {
        http_response_code(400);
        echo json_encode(['error' => 'missing_idE']);
        exit;
    }

    $stmt = $connectorPdo->prepare("SELECT 1 FROM personne_evenement WHERE idPersonne = ? AND idEvenement = ?");
    $stmt->execute([$idPersonne, $idE]);
    $exists = $stmt->fetch();

    if ($exists)
    {
        $stmt = $connectorPdo->prepare("DELETE FROM personne_evenement WHERE idPersonne = ? AND idEvenement = ?");
        $stmt->execute([$idPersonne, $idE]);
        echo json_encode(['status' => 'removed']);
    }
    else
    {
        $stmt = $connectorPdo->prepare("INSERT INTO personne_evenement (idPersonne, idEvenement) VALUES (?, ?)");
        $stmt->execute([$idPersonne, $idE]);
        echo json_encode(['status' => 'added']);
    }
    exit;
}

if ($get['action'] === 'list')
{
    $stmt = $connectorPdo->prepare("SELECT idEvenement FROM personne_evenement WHERE idPersonne = ? ORDER BY dateAjout DESC");
    $stmt->execute([$idPersonne]);
    $ids = [];
    while ($row = $stmt->fetch())
    {
        $ids[] = (int) $row['idEvenement'];
    }
    echo json_encode(['ids' => $ids]);
    exit;
}

if ($get['action'] === 'sync')
{
    $input = json_decode(file_get_contents('php://input'), true);
    $ids = $input['ids'] ?? [];

    if (!is_array($ids))
    {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_ids']);
        exit;
    }

    $stmt = $connectorPdo->prepare("INSERT IGNORE INTO personne_evenement (idPersonne, idEvenement) VALUES (?, ?)");
    $synced = 0;
    foreach ($ids as $id)
    {
        $id = (int) $id;
        if ($id > 0)
        {
            $stmt->execute([$idPersonne, $id]);
            $synced++;
        }
    }
    echo json_encode(['synced' => $synced]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'unknown_action']);
