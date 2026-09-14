<?php

require_once("../app/bootstrap.php");

use Ladecadanse\HtmlShrink;
use Ladecadanse\Organisateur;
use Ladecadanse\Utils\ImageDriver2;
use Ladecadanse\UserLevel;
use Ladecadanse\Utils\QueryParamValidator;
use Ladecadanse\Stats\MonthlyAddedEvents;

$_SESSION['user_prefs_orgas_nom'] ??= '';
if (isset($_GET['nom']))
{
   $_SESSION['user_prefs_orgas_nom'] = $_GET['nom'];
}
$filters['nom'] = $_SESSION['user_prefs_orgas_nom'];

$_SESSION['user_prefs_orgas_statut'] ??= 'actif';
$tab_statuts = ['actif' => 'Actifs', 'inactif' => 'Inactifs', 'ancien' => 'Anciens'];
if (isset($_GET['statut']) && QueryParamValidator::isAcceptedUrlQueryValue($_GET['statut'], "enum", array_keys($tab_statuts)))
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

$get['page'] = QueryParamValidator::pageFromQuery($_GET['page'] ?? '');

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

// suivi de l'activité : réservé aux administrateurs, seuls à relancer un organisateur qui a cessé d'annoncer
$show_monthly_counts = $authorization->isPersonneEditor($_SESSION);
$months_keys = $show_monthly_counts ? MonthlyAddedEvents::monthKeys() : [];
$orgas_monthly_counts = $show_monthly_counts ? MonthlyAddedEvents::forOrganisateurs() : [];

$page_titre = "Organisateurs d'événements culturels à Genève et Vaud : associations, labels, collectifs";
$page_description = "";
$extra_css = ["organisateur/organisateurs"];
include("../_header.inc.php");
?>

<main id="contenu" class="colonne">

    <header id="entete_contenu" style="margin-bottom:1.5em">
        <h1 style="width: 35%;line-height: 1.2em;margin:0">Organisateurs</h1><?php if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 6)
{ ?><a href="/organisateur/edit.php?action=ajouter" style="float: left;padding: 5px 1px;"><i class="fa fa-plus" aria-hidden="true"></i> Ajouter un organisateur</a><?php } ?>
        <div class="spacer"></div>
    </header>

    <section id="default">

        <div>
            <div class="table-filters">
                <form action="" method="get">
                    <span class="search-field">
                        <input type="search" name="nom" value="<?= sanitizeForHtml($_SESSION['user_prefs_orgas_nom']) ?>" placeholder="Nom" aria-label="Nom">
                        <button type="button" class="js-clear-search-field" aria-label="Vider et relancer la recherche" title="Vider et relancer la recherche"></button>
                    </span>
                    <button type="submit" style="margin-top:2px">OK</button>
                </form>
                <ul class="menu_tab">
                    <?php foreach ($tab_statuts as $k => $label) : ?>
                        <?php if ($k == "inactif" && !$authorization->isPersonneEditor($_SESSION)) { continue; } ?>
                        <li class="<?= $k ?><?php if ($_SESSION['user_prefs_orgas_statut'] == $k) : ?> ici<?php endif; ?>">
                            <a href="?<?= HtmlShrink::urlQueryArrayToString($get, ['statut', 'page']) ?>&amp;statut=<?= $k ?>"><?= $label ?></a>
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

            <?= HtmlShrink::getPaginationString($all_results_nb, $get['page'], Organisateur::RESULTS_PER_PAGE, 1, basename(__FILE__), "?" . HtmlShrink::urlQueryArrayToString($get, "page") . "&amp;page=") ?>

            <table id="derniers_lieux">

                <thead>
                    <tr>
                        <th colspan="2"></th>
                        <?php if ($show_monthly_counts) : ?><?= HtmlShrink::getMonthlyCountsHeaderCells($months_keys) ?><?php endif; ?>
                        <th class="td-align-center"><i class="fa fa-calendar-o" aria-label="Nombre d'événements agendés" title="Nombre d'événements agendés"></i></th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($orgas_page_current as $orga) : ?>
                    <tr>
                        <td style="max-width:70px;overflow: hidden;">
                            <?php if ($orga['logo']) : ?>
                            <a href="<?= $assets->get(Organisateur::getAssetPath(Organisateur::getFilePath($orga['logo']))) ?>" class="magnific-popup"><img src="<?= $assets->get(Organisateur::getAssetPath(Organisateur::getFilePath($orga['logo'], "s_"))) ?>" alt="Logo" class="logo" height="<?= ImageDriver2::getProportionalHeightFromGivenWidth(Organisateur::getSystemFilePath(Organisateur::getFilePath($orga['logo'], "s_")), 50) ?>"></a>
                            <?php elseif ($orga['photo'] != '') : ?>
                                <a href="<?= $assets->get(Organisateur::getAssetPath(Organisateur::getFilePath($orga['photo']))) ?>" class="gallery-item"><img src="<?= $assets->get(Organisateur::getAssetPath(Organisateur::getFilePath($orga['photo'], "s_"))) ?>" alt="Photo" height="<?= ImageDriver2::getProportionalHeightFromGivenWidth(Organisateur::getSystemFilePath(Organisateur::getFilePath($orga['photo'], "s_")), 50) ?>"></a>
                            <?php else : ?>
                                <div style="width:60px;height:40px;background: #fafafa"></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="/organisateur/organisateur.php?idO=<?= (int)$orga['idOrganisateur']; ?>"><strong><?= sanitizeForHtml($orga['nom']); ?></strong></a>
                        </td>

                        <?php if ($show_monthly_counts) : ?><?= HtmlShrink::getMonthlyCountsCells($orgas_monthly_counts[$orga['idOrganisateur']] ?? [], $months_keys) ?><?php endif; ?>
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
                <?= HtmlShrink::getPaginationString($all_results_nb, $get['page'], Organisateur::RESULTS_PER_PAGE, 1, basename(__FILE__), "?" . HtmlShrink::urlQueryArrayToString($get, "page") . "&amp;page=") ?>
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
