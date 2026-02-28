<?php

require_once("../app/bootstrap.php");

use Ladecadanse\HtmlShrink;
use Ladecadanse\Lieu;
use Ladecadanse\Utils\ImageDriver2;
use Ladecadanse\UserLevel;
use Ladecadanse\Utils\Utils;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Localite;

$get = [];

$filters['region'] = $_SESSION['region'];

$_SESSION['user_prefs_lieux_nom'] ??= '';
if (isset($_GET['nom']))
{
   $_SESSION['user_prefs_lieux_nom'] = $_GET['nom'];
}
$filters['nom'] = $_SESSION['user_prefs_lieux_nom'];

$_SESSION['user_prefs_lieux_categorie'] ??= '';
if (isset($_GET['categorie']))
{
   $_SESSION['user_prefs_lieux_categorie'] = $_GET['categorie'];
}
$filters['categorie'] = $_SESSION['user_prefs_lieux_categorie'];

$_SESSION['user_prefs_lieux_localite'] ??= '';
// passing to another region needs to reset a stored localité belonging to the previous region
if (!empty($_GET['region']))
{
    $_SESSION['user_prefs_lieux_localite'] = '';
}
if (isset($_GET['localite']))
{
    $_SESSION['user_prefs_lieux_localite']  = (int) $_GET['localite'];
}
$filters['localite'] = $_SESSION['user_prefs_lieux_localite'];

$_SESSION['user_prefs_lieux_statut'] ??= 'actif';
$tab_statuts = ['actif' => 'Actifs', 'inactif' => 'Inactifs', 'ancien' => 'Anciens'];
if (isset($_GET['statut']) && Validateur::validateUrlQueryValue($_GET['statut'], "enum", 1, array_keys($tab_statuts)))
{
   $_SESSION['user_prefs_lieux_statut'] = $_GET['statut'];
}
$filters['statut'] = $_SESSION['user_prefs_lieux_statut'];

$tab_order = ["dateAjout", "nom"];
$_SESSION['user_prefs_lieux_order'] ??= 'dateAjout';
if (isset($_GET['order']) && in_array($_GET['order'], $tab_order))
{
   $_SESSION['user_prefs_lieux_order'] = $_GET['order'];
}

$get['page'] = !empty($_GET['page']) ? Validateur::validateUrlQueryValue($_GET['page'], "int", 1) : 1;

// used to build localite filter Select (exclude localites without lieu)
$lieux_region_localite_ids = array_values(array_unique(array_column(Lieu::getLieux(filters: ['region' => $filters['region'], 'statut' => $filters['statut']], page: null), 'localite_id')));

$lieux_page_current = Lieu::getLieux($filters, $_SESSION['user_prefs_lieux_order'], $get['page']);
$lieux_page_all = Lieu::getLieux($filters, $_SESSION['user_prefs_lieux_order'], null);
$all_results_nb = count($lieux_page_all);

// TODO: lieux with 1+ event today; then below if in_array($idLieu, $lieux_with_events_today) : class="auj"
// list($regionInClause, $regionInParams) = $connectorPdo->buildInClause('e.idLieu', $lieux_page_current_ids]);
// $lieux_page_current_ids = array_column('idLieu', $lieux_page_current)
//$lieux_with_events_today_sql = "SELECT idLieu FROM evenement e WHERE e.statut NOT IN ('inactif', 'propose') AND $regionInClause and dateEvenement = :date"
//$stmt = $connectorPdo->prepare($lieux_with_events_today_sql);
//$stmt->execute(array_merge([':date' => $glo_auj_6h, ...$regionInParams]]));
//$tab_events_today_in_region_by_category = $stmt->fetchAll(PDO::FETCH_GROUP);

$regions_localites = Localite::getListByRegion();

// get lieux salles
$stmt = $connectorPdo->prepare("SELECT
idLieu,
s.*
FROM salle s
ORDER BY nom DESC");
$stmt->execute();
$lieux_salles = $stmt->fetchAll(PDO::FETCH_GROUP);

// get lieux descriptions
$stmt = $connectorPdo->prepare("SELECT
idLieu,
count(*) as nb
FROM descriptionlieu
WHERE type = 'description' GROUP BY idLieu");
$stmt->execute();
$lieux_desc = $stmt->fetchAll(PDO::FETCH_GROUP);

// for each lieu report futur and past events
$stmt = $connectorPdo->prepare("
SELECT
    idLieu,
    count(CASE WHEN e.dateEvenement >= ? THEN 1 END) AS events_futur_nb,
    MAX(e.dateEvenement) AS latest_event_date,
    TIMESTAMPDIFF(MONTH, MAX(e.dateEvenement), CURDATE()) AS latest_event_months_nb,
    (SUM(CASE WHEN e.dateEvenement = CURDATE() THEN 1 ELSE 0 END) > 0) AS has_today_event
FROM evenement e
WHERE e.statut NOT IN ('inactif', 'propose') GROUP BY idLieu ORDER BY idLieu ASC");
$stmt->execute([$glo_auj]);
$lieux_even = $stmt->fetchAll(PDO::FETCH_GROUP);
//dump($lieux_even);

$page_titre = "Lieux de sorties à ".$glo_regions[$_SESSION['region']]." : bistrots, salles, bars, restaurants, cinémas, théâtres, galeries, boutiques, musées...";
include("../_header.inc.php");
?>

<main id="contenu" class="colonne">

    <header id="entete_contenu">
        <h1 style="width: 17%;line-height: 1.2em;margin:0">Lieux</h1> <?php if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= UserLevel::AUTHOR) { ?><a href="/lieu-edit.php?action=ajouter" style="float: left;padding: 5px 1px;"><img src="/web/interface/icons/building_add.png" alt=""  /> Ajouter un lieu</a><?php } ?>
        <?php HtmlShrink::getMenuRegions($glo_regions, $get); ?>
        <div class="spacer"></div>
    </header>

    <section id="default">

        <div>
            <div class="table-filters">
                <form action="" method="get">
                    <input type="search" name="nom" value="<?= sanitizeForHtml($_SESSION['user_prefs_lieux_nom']) ?>" placeholder="Nom" aria-label="Nom">
                    <select name="categorie" class="js-select2-options-with-style" data-placeholder="Catégorie" style="width:80px">
                         <option value="" placeholder="type"></option>
                        <?php foreach ($glo_categories_lieux as $k => $label) : ?>
                            <option value="<?= $k ?>" <?php if ($_SESSION['user_prefs_lieux_categorie'] == $k) : ?>selected="selected"<?php endif; ?>><?= sanitizeForHtml($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= HtmlShrink::getLocalitesSelect($regions_localites, $glo_regions, $glo_tab_ailleurs, $lieux_region_localite_ids); ?>
                    <button type="submit" style="margin-top:2px">OK</button>
                </form>
                <ul class="menu_tab">
                    <?php foreach ($tab_statuts as $k => $label) : ?>
                        <?php if ($k == "inactif" && !$authorization->isPersonneEditor($_SESSION)) { continue; } ?>
                        <li class="<?= $k ?><?php if ($_SESSION['user_prefs_lieux_statut'] == $k) : ?> ici<?php endif; ?>">
                            <a href="?<?= Utils::urlQueryArrayToString($get, ['statut', 'page']) ?>&amp;statut=<?= $k ?>"><?= $label ?></a>
                        </li>
                    <?php endforeach; ?>
                    <div class="spacer"></div>
                </ul>
                <div class="spacer"></div>
            </div>

            <div id="order_navigation">
                <ul>
                    <li style="margin-right:5px"><i class="fa fa-sort-amount-asc" aria-hidden="true"></i></li>
                    <li style="margin-right:2px"><a href="?order=dateAjout" class="<?php if ($_SESSION['user_prefs_lieux_order'] == 'dateAjout') : ?>selected<?php endif; ?>" rel="nofollow">Dernier ajouté</a></li>
                    <li><a href="?order=nom" class="<?php if ($_SESSION['user_prefs_lieux_order'] == 'nom') : ?>selected<?php endif; ?>" rel="nofollow">Nom</a></li>
                </ul>
                <div class="spacer"></div>
            </div>
            <div class="spacer"></div>
        </div>

        <?php if ($all_results_nb === 0) : ?>
            <p style="margin-top:2em;">Pas de lieu correspondant à ces critères</p>
        <?php else : ?>

            <?= HtmlShrink::getPaginationString($all_results_nb, $get['page'], Lieu::RESULTS_PER_PAGE, 1, basename(__FILE__), "?" . Utils::urlQueryArrayToString($get, "page") . "&amp;page=") ?>

            <table id="derniers_lieux">
                <thead>
                    <tr>
                        <th colspan="3"></th>
                        <th class="td-align-center"><i class="fa fa-comment-o" aria-hidden="true"></i></th>
                        <th class="td-align-center"><img src="/web/interface/icons/calendar.png" alt="Nombre d'événements agendés" title="Nombre d'événements agendés" /></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($lieux_page_current as $lieu) : ?>
                    <tr >
                        <td style="max-width:70px;overflow: hidden;">
                            <?php if ($lieu['logo']) : ?>
                            <a href="<?= sanitizeForHtml(Lieu::getWebPath(Lieu::getFilePath($lieu['logo']), true)) ?>" class="magnific-popup"><img src="<?= sanitizeForHtml(Lieu::getWebPath(Lieu::getFilePath($lieu['logo'], "s_"), true)) ?>" alt="Logo" class="logo" height="<?= ImageDriver2::getProportionalHeightFromGivenWidth(Lieu::getSystemFilePath(Lieu::getFilePath($lieu['logo'], "s_")), 50) ?>"></a>
                            <?php elseif ($lieu['photo1'] != '') : ?>
                                <a href="<?= sanitizeForHtml(Lieu::getWebPath(Lieu::getFilePath($lieu['photo1']), true)) ?>" class="gallery-item"><img src="<?= sanitizeForHtml(Lieu::getWebPath(Lieu::getFilePath($lieu['photo1'], "s_"), true)) ?>" alt="Photo du lieu" height="<?= ImageDriver2::getProportionalHeightFromGivenWidth(Lieu::getSystemFilePath(Lieu::getFilePath($lieu['photo1'], "s_")), 50) ?>"></a>
                            <?php else : ?>
                                <div style="width:60px;height:40px;background: #fafafa"></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="lieu.php?idL=<?= (int)$lieu['idLieu']; ?>" ><strong><?= sanitizeForHtml($lieu['nom']); ?></strong></a>
                            <?php  if (0) : // if (!empty($lieux_salles[$lieu['idLieu']])) : ?>
                                <?php foreach ($lieux_salles[$lieu['idLieu']] as $s) : ?>
                                    <br><?= sanitizeForHtml($s['nom']) ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                                <br><small><?= sanitizeForHtml(implode(", ", array_map(fn ($cat) : string => $glo_categories_lieux[$cat], explode(",", str_replace(" ", "", $lieu['categorie']))))) ?></small>
                        </td>

                        <td>
                            <?php
                            // HACK: all lieux in localite.id=1 (localite.canton="") are actually in France
                            if (empty($lieu['loc_canton'])) : ?>France<?php else: ?><?= sanitizeForHtml($lieu['loc_localite']) ?><?php endif;?>
                            <p style="font-size:0.8em"><?= sanitizeForHtml(HtmlShrink::adresseCompacteSelonContexte(region: "", localite:"", quartier: $lieu['quartier'], adresse: $lieu['adresse'])); ?></p>
                        </td>
                        <td class="td-align-center">
                            <?php if (!empty($lieux_desc[$lieu['idLieu']])) : ?>
                                <?= $lieux_desc[$lieu['idLieu']][0]['nb'] ?>
                            <?php endif; ?>
                        </td>
                        <td class="td-align-center<?php if (!empty($lieux_even[$lieu['idLieu']][0]['has_today_event']) ) { echo " ici"; } ?>">

                            <?php if (!empty($lieux_even[$lieu['idLieu']][0]) ) : ?>

                                <?php if ($lieux_even[$lieu['idLieu']][0]['events_futur_nb'] > 0) : ?>
                                    <strong><a href="lieu.php?idL=<?= (int)$lieu['idLieu'] ?>#prochains_evenements"><?= $lieux_even[$lieu['idLieu']][0]['events_futur_nb'] ?></a></strong>

                                <?php elseif ($authorization->isPersonneEditor($_SESSION)) : ?>

                                    <?php if ($lieux_even[$lieu['idLieu']][0]['latest_event_months_nb'] > Lieu::LOW_ACTIVITY_MONTHS_NB) : ?>
                                        <small style="<?php if ($lieux_even[$lieu['idLieu']][0]['latest_event_months_nb'] > Lieu::VERY_LOW_ACTIVITY_MONTHS_NB) : ?>color:red;<?php else : ?>color:orange; <?php endif ?>"><?= (new DateTime($lieux_even[$lieu['idLieu']][0]['latest_event_date']))->format('m.Y') ?></small>

                                    <?php else : ?>
                                        <small style="color:lightsteelblue"><?= (new DateTime($lieux_even[$lieu['idLieu']][0]['latest_event_date']))->format('m.Y') ?></small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (count($lieux_page_current) > 8) : ?>
                <?= HtmlShrink::getPaginationString($all_results_nb, $get['page'], Lieu::RESULTS_PER_PAGE, 1, basename(__FILE__), "?" . Utils::urlQueryArrayToString($get, "page") . "&amp;page=") ?>
            <?php endif; ?>

        <?php endif; ?>

    </section>

    <div class="clear_mobile"></div>

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
