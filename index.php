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

include("_header.inc.php");

// eventsTodayPusblishedInRegionscoveredOrderByCategory
$sqlEv = "SELECT idEvenement, genre, idLieu, idSalle, nomLieu, adresse, quartier, localite.localite AS localite, urlLieu, statut,
 titre, idPersonne, dateEvenement, ref, flyer, image, description, horaire_debut, horaire_fin,
 horaire_complement, prix, prelocations
 FROM evenement, localite
 WHERE evenement.localite_id=localite.id AND dateEvenement LIKE '" . $glo_auj_6h . "%' AND statut NOT IN ('inactif', 'propose') AND (region IN ('" . implode("', '", $glo_regions_coverage[$_SESSION['region']]) ."') OR FIND_IN_SET ('" . $connector->sanitize($_SESSION['region']) . "', localite.regions_covered))
 ORDER BY CASE `genre`
        WHEN 'fête' THEN 1
        WHEN 'cinéma' THEN 2
        WHEN 'théâtre' THEN 3
        WHEN 'expos' THEN 4
        WHEN 'divers' THEN 5
        END, dateAjout DESC";
$req_even = $connector->query($sqlEv);

// countEventsTodayPusblishedInRegionGe
$event_count = ['ge' => 0, 'vd' => 0];
$req_even_ge_nb = $connector->query("SELECT COUNT(idEvenement) AS nb
 FROM evenement, localite
 WHERE evenement.localite_id=localite.id AND dateEvenement LIKE '" . $glo_auj_6h . "%' AND statut NOT IN ('inactif', 'propose') AND (region IN ('" . implode("', '", $glo_regions_coverage['ge']) ."') OR FIND_IN_SET ('ge', localite.regions_covered))");
$event_count['ge'] = $connector->fetchAll($req_even_ge_nb)[0]['nb'];

//countEventsTodayPusblishedInRegionGe
$req_even_vd_nb = $connector->query("SELECT COUNT(idEvenement) AS nb
 FROM evenement, localite
 WHERE evenement.localite_id=localite.id AND dateEvenement LIKE '" . $glo_auj_6h . "%' AND statut NOT IN ('inactif', 'propose') AND (region IN ('" . implode("', '", $glo_regions_coverage['vd']) ."') OR FIND_IN_SET ('vd', localite.regions_covered))");
$event_count['vd'] = $connector->fetchAll($req_even_vd_nb)[0]['nb'];

// tenLastestPublishedEventsInRegioncoveredOrderByCreationDesc
$req_dern_even = $connector->query("
SELECT idEvenement, titre, dateEvenement, dateAjout, nomLieu, idLieu, idSalle, flyer, image, statut
FROM evenement
JOIN localite l on evenement.localite_id = l.id
WHERE (region IN ('" . implode("', '", $glo_regions_coverage[$_SESSION['region']]) ."') OR FIND_IN_SET ('" . $connector->sanitize($_SESSION['region']) . "', l.regions_covered) ) AND statut NOT IN ('inactif', 'propose') ORDER BY dateAjout DESC LIMIT 0, 10
");
?>

<div id="contenu" class="colonne">

    <?php
    // header banners & flash messages
    // banner enabled (by admin) and not yet closed (by user)
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
    // private banner
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

    <!-- TODO: <h1> englobant les 2 <h2> ? -->
    <div id="entete_contenu">
        <h2 class="accueil">Aujourd’hui <a href="/rss.php?type=evenements_auj" title="Flux RSS des événements du jour" class="desktop"><i class="fa fa-rss fa-lg"></i></a></h2>
        <?php HtmlShrink::getMenuRegions($glo_regions, ['auj' => $glo_auj_6h], $event_count); ?>
        <div class="spacer"></div>
        <h2 id="today-date"><small><?php echo ucfirst((string) date_fr($glo_auj_6h)); ?></small></h2>
    </div>

    <div class="spacer"><!-- --></div>

    <section id="prochains_evenements" >

        <?php
        if ($event_count[$_SESSION['region']] == 0)
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
        while ($tab_even = $connector->fetchArray($req_even))
        {
            if ($tab_even['genre'] != $genre_courant)
            {
                $genre_even_nb = 0;
                // cloture d'une categorie
                if ($genre_courant != '')
                {
                    echo "</div>";
                }

                ?>

                <div class="genre">

                    <div class="genre-titre">

                        <h3 id="<?php echo Text::stripAccents($glo_tab_genre[$tab_even['genre']]); ?>"><?php echo ucfirst($glo_tab_genre[$tab_even['genre']]); ?></h3>
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
                    <p class="rappel_date"><?php echo $glo_regions[$_SESSION['region']]; ?>, aujourd’hui, <?php echo $glo_tab_genre[$tab_even['genre']]; ?></p>
                    <?php
                }

                $genre_courant = $tab_even['genre'];

                // Affichage du lieu selon son existence ou non dans la base
                // TODO: left join & lieu_nom, lieu_adresse...
                // TODO: function Event::getLieu($tab_even)
                // TODO: idLieu !== null
                // Lieu::htmlLinkName($tab_even)
                // Lieu::htmlAdresse($tab_even)
                // TODO: left join salle
                if ($tab_even['idLieu'] != 0)
                {
                    $listeLieu = $connector->fetchArray(
                        $connector->query("SELECT nom, adresse, quartier, localite.localite AS localite, URL FROM lieu, localite WHERE lieu.localite_id=localite.id AND idlieu='" . (int) $tab_even['idLieu'] . "'"));

                    $evenLieuNomHtml = "<a href=\"/lieu.php?idL=" . (int) $tab_even['idLieu'] . "\" >" . sanitizeForHtml($listeLieu['nom']) . "</a>";

                    if ($tab_even['idSalle'])
                    {
                        $req_salle = $connector->query("SELECT nom FROM salle WHERE idSalle='" . (int) $tab_even['idSalle'] . "'");
                        $tab_salle = $connector->fetchArray($req_salle);
                        $evenLieuNomHtml .= " - " . sanitizeForHtml($tab_salle['nom']);
                    }
                }
                else
                {
                    $listeLieu['nom'] = sanitizeForHtml($tab_even['nomLieu']);
                    $evenLieuNomHtml = sanitizeForHtml($tab_even['nomLieu']);
                    $listeLieu['adresse'] = sanitizeForHtml($tab_even['adresse']);
                    $listeLieu['quartier'] = sanitizeForHtml($tab_even['quartier']);
                    $listeLieu['localite'] = sanitizeForHtml($tab_even['localite']);
                }
                // TODO: select distinct idOrganisateur, ... from organisateur join evenement_organisateur eo where eo.idE IN (idE1, idE2...)
                // TODO: eventsOrganisateurs[idEvenement][orga1, orga2, ...]
                $sql_event_orga = "SELECT organisateur.idOrganisateur, nom, URL FROM organisateur, evenement_organisateur
                            WHERE evenement_organisateur.idEvenement=" . (int) $tab_even['idEvenement'] . " AND
                             organisateur.idOrganisateur=evenement_organisateur.idOrganisateur
                             ORDER BY nom DESC";
                $req_event_orga = $connector->query($sql_event_orga);

                ?>

                <article class="evenement">

                    <div class="titre">
                        <span class="left">
                            <?php
                            echo Evenement::titre_selon_statut('<a href="/evenement.php?idE=' . (int) $tab_even['idEvenement'] . '">' . sanitizeForHtml($tab_even['titre']) . '</a>', $tab_even['statut']);
                            ?>
                        </span>
                        <span class="right"><?php echo $evenLieuNomHtml ?></span>
                        <div class="spacer"></div>
                    </div>

                    <div class="flyer">
                    <?php
                    // Even::getLinkImageHtml($tab_even)
                    if (!empty($tab_even['flyer'])) { ?>
                        <a href="<?php echo Evenement::getFileHref(Evenement::getFilePath($tab_even['flyer'])) ?>" class="magnific-popup">
                            <img src="<?php echo Evenement::getFileHref(Evenement::getFilePath($tab_even['flyer'], "s_"), true) ?>" alt="Flyer" width="100" />
                        </a>
                    <?php } else if (!empty($tab_even['image'])) { ?>
                        <a href="<?php echo Evenement::getFileHref(Evenement::getFilePath($tab_even['image'])) ?>" class="magnific-popup">
                            <img src="<?php echo Evenement::getFileHref(Evenement::getFilePath($tab_even['image'], "s_"), true) ?>" alt="Photo" width="100" />
                        </a>
                    <?php } ?>
                    </div>

                    <div class="description">
                        <?= Text::texteHtmlReduit(Text::wikiToHtml(sanitizeForHtml($tab_even['description'])), Text::trouveMaxChar($tab_even['description'], 60, 6), " <a class=\"continuer\" href=\"/evenement.php?idE=" . (int) $tab_even['idEvenement'] . "\" title=\"Voir la fiche complète de l'événement\"> Lire la suite</a>"); ?>
                        <ul class="event_orga">
                            <?php
                            // TODO: getOrgaNameAndLink() // nom url urlraccourci
                            while ($tab = $connector->fetchArray($req_event_orga)) {
                                $org_url = $tab['URL'];
                                $org_url_nom = rtrim(preg_replace("(^https?://)", "", (string) $tab['URL']), "/");
                                if (!preg_match("/^https?:\/\//", (string) $tab['URL']))
                                {
                                    $org_url = 'http://' . $tab['URL'];
                                }
                                ?>
                                <li><a href="/organisateur.php?idO=<?php echo (int) $tab['idOrganisateur']; ?>"><?php echo sanitizeForHtml($tab['nom']); ?></a>
                                    <?php
                                    if (!empty($tab['URL']))
                                    {
                                        ?>
                                        <a href="<?php echo sanitizeForHtml($org_url); ?>" title="Site web de l'organisateur" class="lien_ext" target="_blank"><?php echo sanitizeForHtml($org_url_nom); ?></a>
                                    <?php } ?>
                                </li>
                                <?php
                            }
                            ?>
                        </ul>
                    </div> <!-- description -->

                    <div class="spacer"></div>

                    <div class="pratique">
                        <span class="left"><?= sanitizeForHtml(HtmlShrink::getAdressFitted(null, $listeLieu['localite'], $listeLieu['quartier'], $listeLieu['adresse'])); ?></span>
                        <span class="right"><?php
                            echo afficher_debut_fin($tab_even['horaire_debut'], $tab_even['horaire_fin'], $tab_even['dateEvenement'])." " . sanitizeForHtml($tab_even['horaire_complement']);
                            // TODO: getPrix
                            if (!empty($tab_even['prix']))
                            {
                                if (!empty($tab_even['horaire_debut']) || !empty($tab_even['horaire_fin']) || !empty($tab_even['horaire_complement']))
                                {
                                    echo ", ";
                                }
                                echo sanitizeForHtml($tab_even['prix']);
                            }
                            ?>
                        </span>
                        <div class="spacer"></div>
                    </div> <!-- fin pratique -->

                    <div class="edition">

                        <ul class="menu_action">
                            <li><a href="/evenement-report.php?idE=<?php echo (int) $tab_even['idEvenement']; ?>" class="signaler" title="Signaler une erreur"><i class="fa fa-flag-o fa-lg"></i></a></li>
                            <li><a href="/evenement_ics.php?idE=<?php echo (int) $tab_even['idEvenement']; ?>" class="ical" title="Exporter au format iCalendar dans votre agenda"><i class="fa fa-calendar-plus-o fa-lg"></i></a></li>
                        </ul>

                        <?php
                        // TODO: isAllowedToEdit($_Session, $tab_even...)
                        //Peut ètre édité par les 'auteurs' sinon par le propre publicateur de cet événement
                        if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 6 || $_SESSION['SidPersonne'] == $tab_even['idPersonne']) || (isset($_SESSION['Saffiliation_lieu']) && !empty($tab_even['idLieu']) && $tab_even['idLieu'] == $_SESSION['Saffiliation_lieu']) || isset($_SESSION['SidPersonne']) && $authorization->isPersonneInEvenementByOrganisateur($_SESSION['SidPersonne'], $tab_even['idEvenement']) || isset($_SESSION['SidPersonne']) && $tab_even['idLieu'] != 0 && $authorization->isPersonneInLieuByOrganisateur($_SESSION['SidPersonne'], $tab_even['idLieu'])
                        )
                        {
                            ?>

                        <ul class="menu_edition">
                            <li class="action_copier">
                                <a href="/evenement-copy.php?idE=<?= (int) $tab_even['idEvenement'] ?>" title="Copier l'événement">Copier vers d'autres dates</a>
                            </li>
                            <li class="action_editer">
                                <a href="/evenement-edit.php?action=editer&amp;idE=<?= (int) $tab_even['idEvenement'] ?>" title="Modifier l'événement">Modifier</a>
                            </li>
                            <li class="action_depublier">
                                <a href="#" id="btn_event_unpublish_<?= (int) $tab_even['idEvenement'] ?>" class="btn_event_unpublish" data-id="<?= (int) $tab_even['idEvenement'] ?>">Dépublier</a>
                            </li>
                            <li>
                                <a href="/user.php?idP=<?= (int) $tab_even['idPersonne'] ?>"><?= $icone['personne'] ?></a>
                            </li>
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

    <section class="dernieres">

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

    <div class="dernieres">

        <span class="lien_rss"><a href="/rss.php?type=evenements_ajoutes"><i class="fa fa-rss fa-lg"></i></a></span>

        <h2>Derniers événements ajoutés</h2>

        <div id="derniers_evenements">

            <?php
            $date_ajout_courante = "";
            while ($tab_dern_even = $connector->fetchArray($req_dern_even))
            {
                $date_ajout = mb_substr((string) $tab_dern_even['dateAjout'], 0, 10);

                $evenLieuNomHtml = sanitizeForHtml($tab_dern_even['nomLieu']);
                if ($tab_dern_even['idLieu'] != 0)
                {
                    $evenLieuNomHtml = "<a href=\"/lieu.php?idL=" . (int) $tab_dern_even['idLieu'] . "\">" . sanitizeForHtml($tab_dern_even['nomLieu']) . "</a>";
                    if ($tab_dern_even['idSalle'] != 0)
                    {
                        $req_salle = $connector->query("SELECT nom, emplacement FROM salle WHERE idSalle='" . (int) $tab_dern_even['idSalle'] . "'");
                        $tab_salle = $connector->fetchArray($req_salle);
                        $evenLieuNomHtml .= " - " . sanitizeForHtml($tab_salle['nom']);
                    }
                }
                ?>
                <div class="dernier_evenement">

                    <div class="flyer">
                    <?php if (!empty($tab_dern_even['flyer'])) { ?>
                        <a href="<?php echo Evenement::getFileHref(Evenement::getFilePath($tab_dern_even['flyer'])) ?>" class="magnific-popup">
                            <img src="<?php echo Evenement::getFileHref(Evenement::getFilePath($tab_dern_even['flyer'], "s_"), true) ?>" alt="Flyer" width="60" />
                        </a>
                    <?php } else if (!empty($tab_dern_even['image'])) { ?>
                        <a href="<?php echo Evenement::getFileHref(Evenement::getFilePath($tab_dern_even['image'])) ?>" class="magnific-popup">
                            <img src="<?php echo Evenement::getFileHref(Evenement::getFilePath($tab_dern_even['image'], "s_"), true) ?>" alt="Photo" width="60" />
                        </a>
                    <?php } ?>
                    </div>

                    <h4><?= Evenement::titre_selon_statut('<a href="/evenement.php?idE=' . (int) $tab_dern_even['idEvenement'] . '" title="">' . sanitizeForHtml($tab_dern_even['titre']) . '</a>', $tab_dern_even['statut']) ?></h4>
                    <h5><?= $evenLieuNomHtml ?></h5>

                    <p>le&nbsp;<a href="/evenement-agenda.php?courant=<?= urlencode($tab_dern_even['dateEvenement']) ?>"><?= date_fr($tab_dern_even['dateEvenement']) ?></a></p>
                    <div class="spacer"></div>
                </div> <!-- dernier_evenement -->

                <div class="spacer"><!-- --></div>

                <?php
                $date_ajout_courante = $date_ajout;
                }
            ?>

        </div> <!-- Fin derniers_evenements -->

    </div> <!-- fin dernieres -->

</aside> <!-- Fin colonne_droite -->

<div class="spacer"><!-- --></div>

<?php
include("_footer.inc.php");
?>
