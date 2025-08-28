<?php

global $connector;
require_once("app/bootstrap.php");

use Ladecadanse\HtmlShrink;
use Ladecadanse\Lieu;
use Ladecadanse\Utils\ImageDriver2;
use Ladecadanse\UserLevel;
use Ladecadanse\Utils\Utils;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Localite;

$get = [];

$filters['region'] = $_SESSION['region'];

$_SESSION['user_prefs_lieux_categorie'] ?? '';
if (isset($_GET['categorie']))
{
   $_SESSION['user_prefs_lieux_categorie'] = $_GET['categorie'];
}
$filters['categorie'] = $_SESSION['user_prefs_lieux_categorie'];

$_SESSION['user_prefs_lieux_localite'] ?? '';
if (isset($_GET['localite']))
{
    $_SESSION['user_prefs_lieux_localite']  = (int) $_GET['localite'];
}
$filters['localite'] = $_SESSION['user_prefs_lieux_localite'];

$_SESSION['user_prefs_lieux_statut'] ?? 'actif';
$tab_statuts = ['actif' => 'Actifs', 'inactif' => 'Inactifs', 'ancien' => 'Anciens'];
if (isset($_GET['statut']) && Validateur::validateUrlQueryValue($_GET['statut'], "enum", 1, array_keys($tab_statuts)))
{
   $_SESSION['user_prefs_lieux_statut'] = $_GET['statut'];
}
$filters['statut'] = $_SESSION['user_prefs_lieux_statut'];

$tab_order = ["dateAjout", "nom"];
$_SESSION['user_prefs_lieux_order'] ?? 'dateAjout';
if (isset($_GET['order']) && in_array($_GET['order'], $tab_order))
{
   $_SESSION['user_prefs_lieux_order'] = $_GET['order'];
}

$get['page'] = !empty($_GET['page']) ? Validateur::validateUrlQueryValue($_GET['page'], "int", 1) : 1;


$lieux_page = Lieu::getLieux($filters, $_SESSION['user_prefs_lieux_order'], $get['page']);
$lieux_all = Lieu::getLieux($filters, $_SESSION['user_prefs_lieux_order'], null);
//dump($lieux_all);
$all_results_nb = count($lieux_all);


$regions_localites = Localite::getListByRegion();

$stmt = $connectorPdo->prepare("SELECT
idLieu,
s.*
FROM salle s
ORDER BY nom DESC");
$stmt->execute();
$lieux_salles = $stmt->fetchAll(PDO::FETCH_GROUP);

$stmt = $connectorPdo->prepare("SELECT
idLieu,
count(*) as nb
FROM descriptionlieu
WHERE type = 'description' group by idLieu");
$stmt->execute();
$lieux_desc = $stmt->fetchAll(PDO::FETCH_GROUP);

$stmt = $connectorPdo->prepare("SELECT
idLieu,
count(*) as nb
FROM evenement e
WHERE e.statut NOT IN ('inactif', 'propose') AND e.dateEvenement >= '" . $glo_auj . "' group by idLieu");
$stmt->execute();
$lieux_even = $stmt->fetchAll(PDO::FETCH_GROUP);
//dump($lieux_even);

$page_titre = "Lieux de sorties à ".$glo_regions[$_SESSION['region']]." : bistrots, salles, bars, restaurants, cinémas, théâtres, galeries, boutiques, musées...";
include("_header.inc.php");
?>

<main id="contenu" class="colonne">

    <header id="entete_contenu">
        <h1 style="width: 17%;line-height: 1.2em;margin:0">Lieux</h1> <?php if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= UserLevel::AUTHOR) { ?><a href="/lieu-edit.php?action=ajouter" style="float: left;padding: 5px 1px;"><img src="/web/interface/icons/building_add.png" alt=""  /> Ajouter un lieu</a><?php } ?>
        <?php HtmlShrink::getMenuRegions($glo_regions, $get); ?>
        <div class="spacer"></div>
    </header>

    <div class="spacer"></div>

    <section id="default">

        <div>
            <div class="table-filters">
                <form action="" method="get">
                    <select name="categorie" class="js-select2-options-with-style" data-placeholder="Catégorie" style="width:100px">
                         <option value="" placeholder="type"></option>
                        <?php foreach ($glo_categories_lieux as $k => $label) : ?>
                            <option value="<?= $k ?>" <?php if ($_SESSION['user_prefs_lieux_categorie'] == $k) : ?>selected="selected"<?php endif; ?>><?= sanitizeForHtml($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= HtmlShrink::getLocalitesSelect($regions_localites, $glo_regions, $glo_tab_ailleurs); ?>
                    <button type="submit" style="margin-top:2px">OK</button>
                </form>
                <ul class="menu_tab">
                    <?php foreach ($tab_statuts as $k => $label) : ?>
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

        <?= HtmlShrink::getPaginationString($all_results_nb, $get['page'], Lieu::RESULTS_PER_PAGE, 1, basename(__FILE__), "?" . Utils::urlQueryArrayToString($get, "page") . "&amp;page=") ?>

        <table id="derniers_lieux">
            <thead>
                <tr>
                    <th colspan="3"></th><th></th><th><i class="fa fa-comment-o" aria-hidden="true"></i></th><th><img src="/web/interface/icons/calendar.png" alt="Nombre d'événements agendés" title="Nombre d'événements agendés" /></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($lieux_page as $lieu) : ?>
                <tr>
                    <td style="max-width:60px;overflow: hidden;">
                        <?php if ($lieu['logo']) : ?>
                        <a href="<?= Lieu::getFileHref(Lieu::getFilePath($lieu['logo']), true) ?>" class="magnific-popup"><img src="<?= Lieu::getFileHref(Lieu::getFilePath($lieu['logo'], "s_"), true) ?>" alt="Logo" class="logo" height="<?= ImageDriver2::getProportionalHeightFromGivenWidth(Lieu::getSystemFilePath(Lieu::getFilePath($lieu['logo'], "s_")), 40) ?>"></a>
                        <?php elseif ($lieu['photo1'] != '') : ?>
                            <a href="<?= Lieu::getFileHref(Lieu::getFilePath($lieu['photo1']), true) ?>" class="gallery-item"><img src="<?= Lieu::getFileHref(Lieu::getFilePath($lieu['photo1'], "s_"), true) ?>" alt="Photo du lieu" height="<?= ImageDriver2::getProportionalHeightFromGivenWidth(Lieu::getSystemFilePath(Lieu::getFilePath($lieu['photo1'], "s_")), 40) ?>"></a>
                        <?php else : ?>
                            <div style="width:60px;height:40px;background: #fafafa"></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="/lieu.php?idL=<?= (int)$lieu['idLieu']; ?>"><strong><?= sanitizeForHtml($lieu['nom']); ?></strong></a>
                        <?php  if (0) : // if (!empty($lieux_salles[$lieu['idLieu']])) : ?>
                            <?php foreach ($lieux_salles[$lieu['idLieu']] as $s) : ?>
                                <br><?= sanitizeForHtml($s['nom']) ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td><?= sanitizeForHtml(implode(", ", array_map(fn ($cat) : string => $glo_categories_lieux[$cat], explode(",", str_replace(" ", "", $lieu['categorie']))))) ?></td>
                    <td>
                        <?= sanitizeForHtml($lieu['loc_localite']) ?>
                        <p style="font-size:0.8em"><?= sanitizeForHtml(HtmlShrink::adresseCompacteSelonContexte('', '', $lieu['quartier'], $lieu['adresse'])); ?></p>
                    </td>
                    <td>
                        <?php if (!empty($lieux_desc[$lieu['idLieu']])) : ?>
                            <?= $lieux_desc[$lieu['idLieu']][0]['nb'] ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($lieux_even[$lieu['idLieu']])) : ?>
                            <strong><?= $lieux_even[$lieu['idLieu']][0]['nb'] ?></strong>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (count($lieux_page) > 8) : ?>
            <?= HtmlShrink::getPaginationString($all_results_nb, $get['page'], Lieu::RESULTS_PER_PAGE, 1, basename(__FILE__), "?" . Utils::urlQueryArrayToString($get, "page") . "&amp;page=") ?>
        <?php endif; ?>

    </section>

    <div class="clear_mobile"></div>

</main>

<div id="colonne_gauche" class="colonne">
    <?php include("event/_navigation_calendrier.inc.php"); ?>
</div>

<div id="colonne_droite" class="colonne">
</div>

<div class="spacer"><!-- --></div>

<?php
include("_footer.inc.php");
?>
