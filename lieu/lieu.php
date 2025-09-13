<?php

require_once("../app/bootstrap.php");

use Ladecadanse\UserLevel;
use Ladecadanse\Lieu;
use Ladecadanse\Evenement;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Utils\Text;
use Ladecadanse\Utils\Utils;
use Ladecadanse\Utils\Validateur;

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

$tab_menu_periodes = ["ancien" => "Passés", "futur" => "Prochains"]; //, "tous" => "Tous"
$get['periode'] = "futur";
if (!empty($_GET['periode']) && Validateur::validateUrlQueryValue($_GET['periode'], "enum", 1, array_keys($tab_menu_periodes)))
{
    $get['periode'] = $_GET['periode'];
}

$get['page'] = !empty($_GET['page']) ? Validateur::validateUrlQueryValue($_GET['page'], "int", 1) : 1;
$results_per_page = 20;

$categories_fr = implode(", ", array_map(fn ($cat) : string => $glo_categories_lieux[$cat], explode(",", str_replace(" ", "", $lieu['categorie']))));
$lieu_salles = Lieu::getActivesSalles((int) $get['idL']);
$lieu_orgas = Lieu::getActivesOrganisateurs((int) $get['idL']);
$lieu_images = Lieu::getImagesUploaded((int) $get['idL']);
$lieu_descriptions = Lieu::getDescriptions((int) $get['idL']);
$presentations_nb = isset($lieu_descriptions['presentation']) ? count($lieu_descriptions['presentation']) : 0;
$descriptions_nb = isset($lieu_descriptions['description']) ? count($lieu_descriptions['description']) : 0;

$sql_select = "SELECT
  DATE_FORMAT(e.dateEvenement, '%Y-%m-01') AS yearmonth,
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
  e.region AS e_region,
  e.urlLieu AS e_urlLieu,

  s.nom AS s_nom

FROM evenement e
LEFT JOIN salle s ON e.idSalle = s.idSalle
WHERE
    e.statut NOT IN ('inactif', 'propose') AND e.idLieu = ?";

$sql_periode_operator = ">=";
if ($get['periode'] == "ancien")
{
    $sql_periode_operator = "<";
}
$sql_select .= " AND e.dateEvenement $sql_periode_operator '" . $glo_auj . "'";
$sql_select .= ' ORDER BY dateEvenement ASC';
$sql_select .= " LIMIT " . (int) (($get['page'] - 1) * $results_per_page) . ", " . (int) (($get['page'] - 1) * $results_per_page + $results_per_page);
//echo $sql_select;
$stmt = $connectorPdo->prepare($sql_select);
$stmt->execute([$get['idL']]);
$page_results = $stmt->fetchAll(PDO::FETCH_GROUP);

$sql_select_all =
    "SELECT count(*) AS nb
    FROM evenement e
    WHERE
    e.statut NOT IN ('inactif', 'propose') AND e.idLieu = ?";

$sql_periode_operator = ">=";
if ($get['periode'] == "ancien")
{
    $sql_periode_operator = "<";
}
$sql_select_all .= " AND e.dateEvenement $sql_periode_operator '" . $glo_auj . "'";
//echo $sql_select_all;
$stmtAll = $connectorPdo->prepare($sql_select_all);
$stmtAll->execute([$get['idL']]);
$all_results_nb = $stmtAll->fetchColumn();

$page_titre = $lieu['nom']. " - ".HtmlShrink::adresseCompacteSelonContexte($lieu['loc_canton'], $lieu['loc_localite'], $lieu['quartier'], $lieu['adresse']);
$page_description = $page_titre." : accès, horaires, description, photos et prochains événements";
include("../_header.inc.php");
?>

<main id="contenu" class="colonne">

    <?php
    if (!empty($_SESSION['lieu_flash_msg']))
    {
        HtmlShrink::msgOk($_SESSION['lieu_flash_msg']);
        unset($_SESSION['lieu_flash_msg']);
    }
    ?>

	<section class="vcard">

        <header id="entete_contenu">

            <h1 class="fn org">
                <?= sanitizeForHtml($lieu['nom']); ?>
            </h1>

            <?php if ($lieu['logo']) : ?>
                <a href="<?= Lieu::getFileHref(Lieu::getFilePath($lieu['logo']), true) ?>" class="magnific-popup"><img src="<?= Lieu::getFileHref(Lieu::getFilePath($lieu['logo'], "s_"), true) ?>" alt="Logo" class="logo" /></a>
            <?php endif; ?>

            <?php if ($lieu['statut'] == 'ancien') : ?>
                <p class="alert-warn"><strong>Ce lieu n'existe plus</strong></p>
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
                    <li><?= sanitizeForHtml($categories_fr); ?></li>

                    <li class="adr"><?= sanitizeForHtml(HtmlShrink::adresseCompacteSelonContexte($lieu['loc_canton'], $lieu['loc_localite'], $lieu['quartier'], $lieu['adresse'])) ?></li>
                    <?php if (!empty((float) $lieu['lat']) && !empty((float) $lieu['lng'])) : ?>
                        <li><a href="#" class="dropdown" data-target="plan"><?= $icone['plan'] ?> Voir sur le plan <i class="fa fa-caret-down" aria-hidden="true"></i></a></li>
                    <?php endif; ?>

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
                        <li><a href="/lieu-salle-edit.php?idL=<?= (int)$get['idL'] ?>"><?= $icone['ajouts'] ?>ajouter une salle</a></li>
                    <?php endif; ?>

                    <?php
                    // ??
                    if (!empty((float) $lieu['lat']) && !empty((float) $lieu['lng'])) { ?>
                        <span class="latitude"><span class="value-title" title="<?= $lieu['lat']; ?>"></span></span>
                        <span class="longitude"><span class="value-title" title="<?= $lieu['lng']; ?>"></span></span>
                    <?php } ?>


                    <li><?= Text::wikiToHtml(sanitizeForHtml($lieu['horaire_general'])); ?></li>

                    <?php if (!empty($lieu['URL'])) : $lieu_url = Text::getUrlWithName($lieu['URL']); ?>
                        <li class="sitelieu"><a class="url lien_ext" href="<?= sanitizeForHtml($lieu_url['url']) ?>" target="_blank"><?= sanitizeForHtml($lieu_url['urlName']) ?></a>
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

                                <div class="js-read-smore" data-read-smore-words="50">
                                    <?= $des_contenu ?>
                                </div>

                                <?php if ($type == 'description') : ?>
                                    <p><?= HtmlShrink::authorSignatureForHtml($des['idPersonne']) ?></p>
                                <?php endif; ?>

                                <div class="auteur">
                                    <span class="left">
                                        <?= ucfirst((string) date_fr($des['dateAjout'], 'annee', '', 'non')) ?><?php if ($des['date_derniere_modif'] != "0000-00-00 00:00:00" && $des['date_derniere_modif'] != $des['dateAjout']) : ?>, modifié le <?= date_fr($des['date_derniere_modif'], 'annee', '', 'non') ?><?php endif; ?>
                                    </span>
                                    <?php if (isset($_SESSION['Sgroupe']) && (
                                                $_SESSION['Sgroupe'] <= UserLevel::ADMIN
                                                || ($type == 'description' && $_SESSION['Sgroupe'] <= UserLevel::AUTHOR && $_SESSION['SidPersonne'] == $des['idPersonne'])
                                                || ($type == 'presentation' &&
                                                ($_SESSION['Sgroupe'] <= UserLevel::AUTHOR)
                                                    || ($_SESSION['Sgroupe'] <= UserLevel::ACTOR && ($authorization->isPersonneInLieuByOrganisateur($_SESSION['SidPersonne'], $get['idL']) || $authorization->isPersonneAffiliatedWithLieu($_SESSION['SidPersonne'], $get['idL']))))
                                            )) : ?>
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

    </section> <!-- .vcard -->

    <div class="spacer"><!-- --></div>


    <section id="prochains_evenements">

        <header>

            <h2>Événements</h2>

            <!-- menu tous | futurs | anciens -->
            <ul id="menu_periode" class="entete_contenu_navigation">
                <?php foreach ($tab_menu_periodes as $k => $label) : ?>
                    <li class="<?= $k ?><?php if ($get['periode'] == $k) : ?> ici<?php endif; ?>">
                        <a href="?<?= Utils::urlQueryArrayToString($get, ['periode', 'page']) ?>&amp;periode=<?= $k ?>"><?= $label ?></a>
                    </li>
                <?php endforeach; ?>
                <div class="spacer"></div>
            </ul>

            <div class="spacer"><!-- --></div>

        </header>

        <?php
        if ($all_results_nb == 0) :  ?>

            <p><?= $translator->get("lieu-events-{$get['periode']}-none") ?> <?= Lieu::prepositionToPutInSentence($lieu['determinant']) ?><strong><?= $lieu['nom'] ?></strong></p>

        <?php else : ?>

            <?= HtmlShrink::getPaginationString($all_results_nb, $get['page'], $results_per_page, 1, basename(__FILE__), "?" . Utils::urlQueryArrayToString($get, "page") . "&amp;page=") ?>
            <table>
            <?php foreach ($page_results as $yearmonth => $tab_even) : ?>
                <tr>
                    <td colspan="5" class="mois"><?= ucfirst((string) mois2fr(date2mois($yearmonth))) ?>
                    <?php if (date2annee($yearmonth) != date('Y')) : echo date2annee($yearmonth); endif; ?>
                    </td>
                </tr>
                <?php foreach ($tab_even as $e) :
                    $vcard_starttime = '';
                    if (mb_substr((string) $e['e_horaire_debut'], 11, 5) != '06:00')
                        $vcard_starttime = "T".mb_substr((string)$e['e_horaire_debut'], 11, 5).":00";
                            ?>
                    <tr class="<?php if ($glo_auj_6h == $e['e_dateEvenement']) { echo "ici"; } ?> vevent evenement">
                        <td class="dtstart">
                            <?= date2nomJour($e['e_dateEvenement']); ?>&nbsp;<?= date2jour($e['e_dateEvenement']); ?><span class="value-title" title="<?= $e['e_dateEvenement'].$vcard_starttime; ?>"></span><br>
                            <span class="pratique"><?= afficher_debut_fin($e['e_horaire_debut'], $e['e_horaire_fin'], $e['e_dateEvenement']) ?></span>
                        </td>
                        <td class="flyer photo">
                            <?= Evenement::mainFigureHtml($e['e_flyer'], $e['e_image'], $e['e_titre'], 60) ?>
                        </td>
                        <td>
                            <a class="url" href="/event/evenement.php?idE=<?= (int)$e['e_idEvenement' ]?>"><strong class="summary"><?= Evenement::titreSelonStatutHtml(sanitizeForHtml($e['e_titre']), $e['e_statut']) ?></strong></a><br>
                            <span class="category"><?= $glo_tab_genre[$e['e_genre']]; ?></span>
                        </td>
                        <td class="location">
                            <?= sanitizeForHtml($e['s_nom']) ?>
                            <div class="location">
                                <span class="value-title" title="<?= sanitizeForHtml($e['s_nom']); ?>"></span>
                            </div>
                        </td>
                        <?php if ($authorization->isPersonneAllowedToEditEvenement($_SESSION, $tab_even)) : ?>
                        <td class="lieu_actions_evenement">
                            <ul>
                                <li><a href="/event/copy.php?idE=<?= (int) $e['e_idEvenement'] ?>" title="Copier cet événement"><?= $iconeCopier ?></a></li>
                                <li><a href="/evenement-edit.php?action=editer&amp;idE=<?= (int) $e['e_idEvenement'] ?>" title="Modifier cet événement"><?= $iconeEditer ?></a></li>
                                <li class=""><a href="#" id="btn_event_unpublish_<?= (int) $e['e_idEvenement'] ?>" class="btn_event_unpublish" data-id="<?= (int) $e['e_idEvenement'] ?>"><?= $icone['depublier']; ?></a></li>
                            </ul>

                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </table>

            <?= HtmlShrink::getPaginationString($all_results_nb, $get['page'], $results_per_page, 1, basename(__FILE__), "?" . Utils::urlQueryArrayToString($get, "page") . "&amp;page=") ?>

        <?php endif; ?>

        <?php if (!empty($lieu['URL'])) :
            $url_with_name = Text::getUrlWithName($lieu['URL'])     ?>
            <p><br>Pour des informations complémentaires veuillez consulter <a href="<?= $url_with_name['url'] ?>" target='_blank'><?= sanitizeForHtml($url_with_name['urlName']) ?></a></p>
        <?php endif; ?>

    </section> <!-- #prochains_evenenents -->

</main>

<div id="colonne_gauche" class="colonne">
    <?php include("../event/_navigation_calendrier.inc.php");?>
</div> <!-- Fin Colonnegauche -->

<div id="colonne_droite" class="colonne">
</div> <!-- #colonne_droite -->

<div class="spacer"><!-- --></div>
<?php
include("../_footer.inc.php");
?>
