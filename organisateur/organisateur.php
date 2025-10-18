<?php

require_once("../app/bootstrap.php");

use Ladecadanse\UserLevel;
use Ladecadanse\Organisateur;
use Ladecadanse\Evenement;
use Ladecadanse\Lieu;
use Ladecadanse\Personne;
use Ladecadanse\Utils\Text;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Utils\Utils;
use Ladecadanse\Utils\Validateur;

if (empty($_GET['idO']) || !is_numeric($_GET['idO']))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
    exit;
}
$get['idO'] = (int) $_GET['idO'];

$organisateur = new Organisateur();
$organisateur->setId($get['idO']);
$organisateur->load();

if (empty($organisateur))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
    exit;
}

if ($organisateur->getValue('statut') == 'inactif' && !((isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= UserLevel::AUTHOR)))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
    exit;
}

$tab_menu_periodes = ["ancien" => "Passés", "futur" => "Prochains"];
$get['periode'] = "futur";
$sql_periode_operator = ">=";
if (!empty($_GET['periode']) && Validateur::validateUrlQueryValue($_GET['periode'], "enum", 1, array_keys($tab_menu_periodes)))
{
    $get['periode'] = $_GET['periode'];
    if ($get['periode'] == "ancien")
    {
        $sql_periode_operator = "<";
    }
}

$get['page'] = !empty($_GET['page']) ? Validateur::validateUrlQueryValue($_GET['page'], "int", 1) : 1;
$results_per_page = 50;

$orga_lieux = Organisateur::getActivesLieux($get['idO']);
$orga_personnes = Personne::getPersonnesOfOrganisateur($get['idO']);

//$evenements = new EvenementCollection($connector);
//$evenements->loadOrganisateur($get['idO'], $glo_auj_6h, "");

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
    JOIN evenement_organisateur eo ON e.idEvenement = eo.idEvenement
    JOIN localite loc ON e.localite_id = loc.id
    LEFT JOIN lieu l ON e.idLieu = l.idLieu
    LEFT JOIN localite lloc ON l.localite_id = lloc.id
    LEFT JOIN salle s ON e.idSalle = s.idSalle
    WHERE
    e.statut NOT IN ('inactif', 'propose') AND eo.idOrganisateur = ?";

$sql_select .= " AND e.dateEvenement $sql_periode_operator ?";
$sql_select .= ' ORDER BY dateEvenement ASC';
$sql_select .= " LIMIT " . (int) (($get['page'] - 1) * $results_per_page) . ", " . (int) ($results_per_page); // ($get['page'] - 1) * $results_per_page +
//echo $sql_select;
$stmt = $connectorPdo->prepare($sql_select);
$stmt->execute([$get['idO'], $glo_auj]);
$page_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
//echo " <BR>NB RES:" . count($page_results);
$page_results_grouped_by_yearmonth = [];
foreach ($page_results as $event) {
    $yearmonth = date('Y-m-01', strtotime($event['e_dateEvenement']));
    $page_results_grouped_by_yearmonth[$yearmonth][] = $event;
}

//dump($page_results_grouped_by_yearmonth);

$sql_select_all =
    "SELECT count(*) AS nb
    FROM evenement e
    JOIN evenement_organisateur eo ON e.idEvenement = eo.idEvenement
    WHERE
    e.statut NOT IN ('inactif', 'propose') AND eo.idOrganisateur = ? AND e.dateEvenement $sql_periode_operator ?";
// echo $sql_select_all;
$stmtAll = $connectorPdo->prepare($sql_select_all);
$stmtAll->execute([$get['idO'], $glo_auj]);
$all_results_nb = $stmtAll->fetchColumn();

$extra_css = ["organisateurs_menu"];
$page_titre = $organisateur->getValue('nom');
$page_description = $organisateur->getValue('nom') . " : informations pratiques, présentation et événements";
include("../_header.inc.php");
?>

<main id="contenu" class="colonne">

    <?php
    if (!empty($_SESSION['organisateur_flash_msg']))
    {
        HtmlShrink::msgOk($_SESSION['organisateur_flash_msg']);
        unset($_SESSION['organisateur_flash_msg']);
    }
    ?>

    <section class="vcard">

        <header id="entete_contenu">

            <h1 class="fn org"><?= $organisateur->getHtmlValue('nom'); ?></h1>

            <?php if ($organisateur->getValue('logo') != '') : ?>
            <a href="<?= Organisateur::getWebPath(Organisateur::getFilePath($organisateur->getValue('logo')), isWithAntiCache: true) ?>" class="magnific-popup"><img src="<?= Organisateur::getWebPath(Organisateur::getFilePath($organisateur->getValue('logo'), "s_"), true) ?>" alt="Logo" class="logo" /></a>
            <?php endif ?>
            <div class="spacer"></div>
        </header>

        <ul class="menu_actions_lieu">
            <?php if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= UserLevel::ACTOR) ) : ?>
                <li class="action_ajouter"><a href="/evenement-edit.php?idO=<?= (int)$get['idO'] ?>">Ajouter un événement de cet organisateur</a></li>
            <?php endif; ?>
            <?php if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= UserLevel::AUTHOR || (isset($_SESSION['SidPersonne']) && $authorization->isPersonneInOrganisateur($_SESSION['SidPersonne'], $get['idO']) && $_SESSION['Sgroupe'] <= UserLevel::ACTOR))) : ?>
                <li class="action_editer"><a href="/organisateur-edit.php?action=editer&amp;idO=<?= (int) $get['idO'] ?>">Modifier cet organisateur</a></li>
            <?php endif; ?>
        </ul>

        <div class="spacer"><!-- --></div>

        <article id="fiche">

            <div id="medias">
                <figure id="photo">
                    <?php if ($organisateur->getValue('photo') != '') : ?>
                        <a href="<?= Organisateur::getWebPath(Organisateur::getFilePath($organisateur->getValue('photo')), isWithAntiCache: true) ?>" class="magnific-popup">
                            <img src="<?= Organisateur::getWebPath(Organisateur::getFilePath($organisateur->getValue('photo'), "s_"), isWithAntiCache: true) ?>" alt="Photo" />
                        </a>
                    <?php endif; ?>
                </figure>
                <div class="spacer"><!-- --></div>
            </div>

            <div id="pratique">
                <ul>
                    <?php if (!empty($organisateur->getValue('URL'))) : $lieu_url = Text::getUrlWithName($organisateur->getValue('URL')); ?>
                        <li class="sitelieu">
                            <a class="url lien_ext" href="<?= sanitizeForHtml($lieu_url['url']) ?>" target="_blank"><?= sanitizeForHtml($lieu_url['urlName']) ?></a>
                        </li>
                    <?php endif; ?>
                    <?php if (count($orga_lieux) > 0) : ?>
                        <li>Lieu(x) géré(s) :
                            <ul class="salles">
                                <?php foreach ($orga_lieux as $l) : ?>
                                   <li><a href="/lieu/lieu.php?idL=<?= (int)$l['idLieu'] ?>"><?= sanitizeForHtml($l['nom']) ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['SidPersonne']) && ($authorization->isAuthor("organisateur", $_SESSION['SidPersonne'], $get['idO']) || $authorization->isPersonneInOrganisateur($_SESSION['SidPersonne'], $get['idO'])) && count($orga_personnes) > 0) : ?>
                        <li>Membres :
                            <ul class="salles">
                                <?php foreach ($orga_personnes as $op) : ?>
                                <li><a href="/user.php?idP=<?= (int)$op['idPersonne'] ?>"><?= sanitizeForHtml($op['pseudo']) ?></a>&nbsp;<small><?= sanitizeForHtml($op['email']) ?></small></li>
                                <?php endforeach ?>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
            </div> <!-- pratique -->

            <div id="descriptions">
                <?php if (mb_strlen($organisateur->getHtmlValue('presentation')) > 0) : ?>
                    <ul id="menu_descriptions">
                        <li class="btn-description ici"><h2>L'organisateur se présente</h2></li>
                    </ul>
                    <div class="description">
                        <div class="js-read-smore" data-read-smore-words="50">
                        <?= $organisateur->getValue('presentation') ?>
                        </div>
                    </div>
                <?php endif ?>
            </div>

            <div class="spacer"></div>

        </article>

    </section> <!-- .vcard -->

    <div class="spacer"></div>


    <section id="prochains_evenements">

        <header>
            <h2>Événements <a href="/event/rss.php?type=organisateur_evenements&amp;id=<?= (int)$get['idO'] ?>" title="Flux RSS des prochains événements"><i class="fa fa-rss fa-lg" style="font-size:0.9em;color:#f5b045"></i></a></h2>
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

        <?php if ($all_results_nb == 0) : ?>

            <p><?= $translator->get("lieu-events-{$get['periode']}-none") ?> pour <strong><?= $organisateur->getHtmlValue('nom') ?></strong></p>

        <?php else : ?>

            <?= HtmlShrink::getPaginationString($all_results_nb, $get['page'], $results_per_page, 1, basename(__FILE__), "?" . Utils::urlQueryArrayToString($get, "page") . "&amp;page=") ?>

            <table>
                <?php foreach ($page_results_grouped_by_yearmonth as $yearmonth => $tab_month_events) : ?>
                    <tr>
                        <td colspan="5" class="mois">
                            <?= ucfirst((string) mois2fr(date2mois($yearmonth))) ?><?php if (date2annee($yearmonth) != date('Y')) : echo "&nbsp;".date2annee($yearmonth); endif; ?>
                        </td>
                    </tr>
                    <?php
                    foreach ($tab_month_events as $tab_event) :
                        echo Ladecadanse\EvenementRenderer::eventTableRowHtml($tab_event, $authorization, isWithLieu: true);
                    endforeach;
                    ?>
                <?php endforeach; ?>
            </table>

            <?= HtmlShrink::getPaginationString($all_results_nb, $get['page'], $results_per_page, 1, basename(__FILE__), "?" . Utils::urlQueryArrayToString($get, "page") . "&amp;page=") ?>

        <?php endif; // nb even ?>

        <?php if (!empty($organisateur->getValue('URL'))) :
            $url_with_name = Text::getUrlWithName($organisateur->getValue('URL'))     ?>
            <p><br>Pour des informations complémentaires veuillez consulter <a href="<?= $url_with_name['url'] ?>" target='_blank'><?= sanitizeForHtml($url_with_name['urlName']) ?></a></p>
        <?php endif; ?>

    </section> <!-- #prochains_evenements -->

</main>

<div id="colonne_gauche" class="colonne">
    <?php include("../event/_navigation_calendrier.inc.php"); ?>
</div>
<div id="colonne_droite" class="colonne">
</div>

<div class="spacer"><!-- --></div>
<?php
include("../_footer.inc.php");
?>
