<?php
// declare(strict_types=1);
/*
 * @package ladecadanse
 * @copyright  Copyright (c) 2007 - 2025 Michel Gaudry <michel@ladecadanse.ch>
 * @license    AGPL License; see LICENSE file for details.
 */

global $connector;
require_once("app/bootstrap.php");

use Ladecadanse\Utils\Text;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Evenement;
use Ladecadanse\UserLevel;
use Ladecadanse\Utils\ImageDriver2;

// used for meta tags, opengraph
$page_titre = " agenda de sorties à " . $glo_regions[$_SESSION['region']] . ", prochains événements : concerts, soirées, films, théâtre, expos, bars, cinémas";
$page_description = "Programme des prochains événements festifs et culturels à Genève et Lausanne : fêtes, concerts et soirées, cinéma, théâtre, expositions, vernissages, conférences, lieux culturels et alternatifs";


list($regionInClause, $regionInParams) = $connectorPdo->buildInClause('e.region', $glo_regions_coverage[$_SESSION['region']]);

$sql_even_in_status_and_region_clause = " e.statut NOT IN ('inactif', 'propose') AND ($regionInClause OR FIND_IN_SET (:region, loc.regions_covered)) ";
$sql_even_in_status_and_region_params = array_merge([':region' => $_SESSION['region']], $regionInParams);


$sql_events_today_in_region_order_by_category = "SELECT

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
  e.urlLieu AS e_urlLieu,

  l.nom AS l_nom,
  l.adresse AS l_adresse,
  l.quartier AS l_quartier,
  l.URL AS l_URL    ,
  lloc.localite AS lloc_localite,

  s.nom AS s_nom

FROM evenement e
JOIN localite loc ON e.localite_id = loc.id
LEFT JOIN lieu l ON e.idLieu = l.idLieu
LEFT JOIN localite lloc ON l.localite_id = lloc.id
LEFT JOIN salle s ON e.idSalle = s.idSalle
WHERE
  e.dateEvenement = :date AND $sql_even_in_status_and_region_clause
ORDER BY
  CASE e.genre
    WHEN 'fête' THEN 1
    WHEN 'cinéma' THEN 2
    WHEN 'théâtre' THEN 3
    WHEN 'expos' THEN 4
    WHEN 'divers' THEN 5
  END,
  e.dateAjout DESC";

$stmt = $connectorPdo->prepare($sql_events_today_in_region_order_by_category);
$stmt->execute(array_merge([':date' => $glo_auj_6h], $sql_even_in_status_and_region_params));

$tab_events_today_in_region_by_category = $stmt->fetchAll(PDO::FETCH_GROUP);
$count_events_today_in_region = $stmt->rowCount();

// from all events ids build an array of their organizers
$tab_events_today_ids = [];
foreach ($tab_events_today_in_region_by_category as $g => $events) {
    $tab_events_today_ids = [...$tab_events_today_ids, ...array_column($events, 'e_idEvenement')];
}

$tab_events_today_in_region_orgas = [];
if (!empty($tab_events_today_ids))
{
    list($eventsTodayIdsInClause, $eventsTodayIdsParams) = $connectorPdo->buildInClause('eo.idEvenement', $tab_events_today_ids);
//    dump($eventsTodayIdsInClause);
//    dump($eventsTodayIdsParams);

    $stmt = $connectorPdo->prepare("SELECT

    eo.idEvenement AS idEvenement,
    o.idOrganisateur AS o_idOrganisateur,
    o.nom AS o_nom,
    o.URL AS o_URL

    FROM evenement_organisateur eo
    JOIN organisateur o ON eo.idOrganisateur = o.idOrganisateur AND $eventsTodayIdsInClause
    ORDER BY nom DESC");

    $stmt->execute($eventsTodayIdsParams);

    $tab_orgas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tab_orgas AS $eo)
    {
        $tab_events_today_in_region_orgas[$eo['idEvenement']][] = [
            'idOrganisateur' => $eo['o_idOrganisateur'],
            'nom' => $eo['o_nom'],
            'url' => $eo['o_URL']
        ];
    }
}

// ten latest events added
$stmt = $connectorPdo->prepare("SELECT

  e.idEvenement as e_idEvenement,
  e.titre as e_titre,
  e.dateEvenement as e_dateEvenement,
  e.dateAjout as e_dateAjout,
  e.idLieu AS e_idLieu,
  e.idSalle AS e_idSalle,
  e.nomLieu AS e_nomLieu,
  e.adresse AS e_adresse,
  e.quartier AS e_quartier,
  loc.localite AS e_localite,
  e.urlLieu AS e_urlLieu,
  e.flyer e_flyer,
  e.image e_image,
  e.statut e_statut,

  l.nom AS l_nom,
  l.adresse AS l_adresse,
  l.quartier AS l_quartier,
  l.URL AS l_URL    ,
  lloc.localite AS lloc_localite,

  s.nom AS s_nom

FROM evenement e
LEFT JOIN lieu l ON e.idLieu = l.idLieu
LEFT JOIN localite lloc ON l.localite_id = lloc.id
LEFT JOIN salle s ON e.idSalle = s.idSalle
JOIN localite loc on e.localite_id = loc.id
WHERE $sql_even_in_status_and_region_clause ORDER BY e.dateAjout DESC LIMIT 0, 10");

$stmt->execute($sql_even_in_status_and_region_params);

$tab_ten_latest_events_in_region = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = null;

include("_header.inc.php");
?>

<main id="contenu" class="colonne">

    <?php
    // header banners & flash messages

    // public banner enabled (by admin) and not yet closed (by user)
    if (HOME_TMP_BANNER_ENABLED && !isset($_COOKIE['home_tmp_banner']))
    {
        ?>
        <div id="home_tmp_banner" class="alert-warn">
            <h2><?php echo HOME_TMP_BANNER_TITLE; ?></h2><a href="#" class="js-alert-close-btn close">&times;</a>
            <p><?php echo HOME_TMP_BANNER_CONTENT; ?></p>
        </div>
        <?php
    }
    ?>

    <?php
    // private banner enabled (by admin) and not yet closed (by user)
    if ($videur->checkGroup(UserLevel::ACTOR) && HOME_TMP_BACK_BANNER_ENABLED && !isset($_COOKIE['home_tmp_back_banner']))
    {
        ?>
        <div id="home_tmp_back_banner" class="alert-info">
            <h2><?php echo HOME_TMP_BACK_BANNER_TITLE; ?></h2><a href="#" class="js-alert-close-btn close">&times;</a>
            <p><?php echo HOME_TMP_BACK_BANNER_CONTENT; ?></p>
        </div>
        <?php
    }
    ?>

    <?php
    if (!empty($_SESSION['evenement-edit_flash_msg']))
    {
        HtmlShrink::msgOk($_SESSION['evenement-edit_flash_msg']);
        unset($_SESSION['evenement-edit_flash_msg']);
    }
    ?>

    <header id="entete_contenu">
        <h1 class="accueil">Aujourd’hui <a href="/rss.php?type=evenements_auj" title="Flux RSS des événements du jour" class="desktop"><i class="fa fa-rss fa-lg"></i></a><br>
            <small><?php echo ucfirst((string) date_fr($glo_auj_6h)); ?></small>
        </h1>
        <?php HtmlShrink::getMenuRegions($glo_regions, ['auj' => $glo_auj_6h], [$_SESSION['region'] => $count_events_today_in_region]); ?>
        <div class="spacer"></div>
    </header>

    <div class="spacer"><!-- --></div>

    <div id="prochains_evenements" >

        <?php
        if ($count_events_today_in_region == 0)
        {
            HtmlShrink::msgInfo("Pas d’événement prévu aujourd’hui");
        }

        // array categoriesIndexOfResults, par ex. : [0 => fetes, 1 => cine, 2 => expos] (pas de théatre ni divers)
        // start j=0
        // next : +1, prev : -1 (if exists) or current index +1 -1
        // if newCategory : j++
        $categoriesToIterateForAnchors = $glo_tab_genre;
        $genres_today = array_keys($tab_events_today_in_region_by_category);
        //dump($tab_events_today_in_region_order_by_category);
        foreach ($tab_events_today_in_region_by_category as $genre => $tab_genre_events)
        {
            $genre_even_nb = 0;
            ?>
                <section class="genre">

                    <header class="genre-titre">
                        <h2 id="<?php echo Text::stripAccents($glo_tab_genre[$genre]); ?>"><?php echo ucfirst($glo_tab_genre[$genre]); ?></h2>
                        <?php
                        $genre_proch = next($genres_today);
                        if (isset($tab_events_today_in_region_by_category[$genre_proch])) { ?>
                            <a class="genre-jump" href="#<?php echo Text::stripAccents($glo_tab_genre[$genre_proch]); ?>"><?php echo $glo_tab_genre[$genre_proch]; ?>&nbsp;<i class="fa fa-long-arrow-down"></i></a>
                        <?php } ?>
                        <div class="spacer"></div>
                    </header>

                    <?php
                    foreach ($tab_genre_events as $tab_even)
                    {
                        $genre_even_nb++;

                        // après le 1er even puis 1 item sur 2 : rappel
                        if (($genre_even_nb % 2 != 0)) : ?>
                            <p class="rappel_date"><?php echo $glo_regions[$_SESSION['region']]; ?>, aujourd’hui, <?php echo $glo_tab_genre[$genre]; ?></p>
                        <?php endif; ?>

                        <article class="evenement">

                            <header class="titre">
                                <h3 class="left"><?= Evenement::titre_selon_statut('<a href="/evenement.php?idE=' . (int) $tab_even['e_idEvenement'] . '">' . sanitizeForHtml($tab_even['e_titre']) . '</a>', $tab_even['e_statut']) ?></h3>
                                <span class="right">
                                    <!-- TODO: Lieu::getLinkNameHtml($tab_even) -->
                                    <?php
                                    $even_lieu = Evenement::getLieu($tab_even);
                                    if ($tab_even['e_idLieu']) { ?>
                                        <a href="/lieu.php?idL=<?= (int) $even_lieu['idLieu'] ?>"><?= sanitizeForHtml($even_lieu['nom']) ?></a>
                                    <?php } else { ?>
                                        <?= sanitizeForHtml($even_lieu['nom']) ?>
                                    <?php } ?>
                                </span>
                                <div class="spacer"></div>
                            </header> <!-- titre -->

                            <figure class="flyer">
                            <?php
                            // TODO: getFlyerHtml(path, smallWidth)
                            if (!empty($tab_even['e_flyer'])) { ?>
                                <a href="<?php echo Evenement::getFileHref(Evenement::getFilePath($tab_even['e_flyer'])) ?>" class="magnific-popup">
                                    <img src="<?php echo Evenement::getFileHref(Evenement::getFilePath($tab_even['e_flyer'], "s_"), true) ?>" alt="Flyer de <?= sanitizeForHtml($tab_even['e_titre'])?>" width="100" height="<?= ImageDriver2::getProportionalHeightFromGivenWidth(Evenement::getSystemFilePath(Evenement::getFilePath($tab_even['e_flyer'], "s_")), 100); ?>">
                                </a>
                            <?php } else if (!empty($tab_even['e_image'])) { ?>
                                <a href="<?php echo Evenement::getFileHref(Evenement::getFilePath($tab_even['e_image'])) ?>" class="magnific-popup">
                                    <img src="<?php echo Evenement::getFileHref(Evenement::getFilePath($tab_even['e_image'], "s_"), true) ?>" alt="Illustration de <?= sanitizeForHtml($tab_even['e_titre'])?>" width="100" height="<?= ImageDriver2::getProportionalHeightFromGivenWidth(Evenement::getSystemFilePath(Evenement::getFilePath($tab_even['e_image'], "s_")), 100); ?>">
                                </a>
                            <?php } ?>
                            </figure>

                    <div class="description">
                        <p>
                        <?= Text::texteHtmlReduit(Text::wikiToHtml(sanitizeForHtml($tab_even['e_description'])), Text::trouveMaxChar($tab_even['e_description'], 60, 6), " <a class=\"continuer\" href=\"/evenement.php?idE=" . (int) $tab_even['e_idEvenement'] . "\" title=\"Voir la fiche complète de l'événement\"> Lire la suite</a>"); ?>
                        </p>
                        <?php if (!empty($tab_events_today_in_region_orgas[$tab_even['e_idEvenement']])) { ?>
                            <ul class="event_orga" aria-label="Organisateurs">
                                <?php foreach ($tab_events_today_in_region_orgas[$tab_even['e_idEvenement']] as $eo) { ?>
                                    <li>
                                        <a href="/organisateur.php?idO=<?php echo (int) $eo['idOrganisateur']; ?>"><?php echo sanitizeForHtml($eo['nom']); ?></a><?php if (!empty($eo['url'])) { $organisateurUrl = Text::getUrlWithName($eo['url']); ?> -&nbsp;<a href="<?php echo sanitizeForHtml($organisateurUrl['url']); ?>" title="Site web de l'organisateur" class="lien_ext" target="_blank"><?php echo sanitizeForHtml($organisateurUrl['urlName']); ?></a>
                                        <?php } ?>
                                    </li>
                                <?php } ?>
                            </ul>
                        <?php } ?>
                    </div> <!-- description -->

                    <div class="spacer"></div>

                    <div class="pratique">

                        <span class="left"><?= sanitizeForHtml(HtmlShrink::getAdressFitted(null, $even_lieu['localite'], $even_lieu['quartier'], $even_lieu['adresse'])); ?></span>
                        <span class="right"><?php
                            $horaire_complet = afficher_debut_fin($tab_even['e_horaire_debut'], $tab_even['e_horaire_fin'], $tab_even['e_dateEvenement'])." " . sanitizeForHtml($tab_even['e_horaire_complement']);
                            echo $horaire_complet;
                            // TODO: try echo implode(", ", [$horaire_complet, $tab_even['e_prix']]);
                            if (!empty($tab_even['e_prix']))
                            {
                                if (!empty($tab_even['e_horaire_debut']) || !empty($tab_even['e_horaire_fin']) || !empty($tab_even['e_horaire_complement']))
                                {
                                    echo ", ";
                                }
                                echo sanitizeForHtml($tab_even['e_prix']);
                            }
                            ?>
                        </span>
                        <div class="spacer"></div>
                    </div> <!-- fin pratique -->

                    <footer class="edition">

                        <ul class="menu_action">
                            <li><a href="/evenement-report.php?idE=<?php echo (int) $tab_even['e_idEvenement']; ?>" class="signaler" title="Signaler une erreur"><i class="fa fa-flag-o fa-lg"></i></a></li>
                            <li><a href="/evenement_ics.php?idE=<?php echo (int) $tab_even['e_idEvenement']; ?>" class="ical" title="Exporter au format iCalendar dans votre agenda"><i class="fa fa-calendar-plus-o fa-lg"></i></a></li>
                        </ul>

                        <?php if ($authorization->isPersonneAllowedToEditEvenement($_SESSION, $tab_even)) : ?>
                        <ul class="menu_edition">
                            <li class="action_copier">
                                <a href="/evenement-copy.php?idE=<?= (int) $tab_even['e_idEvenement'] ?>" title="Copier l'événement">Copier vers d'autres dates</a>
                            </li>
                            <li class="action_editer">
                                <a href="/evenement-edit.php?action=editer&amp;idE=<?= (int) $tab_even['e_idEvenement'] ?>" title="Modifier l'événement">Modifier</a>
                            </li>
                            <li class="action_depublier">
                                <a href="#" id="btn_event_unpublish_<?= (int) $tab_even['e_idEvenement'] ?>" class="btn_event_unpublish" data-id="<?= (int) $tab_even['e_idEvenement'] ?>">Dépublier</a>
                            </li>
                            <?php if ($authorization->isPersonneAllowedToManageEvenement($_SESSION, $tab_even)) : ?>
                            <li>
                                <a href="/user.php?idP=<?= (int) $tab_even['e_idPersonne'] ?>"><?= $icone['personne'] ?></a>
                            </li>
                            <?php endif; ?>
                        </ul>
                        <?php endif; ?>

                    </footer> <!-- fin edition -->

                    <div class="spacer"></div>

                </article> <!-- evenement -->

                <div class="spacer"></div>

            <?php } // foreach events ?>

        </section>

       <?php } // foreach ?>

    </div> <!-- prochains_evenements -->

</main>
<!-- fin contenu -->


<aside id="colonne_gauche" class="colonne">

    <?php include("_navigation_calendrier.inc.php"); ?>

    <div class="secondaire">

        <ul class="autour">
            <li><a href="https://www.facebook.com/ladecadanse" aria-label="Page Facebook" style="font-size:1em" target="_blank"><i class="fa fa-facebook fa-2x" aria-hidden="true"></i></a></li>
            <li style="margin-left:10px;font-size:1em"><a href="https://github.com/agilare/ladecadanse/" aria-label="Watch agilare/ladecadanse on GitHub" target="_blank"><i class="fa fa-github fa-2x" aria-hidden="true"></i></a>
            </li>
            <li id="faireundon_btn" class="clear_mobile_important"><a href="/articles/faireUnDon.php">Faire un don</a>
            </li>
        </ul>

        <section class="partenaires">
            <h2>Partenaires</h2>
            <ul class="autour">
                <li><a href="https://olivedks.ch/" target="_blank"><img src="/web/content/debout-les-braves-s.jpg" alt="Debout les braves - Visions de la scène genevoise et d'ailleurs" title="Debout les braves - Visions de la scène genevoise et d'ailleurs" width="150" height="63"></a></li>
                <li><a href="https://culture-accessible.ch/" target="_blank"><img src="/web/content/culture-accessible-geneve.svg" alt="Culture accessible Genève" width="150" height="46"></a></li>
                <li><a href="https://epic-magazine.ch/" target="_blank"><img src="/web/content/EPIC_noir.png" alt="EPIC Magazine" width="150" height="94"></a></li>
                <li><a href="https://www.radiovostok.ch/" target="_blank"><img src="/web/content/radio_vostok.png" alt="Radio Vostok" width="150" height="59"></a></li>
                <li><a href="https://www.darksite.ch/" target="_blank"><img src="/web/content/darksite.png" alt="Darksite" width="150" height="43"></a></li>
            </ul>
        </section>
    </div>

</aside>
<!-- Fin Colonnegauche -->


<aside id="colonne_droite" class="colonne">

    <section class="secondaire">

        <span class="lien_rss"><a href="/rss.php?type=evenements_ajoutes" aria-label="Flux RSS des derniers événements"><i class="fa fa-rss fa-lg"></i></a></span>

        <h2>Derniers événements ajoutés</h2>

        <div id="derniers_evenements">

            <?php
            foreach ($tab_ten_latest_events_in_region as $tab_even)
            {
                $even_lieu = Evenement::getLieu($tab_even);
                ?>
                <div class="dernier_evenement">

                    <figure class="flyer">
                    <?php if (!empty($tab_even['e_flyer'])) { ?>
                        <a href="<?php echo Evenement::getFileHref(Evenement::getFilePath($tab_even['e_flyer'])) ?>" class="magnific-popup">
                            <img src="<?php echo Evenement::getFileHref(Evenement::getFilePath($tab_even['e_flyer'], "s_"), true) ?>" alt="Flyer de <?= sanitizeForHtml($tab_even['e_titre']) ?>" width="60" height="<?= ImageDriver2::getProportionalHeightFromGivenWidth(Evenement::getSystemFilePath(Evenement::getFilePath($tab_even['e_flyer'], "s_")), 60); ?>">
                        </a>
                    <?php } else if (!empty($tab_even['e_image'])) { ?>
                        <a href="<?php echo Evenement::getFileHref(Evenement::getFilePath($tab_even['e_image'])) ?>" class="magnific-popup">
                            <img src="<?php echo Evenement::getFileHref(Evenement::getFilePath($tab_even['e_image'], "s_"), true) ?>" alt="Illustration de <?= sanitizeForHtml($tab_even['e_titre']) ?>" width="60" height="<?= ImageDriver2::getProportionalHeightFromGivenWidth(Evenement::getSystemFilePath(Evenement::getFilePath($tab_even['e_image'], "s_")), 60); ?>">
                        </a>
                    <?php } ?>
                    </figure>

                    <h3><?= Evenement::titre_selon_statut('<a href="/evenement.php?idE=' . (int) $tab_even['e_idEvenement'] . '">' . sanitizeForHtml($tab_even['e_titre']) . '</a>', $tab_even['e_statut']) ?>
                    </h3>
                    <span>
                    <?php if ($tab_even['e_idLieu']) { ?>
                        <a href="/lieu.php?idL=<?= (int) $even_lieu['idLieu'] ?>"><?= sanitizeForHtml($even_lieu['nom']) ?></a>
                    <?php } else { ?>
                        <?= sanitizeForHtml($even_lieu['nom']) ?>
                    <?php } ?>
                    </span>

                    <p>le&nbsp;<a href="/evenement-agenda.php?courant=<?= urlencode($tab_even['e_dateEvenement']) ?>"><?= date_fr($tab_even['e_dateEvenement']) ?></a></p>
                    <div class="spacer"></div>
                </div> <!-- dernier_evenement -->

                <div class="spacer"><!-- --></div>

            <?php
            }
            ?>

        </div> <!-- Fin derniers_evenements -->

    </section> <!-- fin dernieres -->

</aside> <!-- Fin colonne_droite -->

<div class="spacer"><!-- --></div>

<?php
include("_footer.inc.php");
?>
