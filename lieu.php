<?php

require_once("app/bootstrap.php");

use Ladecadanse\UserLevel;
use Ladecadanse\Lieu;
use Ladecadanse\Evenement;
use Ladecadanse\EvenementCollection;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Utils\Text;

if (empty($_GET['idL']) || !is_numeric($_GET['idL']))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
    exit;
}

$get['idL'] = (int) $_GET['idL'];

$lieu = Lieu::getLieu($get['idL']);

if (empty($lieu))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
    exit;
}

if ($lieu['statut'] == 'inactif' && !((isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= UserLevel::AUTHOR)))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
    exit;
}

$categories_fr = implode(", ", array_map(fn ($cat) : string => $glo_categories_lieux[$cat], explode(",", str_replace(" ", "", $lieu['categorie']))));

$lieu_salles = Lieu::getActivesSalles((int) $get['idL']);
$lieu_orgas = Lieu::getActivesOrganisateurs((int) $get['idL']);
$lieu_images = Lieu::getImagesUploaded((int) $get['idL']);

/* Galerie d'images */
$sql_galerie = "SELECT fichierrecu.idFichierrecu AS idFichierrecu, description, mime, extension
FROM fichierrecu, lieu_fichierrecu
WHERE lieu_fichierrecu.idLieu=" . (int) $get['idL'] . " AND type='image' AND fichierrecu.idFichierrecu=lieu_fichierrecu.idFichierrecu
ORDER BY dateAjout DESC";

$req_galerie = $connector->query($sql_galerie);

$lieu_descriptions = Lieu::getDescriptions((int) $get['idL']);
$presentations_nb = isset($lieu_descriptions['presentation']) ? count($lieu_descriptions['presentation']) : 0;
$descriptions_nb = isset($lieu_descriptions['description']) ? count($lieu_descriptions['description']) : 0;

$deb_nom_lieu = mb_strtolower(mb_substr((string) $lieu['nom'], 0, 1));
if (!isset($_GET['tranche']) && $deb_nom_lieu > "l" && $deb_nom_lieu < "z")
{
	$_GET['tranche'] = "lz";
}
include_once "_menulieux.inc.php";

$page_titre = $lieu['nom']. " - ".HtmlShrink::adresseCompacteSelonContexte($lieu['loc_canton'], $lieu['loc_localite'], $lieu['quartier'], $lieu['adresse']);
$page_description = $page_titre." : accès, horaires, description, photos et prochains événements";
$extra_css = ["lieux_menu"];
include("_header.inc.php");
?>

<main id="contenu" class="colonne">

	<p id="btn_listelieux" class="mobile" >
        <button href="#"><i class="fa fa-list fa-lg"></i>&nbsp;Liste des lieux</button>
	</p>

    <?php
    if (!empty($_SESSION['lieu_flash_msg']))
    {
        HtmlShrink::msgOk($_SESSION['lieu_flash_msg']);
        unset($_SESSION['lieu_flash_msg']);
    }
    ?>

	<div class="vcard">

        <header id="entete_contenu">

            <h1 class="fn org"><?= $lieu['nom']; ?></h1>

            <?php if ($lieu['statut'] == 'ancien') : ?>
                <p class="info">Ce lieu n'existe plus</p>
            <?php endif; ?>

            <?php if ($lieu['logo']) : ?>
                <a href="<?= Lieu::getFileHref(Lieu::getFilePath($lieu['logo']), true) ?>" class="magnific-popup"><img src="<?= Lieu::getFileHref(Lieu::getFilePath($lieu['logo'], "s_"), true) ?>" alt="Logo" class="logo" /></a>
            <?php endif; ?>

            <div class="spacer"></div>

        </header>

        <div class="spacer"><!-- --></div>

        <ul class="menu_actions_lieu desktop">
            <?php if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= UserLevel::ACTOR)) : ?>
                <li class="action_ajouter"><a href="/evenement-edit.php?idL=<?= (int)$get['idL'] ?>">Ajouter un événement à ce lieu</a></li>
            <?php endif; ?>
            <?php if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= UserLevel::AUTHOR || $authorization->isPersonneAffiliatedWithLieu($_SESSION['SidPersonne'], $get['idL']) || $authorization->isPersonneInLieuByOrganisateur($_SESSION['SidPersonne'], $get['idL']))) : ?>
                <li class="action_editer"><a href="/lieu-edit.php?action=editer&amp;idL=<?= (int)$get['idL'] ?>">Modifier ce lieu</a></li>
            <?php endif; ?>
        </ul>

        <div class="spacer"><!-- --></div>

        <article id="fiche"<?php // $class_vide; ?>>

            <div id="medias">

                <figure id="photo">

                    <?php if ($lieu['photo1'] != '') { ?>
                        <a href="<?= Lieu::getFileHref(Lieu::getFilePath($lieu['photo1']), true) ?>" class="gallery-item"><img src="<?= Lieu::getFileHref(Lieu::getFilePath($lieu['photo1'], "s_"), true) ?>" alt="Photo du lieu"></a>
                    <?php } elseif (empty($_SESSION['Sgroupe'])) { ?>
                        <p style="background: #eaeaea;font-size:0.9em;padding:2em 0.5em;line-height:1.2em">Vous gérez ce lieu ? <a href="/user-register.php">Inscrivez-vous</a> pour pouvoir ajouter ou modifier les informations et des photos</p>
                    <?php } ?>
                </figure>

                <div class="spacer"><!-- --></div>

                <?php if (count($lieu_images) > 0) : ?>
                    <figure class="section">
                        <?php foreach ($lieu_images as $img) :
                              $image_filename = $img['idFichierrecu'] . "." . $img['extension'];
                            ?>
                            <a href="<?= Lieu::getFileHref(Lieu::getFilePath($image_filename, "galeries/"), true) ?>" class="gallery-item"><img src="<?= Lieu::getFileHref(Lieu::getFilePath($image_filename, "galeries/s_"), true) ?>" alt="Photo du lieu"></a>
                        <?php endforeach; ?>
                    </figure>
                    <div class="spacer"></div>
                <?php endif ?>

            </div> <!-- Fin medias -->

            <div id="pratique">

                <ul>
                    <li><?= $categories_fr; ?></li>

                    <li class="adr"><?= sanitizeForHtml(HtmlShrink::adresseCompacteSelonContexte($lieu['loc_canton'], $lieu['loc_localite'], $lieu['quartier'], $lieu['adresse'])) ?></li>
                    <?php if (!empty((float) $lieu['lat']) && !empty((float) $lieu['lng'])) : ?>
                        <li><a href="#" class="dropdown" data-target="plan"><?= $icone['plan'] ?> Voir sur le plan <i class="fa fa-caret-down" aria-hidden="true"></i></a></li>
                    <?php endif; ?>

                    <div>
                        <?php if (count($lieu_salles) > 0) : ?>
                            <li>Salles :
                                <ul class="salles">
                                    <?php foreach ($lieu_salles as $s) : ?>
                                        <li><?= sanitizeForHtml($s['nom']) ?><?php if ($authorization->isPersonneEditor($_SESSION)) : ?><a href="/lieu-salle-edit.php?action=editer&amp;idS=<?= (int)$s['idSalle'] ?>"><?= $iconeEditer ?></a><?php endif ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php endif; ?>

                        <?php if ($authorization->isPersonneEditor($_SESSION)) : ?>
                            <a href="/lieu-salle-edit.php?idL=<?= (int)$get['idL'] ?>"><?= $icone['ajouts'] ?>ajouter une salle</a>
                        <?php endif; ?>
                    </div>

                    <?php
                    // ??
                    if (!empty((float) $lieu['lat']) && !empty((float) $lieu['lng'])) { ?>
                        <span class="latitude"><span class="value-title" title="<?= $lieu['lat']; ?>"></span></span>
                        <span class="longitude"><span class="value-title" title="<?= $lieu['lng']; ?>"></span></span>
                    <?php } ?>


                    <li><?= Text::wikiToHtml(sanitizeForHtml($lieu['horaire_general'])); ?></li>

                    <?php if (!empty($lieu['URL'])) : $lieu_url = Text::getUrlWithName($lieu['URL']); ?>
                        <li class="sitelieu"><a class="url lien_ext" href="<?= $lieu_url['url'] ?>" target="_blank"><?= $lieu_url['urlName']?></a>
                        <?php if ($get['idL'] == 13) : // exception pour idLieu=13 (Le Rez - Usine) ?>
                            <a href="https://rez-usine.ch" class="url lien_ext" target="_blank">rez-usine.ch</a><br>
                            <a href="http://www.ptrnet.ch" class="url lien_ext" target="_blank">ptrnet.ch</a>
                        <?php endif; ?>
                        </li>
                    <?php endif; ?>

                    <?php if (count($lieu_orgas) > 0) : ?>
                        <li>Organisateur<?php if (count($lieu_orgas) > 1) : ?>s<?php endif; ?>&nbsp;:
                            <ul class="salles">
                            <?php foreach ($lieu_orgas as $o) : ?>
                                <li><a href="/organisateur.php?idO=<?= (int)$o['idOrganisateur'] ?>"><?= sanitizeForHtml($o['nom']) ?></a></li>
                            <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                </ul>

                <div id="plan" style="display:none">
                    <div id="lieu-map-infowindow" style='display:none;width:200px'>
                       <div class=details><p class=adresse><strong><?= sanitizeForHtml($lieu['nom']); ?></strong></p><p class=adresse><?= sanitizeForHtml($lieu['adresse']); ?></p><p class=adresse><?= $lieu['quartier']; ?></p></div>
                    </div>
                    <?php if (!empty((float) $lieu['lat']) && !empty((float) $lieu['lng'])) : ?>
                        <div id="lieu-map" data-lat="<?= $lieu['lat'] ?>" data-lng="<?= $lieu['lng'] ?>"></div>
                    <?php endif; ?>
                </div>

            </div><!-- Fin pratique -->

            <div class="spacer only-mobile"></div>

            <ul id="menu_descriptions">
                <?php if ($descriptions_nb > 0) : ?>
                    <li class="btn-description ici">
                        <h2><a href="#description" id="show-description-btn">Description</a></h2>
                    </li>
                <?php endif; ?>

                <?php if ($presentations_nb > 0) : ?>
                    <li class="btn-presentation<?php if ($descriptions_nb === 0) : ?> ici<?php endif; ?>">
                        <h2><a href="#presentation" id="show-presentation-btn">Le lieu se présente</a></h2>
                    </li>
                 <?php endif; ?>
            </ul>

            <div id="descriptions">

            <?php
            $types_desc = array_column($lieu_descriptions, 'type');
            $idPersonne_authors_of_desc = [];
            foreach ($lieu_descriptions as $type => $descriptions) :

                $idPersonne_authors_of_desc = [$idPersonne_authors_of_desc, ...array_column($descriptions, 'idPersonne')];
                ?>

                <div class="type-<?= $type; ?>" <?php if ($type === 'presentation' && $descriptions_nb > 0) : ?>style="display:none"<?php endif; ?>>

                    <?php foreach ($descriptions as $des) : ?>

                    <div class="description">
                        <?php
                        // HACK: before oct 2009 text "wiki" formated
                        $des_contenu = $des['contenu'];
                        if (datetime_iso2time($des['date_derniere_modif']) <= datetime_iso2time("2009-10-12 12:00:00")) :
                            $des_contenu = "<p>".Text::wikiToHtml(sanitizeForHtml($des['contenu']))."</p>";
                        endif;
                        ?>

                        <?= $des_contenu ?>

                        <?php if ($type == 'description') : ?>
                            <p><?= HtmlShrink::authorSignatureForHtml($des['idPersonne']) ?></p>
                        <?php endif; ?>

                        <div class="auteur">
                            <span class="left">
                                <?= ucfirst((string) date_fr($des['dateAjout'], 'annee', '', 'non')) ?><?php if ($des['date_derniere_modif'] != "0000-00-00 00:00:00" && $des['date_derniere_modif'] != $des['dateAjout']) : ?>, modifié le <?= date_fr($des['date_derniere_modif'], 'annee', '', 'non') ?><?php endif; ?>
                            </span>
                            <?php if (isset($_SESSION['Sgroupe']) && (
                                        $_SESSION['Sgroupe'] <= UserLevel::ADMIN)
                                        || ($type == 'description' &&  $_SESSION['Sgroupe'] <= UserLevel::AUTHOR && $_SESSION['SidPersonne'] == $des['idPersonne'])
                                        || ($type == 'presentation' &&
                                        ($_SESSION['Sgroupe'] <= UserLevel::AUTHOR)
                                            || ($_SESSION['Sgroupe'] <= UserLevel::ACTOR && ($authorization->isPersonneInLieuByOrganisateur($_SESSION['SidPersonne'], $get['idL']) || $authorization->isPersonneAffiliatedWithLieu($_SESSION['SidPersonne'], $get['idL']))))
                                    ) : ?>
                                    <span class="right">
                                        <a href="/lieu-text-edit.php?action=editer&amp;type=<?= $type ?>&amp;idL=<?= (int)$get['idL'] ?>&amp;idP=<?= (int) $des['idPersonne'] ?>"><?= $iconeEditer ?> Modifier</a>
                                    </span>
                            <?php endif; ?>
                            <div class="spacer"><!-- --></div>
                        </div> <!-- .auteur -->

                    </div> <!-- .description -->

                    <?php endforeach; ?>
                </div> <!-- .type-... -->
            <?php endforeach; ?>

            <?php
            // add description :
            // Description : un rédacteur qui n'en n'a pas déjà écrit une
            if ($authorization->isPersonneEditor($_SESSION) && !in_array($_SESSION['SidPersonne'], $idPersonne_authors_of_desc)) : ?>
                <a href="/lieu-text-edit.php?idL=<?= (int)$get['idL'] ?>&amp;type=description"><?= $icone['ajouter_texte'] ?> Ajouter une description (avis)</a><br>
            <?php endif; ?>

            <?php
            // Presentation : if no presentation yet, allow authorized users to add it
            if ($presentations_nb == 0 && isset($_SESSION['Sgroupe']) &&
                    ($_SESSION['Sgroupe'] <= UserLevel::AUTHOR ||
                        ($_SESSION['Sgroupe'] == UserLevel::ACTOR && ($authorization->isPersonneAffiliatedWithLieu($_SESSION['SidPersonne'], $get['idL']) || $authorization->isPersonneInLieuByOrganisateur($_SESSION['SidPersonne'], $get['idL'])))
                    )) : ?>
                <a href="/lieu-text-edit.php?idL=<?= (int)$get['idL'] ?>&amp;type=presentation"><?= $icone['ajouter_texte'] ?> Ajouter une présentation</a>
            <?php endif; ?>

            </div><!-- #descriptions -->

            <div class="spacer"></div>

        </article> <!-- #fiche -->

        <div class="spacer"><!-- --></div>

    </div> <!-- .vcard -->

    <div class="spacer"><!-- --></div>


    <h2 style="font-size:1.2em;font-weight:bold;color:#5C7378;width:96%;margin:2em 2% 0.4em 2%;min-height:30px">Prochains événements</h2>

    <div id="prochains_evenements">
    <?php
        $date_debut = date("Y-m-d", time() - 21600);

        $genre = "";
        if (isset($get['genre_even']) && $get['genre_even'] != "tous")
        {
            $genre .= $get['genre_even'];
        }

        $evenements = new EvenementCollection($connector);
        $evenements->loadForLieu($get['idL'], $date_debut, $genre);

        /* Construction du menu par genre */
        $menu_genre = '';
        if ($evenements->getNbElements() > 0)
        {
            $menu_genre .= '<ul id="menu_genre">';
            $genres_even = ["tous", "fête", "cinéma", "théâtre", "expos", "divers"];

            foreach ($genres_even as $g)
            {

                $genre = "";
                if ($g != "tous")
                {
                    $genre = "AND genre='".$g."'";
                }

                $sql_nb_even = "SELECT idEvenement
                 FROM evenement
                 WHERE idLieu=" . (int) $get['idL'] . " AND dateEvenement >= '" . $date_debut . "' AND statut NOT IN ('inactif', 'propose') " . $genre;

                $req_nb_even = $connector->query($sql_nb_even);
                $nb_even_genre = $connector->getNumRows($req_nb_even);

                $menu_genre .= "<li";
                if ($g == $get['genre_even'])
                {
                    $menu_genre .= " class=\"ici\"><a href=\"/lieu.php?idL=".(int)$get['idL']."&amp;genre_even=".urlencode($g)."#prochains_even\" title=\"".$g."\" rel=\"nofollow\">";
                    if ($g == "fête")
                    {
                        $g .= "s";
                    }
                    else if ($g == "cinéma")
                    {
                        $g = "ciné";
                    }
                    $menu_genre .= $g;
                    $menu_genre .= " (".$nb_even_genre.")";

                    $menu_genre .= "</a>";
                }
                else if ($nb_even_genre == 0 && $g != "tous")
                {
                    if ($g == "fête")
                    {
                        $g .= "s";
                    }
                    else if ($g == "cinéma")
                    {
                        $g = "ciné";
                    }
                    $menu_genre .= ' class="rien">';
                    $menu_genre .= $g;
                }
                else
                {
                    $menu_genre .= "><a href=\"/lieu.php?idL=".$get['idL']."&amp;genre_even=".$g."#prochains_even\" title=\"".$g."\" rel=\"nofollow\">";
                    if ($g == "fête")
                    {
                        $g .= "s";
                    }
                    else if ($g == "cinéma")
                    {
                        $g = "ciné";
                    }
                    $menu_genre .=  $g;
                    $menu_genre .= " (".$nb_even_genre.")";
                    $menu_genre .= "</a>";
                }

                $menu_genre .= "</li>";


            }
            $menu_genre .= "</ul>";
            echo $menu_genre;
        ?>
        <div class="clear_mobile"></div>

        <table>

        <?php
        $nbMois = 0;
        $moisCourant = 0;
        //listage des événements
        foreach ($evenements->getElements() as $id => $even)
        {
            $description = '';
            if ($even->getValue('description') != '')
            {
                $maxChar = Text::trouveMaxChar($even->getValue('description'), 50, 2);

                if (mb_strlen((string) $even->getValue('description')) > $maxChar)
                {
                    //$continuer = "<span class=\"continuer\"><a href=\"/event/evenement.php?idE=".$even->getValue('idEvenement')."\" title=\"Voir la fiche complète de l'événement\"> Lire la suite</a></span>";
                    $description = Text::texteHtmlReduit(Text::wikiToHtml(sanitizeForHtml($even->getValue('description'))), $maxChar);
                        }
                else
                {
                    $description = Text::wikiToHtml(sanitizeForHtml($even->getValue('description')));
                        }
            }

            if ($nbMois == 0)
            {
                $moisCourant = date2mois($even->getValue('dateEvenement'));
                echo "<tr><td colspan=\"3\" class=\"mois\">".ucfirst((string) mois2fr($moisCourant))."</td></tr>";
            }

            if (date2mois($even->getValue('dateEvenement')) != $moisCourant)
            {
                echo "<tr><td colspan=\"3\" class=\"mois\">".ucfirst((string) mois2fr(date2mois($even->getValue('dateEvenement'))));

                if (date2mois($even->getValue('dateEvenement')) == "01")
                {
                    echo " ".date2annee($even->getValue('dateEvenement'));
                }

                echo "</td></tr>";
            }

            $salle = '';
            $sql_salle = "SELECT nom FROM salle WHERE idSalle=" . (int) $even->getValue('idSalle');
            $req_salle = $connector->query($sql_salle);

            if ($connector->getNumRows($req_salle) > 0)
            {
                $tab_salle = $connector->fetchArray($req_salle);
                $salle = $tab_salle['nom'];
            }
            $vcard_starttime = '';
            if (mb_substr((string) $even->getValue('horaire_debut'), 11, 5) != '06:00')
                $vcard_starttime = "T".mb_substr((string) $even->getValue('horaire_debut'), 11, 5).":00";
            ?>

            <tr class="<?php if ($date_debut == $even->getValue('dateEvenement')) { echo "ici"; } ?> vevent evenement">

                <td class="dtstart">
                    <?= date2nomJour($even->getValue('dateEvenement')); ?>
                    <span class="value-title" title="<?= $even->getValue('dateEvenement').$vcard_starttime; ?>"></span>
                </td>

                <td><?= date2jour($even->getValue('dateEvenement'));  ?>

                </td>

                <td class="flyer photo">
                    <?= Evenement::mainFigureHtml($even->getValue('flyer'), $even->getValue('image'), $even->getValue('titre'), 60) ?>
                </td>

                <td>
                <h3 class="summary">
                    <?php
                    $titre_url = '<a class="url" href="/event/evenement.php?idE='.(int)$even->getValue('idEvenement').'" title="Voir la fiche de l\'événement">'.Evenement::titreSelonStatutHtml(sanitizeForHtml($even->getValue('titre')), $even->getValue('statut')).'</a>';
                    echo $titre_url; ?>
                </h3>

                <p class="description">
                <?php
                echo $description;
                ?></p>
                <div class="location">
                <span class="value-title" title="<?= $lieu['nom']; ?>"></span>
                </div>
                <p class="pratique"><?= afficher_debut_fin($even->getValue('horaire_debut'), $even->getValue('horaire_fin'), $even->getValue('dateEvenement')) . " " . sanitizeForHtml($even->getValue('prix')) ?></p>
                        </td>

                <td><?= sanitizeForHtml($salle); ?></td>
                        <td class="category"><?= $glo_tab_genre[$even->getValue('genre')]; ?></td>

                <td class="lieu_actions_evenement">
                <?php
                if (
                (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 6
                || $_SESSION['SidPersonne'] == $even->getValue('idPersonne'))
                )
                ||  (isset($_SESSION['Saffiliation_lieu']) && !empty($get['idL']) && $get['idL'] == $_SESSION['Saffiliation_lieu'])
                 || isset($_SESSION['SidPersonne']) && $authorization->isPersonneInEvenementByOrganisateur($_SESSION['SidPersonne'], $even->getValue('idEvenement'))
                 || isset($_SESSION['SidPersonne']) && $authorization->isPersonneInLieuByOrganisateur($_SESSION['SidPersonne'], $get['idL'])
                )
                {
                ?>
                <ul>
                    <li><a href="/event/copy.php?idE=<?= (int) $even->getValue('idEvenement') ?>" title="Copier cet événement"><?= $iconeCopier ?></a></li>
                    <li><a href="/evenement-edit.php?action=editer&amp;idE=<?= (int) $even->getValue('idEvenement') ?>" title="Éditer cet événement"><?= $iconeEditer ?></a></li>
                    <li class=""><a href="#" id="btn_event_unpublish_<?= (int) $even->getValue('idEvenement'); ?>" class="btn_event_unpublish" data-id="<?= (int) $even->getValue('idEvenement') ?>"><?= $icone['depublier']; ?></a></li>
                </ul>
                <?php
                }
                ?>
                </td>
            </tr>

        <?php
            $moisCourant = date2mois($even->getValue('dateEvenement'));
            $nbMois++;
        }
        ?>

        </table>

        <?php

        }
        else
        {
            echo "<p>Pas d'événement actuellement annoncé au lieu <strong>".$lieu['nom']."</strong></p>";
        }
        ?>
        <?php if (!empty($tab_lieu['URL'])) {
            $URLcomplete = $tab_lieu['URL'];

            if (!preg_match("/^(https?:\/\/)/i", (string) $tab_lieu['URL']))
            {
                $URLcomplete = "http://".$tab_lieu['URL'];
            }
            echo "<p>Pour des informations complémentaires veuillez consulter <a href=\"" . sanitizeForHtml($URLcomplete) . "\" target='_blank'>" . sanitizeForHtml($tab_lieu['URL']) . "</a></p>\n";
        }
    ?>
    </div> <!-- #prochains_evenenents -->

</main>

<div id="colonne_gauche" class="colonne">
    <?php include("event/_navigation_calendrier.inc.php");?>
</div> <!-- Fin Colonnegauche -->

<div id="colonne_droite" class="colonne">
    <?= $aff_menulieux; ?>
</div> <!-- #colonne_droite -->

<div class="spacer"><!-- --></div>
<?php
include("_footer.inc.php");
?>
