<?php

global $connector, $glo_regions, $glo_auj, $iconeEditer, $glo_auj_6h;

require_once("app/bootstrap.php");

use Ladecadanse\Utils\Logger;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Utils\Utils;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Evenement;
use Ladecadanse\Lieu;
use Ladecadanse\UserLevel;


$tab_menu_tri = ["pertinence" => "Pertinence", "dateEvenement" => "Date", "dateAjout" => "Date d'ajout"];
$tab_menu_periodes = ["futur" => "Prochains", "ancien" => "Passés"]; //, "tous" => "Tous"

$get = [];

$get['mots'] = $_GET['mots'];
if (empty($get['mots']) || !empty($_GET['name_as']))
{
    header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request"); echo "Veuillez saisir un texte à rechercher"; exit;
}

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
$tab_mots_sans_les_mots_vides = array_filter($tab_mots_sans_les_mots_vides, function($v) {
    return (mb_strlen($v) > 1);
});

$nb_mots_sans_les_mots_vides = count($tab_mots_sans_les_mots_vides);

if ($nb_mots_sans_les_mots_vides === 0)
{
    header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request"); echo "Veuillez saisir un texte pertinent à rechercher"; exit;
}

// legacy method with LIKE (September 2007 - July 2025)
//$champs_a_rechercher = ["titre", "nomLieu", "description"];
//
//$sql_select = "
//    SELECT SQL_CALC_FOUND_ROWS
//    idEvenement, idPersonne, titre, idLieu, idSalle, nomLieu, description, genre, dateEvenement, flyer, prix, horaire_debut, horaire_complement, dateAjout
//    FROM evenement
//    JOIN localite on evenement.localite_id = localite.id
//    WHERE statut NOT IN ('inactif', 'propose')  AND ( ";
//    // USELESS REGION FILTERING DISABLED: AND (region IN ('" . $connector->sanitize($_SESSION['region']) . "', 'rf', 'hs') OR FIND_IN_SET ('". $connector->sanitize($_SESSION['region']) ."', localite.regions_covered))  ";
//
//$tabChampsTermesSql = [];
//foreach ($champs_a_rechercher as $champ)
//{
//    $tabChampLikes = [];
//    foreach ($tab_mots_sans_les_mots_vides as $mot)
//    {
//        $tabChampLikes[] = $connector->sanitize($champ) . " LIKE '%" . $connector->sanitize($mot) . "%'";
//    }
//
//    $tabChampsTermesSql[] = "(".implode(($champ == "nomLieu") ? " OR " : " AND ", $tabChampLikes).") ";
//}
//
//$sql_select .= implode(" OR ", $tabChampsTermesSql);
//$sql_select .= ") ";
// END legacy method with LIKE (September 2007 - July 2025)

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
      MATCH(e.titre) AGAINST(? IN NATURAL LANGUAGE MODE) * 5 +
      MATCH(e.nomLieu) AGAINST(? IN NATURAL LANGUAGE MODE) * 3 +
      MATCH(l.nom) AGAINST(? IN NATURAL LANGUAGE MODE) * 3 +
      MATCH(e.description) AGAINST(? IN NATURAL LANGUAGE MODE) * 1
    ) AS score

FROM evenement e
JOIN localite loc ON e.localite_id = loc.id
LEFT JOIN lieu l ON e.idLieu = l.idLieu
LEFT JOIN localite lloc ON l.localite_id = lloc.id
LEFT JOIN salle s ON e.idSalle = s.idSalle
WHERE
    e.statut NOT IN ('inactif', 'propose') AND (
    MATCH(e.titre) AGAINST(? IN NATURAL LANGUAGE MODE) OR MATCH(e.nomLieu) AGAINST(? IN NATURAL LANGUAGE MODE) OR MATCH(e.description) AGAINST(? IN NATURAL LANGUAGE MODE) OR MATCH(l.nom) AGAINST(? IN NATURAL LANGUAGE MODE) )";

if ($get['periode'] != "tous")
{
    $sql_periode_operator = ">=";
    if ($get['periode'] == "ancien")
    {
        $sql_periode_operator = "<";
    }
    $sql_select .= " AND e.dateEvenement $sql_periode_operator '" . $glo_auj . "'";
}

$sql_select .= ' ORDER BY ' . (($get['tri'] == "dateAjout" || $get['tri'] == "dateEvenement") ? "e." . $get['tri'] : 'score') . ' DESC';
$sql_select .= " LIMIT " . (int) (($get['page'] - 1) * $results_per_page) . ", " . (int) (($get['page'] - 1) * $results_per_page + $results_per_page);


//echo $sql_select;
// legacy method with LIKE (September 2007 - July 2025)
//$req_even = $connector->query($sql_select);
//$nb_even = $connector->getNumRows($req_even);
// END legacy method with LIKE (September 2007 - July 2025)

$stmt = $connectorPdo->prepare($sql_select);
$stmt->execute(array_fill(0, 8, implode(' ', $tab_mots_sans_les_mots_vides)));
$page_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
//dump($page_results);
//if ($get['tri'] == "pertinence")
//{
//    foreach($page_results_without_weighting as $r)
//    {
//
//    }
//    $page_results;
//}



$sql_select_all =
    "SELECT count(*) AS nb
FROM evenement e
LEFT JOIN lieu l ON e.idLieu = l.idLieu
WHERE
    e.statut NOT IN ('inactif', 'propose') AND (
    MATCH(e.titre) AGAINST(? IN NATURAL LANGUAGE MODE) OR MATCH(e.nomLieu) AGAINST(? IN NATURAL LANGUAGE MODE) OR MATCH(e.description) AGAINST(? IN NATURAL LANGUAGE MODE) OR MATCH(l.nom) AGAINST(? IN NATURAL LANGUAGE MODE) )";

if ($get['periode'] != "tous")
{
    $sql_periode_operator = ">=";
    if ($get['periode'] == "ancien")
    {
        $sql_periode_operator = "<";
    }
    $sql_select_all .= " AND e.dateEvenement $sql_periode_operator '" . $glo_auj . "'";
}
//echo $sql_select_all;
$stmtAll = $connectorPdo->prepare($sql_select_all);
$stmtAll->execute(array_fill(0, 4, implode(' ', $tab_mots_sans_les_mots_vides)));
$all_results_nb = $stmtAll->fetchColumn();
//$page_results2 = $stmtAll->fetchAll(PDO::FETCH_ASSOC);
// dump($all_results_nb);
//$all_results_nb = count($page_results2);
// legacy method with LIKE (September 2007 - July 2025)
//if ($get['tri'] == "pertinence")
//{
//    $events_with_score = [];
//    while ($tab_even = $connector->fetchArray($req_even))
//    {
//        $events_with_score[$tab_even['idEvenement']] = 0;
//        /**
//        $even_points[$p][0] = $tab_even['idEvenement'];
//        $even_points[$p][1] = 0;
//        print_r($tab_even);
//        echo "<br>";
//        */
//        for ($i = $nb_mots_sans_les_mots_vides; $i >= 1; $i--)
//        {
//            $dep_max = $nb_mots_sans_les_mots_vides - $i;
//            for ($m = 0; $m <= $dep_max; $m++)
//            {
//                $sous_phrase = "";
//                for ($n = $m; $n < $m + $i; $n++)
//                {
//                    $sous_phrase .= $tab_mots_sans_les_mots_vides[$n]." ";
//                }
//                $sous_phrase = mb_substr($sous_phrase, 0, -1);
//
//                $nb_titre = 0;
//                $nb_nomLieu = 0;
//                $nb_desc = 0;
//
//                if (mb_strlen($sous_phrase) > 0)
//                {
//                    $nb_titre = mb_substr_count(mb_strtolower((string) $tab_even['titre']), $sous_phrase);
//                    $nb_nomLieu = mb_substr_count(mb_strtolower((string) $tab_even['nomLieu']), $sous_phrase);
//                    $nb_desc = mb_substr_count(mb_strtolower((string) $tab_even['description']), $sous_phrase);
//                }
//
//                $events_with_score[$tab_even['idEvenement']] += ($nb_titre * $i) * 5;
//                $events_with_score[$tab_even['idEvenement']] += ($nb_nomLieu * $i) * 5;
//                $events_with_score[$tab_even['idEvenement']] += $nb_desc * $i;
//            }
//        }
//    }
//    //$tab_res = $connector->fetchAll($req_even);
//    arsort($events_with_score);
//}
// END legacy method with LIKE (September 2007 - July 2025)


$logger->log('global', 'activity', "[recherche] \"" . urlencode($get['mots']) .  "\" with " . $all_results_nb . " events found in " . $get['periode'] . " sorted by " . $get['tri'] . ", page " . $get['page'], Logger::GRAN_YEAR);


// prepare mots to be transmitted in links (menus order, filters, pagination)
$get['mots'] = urlencode($get['mots']);

$page_titre = "Rechercher des événements " . strtolower($tab_menu_periodes[$get['periode']]) . " par " . strtolower($tab_menu_tri[$get['tri']]);
include("_header.inc.php");
?>

<main id="contenu" class="colonne rechercher">

	<header id="entete_contenu">
        <h1 style="float:left; width: 50%">Rechercher des événements pour <em><?= sanitizeForHtml($mots) ?></em></h1>
        <?php // HtmlShrink::getMenuRegions($glo_regions, $get); ?>

        <!-- menu tous | futurs | anciens -->
        <ul id="menu_periode" style="float:right; width: 49%">
            <?php foreach ($tab_menu_periodes as $k => $label) : ?>
                <li class="<?= $k ?><?php if ($get['periode'] == $k) : ?> ici<?php endif; ?>">
                    <a href="?<?= Utils::urlQueryArrayToString($get, ['periode', 'page']) ?>&amp;periode=<?= $k ?>"><?= $label ?></a>
                </li>
            <?php endforeach; ?>
            <div class="spacer"></div>
        </ul>
		<div class="spacer"></div>
	</header>

    <div id="res_recherche">

        <?php if ($all_results_nb > 0) : ?>
            <div>
                <!-- menu tri pertinence | date ajout | début -->
                <ul id="menu_tri" > <!-- style="float:right; width: 45%" -->
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

            <h2 class="res"><strong><?= (int)$all_results_nb ?></strong> événement<?= $all_results_nb > 1 ? "s" : "" ?> trouvé<?= $all_results_nb > 1 ? "s" : "" ?></h2>

            <?= HtmlShrink::getPaginationString($all_results_nb, $get['page'], $results_per_page, 1, basename(__FILE__), "?" . Utils::urlQueryArrayToString($get, "page") . "&amp;page=") ?>

            <table>
                <tbody>
                    <?php
                    // legacy method with LIKE (September 2007 - July 2025)
        //            $no_score = 0;
        //            if ($get['tri'] == "pertinence")
        //            {
        //                echo HtmlShrink::getSearchByPertinenceList($events_with_score, $no_score, $results_per_page, '', $get);

        //            } //tri Pertinence
        //            else
        //            {
                    // END legacy method with LIKE (September 2007 - July 2025)
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
                                <h3><a href="/evenement.php?idE=<?= (int) $tab_even['e_idEvenement'] ?>"><?= sanitizeForHtml($tab_even['e_titre']) ?></a></h3><?= sanitizeForHtml($tab_even['e_genre']) ?>
                            </td>
                            <td><?= Lieu::getLinkNameHtml($even_lieu['nom'], $even_lieu['idLieu'], $even_lieu['salle']) ?></td>
                            <td><?php if ($authorization->isPersonneAllowedToEditEvenement($_SESSION, $tab_even)) : ?>
                                <a href="/evenement-edit.php?action=editer&amp;idE=<?= (int) $tab_even['e_idEvenement'] ?>"><?= $iconeEditer; ?></a>
                                <?php endif; ?>
                            </td>
                            <td><a href="index.php?courant=<?= sanitizeForHtml($tab_even['e_dateEvenement']); ?>"><?= date_fr($tab_even['e_dateEvenement'], 'annee') ?></a></td>
                            <?php if ((isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] == UserLevel::SUPERADMIN)) : ?>
                            <td><?= round($tab_even['score'], 5) ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach;
                    // legacy method with LIKE (September 2007 - July 2025)
                    // }
                    // END legacy method with LIKE (September 2007 - July 2025)
                    ?>
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
    <?php include("_navigation_calendrier.inc.php"); ?>
</div>

<!--<div id="colonne_droite" class="colonne"></div>-->

<?php include("_footer.inc.php"); ?>
