<?php

require_once("../app/bootstrap.php");

use Ladecadanse\UserLevel;
use Ladecadanse\Lieu;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Personne;
use Ladecadanse\Utils\DateHelper;
use Ladecadanse\Utils\Text;
use Ladecadanse\Utils\QueryParamValidator;

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
$sql_periode_operator = ">=";
if (!empty($_GET['periode']) && QueryParamValidator::isAcceptedUrlQueryValue($_GET['periode'], "enum", array_keys($tab_menu_periodes)))
{
    $get['periode'] = $_GET['periode'];
    if ($get['periode'] == "ancien")
    {
        $sql_periode_operator = "<";
    }
}

// sens du tri des événements passés, mémorisé en session comme user_prefs_agenda_order l'est
// entre l'accueil et la fiche événement (cf. event/_events_order_menu.inc.php)
if (!empty($_GET['ordre']) && QueryParamValidator::isAcceptedUrlQueryValue($_GET['ordre'], "enum", array_keys($tab_ordre_evenements_passes)))
{
    $_SESSION['user_prefs_past_events_order'] = $_GET['ordre'];
}
// l'onglet « Prochains » reste chronologique : le menu de tri n'y est pas proposé
$is_past_events_desc = ($get['periode'] == "ancien" && $_SESSION['user_prefs_past_events_order'] == "desc");
$sql_events_order_direction = $is_past_events_desc ? "DESC" : "ASC";

$results_per_page = 50;

$sql_select_all =
    "SELECT count(*) AS nb
    FROM evenement e
    WHERE
    e.statut NOT IN ('inactif', 'propose') AND e.idLieu = ?";

$sql_select_all .= " AND e.dateEvenement $sql_periode_operator ?";
//echo $sql_select_all;
$stmtAll = $connectorPdo->prepare($sql_select_all);
$stmtAll->execute([$get['idL'], $glo_auj]);
$all_results_nb = $stmtAll->fetchColumn();

// atterrir sur les événements passés les plus récents : ils sont en dernière page en tri
// ascendant, en première page en tri descendant
$default_page = ($get['periode'] == "ancien" && !$is_past_events_desc) ? (int) max(1, ceil($all_results_nb / $results_per_page)) : 1;
$get['page'] = QueryParamValidator::pageFromQuery($_GET['page'] ?? '', $default_page);

$categories_fr = implode(", ", array_map(fn ($cat) : string => $glo_categories_lieux[$cat], explode(",", str_replace(" ", "", $lieu['categorie']))));
$lieu_salles = Lieu::getActivesSalles((int) $get['idL']);
$lieu_orgas = Lieu::getActivesOrganisateurs((int) $get['idL']);

$lieu_affiliates = [];
if ($authorization->isPersonneEditor($_SESSION))
{
    $lieu_affiliates = Lieu::getActivesAffiliates((int) $get['idL']);
}


$lieu_images = Lieu::getImagesUploaded((int) $get['idL']);
$lieu_descriptions = Lieu::getDescriptions((int) $get['idL']);
$presentations_nb = isset($lieu_descriptions['presentation']) ? count($lieu_descriptions['presentation']) : 0;
$descriptions_nb = isset($lieu_descriptions['description']) ? count($lieu_descriptions['description']) : 0;

$sql_select = "SELECT
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

  l.nom AS l_nom,
  l.adresse AS l_adresse,
  l.quartier AS l_quartier,
  l.URL AS l_URL,
  l.region AS l_region,
  lloc.localite AS lloc_localite,

  s.nom AS s_nom

FROM evenement e
LEFT JOIN lieu l ON e.idLieu = l.idLieu
LEFT JOIN localite lloc ON l.localite_id = lloc.id
LEFT JOIN salle s ON e.idSalle = s.idSalle
WHERE
    e.statut NOT IN ('inactif', 'propose') AND e.idLieu = ?";

$sql_select .= " AND e.dateEvenement $sql_periode_operator ?";
$sql_select .= " ORDER BY dateEvenement $sql_events_order_direction";
$sql_select .= " LIMIT " . (int) (($get['page'] - 1) * $results_per_page) . ", " . (int) ($results_per_page); // ($get['page'] - 1) * $results_per_page +
//echo $sql_select;
$stmt = $connectorPdo->prepare($sql_select);
$stmt->execute([$get['idL'], $glo_auj]);
$page_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
//echo " <BR>NB RES:" . count($page_results);
$page_results_grouped_by_yearmonth = [];
foreach ($page_results as $event) {
    $yearmonth = date('Y-m-01', strtotime($event['e_dateEvenement']));
    $page_results_grouped_by_yearmonth[$yearmonth][] = $event;
}

//dump($page_results_grouped_by_yearmonth);

$page_titre = $lieu['nom']. " - ".HtmlShrink::adresseCompacteSelonContexte($lieu['loc_canton'], $lieu['loc_localite'], $lieu['quartier'], $lieu['adresse']);
$page_description = $page_titre." : accès, horaires, description, photos et prochains événements";
if ($lieu['logo'])
{
    $page_image = $assets->get(Lieu::getAssetPath(Lieu::getFilePath($lieu['logo'])));
}
$page_url = "lieu/lieu.php?idL=" .  $get['idL'];

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
                <a href="<?= $assets->get(Lieu::getAssetPath(Lieu::getFilePath($lieu['logo']))) ?>" class="magnific-popup"><img src="<?= $assets->get(Lieu::getAssetPath(Lieu::getFilePath($lieu['logo'], "s_"))) ?>" width="100" alt="Logo" class="logo" /></a>
            <?php endif; ?>

            <?php if ($lieu['statut'] == 'ancien') : ?>
                <p class="alert-warn"><strong>Ce lieu n'existe plus</strong></p>
            <?php endif; ?>
            <div class="spacer"></div>

        </header>

        <div class="spacer"><!-- --></div>

        <ul class="menu_actions_lieu">
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
                        <a href="<?= $assets->get(Lieu::getAssetPath(Lieu::getFilePath($lieu['photo1']))) ?>" class="gallery-item"><img src="<?= $assets->get(Lieu::getAssetPath(Lieu::getFilePath($lieu['photo1'], "s_"))) ?>" width="200" alt="Photo du lieu"></a>
                    <?php } elseif (empty($_SESSION['Sgroupe'])) { ?>
                        <p style="background: #eaeaea;font-size:0.9em;padding:2em 0.5em;line-height:1.2em">Vous gérez ce lieu ? <a href="/user/register.php">Inscrivez-vous</a> pour pouvoir ajouter ou modifier les informations et des photos</p>
                    <?php } ?>
                </figure>

                <div class="spacer"><!-- --></div>

                <?php if (count($lieu_images) > 0) : ?>
                    <figure class="section">
                        <?php foreach ($lieu_images as $img) :
                              $image_filename = $img['idFichierrecu'] . "." . $img['extension'];
                            ?>
                            <a href="<?= $assets->get(Lieu::getAssetPath(Lieu::getFilePath($image_filename, "galeries/"))) ?>" class="gallery-item"><img src="<?= $assets->get(Lieu::getAssetPath(Lieu::getFilePath($image_filename, "galeries/s_"))) ?>" alt="Photo du lieu"></a>
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
                                    <li><?= sanitizeForHtml($s['nom']) ?><?php if ($authorization->isPersonneEditor($_SESSION)) : ?>&nbsp;<a href="/lieu/salle-edit.php?action=editer&amp;idS=<?= (int)$s['idSalle'] ?>" title="Modifier cette salle" aria-label="Modifier cette salle"><?= $iconeEditer ?></a><?php endif ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <?php if ($authorization->isPersonneEditor($_SESSION)) : ?>
                        <li><a href="/lieu/salle-edit.php?idL=<?= (int)$get['idL'] ?>"><?= $icone['ajouts'] ?> ajouter une salle</a></li>
                    <?php endif; ?>

                    <?php
                    // ??
                    if (!empty((float) $lieu['lat']) && !empty((float) $lieu['lng'])) { ?>
                        <span class="latitude"><span class="value-title" title="<?= $lieu['lat']; ?>"></span></span>
                        <span class="longitude"><span class="value-title" title="<?= $lieu['lng']; ?>"></span></span>
                    <?php } ?>


                    <li><?= Text::lnAndUrlToHtml(sanitizeForHtml($lieu['horaire_general'])); ?></li>

                    <?php if (!empty($lieu['URL'])) : $lieu_url = Text::getUrlWithName($lieu['URL']); ?>
                        <li class="sitelieu"><a class="url" href="<?= sanitizeForHtml($lieu_url['url']) ?>" rel="external" target="_blank"><?= sanitizeForHtml($lieu_url['urlName']) ?></a>
                        <?php if ($get['idL'] == 13) : // exception pour idLieu=13 (Le Rez - Usine) ?>
                            <a href="https://rez-usine.ch" class="url" rel="external" target="_blank">rez-usine.ch</a><br>
                            <a href="http://www.ptrnet.ch" class="url" rel="external" target="_blank">ptrnet.ch</a>
                        <?php endif; ?>
                        </li>
                    <?php endif; ?>

                    <?php if (count($lieu_orgas) > 0) : ?>
                        <li>Organisateur<?php if (count($lieu_orgas) > 1) : ?>s<?php endif; ?>&nbsp;:
                            <ul>
                            <?php foreach ($lieu_orgas as $o) : ?>
                                <li><a href="/organisateur/organisateur.php?idO=<?= (int)$o['idOrganisateur'] ?>"><?= sanitizeForHtml($o['nom']) ?></a></li>
                            <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <?php if ($authorization->isPersonneEditor($_SESSION) && count($lieu_affiliates) > 0) : ?>
                        <li>
                            <details>
                                <summary>Affiliés (<?= count($lieu_affiliates) ?>)&nbsp;:</summary>
                                <ul>
                                <?php foreach ($lieu_affiliates as $a) : ?>
                                    <li>
                                        <a href="/user/dashboard.php?idP=<?= (int)$a['idPersonne'] ?>"><?= sanitizeForHtml($a['pseudo']) ?></a>
                                        <small><?= sanitizeForHtml($a['email']) ?> <?= DateHelper::isoToApp($a['p_dateAjout']) ?></small>
                                    </li>
                                <?php endforeach; ?>
                                </ul>
                            </details>
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

            <div id="descriptions">

                <?php
                $idPersonne_authors_of_desc = [];
                if (count($lieu_descriptions) > 0) : ?>

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

                    <?php foreach ($lieu_descriptions as $type => $descriptions) :
                        $idPersonne_authors_of_desc = [$idPersonne_authors_of_desc, ...array_column($descriptions, 'idPersonne')];
                        ?>

                        <div class="type-<?= $type; ?>" <?php if ($type === 'presentation' && $descriptions_nb > 0) : ?>style="display:none"<?php endif; ?>>

                            <?php foreach ($descriptions as $des) : ?>

                                <div class="description">
                                    <?php
                                    // HACK: before oct 2009 text "wiki" formated
                                    $des_contenu = $des['contenu'];
                                    if (new \DateTime($des['date_derniere_modif']) <= new \DateTime("2009-10-12")) :
                                        $des_contenu = "<p>".Text::lnAndUrlToHtml(sanitizeForHtml($des['contenu']))."</p>";
                                    endif;
                                    ?>

                                    <?php
                                    $texteRepliableHtml = $des_contenu;
                                    // une fiche peut porter plusieurs descriptions et une présentation :
                                    // l'identifiant doit rester unique dans la page
                                    $texteRepliableId = $type . '-' . (int) $des['idPersonne'];
                                    include(__ROOT__ . "/_texte_repliable.inc.php");
                                    ?>

                                    <?php if ($type == 'description') : ?>
                                        <p><?= Personne::getSignatureHtml((int) $des['idPersonne']) ?></p>
                                    <?php endif; ?>

                                    <div class="auteur">
                                        <span class="left">
                                            <?= ucfirst(DateHelper::isoToFr($des['dateAjout'], 'annee', showDayOfWeek: false)) ?><?php if ($des['date_derniere_modif'] != "0000-00-00 00:00:00" && $des['date_derniere_modif'] != $des['dateAjout']) : ?>, modifié le <?= DateHelper::isoToFr($des['date_derniere_modif'], 'annee', showDayOfWeek: false) ?><?php endif; ?>
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

                <?php endif; ?>

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
                        <a href="?<?= HtmlShrink::urlQueryArrayToString($get, ['periode', 'page']) ?>&amp;periode=<?= $k ?>"><?= $label ?></a>
                    </li>
                <?php endforeach; ?>
                <div class="spacer"></div>
            </ul>

            <div class="spacer"><!-- --></div>

        </header>

        <?php
        if ($all_results_nb == 0) :  ?>

        <p><?= sanitizeForHtml($translator->get("lieu-events-{$get['periode']}-none")) ?> <?= sanitizeForHtml(Lieu::prepositionToPutInSentence($lieu['determinant'])) ?> <strong><?= sanitizeForHtml($lieu['nom']) ?></strong></p>

        <?php else : ?>

            <?php // pagination à gauche, menu de tri à droite ; le tri ne concerne que les événements passés ?>
            <div class="liste-barre">
                <?= HtmlShrink::getPaginationString($all_results_nb, $get['page'], $results_per_page, 1, basename(__FILE__), "?" . HtmlShrink::urlQueryArrayToString($get, "page") . "&amp;page=") ?>
                <?php if ($get['periode'] == "ancien") : include(__ROOT__ . "/event/_events_order_menu.inc.php"); endif; ?>
            </div>
            <table>
                <?php foreach ($page_results_grouped_by_yearmonth as $yearmonth => $tab_month_events) : ?>
                    <tr>
                        <td colspan="5" class="mois"><?= ucfirst((string) DateHelper::monthName((int)(new \DateTime($yearmonth))->format('m'))) ?><?php if ((new \DateTime($yearmonth))->format('Y') != date('Y')) : echo "&nbsp;".(new \DateTime($yearmonth))->format('Y'); endif; ?>
                        </td>
                    </tr>
                    <?php
                    foreach ($tab_month_events as $tab_event) :
                        echo Ladecadanse\EvenementRenderer::eventTableRowHtml($tab_event, $authorization, isWithLieu: false, lieuName: $lieu['nom']);
                    endforeach;
                    ?>
                <?php endforeach; ?>
            </table>

            <?= HtmlShrink::getPaginationString($all_results_nb, $get['page'], $results_per_page, 1, basename(__FILE__), "?" . HtmlShrink::urlQueryArrayToString($get, "page") . "&amp;page=") ?>

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
