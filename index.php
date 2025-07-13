<?php
// declare(strict_types=1);
/*
 * @package ladecadanse
 * @copyright  Copyright (c) 2007 - 2025 Michel Gaudry <michel@ladecadanse.ch>
 * @license    AGPL License; see LICENSE file for details.
 */

global $connector;
require_once("app/bootstrap.php");

use Ladecadanse\Evenement;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Lieu;
use Ladecadanse\Organisateur;
use Ladecadanse\UserLevel;
use Ladecadanse\Utils\Text;

// used for meta tags, opengraph
$page_titre = " agenda de sorties à " . $glo_regions[$_SESSION['region']] . ", prochains événements : concerts, soirées, films, théâtre, expos, bars, cinémas";
$page_description = "Programme des prochains événements festifs et culturels à Genève et Lausanne : fêtes, concerts et soirées, cinéma, théâtre, expositions, vernissages, conférences, lieux culturels et alternatifs";

// filter & overwrite date
$get['courant'] = $glo_auj_6h;
if (!empty($_GET['courant']) && preg_match("/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}$/", trim((string) $_GET['courant'])))
{
    $get['courant'] = $_GET['courant'];
    $page_titre = "Agenda d'événements du " . date_fr($get['courant'], "annee", "", "", false);
    $page_description = "Événements culturels et festifs à Genève et Lausanne : concerts, soirées, films, théâtre, expos... ";
}

$is_courant_today = (empty($get['courant']) || $get['courant'] == $glo_auj_6h);

$day_label = !$is_courant_today ? date_fr($get['courant']) : "aujourd'hui";

$courant_year = (new DateTime($get['courant']))->format("Y");

$page_titre .= " à Genève, Nyon, Lausanne, Pays de Gex, Annemasse...";
$page_description .= "";

list($regionInClause, $regionInParams) = $connectorPdo->buildInClause('e.region', $glo_regions_coverage[$_SESSION['region']]);

$sql_even_in_status_and_region_clause = " e.statut NOT IN ('inactif', 'propose') AND ($regionInClause OR FIND_IN_SET (:region, loc.regions_covered)) ";
$sql_even_in_status_and_region_params = array_merge([':region' => $_SESSION['region']], $regionInParams);

//// filter eventual user sorting
//$get['tri_agenda'] = "dateAjout";
//if (!empty($_GET['tri_agenda']) && in_array($_GET['tri_agenda'], $tab_tri_agenda)) {
//
//    $get['tri_agenda'] = $_GET['tri_agenda'];
//}
//
//// build SQL
//$sql_tri_agenda = "e.".$get['tri_agenda'] . " DESC";
//if ($get['tri_agenda'] == "horaire_debut")
//{
//	$sql_tri_agenda = "e.horaire_debut ASC";
//}

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
  e.region AS e_region,
  e.urlLieu AS e_urlLieu,

  l.nom AS l_nom,
  l.adresse AS l_adresse,
  l.quartier AS l_quartier,
  l.URL AS l_URL    ,
  lloc.localite AS lloc_localite,
  l.region AS l_region,
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
//$sql_tri_agenda
$stmt = $connectorPdo->prepare($sql_events_today_in_region_order_by_category);
$stmt->execute(array_merge([':date' => $get['courant']], $sql_even_in_status_and_region_params));

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

$date_prev = (new DateTime($get['courant']))->modify('-1 day')->format("Y-m-d");
$date_next = (new DateTime($get['courant']))->modify('+1 day')->format("Y-m-d");

include("_header.inc.php");
?>

<main id="contenu" class="colonne">

    <?php
    // header banners & flash messages

    // public banner enabled (by admin) and not yet closed (by user)
    if (HOME_TMP_BANNER_ENABLED && !isset($_COOKIE['home_tmp_banner'])) : ?>
        <div id="home_tmp_banner" class="alert-warn">
            <h2><?= HOME_TMP_BANNER_TITLE; ?></h2><a href="#" class="js-alert-close-btn close">&times;</a>
            <p><?= HOME_TMP_BANNER_CONTENT; ?></p>
        </div>
    <?php endif; ?>

    <?php
    // private banner enabled (by admin) and not yet closed (by user)
    if ($videur->checkGroup(UserLevel::ACTOR) && HOME_TMP_BACK_BANNER_ENABLED && !isset($_COOKIE['home_tmp_back_banner'])) : ?>
        <div id="home_tmp_back_banner" class="alert-info">
            <h2><?= HOME_TMP_BACK_BANNER_TITLE; ?></h2><a href="#" class="js-alert-close-btn close">&times;</a>
            <p><?= HOME_TMP_BACK_BANNER_CONTENT; ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['evenement-edit_flash_msg'])) :
        HtmlShrink::msgOk($_SESSION['evenement-edit_flash_msg']);
        unset($_SESSION['evenement-edit_flash_msg']);
    endif; ?>

    <header id="entete_contenu">
        <h1 class="accueil"><?= ucfirst((string) date_fr($get['courant'])); ?>
            <?php if ($courant_year !== date("Y")) { echo $courant_year; } ?>
            <?php if ($is_courant_today) : ?><br>
                <small>Aujourd’hui <a href="/rss.php?type=evenements_auj" title="Flux RSS des événements du jour" class="desktop"><i class="fa fa-rss fa-lg"></i></a></small><?php endif; ?>
        </h1>
        <ul class="entete_contenu_navigation">
            <li><a href="index.php?courant=<?= $date_prev ?>" rel="nofollow"><?= $iconePrecedent ?></a></li><li><a href="index.php?courant=<?= $date_next ?>" rel="nofollow"><?= ucfirst(date_fr($date_next, "tout", "non", "")).$iconeSuivant ?></a></li>
        </ul>
        <div class="spacer"></div>
    </header>


    <div id="prochains_evenements">

<!--        <div style="">
            <div style="float: right;margin:0.2em 0;">
                <span style="text-align:bottom"><?= $icone['date'] ?></span>
                <ul style="list-style-type: none;display:inline-block;margin:0;padding:0">
                    <li style="display:inline-block;"><a href="?dateAjout<?= ($get['tri_agenda'] !== 'dateAjout' ? '&amp;tri_agenda=' . $get['tri_agenda'] : '' ) ?>" class="<?php if ($get['tri_agenda'] == 'dateAjout') { ?>selected<?php } ?>">dernier ajouté</a></li>
                    <li style="display:inline-block;"><a href="?horaire_debut<?= ($get['tri_agenda'] !== 'dateAjout' ? '&amp;tri_agenda=' . $get['tri_agenda'] : '' ) ?>" class="<?php if ($get['tri_agenda'] == 'horaire_debut') { ?>selected<?php } ?>">heure de début</a></li>
                </ul>
            </div>
            <div class="spacer"></div>
        </div>-->

        <?php
        if ($count_events_today_in_region == 0)
        {
            HtmlShrink::msgInfo("Pas d’événement prévu ce jour");
        }

        $genres_today = array_keys($tab_events_today_in_region_by_category);
        foreach ($tab_events_today_in_region_by_category as $genre => $tab_genre_events)
        {
            $genre_even_nb = 0;
            ?>
                <section class="genre">

                    <header class="genre-titre">
                        <h2 id="<?= Text::stripAccents($glo_tab_genre[$genre]); ?>"><?= ucfirst($glo_tab_genre[$genre]); ?></h2>
                        <?php
                        $genre_proch = next($genres_today);
                        if (isset($tab_events_today_in_region_by_category[$genre_proch])) : ?>
                            <a class="genre-jump" href="#<?= Text::stripAccents($glo_tab_genre[$genre_proch]); ?>"><?= $glo_tab_genre[$genre_proch]; ?>&nbsp;<i class="fa fa-long-arrow-down"></i></a>
                        <?php endif; ?>
                        <div class="spacer"></div>
                    </header>

                    <?php
                    foreach ($tab_genre_events as $tab_even)
                    {
                        $genre_even_nb++;

                        $even_lieu = Evenement::getLieu($tab_even);

                        // après le 1er even puis 1 item sur 2 : rappel
                        if (($genre_even_nb % 2 != 0) && $genre_even_nb > 1) : ?>
                            <p class="rappel_date"><?= ucfirst($day_label) ?>, <?= $glo_tab_genre[$genre]; ?></p>
                        <?php endif; ?>

                        <article class="evenement">

                            <header class="titre">
                                <h3 class="left"><a href="/evenement.php?idE=<?= (int) $tab_even['e_idEvenement'] ?>"><?= Evenement::titreSelonStatutHtml(sanitizeForHtml($tab_even['e_titre']), $tab_even['e_statut']) ?></a></h3>
                                <span class="right"><?= Lieu::getLinkNameHtml($even_lieu['nom'], $even_lieu['idLieu'], $even_lieu['salle']) ?></span>
                                <div class="spacer"></div>
                            </header>

                            <figure class="flyer"><?= Evenement::mainFigureHtml($tab_even['e_flyer'], $tab_even['e_image'], $tab_even['e_titre'], 100) ?></figure>

                            <div class="description">
                                <p>
                                <?= Text::texteHtmlReduit(Text::wikiToHtml(sanitizeForHtml($tab_even['e_description'])), Text::trouveMaxChar($tab_even['e_description'], 60, 6), ' <a class="continuer" href="/evenement.php?idE=' . (int) $tab_even['e_idEvenement'] . '"> Lire la suite</a>'); ?>
                                </p>
                                <?php if (!empty($tab_events_today_in_region_orgas[$tab_even['e_idEvenement']])): ?>
                                    <?= Organisateur::getListLinkedHtml($tab_events_today_in_region_orgas[$tab_even['e_idEvenement']]) ?>
                                <?php endif; ?>
                            </div>

                            <div class="spacer"></div>

                            <div class="pratique">
                                <span class="left"><?= sanitizeForHtml(HtmlShrink::adresseCompacteSelonContexte($even_lieu['region'], $even_lieu['localite'], $even_lieu['quartier'], $even_lieu['adresse'])); ?></span>
                                <span class="right">
                                    <?php
                                    $horaire_complet = afficher_debut_fin($tab_even['e_horaire_debut'], $tab_even['e_horaire_fin'], $tab_even['e_dateEvenement'])." " . sanitizeForHtml($tab_even['e_horaire_complement']);
                                    echo $horaire_complet;
                                    if (!empty($horaire_complet))
                                    {
                                        echo ", ";
                                    }
                                    echo sanitizeForHtml($tab_even['e_prix']);
                                    ?>
                                </span>
                                <div class="spacer"></div>
                            </div> <!-- fin pratique -->

                            <footer class="edition">

                                <ul class="menu_action">
                                    <li><a href="/evenement-report.php?idE=<?= (int) $tab_even['e_idEvenement']; ?>" class="signaler" title="Signaler une erreur"><i class="fa fa-flag-o fa-lg"></i></a></li>
                                    <li><a href="/evenement_ics.php?idE=<?= (int) $tab_even['e_idEvenement']; ?>" class="ical" title="Exporter au format iCalendar dans votre agenda"><i class="fa fa-calendar-plus-o fa-lg"></i></a></li>
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
    <?php if ($is_courant_today) : ?>
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
    <?php endif; ?>
</aside>
<!-- Fin Colonnegauche -->


<aside id="colonne_droite" class="colonne">

    <?php if ($is_courant_today) : ?>

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

                        <figure class="flyer"><?= Evenement::mainFigureHtml($tab_even['e_flyer'], $tab_even['e_image'], $tab_even['e_titre'], 60) ?></figure>

                        <h3><a href="/evenement.php?idE=<?= (int) $tab_even['e_idEvenement'] ?>"><?= Evenement::titreSelonStatutHtml(sanitizeForHtml($tab_even['e_titre']), $tab_even['e_statut']) ?></a></h3>
                        <span><?= Lieu::getLinkNameHtml($even_lieu['nom'], $even_lieu['idLieu'], $even_lieu['salle']) ?></span>

                        <p>le&nbsp;<a href="/evenement-agenda.php?courant=<?= urlencode($tab_even['e_dateEvenement']) ?>"><?= date_fr($tab_even['e_dateEvenement']) ?></a></p>
                        <div class="spacer"></div>
                    </div> <!-- dernier_evenement -->

                    <div class="spacer"><!-- --></div>

                <?php
                }
                ?>

            </div> <!-- Fin derniers_evenements -->

        </section> <!-- fin dernieres -->

    <?php endif; ?>

</aside> <!-- Fin colonne_droite -->

<div class="spacer"><!-- --></div>

<?php
include("_footer.inc.php");
?>
