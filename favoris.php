<?php

/*
 * @package ladecadanse
 * @copyright  Copyright (c) 2007 - 2025 Michel Gaudry <michel@ladecadanse.ch>
 * @license    AGPL License; see LICENSE file for details.
 */

global $connector, $glo_auj_6h;
require_once("app/bootstrap.php");

use Ladecadanse\Evenement;
use Ladecadanse\EvenementRenderer;
use Ladecadanse\HtmlShrink;

if (!isFavoritesEnabled())
{
    header('Location: /');
    exit;
}

$page_titre = "Favoris";
$page_description = "Mes événements favoris";
$nom_page = "favoris";

$view = strip_tags((string) ($_GET['view'] ?? 'avenir'));
if (!in_array($view, ['avenir', 'passes']))
{
    $view = 'avenir';
}

$perPage = 50;
$pageNum = max(1, (int) ($_GET['page'] ?? 1));

include("_header.inc.php");
?>

<main id="contenu" class="colonne">

    <header id="entete_contenu">
        <hgroup>
            <h1><i class="fa fa-heart" style="color:#e74c3c"></i> Favoris</h1>
        </hgroup>
        <div class="spacer"></div>
    </header>

    <div id="order_navigation">
        <ul>
            <li><a href="/favoris.php" class="<?= $view === 'avenir' ? 'selected' : '' ?>">Événements à venir</a></li>
            <li><a href="/favoris.php?view=passes" class="<?= $view === 'passes' ? 'selected' : '' ?>">Événements passés</a></li>
        </ul>
        <div class="spacer"></div>
    </div>

    <?php $sidebarMonths = []; ?>

    <?php if (!empty($_SESSION['logged'])) : ?>

        <?php
        $idPersonne = (int) $_SESSION['SidPersonne'];

        $selectFields = Evenement::LIST_SELECT_FIELDS;
        $fromJoins = "
        FROM personne_evenement pe
        JOIN evenement e ON pe.idEvenement = e.idEvenement " . Evenement::LIST_JOINS;

        $dateOp = ($view === 'passes') ? '<' : '>=';
        $whereClause = "WHERE pe.idPersonne = :idPersonne AND e.statut = 'actif' AND e.dateEvenement " . $dateOp . " :today";

        if ($view === 'passes')
        {
            $orderClause = "ORDER BY e.dateEvenement DESC, e.horaire_debut ASC";

            $countSql = "SELECT COUNT(*) AS total " . $fromJoins . " " . $whereClause;
            $countStmt = $connectorPdo->prepare($countSql);
            $countStmt->bindValue(':idPersonne', $idPersonne, PDO::PARAM_INT);
            $countStmt->bindValue(':today', $glo_auj_6h, PDO::PARAM_STR);
            $countStmt->execute();
            $totalCount = (int) $countStmt->fetchColumn();
            $totalPages = max(1, (int) ceil($totalCount / $perPage));
            $pageNum = min($pageNum, $totalPages);
            $offset = ($pageNum - 1) * $perPage;

            $sql = "SELECT " . $selectFields . $fromJoins . " " . $whereClause . " " . $orderClause . " LIMIT :limit OFFSET :offset";
            $stmt = $connectorPdo->prepare($sql);
            $stmt->bindValue(':idPersonne', $idPersonne, PDO::PARAM_INT);
            $stmt->bindValue(':today', $glo_auj_6h, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }
        else
        {
            $orderClause = "ORDER BY e.dateEvenement ASC, e.horaire_debut ASC";
            $totalCount = null;

            $sql = "SELECT " . $selectFields . $fromJoins . " " . $whereClause . " " . $orderClause . " LIMIT 200";
            $stmt = $connectorPdo->prepare($sql);
            $stmt->bindValue(':idPersonne', $idPersonne, PDO::PARAM_INT);
            $stmt->bindValue(':today', $glo_auj_6h, PDO::PARAM_STR);
        }

        $stmt->execute();
        $favorites = $stmt->fetchAll();
        $count = count($favorites);
        ?>

        <?php
        $paginationHtml = ($view === 'passes') ? HtmlShrink::getPaginationString($totalCount, $pageNum, $perPage, 1, "/favoris.php", "?view=passes&amp;page=") : '';
        ?>

        <?php if ($count === 0) : ?>
            <?php if ($view === 'passes') : ?>
                <div><p>Aucun événement passé dans vos favoris.</p></div>
            <?php else : ?>
                <div><p>Vous n'avez pas encore de favoris à venir. Cliquez sur <i class="fa fa-heart-o" style="color:#e74c3c"></i> à côté d'un événement pour l'ajouter.</p></div>
            <?php endif; ?>
        <?php else : ?>
            <div><p><?= $view === 'passes' ? $totalCount : $count ?> événement<?= ($view === 'passes' ? $totalCount : $count) > 1 ? 's' : '' ?></p></div>

            <?= $paginationHtml ?>

            <?php
            $list = EvenementRenderer::favoritesListHtml($favorites);
            $sidebarMonths = array_column($list['months'], 'label', 'key');
            echo $list['html'];
            ?>

            <?= $paginationHtml ?>
        <?php endif; ?>

    <?php else : ?>

        <div id="favorites_guest_banner" class="favorites-guest-banner">
            <p>Vos favoris sont enregistrés uniquement sur cet appareil et peuvent être supprimés si vous videz les données de votre navigateur. Pour les conserver sur tous vos appareils, <a href="/user-login.php">connectez-vous</a>.
            <a href="#" class="js-favorites-banner-dismiss" title="Fermer"><i class="fa fa-times"></i></a></p>
        </div>

        <div id="favorites-guest-list">
            <p class="js-favorites-loading">Chargement de vos favoris...</p>
            <p class="js-favorites-empty" style="display:none">Vous n'avez pas encore de favoris. Cliquez sur <i class="fa fa-heart-o" style="color:#e74c3c"></i> à côté d'un événement pour l'ajouter.</p>
            <div class="js-favorites-pagination-top"></div>
            <div class="js-favorites-content"></div>
            <div class="js-favorites-pagination"></div>
        </div>

    <?php endif; ?>

</main>

<aside id="colonne_gauche" class="colonne">
    <nav class="favoris-sidebar">
        <?php if (!empty($sidebarMonths)) : ?>
            <div class="favoris-sidebar-header"><i class="fa fa-calendar-o"></i> Mois</div>
            <ul>
                <?php foreach ($sidebarMonths as $key => $label) : ?>
                    <li><a href="#favoris-mois-<?= $key ?>"><?= $label ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </nav>
</aside>

<?php
include("_footer.inc.php");
