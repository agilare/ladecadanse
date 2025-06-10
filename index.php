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

// used for meta tags, opengraph
$page_titre = " agenda de sorties à " . $glo_regions[$_SESSION['region']] . ", prochains événements : concerts, soirées, films, théâtre, expos, bars, cinémas";
$page_description = "Programme des prochains événements festifs et culturels à Genève et Lausanne : fêtes, concerts et soirées, cinéma, théâtre, expositions, vernissages, conférences, lieux culturels et alternatifs";

$sql_even_in_status_and_region = " e.statut NOT IN ('inactif', 'propose') AND (e.region IN ('" . implode("', '", $glo_regions_coverage[$_SESSION['region']]) ."') OR FIND_IN_SET ('" . $connector->sanitize($_SESSION['region']) . "', loc.regions_covered)) ";

$res_events_today_in_region_order_by_category = $connector->query("SELECT
  e.idEvenement AS e_idEvenement,
  e.genre AS e_genre,
  e.titre AS e_titre,
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

  e.statut AS e_statut,

  l.nom AS l_nom,
  l.adresse AS l_adresse,
  l.quartier AS l_quartier,
  l.URL AS l_URL    ,
  ll.localite AS ll_localite,

  s.nom AS s_nom

FROM evenement e
JOIN localite loc ON e.localite_id = loc.id
LEFT JOIN lieu l ON e.idLieu = l.idLieu
LEFT JOIN localite ll ON l.localite_id = ll.id
LEFT JOIN salle s ON e.idSalle = s.idSalle

WHERE
  e.dateEvenement = '". $connector->sanitize($glo_auj_6h) ."' AND  $sql_even_in_status_and_region

ORDER BY
  CASE e.genre
    WHEN 'fête' THEN 1
    WHEN 'cinéma' THEN 2
    WHEN 'théâtre' THEN 3
    WHEN 'expos' THEN 4
    WHEN 'divers' THEN 5
  END,
  e.dateAjout DESC");

$count_events_today_in_region = $connector->getNumRows($res_events_today_in_region_order_by_category);

$tab_events_today_in_region_order_by_category  = $connector->fetchAllAssoc($res_events_today_in_region_order_by_category);

$tab_orgas = $connector->fetchAllAssoc($connector->query("SELECT eo.idEvenement AS idEvenement, o.idOrganisateur AS o_idOrganisateur, o.nom AS o_nom, o.URL AS o_URL FROM evenement_organisateur eo"
    . " JOIN organisateur o ON eo.idOrganisateur = o.idOrganisateur AND eo.idEvenement IN (". implode(', ', array_map('intval', array_column($tab_events_today_in_region_order_by_category, 'e_idEvenement')))
    . ") ORDER BY nom DESC"));

$tab_events_today_in_region_orgas = [];
foreach ($tab_orgas AS $eo)
{
    $tab_events_today_in_region_orgas[$eo['idEvenement']][] = [
        'idOrganisateur' => $eo['o_idOrganisateur'],
        'nom' => $eo['o_nom'],
        'url' => $eo['o_URL']
    ];
}

$tab_ten_latest_events_in_region = $connector->query("
SELECT e.idEvenement as e_idEvenement, e.titre as e_titre, e.dateEvenement as e_dateEvenement, e.dateAjout as e_dateAjout, e.idLieu AS e_idLieu, e.idSalle AS e_idSalle, e.nomLieu AS e_nomLieu, e.adresse AS e_adresse, e.quartier AS e_quartier, loc.localite AS e_localite, e.urlLieu AS e_urlLieu, e.flyer e_flyer, e.image e_image, e.statut e_statut,
  l.nom AS l_nom,
  l.adresse AS l_adresse,
  l.quartier AS l_quartier,
  l.URL AS l_URL    ,
  ll.localite AS ll_localite,

  s.nom AS s_nom

FROM evenement e
LEFT JOIN lieu l ON e.idLieu = l.idLieu
LEFT JOIN localite ll ON l.localite_id = ll.id
LEFT JOIN salle s ON e.idSalle = s.idSalle
JOIN localite loc on e.localite_id = loc.id

WHERE $sql_even_in_status_and_region ORDER BY e.dateAjout DESC LIMIT 0, 10");

include("_header.inc.php");
?>

<div id="contenu" class="colonne">

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

    <div id="entete_contenu">
        <h1 class="accueil">Aujourd’hui <a href="/rss.php?type=evenements_auj" title="Flux RSS des événements du jour" class="desktop"><i class="fa fa-rss fa-lg"></i></a><br>
            <small><?php echo ucfirst((string) date_fr($glo_auj_6h)); ?></small>
        </h1>
        <?php HtmlShrink::getMenuRegions($glo_regions, ['auj' => $glo_auj_6h], [$_SESSION['region'] => $count_events_today_in_region]); ?>
        <div class="spacer"></div>
    </div>

    <div class="spacer"><!-- --></div>

    <section id="prochains_evenements" >

        <?php
        if ($count_events_today_in_region == 0)
        {
            HtmlShrink::msgInfo("Pas d’événement prévu aujourd’hui");
        }

        //
        // array categoriesIndexOfResults, par ex. : [0 => fetes, 1 => cine, 2 => expos] (pas de théatre ni divers)
        // start j=0
        // next : +1, prev : -1 (if exists) or current index +1 -1
        // if newCategory : j++
        //
        // tab_even[genre]
        $categoriesToIterateForAnchors = $glo_tab_genre;
        $genre_courant = '';
        foreach ($tab_events_today_in_region_order_by_category as $tab_even)
        {
            if ($tab_even['e_genre'] != $genre_courant)
            {
                $genre_even_nb = 0;
                // cloture d'une categorie
                if ($genre_courant != '')
                { ?>
                   </div>
                <?php }
                ?>

                <div class="genre">

                    <div class="genre-titre">

                        <h2 id="<?php echo Text::stripAccents($glo_tab_genre[$tab_even['e_genre']]); ?>"><?php echo ucfirst($glo_tab_genre[$tab_even['e_genre']]); ?></h2>
                        <?php
                        $genre_proch = next($categoriesToIterateForAnchors);
                        if ($genre_proch)
                        {
                        ?>
                            <a class="genre-jump" href="#<?php echo Text::stripAccents($genre_proch); ?>"><?php echo $genre_proch; ?>&nbsp;<i class="fa fa-long-arrow-down"></i></a>
                        <?php
                        }
                        ?>
                        <div class="spacer"></div>
                    </div>
            <?php
            }

            // après le 1er even puis 1 item sur 2 : rappel
            if ($genre_even_nb > 1 && ($genre_even_nb % 2 != 0))
            {
                ?>
                <p class="rappel_date"><?php echo $glo_regions[$_SESSION['region']]; ?>, aujourd’hui, <?php echo $glo_tab_genre[$tab_even['e_genre']]; ?></p>
                <?php
            }

            $genre_courant = $tab_even['e_genre'];
            ?>

            <article class="evenement">

                <div class="titre">
                    <span class="left"><?= Evenement::titre_selon_statut('<a href="/evenement.php?idE=' . (int) $tab_even['e_idEvenement'] . '">' . sanitizeForHtml($tab_even['e_titre']) . '</a>', $tab_even['e_statut']) ?></span>
                    <span class="right">
                        <!-- TODO: Lieu::htmlLinkName($tab_even) -->
                        <?php
                        $even_lieu = Evenement::getLieu($tab_even);
                        if ($tab_even['e_idLieu']) {
                            ?>
                            <a href="/lieu.php?idL=<?= (int) $even_lieu['idLieu'] ?>"><?= sanitizeForHtml($even_lieu['nom']) ?></a>
                        <?php } else { ?>
                            <?= sanitizeForHtml($even_lieu['nom']) ?>
                        <?php } ?>
                    </span>
                    <div class="spacer"></div>
                </div> <!-- titre -->

                <div class="flyer">
                <?php
                // Even::getLinkImageHtml($tab_even)
                if (!empty($tab_even['e_flyer'])) { ?>
                    <a href="<?php echo Evenement::getFileHref(Evenement::getFilePath($tab_even['e_flyer'])) ?>" class="magnific-popup">
                        <img src="<?php echo Evenement::getFileHref(Evenement::getFilePath($tab_even['e_flyer'], "s_"), true) ?>" alt="Flyer" width="100" />
                    </a>
                <?php } else if (!empty($tab_even['e_image'])) { ?>
                    <a href="<?php echo Evenement::getFileHref(Evenement::getFilePath($tab_even['e_image'])) ?>" class="magnific-popup">
                        <img src="<?php echo Evenement::getFileHref(Evenement::getFilePath($tab_even['e_image'], "s_"), true) ?>" alt="Photo" width="100" />
                    </a>
                <?php } ?>
                </div>

                <div class="description">
                    <?= Text::texteHtmlReduit(Text::wikiToHtml(sanitizeForHtml($tab_even['e_description'])), Text::trouveMaxChar($tab_even['e_description'], 60, 6), " <a class=\"continuer\" href=\"/evenement.php?idE=" . (int) $tab_even['e_idEvenement'] . "\" title=\"Voir la fiche complète de l'événement\"> Lire la suite</a>"); ?>
                    <?php if (!empty($tab_events_today_in_region_orgas[$tab_even['e_idEvenement']])) { ?>
                    <ul class="event_orga">
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

                <div class="edition">

                    <ul class="menu_action">
                        <li><a href="/evenement-report.php?idE=<?php echo (int) $tab_even['e_idEvenement']; ?>" class="signaler" title="Signaler une erreur"><i class="fa fa-flag-o fa-lg"></i></a></li>
                        <li><a href="/evenement_ics.php?idE=<?php echo (int) $tab_even['e_idEvenement']; ?>" class="ical" title="Exporter au format iCalendar dans votre agenda"><i class="fa fa-calendar-plus-o fa-lg"></i></a></li>
                    </ul>

                    <?php
                    // TODO: isAllowedToEdit($_Session, $tab_even...)
                    //Peut ètre édité par les 'auteurs' sinon par le propre publicateur de cet événement
                    if (
                        isset($_SESSION['Sgroupe'])
                        && ($_SESSION['Sgroupe'] <= UserLevel::AUTHOR || $_SESSION['SidPersonne'] == $tab_even['e_idPersonne'])

                        || (isset($_SESSION['Saffiliation_lieu']) && !empty($tab_even['idLieu']) && $tab_even['e_idLieu'] == $_SESSION['Saffiliation_lieu'])
                        || isset($_SESSION['SidPersonne']) && $authorization->isPersonneInEvenementByOrganisateur($_SESSION['SidPersonne'], $tab_even['e_idEvenement'])
                        || isset($_SESSION['SidPersonne']) && $tab_even['e_idLieu'] != 0 && $authorization->isPersonneInLieuByOrganisateur($_SESSION['SidPersonne'], $tab_even['e_idLieu'])
                    )
                    {
                        ?>

                    <ul class="menu_edition">
                        <li class="action_copier">
                            <a href="/evenement-copy.php?idE=<?= (int) $tab_even['e_idEvenement'] ?>" title="Copier l'événement">Copier vers d'autres dates</a>
                        </li>
                        <li class="action_editer">
                            <a href="/evenement-edit.php?action=editer&amp;idE=<?= (int) $tab_even['e_idEvenement'] ?>" title="Modifier l'événement">Modifier</a>
                        </li>
                        <li class="action_depublier">
                            <a href="#" id="btn_event_unpublish_<?= (int) $tab_even['e_idEvenement'] ?>" class="btn_event_unpublish" data-id="<?= (int) $tab_even['idEvenement'] ?>">Dépublier</a>
                        </li>
                        <?php if ($_SESSION['Sgroupe'] <= UserLevel::AUTHOR) { ?>
                        <li>
                            <a href="/user.php?idP=<?= (int) $tab_even['e_idPersonne'] ?>"><?= $icone['personne'] ?></a>
                        </li>
                        <?php }?>
                    </ul>

                    <?php } ?>

                </div> <!-- fin edition -->

                <div class="spacer"></div>

            </article> <!-- evenement -->

            <div class="spacer"></div>

            <?php
            $genre_even_nb++;
        } //while

        // closes last <div class="genre">
        if ($genre_courant != '')
        {
            echo "</div> ";
        }
        ?>
    </section> <!-- prochains_evenements -->

</div>
<!-- fin contenu -->


<aside id="colonne_gauche" class="colonne">

    <?php include("_navigation_calendrier.inc.php"); ?>

    <section class="secondaire">

        <ul class="autour">
            <li><a href="https://www.facebook.com/ladecadanse" aria-label="Watch agilare/ladecadanse on GitHub" style="font-size:1em" target="_blank"><i class="fa fa-facebook fa-2x" aria-hidden="true"></i></a></li>
            <li style="margin-left:10px;font-size:1em"><a href="https://github.com/agilare/ladecadanse/" aria-label="Watch agilare/ladecadanse on GitHub" target="_blank"><i class="fa fa-github fa-2x" aria-hidden="true"></i></a>
            </li>
            <li id="faireundon_btn" class="clear_mobile_important"><a href="/articles/faireUnDon.php">Faire un don</a>
            </li>
        </ul>

        <div class="partenaires">
            <h2>Partenaires</h2>
            <ul class="autour">
                <li><a href="https://olivedks.ch/" target="_blank"><img src="/web/content/debout-les-braves.jpg" alt="Debout les braves - Visions de la scène genevoise et d'ailleurs" title="Debout les braves - Visions de la scène genevoise et d'ailleurs" width="150" /></a></li>
                <li><a href="https://culture-accessible.ch/" target="_blank"><img src="/web/content/culture-accessible-geneve.svg" alt="Culture accessible Genève" width="150" /></a></li>
                <li><a href="https://epic-magazine.ch/" target="_blank"><img src="/web/content/EPIC_noir.png" alt="EPIC Magazine" width="150" /></a></li>
                <li><a href="https://www.radiovostok.ch/" target="_blank"><img src="/web/content/radio_vostok.png" alt="Radio Vostok" width="150" height="59" /></a></li>
                <li><a href="https://www.darksite.ch/" target="_blank"><img src="/web/content/darksite.png" alt="Darksite" width="150" height="43"  /></a></li>
            </ul>
        </div>
    </section>

</aside>
<!-- Fin Colonnegauche -->


<aside id="colonne_droite" class="colonne">

    <div class="secondaire">

        <span class="lien_rss"><a href="/rss.php?type=evenements_ajoutes"><i class="fa fa-rss fa-lg"></i></a></span>

        <h2>Derniers événements ajoutés</h2>

        <div id="derniers_evenements">

            <?php
            while ($tab_even = $connector->fetchArray($tab_ten_latest_events_in_region))
            {
                $even_lieu = Evenement::getLieu($tab_even);
                ?>
                <div class="dernier_evenement">

                    <div class="flyer">
                    <?php if (!empty($tab_even['e_flyer'])) { ?>
                        <a href="<?php echo Evenement::getFileHref(Evenement::getFilePath($tab_even['e_flyer'])) ?>" class="magnific-popup">
                            <img src="<?php echo Evenement::getFileHref(Evenement::getFilePath($tab_even['e_flyer'], "s_"), true) ?>" alt="Flyer" width="60" />
                        </a>
                    <?php } else if (!empty($tab_even['e_image'])) { ?>
                        <a href="<?php echo Evenement::getFileHref(Evenement::getFilePath($tab_even['e_image'])) ?>" class="magnific-popup">
                            <img src="<?php echo Evenement::getFileHref(Evenement::getFilePath($tab_even['e_image'], "s_"), true) ?>" alt="Photo" width="60" />
                        </a>
                    <?php } ?>
                    </div>

                    <h3><?= Evenement::titre_selon_statut('<a href="/evenement.php?idE=' . (int) $tab_even['e_idEvenement'] . '">' . sanitizeForHtml($tab_even['e_titre']) . '</a>', $tab_even['e_statut']) ?>
                    </h3>
                    <h4>
                    <?php if ($tab_even['e_idLieu']) { ?>
                        <a href="/lieu.php?idL=<?= (int) $even_lieu['idLieu'] ?>"><?= sanitizeForHtml($even_lieu['nom']) ?></a>
                    <?php } else { ?>
                        <?= sanitizeForHtml($even_lieu['nom']) ?>
                    <?php } ?>
                    </h4>

                    <p>le&nbsp;<a href="/evenement-agenda.php?courant=<?= urlencode($tab_even['e_dateEvenement']) ?>"><?= date_fr($tab_even['e_dateEvenement']) ?></a></p>
                    <div class="spacer"></div>
                </div> <!-- dernier_evenement -->

                <div class="spacer"><!-- --></div>

            <?php
            }
            ?>

        </div> <!-- Fin derniers_evenements -->

    </div> <!-- fin dernieres -->

</aside> <!-- Fin colonne_droite -->

<div class="spacer"><!-- --></div>

<?php
include("_footer.inc.php");
?>
