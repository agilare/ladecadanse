<?php

/*
 * @package ladecadanse
 * @copyright  Copyright (c) 2007 - 2025 Michel Gaudry <michel@ladecadanse.ch>
 * @license    AGPL License; see LICENSE file for details.
 */

global $connector, $glo_auj_6h;
require_once("app/bootstrap.php");

use Ladecadanse\EvenementRenderer;

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
        FROM personne_evenement pe
        JOIN evenement e ON pe.idEvenement = e.idEvenement
        JOIN localite loc ON e.localite_id = loc.id
        LEFT JOIN lieu l ON e.idLieu = l.idLieu
        LEFT JOIN localite lloc ON l.localite_id = lloc.id
        LEFT JOIN salle s ON e.idSalle = s.idSalle";

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
        $paginationHtml = '';
        if ($view === 'passes' && isset($totalPages) && $totalPages > 1)
        {
            $paginationHtml = '<div class="pagination">';
            if ($pageNum > 1)
            {
                $paginationHtml .= '<a id="prec" href="/favoris.php?view=passes&amp;page=' . ($pageNum - 1) . '" rel="prev">préc</a>';
            }
            for ($i = 1; $i <= $totalPages; $i++)
            {
                if ($i === $pageNum)
                {
                    $paginationHtml .= '<span class="current">' . $i . '</span>';
                }
                else
                {
                    $paginationHtml .= '<a href="/favoris.php?view=passes&amp;page=' . $i . '">' . $i . '</a>';
                }
            }
            if ($pageNum < $totalPages)
            {
                $paginationHtml .= '<a id="suiv" href="/favoris.php?view=passes&amp;page=' . ($pageNum + 1) . '" rel="next">suiv</a>';
            }
            $paginationHtml .= '</div>';
        }
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
            $Mois = ["", "janvier", "février", "mars", "avril", "mai", "juin", "juillet", "août", "septembre", "octobre", "novembre", "décembre"];
            $sidebarMonths = [];
            foreach ($favorites as $ev)
            {
                $monthKey = substr($ev['e_dateEvenement'], 0, 7);
                if (!isset($sidebarMonths[$monthKey]))
                {
                    $m = (int) substr($ev['e_dateEvenement'], 5, 2);
                    $y = substr($ev['e_dateEvenement'], 0, 4);
                    $sidebarMonths[$monthKey] = ucfirst($Mois[$m]) . ' ' . $y;
                }
            }

            $lastDate = null;
            $lastMonth = null;
            foreach ($favorites as $tab_even) :
                $date = $tab_even['e_dateEvenement'];
                $monthKey = substr($date, 0, 7);
            ?>
                <?php if ($monthKey !== $lastMonth) :
                    $lastMonth = $monthKey;
                    $m = (int) substr($date, 5, 2);
                    $y = substr($date, 0, 4);
                ?>
                    <header class="genre-titre" id="favoris-mois-<?= $monthKey ?>">
                        <h2><?= ucfirst($Mois[$m]) . ' ' . $y ?></h2>
                        <div class="spacer"></div>
                    </header>
                <?php endif; ?>

                <?php if ($date !== $lastDate) :
                    $lastDate = $date;
                ?>
                    <div><p class="rappel_date"><?= ucfirst(date_fr($date)) ?></p></div>
                <?php endif; ?>

                <?= EvenementRenderer::eventShortArticleHtml($tab_even) ?>

                <footer class="edition">
                    <ul class="menu_action">
                        <li><a href="/event/send.php?action=report&idE=<?= (int) $tab_even['e_idEvenement'] ?>" class="signaler" title="Signaler une erreur"><i class="fa fa-flag-o fa-lg"></i></a></li>
                        <li><a href="/event/to-ics.php?idE=<?= (int) $tab_even['e_idEvenement'] ?>" class="ical" title="Exporter au format iCalendar dans votre agenda"><i class="fa fa-calendar-plus-o fa-lg"></i></a></li>
                        <li><a href="#" class="js-favorite-toggle favorite-btn is-favorite" data-event-id="<?= (int) $tab_even['e_idEvenement'] ?>" title="Retirer des favoris"><i class="fa fa-heart fa-lg"></i></a></li>
                    </ul>
                    <div class="spacer"></div>
                </footer>

                </article>
            <?php endforeach; ?>

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
