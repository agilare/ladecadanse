<?php

require_once("../app/bootstrap.php");

use Ladecadanse\UserLevel;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Utils\Utils;
use Ladecadanse\Personne;
use Ladecadanse\EvenementRenderer;

if (!$videur->checkGroup(UserLevel::ADMIN))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
	header("Location: /user-login.php"); die();
}

// admin by region : suspended
//$_SESSION['region_admin'] = '';
//if ($_SESSION['Sgroupe'] >= UserLevel::ADMIN && !empty($_SESSION['Sregion']) && in_array($get['element'], ['lieu']))
//{
//    $_SESSION['region_admin'] = $_SESSION['Sregion'];
//}
//
//$sql_where_region = '';
//$titre_region = '';
//if (!empty($_SESSION['region_admin']))
//{
//    $sql_where_region = " WHERE region='".$connector->sanitize($_SESSION['region_admin'])."' ";
//    $titre_region = " - ".$glo_regions[$_SESSION['region_admin']];
//}


// search
$_SESSION['user_prefs_users_terme'] ??= '';
if (isset($_GET['terme']))
{
   $_SESSION['user_prefs_users_terme'] = $_GET['terme'];
}
$filters['terme'] = $_SESSION['user_prefs_users_terme'];

// order
$_SESSION['user_prefs_users_order_by'] ??= 'dateAjout';
$tab_order_by = ["pseudo", "groupe", "statut", "dateAjout", "date_derniere_modif", "last_login"];
if (isset($_GET['order_by']) && in_array($_GET['order_by'], $tab_order_by))
{
   $_SESSION['user_prefs_users_order_by'] = $_GET['order_by'];
}

$_SESSION['user_prefs_users_order_asc'] ??= 'desc';
if (isset($_GET['order_asc']))
{
   $_SESSION['user_prefs_users_order_asc'] = $_GET['order_asc'];
}

$get = [];

// pagination
$get['page'] = !empty($_GET['page']) ? Validateur::validateUrlQueryValue($_GET['page'], "int", 1) : 1;

$_SESSION['user_prefs_users_nblignes'] ??= 20;
if (isset($_GET['nblignes']) && Validateur::validateUrlQueryValue($_GET['nblignes'], "int", 0))
{
   $_SESSION['user_prefs_users_nblignes'] = $_GET['nblignes'];
}

//dump($_SESSION);

$users_page_current = Personne::getPersonnes($filters, $_SESSION['user_prefs_users_order_by'], $_SESSION['user_prefs_users_order_asc'], $get['page'], $_SESSION['user_prefs_users_nblignes']);
//dump($users_page_current);
$users_page_all = Personne::getPersonnes($filters, $_SESSION['user_prefs_users_order_by'], $_SESSION['user_prefs_users_order_asc']);
$all_results_nb = count($users_page_all);


// TODO: nb events and date latest event added
//$sql_select = "SELECT
//
//    idPersonne, pseudo, email, groupe, affiliation, statut, DATE(dateAjout) AS dateAjout, date_derniere_modif, DATE(last_login) AS last_login
//
//    FROM personne p
//    LEFT JOIN personne_organisateur po ON p.idPersonne = po.idPersonne
//    LEFT JOIN organisateur o ON po.idOrganisateur = o.idOrganisateur
//    LEFT JOIN affiliation a ON p.idPersonne = a.idPersonne AND a.genre = 'lieu'
//    LEFT JOIN lieu l ON a.idAffiliation = l.idLieu
//    LEFT JOIN evenement e ON e.idEvenement = (
//        SELECT MAX(e2.idEvenement)
//        FROM evenement e2
//        WHERE e2.idPersonne = p.idPersonne
//    )
//    WHERE
//    p.dateAjout >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
//    GROUP BY p.idPersonne
//    ORDER BY p.dateAjout DESC, e.dateAjout DESC LIMIT 100";
//
////echo $sql_select;
//$stmt = $connectorPdo->prepare($sql_select);
//$stmt->execute();
//$page_results = $stmt->fetchAll(PDO::FETCH_GROUP);


// TODO: from users ids of this page build an array of their lieux and organizers
//$tab_users_ids = array_column($tab_users_ids, 'idPersonne');
//
//$users_events = [];
//if (!empty($tab_users_ids))
//{
//    list($usersIdsInClause, $usersTodayIdsParams) = $connectorPdo->buildInClause('e.idPersonne', $tab_users_ids);
//
//    $stmt = $connectorPdo->prepare("SELECT dateAjout FROM evenement where $eventsTodayIdsInClause
//    ORDER BY idevenement DESC");
//
//    $stmt->execute($usersTodayIdsParams);
//
//    $users_events = $stmt->fetchAll(PDO::FETCH_GROUP);
//
////    foreach ($tab_orgas AS $eo)
////    {
////        $tab_events_today_in_region_orgas[$eo['idEvenement']][] = [
////            'idOrganisateur' => $eo['o_idOrganisateur'],
////            'nom' => $eo['o_nom'],
////            'url' => $eo['o_URL']
////        ];
////    }
//}


$col_fields = [
    "pseudo" => "Pseudo",
    "groupe" => "Groupe",
    "affiliations" => "Affiliations",
    "nbeven" => "Nb even",
    "date_dern_even" => "Date dern. éven.",
    "statut" => "Statut",
    "dateAjout" => "Création",
    "last_login" => "Dern. login"];

$page_titre = "Gérer les utilisateurs";
require_once '../_header.inc.php';
?>

<main id="contenu" class="colonne">

	<header id="entete_contenu">
		<h1>Gérer les utilisateurs</h1>
        <div class="spacer"></div>
	</header>

    <!-- filtres, pagination, tableau de données -->
    <section id="default">

        <div id="filters">

            <form method="get" action="" id="ajouter_editer" style="float:left;width:40%;">
                <input type="text" name="terme" value="<?= sanitizeForHtml($filters['terme']) ?>" placeholder="pseudo ou email" size="20" />
                <input type="submit" name="submit" value="Filtrer" />
            </form>

            <ul class="menu_nb_res">
                <?php foreach ($tab_nblignes as $nb) : ?>
                    <li <?php if ($nb == $_SESSION['user_prefs_users_nblignes']) : ?>class="ici"<?php endif; ?>><a href="?<?= Utils::urlQueryArrayToString($get, ['nblignes', 'page']) ?>&amp;nblignes=<?= (int)$nb ?>"><?= (int)$nb ?></a></li>
                <?php endforeach; ?>

            </ul>
            <div class="spacer"></div>
        </div> <!-- #filters -->

        <?= HtmlShrink::getPaginationString(
            $all_results_nb, $get['page'],
            $_SESSION['user_prefs_users_nblignes'],
            1,
            "",
            "?page=") ?>

        <table id="ajouts">
            <tr>
                <?php foreach ($col_fields as $field => $label) : ?>

                    <th <?php if ($field == $_SESSION['user_prefs_users_order_by']) : ?>class="ici"<?php endif; ?>>

                        <?php if (in_array($field, $tab_order_by)) : ?>
                            <?php if ($field == $_SESSION['user_prefs_users_order_by']) : ?>
                                <a href="?order_asc=<?php if ($_SESSION['user_prefs_users_order_asc'] == 'asc' ) : ?>desc<?php else: ?>asc<?php endif; ?>&amp;page=<?= (int) $get['page'] ?>"><?= $icone[$_SESSION['user_prefs_users_order_asc']]; ?></a>
                            <?php endif; ?>
                            <a href="?order_by=<?= $field ?>&amp;page=<?= (int) $get['page'] ?>"><?= $label ?></a>
                        <?php else: ?>
                            <?= $label ?>
                        <?php endif; ?>
                    </th>
                <?php endforeach; ?>
                <th></th>
            </tr>

            <?php foreach ($users_page_current as $u) : ?>
            <tr>
                <td style="width:20%">
                    <a href="/user.php?idP=<?= (int) $u['idPersonne'] ?>"><?= sanitizeForHtml($u['pseudo']) ?></a>
                    <br><small><a href="mailto:<?= sanitizeForHtml($u['email']) ?>"><?= sanitizeForHtml($u['email']) ?></a></small>
                </td>
                <td><?= $u['groupe'] ?></td>
                <td><?= $u['affiliation'] ?></td>
                <td><?= "-" ?></td>
                <td><?= "-" ?></td>
                <td><?= EvenementRenderer::$iconStatus[$u['statut']] ?></td>
                <td><?= date_iso2app($u['dateAjout']) ?></td>
                <td><?= date_iso2app($u['last_login']) ?></td>
                <td><a href="/user-edit.php?action=editer&amp;idP=<?= (int)$u['idPersonne'] ?>"><?= $iconeEditer ?></a></td>
            </tr>
            <?php endforeach; ?>

        </table>

        <?= HtmlShrink::getPaginationString($all_results_nb, $get['page'], $_SESSION['user_prefs_users_nblignes'], 1, "", "?page="); ?>

    </section>

</main>

<div id="colonne_gauche" class="colonne">
</div>

<div class="spacer"><!-- --></div>
<?php
include("../_footer.inc.php");
?>
