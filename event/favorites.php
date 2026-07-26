<?php

require_once("../app/bootstrap.php");

use Ladecadanse\Evenement;
use Ladecadanse\EvenementRenderer;
use Ladecadanse\HtmlShrink;

header('X-Robots-Tag: noindex');
header('Content-Type: application/json; charset=utf-8');

if (!isFavoritesEnabled())
{
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

$get['action'] = strip_tags((string) ($_GET['action'] ?? ''));

// "events" action is available to everyone (guests need it for the favoris page)
if ($get['action'] === 'events')
{
    $input = json_decode(file_get_contents('php://input'), true);
    $view = in_array($input['view'] ?? '', ['avenir', 'passes'], true) ? $input['view'] : 'avenir';
    $pageNum = max(1, (int) ($input['page'] ?? 1));
    $perPage = 50;

    $ids = is_array($input['ids'] ?? null) ? $input['ids'] : [];
    $ids = array_map('intval', array_slice($ids, 0, 500));
    $ids = array_values(array_filter($ids, function ($id) { return $id > 0; }));

    if (empty($ids))
    {
        echo json_encode(['html' => '', 'count' => 0, 'months' => [], 'totalCount' => 0, 'totalPages' => 1, 'page' => 1, 'paginationHtml' => '']);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $dateOp = ($view === 'passes') ? '<' : '>=';

    $selectFields = Evenement::LIST_SELECT_FIELDS;
    $fromJoins = "
    FROM evenement e " . Evenement::LIST_JOINS;

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

    $rows = $stmt->fetchAll();
    $count = count($rows);
    $list = EvenementRenderer::favoritesListHtml($rows);

    $result = ['html' => $list['html'], 'count' => $count, 'months' => $list['months'], 'totalCount' => $count, 'totalPages' => 1, 'page' => 1, 'paginationHtml' => ''];
    if ($view === 'passes')
    {
        $result['totalCount'] = $totalCount;
        $result['totalPages'] = $totalPages;
        $result['page'] = $pageNum;
        $result['paginationHtml'] = HtmlShrink::getPaginationString($totalCount, $pageNum, $perPage, 1, "/favoris.php", "?view=passes&amp;page=");
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
        $stmt = $connectorPdo->prepare("INSERT IGNORE INTO personne_evenement (idPersonne, idEvenement) VALUES (?, ?)");
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
