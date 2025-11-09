<?php

require_once("../app/bootstrap.php");

use Ladecadanse\HtmlShrink;
use Ladecadanse\OrganisateurCollection;
use Ladecadanse\Organisateur;
use Ladecadanse\Utils\Utils;
use Ladecadanse\Utils\ImageDriver2;
use Ladecadanse\UserLevel;
use Ladecadanse\Utils\Validateur;

$_SESSION['user_prefs_orgas_nom'] ??= '';
if (isset($_GET['nom']))
{
   $_SESSION['user_prefs_orgas_nom'] = $_GET['nom'];
}
$filters['nom'] = $_SESSION['user_prefs_orgas_nom'];

$_SESSION['user_prefs_orgas_statut'] ??= 'actif';
$tab_statuts = ['actif' => 'Actifs', 'inactif' => 'Inactifs', 'ancien' => 'Anciens'];
if (isset($_GET['statut']) && Validateur::validateUrlQueryValue($_GET['statut'], "enum", 1, array_keys($tab_statuts)))
{
   $_SESSION['user_prefs_orgas_statut'] = $_GET['statut'];
}
$filters['statut'] = $_SESSION['user_prefs_orgas_statut'];

$tab_order = ["date_ajout", "nom"];
$_SESSION['user_prefs_orgas_order'] ??= 'date_ajout';
if (isset($_GET['order']) && in_array($_GET['order'], $tab_order))
{
   $_SESSION['user_prefs_orgas_order'] = $_GET['order'];
}

$get['page'] = !empty($_GET['page']) ? Validateur::validateUrlQueryValue($_GET['page'], "int", 1) : 1;


$col = new OrganisateurCollection();
$col->loadFiches();

$orgas_page_current = Organisateur::getOrganisateurs($filters, $_SESSION['user_prefs_orgas_order'], $get['page']);
$orgas_page_all = Organisateur::getOrganisateurs($filters, $_SESSION['user_prefs_orgas_order'], null);
$all_results_nb = count($orgas_page_all);

// for each orga report futur and past events
$stmt = $connectorPdo->prepare("
SELECT
    idOrganisateur,
    count(CASE WHEN e.dateEvenement >= ? THEN 1 END) AS events_futur_nb,
    MAX(e.dateEvenement) AS latest_event_date,
    TIMESTAMPDIFF(MONTH, MAX(e.dateEvenement), CURDATE()) AS latest_event_months_nb,
    (SUM(CASE WHEN e.dateEvenement = CURDATE() THEN 1 ELSE 0 END) > 0) AS has_today_event
FROM evenement e
JOIN evenement_organisateur eo ON e.idEvenement = eo.idEvenement
WHERE e.statut NOT IN ('inactif', 'propose') GROUP BY idOrganisateur ORDER BY idOrganisateur ASC");
$stmt->execute([$glo_auj]);
$orgas_even = $stmt->fetchAll(PDO::FETCH_GROUP);
//dump($lieux_even);

$page_titre = "Organisateurs d'événements culturels à Genève et Lausanne : associations, labels, collectifs";
$page_description = "";
$extra_css = ["organisateur/organisateurs"];
include("../_header.inc.php");
?>

<main id="contenu" class="colonne">

    <header id="entete_contenu" style="margin-bottom:1.5em">
        <h1 style="width: 35%;line-height: 1.2em;margin:0">Organisateurs</h1><?php if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 6)
{ ?><a href="/organisateur-edit.php?action=ajouter" style="float: left;padding: 5px 1px;"><img src="/web/interface/icons/add.png" alt="" style="vertical-align:bottom" /> Ajouter un organisateur</a><?php } ?>
        <div class="spacer"></div>
    </header>

    <section id="default">

        <div>
            <div class="table-filters">
                <form action="" method="get">
                    <input type="search" name="nom" value="<?= $_SESSION['user_prefs_orgas_nom'] ?>" placeholder="Nom" aria-label="Nom">
                    <button type="submit" style="margin-top:2px">OK</button>
                </form>
                <ul class="menu_tab">
                    <?php foreach ($tab_statuts as $k => $label) : ?>
                        <?php if ($k == "inactif" && !$authorization->isPersonneEditor($_SESSION)) { continue; } ?>
                        <li class="<?= $k ?><?php if ($_SESSION['user_prefs_orgas_statut'] == $k) : ?> ici<?php endif; ?>">
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
                    <li style="margin-right:2px"><a href="?order=date_ajout" class="<?php if ($_SESSION['user_prefs_orgas_order'] == 'date_ajout') : ?>selected<?php endif; ?>" rel="nofollow">Dernier ajouté</a></li>
                    <li><a href="?order=nom" class="<?php if ($_SESSION['user_prefs_orgas_order'] == 'nom') : ?>selected<?php endif; ?>" rel="nofollow">Nom</a></li>
                </ul>
                <div class="spacer"></div>
            </div>
            <div class="spacer"></div>
        </div>

        <?php if ($all_results_nb === 0) : ?>
            <p style="margin-top:2em;">Pas d'organisateur correspondant à ces critères</p>
        <?php else : ?>

            <?= HtmlShrink::getPaginationString($all_results_nb, $get['page'], Organisateur::RESULTS_PER_PAGE, 1, basename(__FILE__), "?" . Utils::urlQueryArrayToString($get, "page") . "&amp;page=") ?>

            <table id="derniers_lieux">

                <thead>
                    <tr>
                        <th colspan="2"></th>
                        <th class="td-align-center"><img src="/web/interface/icons/calendar.png" alt="Nombre d'événements agendés" title="Nombre d'événements agendés" /></th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($orgas_page_current as $orga) : ?>
                    <tr>
                        <td style="max-width:70px;overflow: hidden;">
                            <?php if ($orga['logo']) : ?>
                            <a href="<?= Organisateur::getWebPath(Organisateur::getFilePath($orga['logo']), true) ?>" class="magnific-popup"><img src="<?= Organisateur::getWebPath(Organisateur::getFilePath($orga['logo'], "s_"), true) ?>" alt="Logo" class="logo" height="<?= ImageDriver2::getProportionalHeightFromGivenWidth(Organisateur::getSystemFilePath(Organisateur::getFilePath($orga['logo'], "s_")), 50) ?>"></a>
                            <?php elseif ($orga['photo'] != '') : ?>
                                <a href="<?= Organisateur::getWebPath(Organisateur::getFilePath($orga['photo']), true) ?>" class="gallery-item"><img src="<?= Organisateur::getWebPath(Organisateur::getFilePath($orga['photo'], "s_"), true) ?>" alt="Photo" height="<?= ImageDriver2::getProportionalHeightFromGivenWidth(Organisateur::getSystemFilePath(Organisateur::getFilePath($orga['photo'], "s_")), 50) ?>"></a>
                            <?php else : ?>
                                <div style="width:60px;height:40px;background: #fafafa"></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="/organisateur/organisateur.php?idO=<?= (int)$orga['idOrganisateur']; ?>"><strong><?= sanitizeForHtml($orga['nom']); ?></strong></a>
                        </td>

                        <td class="td-align-center<?php if (!empty($orgas_even[$orga['idOrganisateur']][0]['has_today_event']) ) { echo " ici"; } ?>">

                            <?php if (!empty($orgas_even[$orga['idOrganisateur']][0]) ) : ?>

                                <?php if ($orgas_even[$orga['idOrganisateur']][0]['events_futur_nb'] > 0) : ?>

                                    <strong><a href="/organisateur/organisateur.php?idO=<?= (int)$orga['idOrganisateur'] ?>#prochains_evenements"><?= $orgas_even[$orga['idOrganisateur']][0]['events_futur_nb'] ?></a></strong>

                                <?php elseif ($authorization->isPersonneEditor($_SESSION)) : ?>

                                <?php if ($orgas_even[$orga['idOrganisateur']][0]['latest_event_months_nb'] > Organisateur::LOW_ACTIVITY_MONTHS_NB) : ?>
                                    <small style="<?php if ($orgas_even[$orga['idOrganisateur']][0]['latest_event_months_nb'] > Organisateur::VERY_LOW_ACTIVITY_MONTHS_NB) : ?>color:red;<?php else : ?>color:darkorange; <?php endif ?>"><?= (new DateTime($orgas_even[$orga['idOrganisateur']][0]['latest_event_date']))->format('m.Y') ?></small>
                                <?php else : ?>
                                    <small style="color:lightsteelblue"><?= (new DateTime($orgas_even[$orga['idOrganisateur']][0]['latest_event_date']))->format('m.Y') ?></small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (count($orgas_page_current) > 8) : ?>
                <?= HtmlShrink::getPaginationString($all_results_nb, $get['page'], Organisateur::RESULTS_PER_PAGE, 1, basename(__FILE__), "?" . Utils::urlQueryArrayToString($get, "page") . "&amp;page=") ?>
            <?php endif; ?>

        <?php endif; ?>


    </section>

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
