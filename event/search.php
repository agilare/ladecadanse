<?php

global $connector, $glo_regions, $glo_auj, $iconeEditer, $glo_auj_6h;

require_once("../app/bootstrap.php");

use Ladecadanse\Utils\Logger;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Utils\Utils;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Evenement;
use Ladecadanse\Lieu;
use Ladecadanse\UserLevel;


$tab_menu_tri = ["pertinence" => "Pertinence", "dateEvenement" => "Date", "dateAjout" => "Date d'ajout"];
$tab_menu_periodes = ["ancien" => "Passés", "futur" => "Prochains"]; //, "tous" => "Tous"

$get = [];

if (empty($_GET['mots']) || !empty($_GET['name_as']))
{
    header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request"); echo "Veuillez saisir un texte à rechercher"; exit;
}
$get['mots'] = $_GET['mots'];

$get['tri'] = "pertinence";
if (!empty($_GET['tri']) && Validateur::validateUrlQueryValue($_GET['tri'], "enum", 1, array_keys($tab_menu_tri)))
{
    $get['tri'] = $_GET['tri'];
}

$get['periode'] = "futur";
if (!empty($_GET['periode']) && Validateur::validateUrlQueryValue($_GET['periode'], "enum", 1, array_keys($tab_menu_periodes)))
{
    $get['periode'] = $_GET['periode'];
}

$get['years'] ??= date('Y');
if (isset($_GET['years']))
{
   $get['years'] = $_GET['years'];
}

//dump($_GET);
$get['page'] = !empty($_GET['page']) ? Validateur::validateUrlQueryValue($_GET['page'], "int", 1) : 1;
$results_per_page = 20;


$mots = trim($get['mots']);
$mots = mb_strtolower($mots);
$mots = str_replace("+", " ", $mots);
$mots = str_replace("\"", " ",$mots);
$mots = str_replace(",", " ", $mots);
$mots = str_replace(":", " ", $mots);
$tab_tous_mots = explode(" ", $mots);

$mots_vides = Utils::listFileToArray(__ROOT__."/resources/stopwords_list.txt");
$tab_mots_sans_les_mots_vides = array_values(array_diff($tab_tous_mots, $mots_vides));
$tab_mots_sans_les_mots_vides = array_filter($tab_mots_sans_les_mots_vides, function($v)
{
    return (mb_strlen($v) > 1);
});

$nb_mots_sans_les_mots_vides = count($tab_mots_sans_les_mots_vides);

if ($nb_mots_sans_les_mots_vides === 0)
{
    header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request"); echo "Veuillez saisir un texte pertinent à rechercher"; exit;
}

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
  s.nom AS s_nom,
    (
      ## search exact : +disco
      MATCH(e.titre) AGAINST(? IN BOOLEAN MODE) * 3 +
      MATCH(e.nomLieu) AGAINST(? IN BOOLEAN MODE) * 2 +
      MATCH(l.nom) AGAINST(? IN BOOLEAN MODE) * 2 +
      MATCH(e.description) AGAINST(? IN BOOLEAN MODE) * 2 +

      # search prefix : disco*
      MATCH(e.titre) AGAINST(? IN BOOLEAN MODE) * 2 +
      MATCH(e.nomLieu) AGAINST(? IN BOOLEAN MODE) * 1 +
      MATCH(l.nom) AGAINST(? IN BOOLEAN MODE) * 1 +
      MATCH(e.description) AGAINST(? IN BOOLEAN MODE) * 1

    ) AS score

FROM evenement e
JOIN localite loc ON e.localite_id = loc.id
LEFT JOIN lieu l ON e.idLieu = l.idLieu
LEFT JOIN localite lloc ON l.localite_id = lloc.id
LEFT JOIN salle s ON e.idSalle = s.idSalle
WHERE
    e.statut NOT IN ('inactif', 'propose') AND (
    MATCH(e.titre) AGAINST(? IN BOOLEAN MODE) OR MATCH(e.nomLieu) AGAINST(? IN BOOLEAN MODE) OR MATCH(e.description) AGAINST(? IN BOOLEAN MODE) OR MATCH(l.nom) AGAINST(? IN BOOLEAN MODE) )";

// v1 only search prefix
//$sql_params = array_fill(0, 8, implode(' ', array_map(fn($t) => $t . '*', $tab_mots_sans_les_mots_vides)));

$search_exact = implode(' ', array_map(fn($t) => "+" . $t, $tab_mots_sans_les_mots_vides));
$search_prefix = implode(' ', array_map(fn($t) => $t . '*', $tab_mots_sans_les_mots_vides));

$sql_params = [...array_fill(0, 5, $search_exact), ...array_fill(0, 7, $search_prefix)];


$sql_periode_operator = ">=";
if ($get['periode'] == "ancien")
{
    $sql_periode_operator = "<";
}
$sql_select .= " AND e.dateEvenement $sql_periode_operator '" . $glo_auj . "'";

if (!empty($get['years']))
{
    $sql_select .= " AND YEAR(e.dateEvenement) = ? ";
    $sql_params[] = $get['years'];
}

$sql_select .= ' ORDER BY ' . (($get['tri'] == "dateAjout" || $get['tri'] == "dateEvenement") ? "e." . $get['tri'] : 'score') . ' DESC'.($get['tri'] != "dateEvenement" ? ", e.dateEvenement DESC" : '');
$sql_select .= " LIMIT " . (int) (($get['page'] - 1) * $results_per_page) . ", " . (int) $results_per_page;
//dump($sql_params);
$stmt = $connectorPdo->prepare($sql_select);
$stmt->execute($sql_params);
$page_results = $stmt->fetchAll(PDO::FETCH_ASSOC);


$sql_params_all = array_fill(0, 4, $search_prefix);
$sql_select_all =
    "SELECT count(*) AS nb
FROM evenement e
LEFT JOIN lieu l ON e.idLieu = l.idLieu
WHERE
    e.statut NOT IN ('inactif', 'propose') AND (
    MATCH(e.titre) AGAINST(? IN BOOLEAN MODE) OR MATCH(e.nomLieu) AGAINST(? IN BOOLEAN MODE) OR MATCH(e.description) AGAINST(? IN BOOLEAN MODE) OR MATCH(l.nom) AGAINST(? IN BOOLEAN MODE) )";

$sql_periode_operator = ">=";
if ($get['periode'] == "ancien")
{
    $sql_periode_operator = "<";
}
$sql_select_all .= " AND e.dateEvenement $sql_periode_operator '" . $glo_auj . "'";

if (!empty($get['years']))
{
    $sql_select_all .= " AND YEAR(e.dateEvenement) = ? ";
    $sql_params_all[] = $get['years'];
}

//echo $sql_select_all;

$stmtAll = $connectorPdo->prepare($sql_select_all);
$stmtAll->execute($sql_params_all);
$all_results_nb = $stmtAll->fetchColumn();

$logger->log('global', 'activity', "[recherche] \"" . urlencode($get['mots']) .  "\" with " . $all_results_nb . " events found in " . $get['periode'] . " (".$get['years'].") sorted by " . $get['tri'] . ", page " . $get['page'], Logger::GRAN_YEAR);

// prepare mots to be transmitted in links (menus order, filters, pagination)
$get['mots'] = urlencode($get['mots']);

$page_titre = "Rechercher des événements " . strtolower($tab_menu_periodes[$get['periode']]) . " par " . strtolower($tab_menu_tri[$get['tri']]);
include("../_header.inc.php");

$agenda_years = range((int)date("Y"), Evenement::AGENDA_START_YEAR);
?>

<main id="contenu" class="colonne rechercher">

	<header id="entete_contenu">
        <h1>Rechercher des événements pour <em><?= sanitizeForHtml($mots) ?></em></h1>
        <?php // HtmlShrink::getMenuRegions($glo_regions, $get); ?>

        <!-- menu tous | futurs | anciens -->
        <ul id="menu_periode">
            <?php foreach ($tab_menu_periodes as $k => $label) : ?>
                <li class="<?= $k ?><?php if ($get['periode'] == $k) : ?> ici<?php endif; ?>">
                    <a href="?<?= Utils::urlQueryArrayToString($get, ['periode', 'page', 'years']) ?>&amp;periode=<?= $k ?>"><?= $label ?></a>
                </li>
            <?php endforeach; ?>
            <div class="spacer"></div>
        </ul>
		<div class="spacer"></div>
	</header>

    <div id="res_recherche">

        <?php if ($get['periode'] == 'ancien') : ?>
            <form id="years-select" action="" method="get">
                <input type="hidden" name="mots" value="<?= urldecode($get['mots']) ?>">
                <input type="hidden" name="periode" value="<?= sanitizeForHtml($get['periode']) ?>">
                <input type="hidden" name="tri" value="<?= sanitizeForHtml($get['tri']) ?>">
                <label for="years">Année</label>
                <select name="years" id="years" class="js-select2 js-auto-submiter" style="min-width:100px">
                    <?php foreach ($agenda_years as $year): ?>
                        <option value="<?= $year ?>" <?php if ($year == $get['years']) : ?>selected<?php endif; ?>><?= $year ?></option>
                    <?php endforeach; ?>
                </select>
<!--                <button type="submit" style="margin-top:2px">OK</button>-->
            </form>
        <?php endif; ?>

        <?php if ($all_results_nb > 0) : ?>

            <h2 class="res"><strong><?= (int)$all_results_nb ?></strong> événement<?= $all_results_nb > 1 ? "s" : "" ?> trouvé<?= $all_results_nb > 1 ? "s" : "" ?></h2>

            <div>
                <!-- menu tri pertinence | date ajout | début -->
                <ul id="menu_tri">
                    <li style="margin-right:5px"><i class="fa fa-sort-amount-asc" aria-hidden="true"></i></li>
                    <?php foreach ($tab_menu_tri as $k => $label) : ?>
                        <li class="<?= $k ?><?php if ($get['tri'] == $k) : ?> ici<?php endif; ?>">
                            <a href="?<?= Utils::urlQueryArrayToString($get, ['tri', 'page']) ?>&amp;tri=<?= $k ?>"><?= $label ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="spacer"><!-- --></div>
            </div>

            <div class="spacer"></div>

            <?= HtmlShrink::getPaginationString($all_results_nb, $get['page'], $results_per_page, 1, basename(__FILE__), "?" . Utils::urlQueryArrayToString($get, "page") . "&amp;page=") ?>

            <table>
                <tbody>
                    <?php
                    foreach ($page_results as $tab_even) :
                        $even_periode = match (true) {
                            $tab_even['e_dateEvenement'] > $glo_auj_6h => "futur",
                            $tab_even['e_dateEvenement'] == $glo_auj_6h => "auj",
                            default => 'ancien',
                        };
                        $even_lieu = Evenement::getLieu($tab_even);
                        ?>
                        <tr class="<?= $even_periode ?>">
                            <td class="desc_even">
                                <h3><a href="evenement.php?idE=<?= (int) $tab_even['e_idEvenement'] ?>"><?= sanitizeForHtml($tab_even['e_titre']) ?></a></h3>
                                <p><?= $glo_tab_genre[$tab_even['e_genre']] ?></p>
                            </td>
                            <td><?= Lieu::getLinkNameHtml($even_lieu['nom'], $even_lieu['idLieu'], $even_lieu['salle']) ?></td>
                            <td class="date"><a href="/index.php?courant=<?= sanitizeForHtml($tab_even['e_dateEvenement']); ?>"><?= date_fr($tab_even['e_dateEvenement'], 'annee') ?></a></td>
                            <?php if ((isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] == UserLevel::SUPERADMIN)) : ?>
                            <td><?= round($tab_even['score'], 5) ?></td>
                            <?php endif; ?>
                            <?php if ($authorization->isPersonneAllowedToEditEvenement($_SESSION, $tab_even)) : ?>
                                <td><a href="/evenement-edit.php?action=editer&amp;idE=<?= (int) $tab_even['e_idEvenement'] ?>"><?= $iconeEditer; ?></a></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <?= HtmlShrink::getPaginationString($all_results_nb, $get['page'], $results_per_page, 1, basename(__FILE__), "?" . Utils::urlQueryArrayToString($get, "page") . "&amp;page="); ?>

            <?php
            else:

                HtmlShrink::msgInfo("Pas d'événement trouvé pour <em>".sanitizeForHtml($mots)."</em>");

            endif;
            ?>
    </div> <!-- #res_recherche -->

</main>

<div id="colonne_gauche" class="colonne">
    <?php include("../event/_navigation_calendrier.inc.php"); ?>
</div>

<div id="colonne_droite" class="colonne"></div>

<?php include("../_footer.inc.php"); ?>
