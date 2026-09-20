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
use Ladecadanse\Favorites;
use Ladecadanse\HtmlShrink;

if (!Favorites::isEnabled())
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
        <?php
        /*
         * Le menu des deux vues partage la ligne du titre, comme celui des périodes sur la
         * recherche et les fiches lieu. « Événements » n'est pas un lien mais le préfixe des
         * deux : il évite de le répéter dans chaque libellé.
         */
        ?>
        <hgroup>
            <h1><i class="fa fa-heart" style="color:#e74c3c"></i> Favoris</h1>
        </hgroup>

        <ul id="menu_periode">
            <li class="menu_periode_prefixe">Événements</li>
            <li class="<?= $view === 'passes' ? 'ici' : '' ?>"><a href="/favoris.php?view=passes">Passés</a></li>
            <li class="<?= $view === 'avenir' ? 'ici' : '' ?>"><a href="/favoris.php">Prochains</a></li>
        </ul>
    </header>

    <?php
    /*
     * POC : la colonne des mois n'a pas sa place sur un téléphone, où elle est masquée. Cette
     * barre horizontale la remplace, en tête de liste et collante au défilement. Elle est
     * remplie par favorites.js à partir des en-têtes de mois rendus plus bas — les mêmes pour
     * un visiteur, dont la liste arrive par l'API, que pour un membre connecté.
     */
    ?>
    <nav id="favoris_mois_mobile" class="favoris-mois-mobile" aria-label="Aller à un mois" hidden>
        <ul></ul>
    </nav>

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
                <p class="favoris-vide">Aucun événement passé dans vos favoris.</p>
            <?php else : ?>
                <p class="favoris-vide">Vous n'avez pas encore de favoris à venir. Cliquez sur <i class="fa fa-heart-o" style="color:#e74c3c"></i> à côté d'un événement pour l'ajouter.</p>
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
            <a href="#" class="js-favorites-banner-dismiss favoris-bandeau-fermer" title="Fermer" aria-label="Fermer ce message"><i class="fa fa-times" aria-hidden="true"></i></a>
            <p>Ces favoris sont enregistrés uniquement sur <strong>cet</strong> appareil et seront supprimés si vous videz les données de votre navigateur. Pour les conserver sur tous vos appareils, <a href="/user/login.php">connectez-vous</a></p>
        </div>

        <div id="favorites-guest-list">
            <p class="js-favorites-loading favoris-vide">Chargement de vos favoris...</p>
            <p class="js-favorites-empty favoris-vide" style="display:none">Vous n'avez pas encore de favoris. Cliquez sur <i class="fa fa-heart-o" style="color:#e74c3c"></i> à côté d'un événement pour l'ajouter.</p>
            <div class="js-favorites-pagination-top"></div>
            <div class="js-favorites-content"></div>
            <div class="js-favorites-pagination"></div>
        </div>

    <?php endif; ?>

</main>

<aside id="colonne_gauche" class="colonne">
    <?php
    /*
     * Sans mois à lister, le bloc ne montrerait que son fond gris arrondi : il reste masqué.
     * Pour un visiteur, la liste vient de l'API et c'est favorites.js qui le remplit, donc qui
     * le découvre.
     */
    ?>
    <nav class="favoris-sidebar"<?= empty($sidebarMonths) ? ' hidden' : '' ?>>
        <?php if (!empty($sidebarMonths)) : ?>
            <div class="favoris-sidebar-header"><i class="fa fa-calendar-o"></i> Mois</div>
            <ul>
                <?php $moisCourant = date('Y-m'); ?>
                <?php foreach ($sidebarMonths as $key => $label) : ?>
                    <li<?= $key === $moisCourant ? ' class="favoris-mois-courant"' : '' ?>><a href="#favoris-mois-<?= $key ?>"><?= $label ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </nav>
</aside>

<?php // vide, comme sur les fiches lieu et la recherche : la colonne tient la gouttière de droite ?>
<aside id="colonne_droite" class="colonne"></aside>

<div class="spacer"><!-- --></div>

<?php
include("_footer.inc.php");
