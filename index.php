<?php

require_once("app/bootstrap.php");

use Ladecadanse\Utils\Text;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Evenement;

$page_titre = " agenda de sorties à " . $glo_regions[$_SESSION['region']] . ", prochains événements : concerts, soirées, films, théâtre, expos, bars, cinémas";
$page_description = "Programme des prochains événements festifs et culturels à Genève et Lausanne : fêtes, concerts et soirées, cinéma,
théâtre, expositions, vernissages, conférences, lieux culturels et alternatifs";

include("_header.inc.php");

$get['auj'] = date("Y-m-d", time() - 21600); // 6h

$sql_rf = "";
if ($_SESSION['region'] == 'ge')
    $sql_rf = " 'rf', ";


$sqlEv = "SELECT idEvenement, genre, idLieu, idSalle, nomLieu, adresse, quartier, localite.localite AS localite, urlLieu, statut,
 titre, idPersonne, dateEvenement, ref, flyer, image, description, horaire_debut, horaire_fin,
 horaire_complement, prix, prelocations
 FROM evenement, localite
 WHERE evenement.localite_id=localite.id AND dateEvenement LIKE '" . $get['auj'] . "%' AND statut NOT IN ('inactif', 'propose') AND region IN ('" . $connector->sanitize($_SESSION['region']) . "', " . $sql_rf . " 'hs')
 ORDER BY CASE `genre`
        WHEN 'fête' THEN 1
        WHEN 'cinéma' THEN 2
        WHEN 'théâtre' THEN 3
        WHEN 'expos' THEN 4
        WHEN 'divers' THEN 5
        END, dateAjout DESC";

$req_even = $connector->query($sqlEv);

$event_count = ['ge' => 0, 'vd' => 0];
$req_even_ge_nb = $connector->query("SELECT COUNT(idEvenement) AS nb
 FROM evenement, localite
 WHERE evenement.localite_id=localite.id AND dateEvenement LIKE '" . $get['auj'] . "%' AND statut NOT IN ('inactif', 'propose') AND region IN ('ge', 'rf', 'hs')");
$event_count['ge'] = $connector->fetchAll($req_even_ge_nb)[0]['nb'];
$req_even_vd_nb = $connector->query("SELECT COUNT(idEvenement) AS nb
 FROM evenement, localite
 WHERE evenement.localite_id=localite.id AND dateEvenement LIKE '" . $get['auj'] . "%' AND statut NOT IN ('inactif', 'propose') AND region IN ('vd', 'hs')");
$event_count['vd'] = $connector->fetchAll($req_even_vd_nb)[0]['nb'];

$req_dern_even = $connector->query("
SELECT idEvenement, titre, dateEvenement, dateAjout, nomLieu, idLieu, idSalle, flyer, image, statut
FROM evenement WHERE region IN ('" . $connector->sanitize($_SESSION['region']) . "', " . $sql_rf . " 'hs') AND statut NOT IN ('inactif', 'propose') ORDER BY dateAjout DESC LIMIT 0, 10
");
?>

<div id="contenu" class="colonne">

    <?php
    if (HOME_TMP_BANNER_ENABLED && !isset($_COOKIE['msg_orga_benevole'])) {
        ?>
        <div id="home-tmp-banner">
            <h2><?php echo HOME_TMP_BANNER_TITLE; ?></h2>
            <a class="close" href="#" onclick="SetCookie('msg_orga_benevole', 1, 180);this.parentNode.style.display = 'none';return false;">&times;</a>
            <p style="line-height:18px"><?php echo HOME_TMP_BANNER_CONTENT; ?></p>
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
        <h2 class="accueil" style="margin: 0;">Aujourd’hui <a href="/rss.php?type=evenements_auj" title="Flux RSS des événements du jour" style="font-size:12px;vertical-align: top;" class="desktop"><i class="fa fa-rss fa-lg" style="color:#f5b045"></i></a></h2>
        <?php HtmlShrink::getMenuRegions($glo_regions, $get, $event_count); ?>
        <div class="spacer"></div>
        <h2 style="width:65%;font-size: 1.4em;margin-top: 0;"><small><?php echo ucfirst(date_fr($get['auj'])); ?></small></h2>
	</div>

	<div class="spacer"><!-- --></div>

	<section id="prochains_evenements" >

        <?php

        if ($event_count[$_SESSION['region']] == 0) {
            HtmlShrink::msgInfo("Pas d’événement prévu aujourd’hui");
}

        $dateCourante = ' ';

        $tab_genres = $glo_tab_genre;
        $genre_courant = '';
        $genre_prec = '';
        $genre_fr = "";
        $i = 0;

        while ($tab_even = $connector->fetchArray($req_even))
        {
            if ($tab_even['genre'] != $genre_courant) {
                if ($genre_courant != '') {
            $genre_prec = Text::stripAccents($tab_genres[$genre_courant]);
            echo "</div>";
                    $i = 0;
                }

                $genre_fr = ucfirst($glo_tab_genre[$tab_even['genre']]);

                $proch = '';
        /* 		if ($np = next(&$tab_genres))
                    $proch = replace_accents($np); */
                if ($np = next($tab_genres))
                    $proch = Text::stripAccents($np);
            ?>

            <div class="genre">

                <div class="genre-titre">
                    <h3 id="<?php echo mb_strtolower(Text::stripAccents($genre_fr)); ?>"><?php echo $genre_fr; ?></h3>

                    <?php if ($tab_even['genre'] != 'divers') { ?>
                    <a class="genre-jump" href="#<?php echo $proch; ?>"><i class="fa fa-long-arrow-down"></i></a>
                    <?php } else { ?>
                    <span style="float: right;margin: 0.2em;padding: 0.4em 0.8em;">&nbsp;</span>
                    <?php } ?>
                    <?php if ($tab_even['genre'] != 'fête') { ?>
                    <a class="genre-jump" href="#<?php echo $genre_prec; ?>"><i class="fa fa-long-arrow-up"></i></a>
                    <?php } ?>

                    <div class="spacer"></div>
                </div>
            <?php
            }

            $genre_courant = $tab_even['genre'];


            //Affichage du lieu selon son existence ou non dans la base
            if ($tab_even['idLieu'] != 0)
            {
                $listeLieu = $connector->fetchArray(
                $connector->query("SELECT nom, adresse, quartier, localite.localite AS localite, URL FROM lieu, localite WHERE lieu.localite_id=localite.id AND idlieu='".$tab_even['idLieu']."'"));

                $infosLieu = "<a href=\"/lieu.php?idL=" . $tab_even['idLieu'] . "\" >" . htmlspecialchars($listeLieu['nom']) . "</a>";

        if ($tab_even['idSalle'])
                {
                    $req_salle = $connector->query("SELECT nom FROM salle WHERE idSalle='".$tab_even['idSalle']."'");
                    $tab_salle = $connector->fetchArray($req_salle);
                    $infosLieu .= " - ".$tab_salle['nom'];
                }
            }
            else
            {
                $listeLieu['nom'] = htmlspecialchars($tab_even['nomLieu']);
        $infosLieu = htmlspecialchars($tab_even['nomLieu']);
                $listeLieu['adresse'] = htmlspecialchars($tab_even['adresse']);
                $listeLieu['quartier'] = htmlspecialchars($tab_even['quartier']);
                $listeLieu['localite'] = htmlspecialchars($tab_even['localite']);
            }

            $sql_event_orga = "SELECT organisateur.idOrganisateur, nom, URL
            FROM organisateur, evenement_organisateur
            WHERE evenement_organisateur.idEvenement=".$tab_even['idEvenement']." AND
             organisateur.idOrganisateur=evenement_organisateur.idOrganisateur
             ORDER BY nom DESC";

            $req_event_orga = $connector->query($sql_event_orga);

            $even_adresse = HtmlShrink::getAdressFitted(null, $listeLieu['localite'], $listeLieu['quartier'], $listeLieu['adresse']);

            if ($i > 1 && ($i % 2 != 0))
            {
                $region = $glo_regions[$_SESSION['region']];
                if ($_SESSION['region'] == 'vd')
                    $region = "Lausanne";
            ?>
                    <p class="rappel_date"><?php echo $region; ?>, aujourd’hui, <?php echo mb_strtolower($genre_fr); // ",".date_fr($get['auj']); ?></p>

            <?php
            }
        ?>

        <article class="evenement">

            <div class="titre">
                <span class="left">
                <?php
                $maxChar = Text::trouveMaxChar($tab_even['description'], 60, 6);
                $titre_url = '<a href="/evenement.php?idE=' . $tab_even['idEvenement'] . '">' . sanitizeForHtml($tab_even['titre']) . '</a>';

    echo Evenement::titre_selon_statut($titre_url, $tab_even['statut']);
    ?>
                </span>
                <span class="right"><?php echo $infosLieu ?></span>
                <div class="spacer"></div>
            </div>

            <div class="flyer">
                <?php
                if (!empty($tab_even['flyer']))
                {
                    $imgInfo = @getimagesize($rep_images_even.$tab_even['flyer']);
                    ?>
                    <a href="<?php echo $url_uploads_events.$tab_even['flyer']; ?>" class="magnific-popup"><img src="<?php echo $url_uploads_events."s_".$tab_even['flyer']; ?>" alt="Flyer" width="100" /></a>
                    <?php
                }
                else if (!empty($tab_even['image']))
                {
                    $imgInfo = @getimagesize($rep_images_even.$tab_even['image']);
                    ?>

                    <a href="<?php echo $url_uploads_events.$tab_even['image']; ?>" class="magnific-popup"><img src="<?php echo $url_uploads_events."s_".$tab_even['image']; ?>" alt="Photo" width="100" /></a>

                <?php
                }
                ?>
            </div>

            <div class="description">
                <?php
                //reduction de la description pour la caser dans la boite "desc"
                if (mb_strlen($tab_even['description']) > $maxChar)
                {
                    $continuer = " <a class=\"continuer\" href=\"/evenement.php?idE=".$tab_even['idEvenement']."\" title=\"Voir la fiche complète de l'événement\"> Lire la suite</a>";
                    echo Text::texteHtmlReduit(Text::wikiToHtml(htmlspecialchars($tab_even['description'])),$maxChar, $continuer);
                }
                else
                {
                    echo Text::wikiToHtml(htmlspecialchars($tab_even['description']));
                }
                ?>

                <ul class="event_orga">
                    <?php
                    while ($tab = $connector->fetchArray($req_event_orga))
                    {
                        $org_url = $tab['URL'];
                        $org_url_nom = rtrim(preg_replace("(^https?://)", "", $tab['URL']), "/");
                        if (!preg_match("/^https?:\/\//", $tab['URL']))
                        {
                            $org_url = 'http://'.$tab['URL'];
                        }
                    ?>
                    <li><a href="/organisateur.php?idO=<?php echo $tab['idOrganisateur']; ?>"><?php echo $tab['nom']; ?></a> <a href="<?php echo $org_url; ?>" title="Site web de l'organisateur" class="lien_ext" target="_blank"><?php echo $org_url_nom; ?></a></li>
                            <?php
                    }
                    ?>
                </ul>

            </div>

            <div class="spacer"></div>

            <div class="pratique">
                <span class="left"><?php echo htmlspecialchars($even_adresse); ?></span><span class="right"><?php echo afficher_debut_fin($tab_even['horaire_debut'], $tab_even['horaire_fin'], $tab_even['dateEvenement']);
                if (!empty($tab_even['prix']))
                {
                    if (!empty($tab_even['horaire_debut']) || !empty($tab_even['horaire_fin']))
                    {
                        echo ", ";
                    }
                    echo htmlspecialchars($tab_even['prix']);
                }
                ?>

                </span>
                <div class="spacer"></div>
            </div> <!-- fin pratique -->

            <div class="edition">

                <ul class="menu_action">
                        <li><a href="/evenement-report.php?idE=<?php echo $tab_even['idEvenement']; ?>" class="signaler"  title="Signaler une erreur"><i class="fa fa-flag-o fa-lg"></i></a></li>
                        <li><a href="/evenement_ics.php?idE=<?php echo $tab_even['idEvenement']; ?>" class="ical" title="Exporter au format iCalendar dans votre agenda"><i class="fa fa-calendar-plus-o fa-lg"></i></a></li>
                    </ul>

                    <?php
                    //Peut ètre édité par les 'auteurs' sinon par le propre publicateur de cet événement
                    if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 6 || $_SESSION['SidPersonne'] == $tab_even['idPersonne']) || (isset($_SESSION['Saffiliation_lieu']) && !empty($tab_even['idLieu']) && $tab_even['idLieu'] == $_SESSION['Saffiliation_lieu']) || isset($_SESSION['SidPersonne']) && $authorization->isPersonneInEvenementByOrganisateur($_SESSION['SidPersonne'], $tab_even['idEvenement']) || isset($_SESSION['SidPersonne']) && $tab_even['idLieu'] != 0 && $authorization->isPersonneInLieuByOrganisateur($_SESSION['SidPersonne'], $tab_even['idLieu'])
                    ) {
                        ?>

            <ul class="menu_edition">
                <?php
                echo "<li class=\"action_copier\"><a href=\"/evenement-copy.php?idE=".$tab_even['idEvenement']."\" title=\"Copier l'événement\">Copier vers d'autres dates</a></li>";
                echo "<li class=\"action_editer\"><a href=\"/evenement-edit.php?action=editer&amp;idE=".$tab_even['idEvenement']."\" title=\"Modifier l'événement\">Modifier</a></li>";
                echo '<li class="action_depublier"><a href="#" id="btn_event_unpublish_'.$tab_even['idEvenement'].'" class="btn_event_unpublish" data-id='.$tab_even['idEvenement'].'>Dépublier</a></li>';
                echo '<li class=""><a href="/user.php?idP='.$tab_even['idPersonne'].'">'.$icone['personne'].'</a></li>';
                echo '</ul>';
                }
                ?>
            </ul>

        </div> <!-- fin edition -->

        <div class="spacer"></div>

        </article> <!-- evenement -->

        <div class="spacer"></div>

            <?php
            $i++;
            } //while

            if ($genre_courant != '')
        {
            echo "</div>";
        }
        ?>
    </section> <!-- prochains_evenements -->

</div>
<!-- fin contenu -->


<aside id="colonne_gauche" class="colonne">

    <?php include("_navigation_calendrier.inc.php"); ?>


    <section id="dernieres" style="margin-top:15px;width: 100%;">

        <ul style="padding-left:5px">
            <li style="display:inline-block"><a href="https://www.facebook.com/ladecadanse" aria-label="Watch agilare/ladecadanse on GitHub" style="font-size:1em" target="_blank"><i class="fa fa-facebook fa-2x" aria-hidden="true"></i></a></li>
            <li style="display:inline-block;margin-left:10px"><a href="https://github.com/agilare/ladecadanse/" aria-label="Watch agilare/ladecadanse on GitHub" style="font-size:1em" target="_blank"><i class="fa fa-github fa-2x" aria-hidden="true"></i></a>
            </li>
        </ul>

        <h2 style="margin-top:15px;">Partenaires</h2>
        <ul style="list-style-type: none;padding-left:5px">
            <li style="margin:2px 0;float:left;"><a href="https://www.noctambus.ch/" target="_blank"><img src="/web/interface/noctambus.jpg" alt="Noctambus - réseau de bus de nuit desservant le canton de Genève et ses régions transfrontalières" title="Noctambus - réseau de bus de nuit desservant le canton de Genève et ses régions transfrontalières" width="150" style="border:1px solid #eaeaea" /></a></li>
            <li style="margin:2px 0;float:left;"><a href="https://www.darksite.ch/olive/oliveblog/" target="_blank"><img src="/web/interface/debout-les-braves.jpg" alt="Debout les braves - Visions de la scène genevoise et d'ailleurs" title="Debout les braves - Visions de la scène genevoise et d'ailleurs" width="150" style="border:1px solid #eaeaea" /></a></li>
            <li style="margin:2px 0;float:left;"><a href="https://culture-accessible.ch/" target="_blank"><img src="/web/interface/culture-accessible-geneve.svg" alt="Culture accessible Genève" width="150" style="border:1px solid #eaeaea" /></a></li>
            <li style="margin:2px 0;float:left;"><a href="https://epic-magazine.ch/" target="_blank"><img src="/web/interface/EPIC_noir.png" alt="EPIC Magazine" width="150" style="border:1px solid #eaeaea" /></a></li>

            <li style="margin:2px 0;float:left;"><a href="https://www.radiovostok.ch/" target="_blank"><img src="/web/interface/radio_vostok.png" alt="Radio Vostok" width="150" height="59" style="border:1px solid #eaeaea" /></a></li>

        <li style="margin:2px 0;float:left;">
            <a href="https://www.darksite.ch/" target="_blank"><img src="/web/interface/darksite.png" alt="Darksite" width="150" height="43" style="border:1px solid #eaeaea" /></a></li>
        </ul>
    </section>

</aside>
<!-- Fin Colonnegauche -->


<aside id="colonne_droite" class="colonne">

    <div id="dernieres">

        <span style="float:right;margin-top:0.4em;padding:0.2em;"><a href="/rss.php?type=evenements_ajoutes"><i class="fa fa-rss fa-lg" style="color:#f5b045"></i></a></span>
        <h2>Derniers événements ajoutés</h2>

    <div id="derniers_evenements">
        <?php
        $date_ajout_courante = "";

    // Création de la section si il y a moins un lieu
    if ($connector->getNumRows($req_dern_even) > 0)
    {
        while ($tab_dern_even = $connector->fetchArray($req_dern_even))
        {
            $date_ajout = mb_substr($tab_dern_even['dateAjout'], 0, 10);

            if ($tab_dern_even['idLieu'] != 0)
            {

                $infosLieu = "<a href=\"/lieu.php?idL=" . $tab_dern_even['idLieu'] . "\">" . htmlspecialchars($tab_dern_even['nomLieu']) . "</a>";

            if ($tab_dern_even['idSalle'] != 0)
                {
                    $req_salle = $connector->query("SELECT nom, emplacement FROM salle
                    WHERE idSalle='".$tab_dern_even['idSalle']."'");
                    $tab_salle = $connector->fetchArray($req_salle);
                    $infosLieu .=  " - ".$tab_salle['nom'];

                }
            }
            else
            {
                $infosLieu = htmlspecialchars($tab_dern_even['nomLieu']);
            }

            echo "<div class=\"dernier_evenement\">";

            echo "<div class=\"flyer\">";

            if (!empty($tab_dern_even['flyer']))
            {
                $imgInfo = @getimagesize($rep_images_even.$tab_dern_even['flyer']);

                ?>

        <a href="<?php echo $url_uploads_events . $tab_dern_even['flyer']; ?>" class="magnific-popup"><img src="<?php echo $url_uploads_events . "s_" . $tab_dern_even['flyer']; ?>" alt="Flyer" width="60" /></a>

                            <?php
                        }
            else if (!empty($tab_dern_even['image']))
            {
                $imgInfo = @getimagesize($rep_images_even.$tab_dern_even['image']);

                ?>

                <a href="<?php echo $url_uploads_events.$tab_dern_even['image']; ?>" class="magnific-popup"><img src="<?php echo $url_uploads_events."s_".$tab_dern_even['image']; ?>" alt="Photo" width="60" /></a>

                            <?php
                        }

            echo "</div>";



            echo "<h4>";
            $titre_url = "<a href=\"/evenement.php?idE=".$tab_dern_even['idEvenement']."\" title=\"\" >".
            sanitizeForHtml($tab_dern_even['titre']).'</a>';

            echo Evenement::titre_selon_statut($titre_url, $tab_dern_even['statut']);

        echo "</h4>";
            echo '<h5 style="font-size:1em;color:#5C7378">';
            echo $infosLieu;
            echo "</h5>";
            echo "<p>le&nbsp;<a href=/evenement-agenda.php?courant=".$tab_dern_even['dateEvenement'].">".date_fr($tab_dern_even['dateEvenement'])."</a></p><div class=\"spacer\"></div>";
            echo "</div>";
            echo '<div class="spacer"><!-- --></div>';
            $date_ajout_courante = $date_ajout;
        }
    }
    ?>

    </div>
    <!-- Fin derniers_evenements -->

    </div> <!-- fin dernieres

</aside> <!-- Fin colonne_droite -->

<div class="spacer"><!-- --></div>

<?php
include("_footer.inc.php");
?>
