<?php

require_once("../../app/bootstrap.php");

use Ladecadanse\UserLevel;
use Ladecadanse\Utils\DateHelper;
use Ladecadanse\Utils\QueryParamValidator;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Personne;
use Ladecadanse\EvenementRenderer;

if (!$authorization->checkGroup(UserLevel::ADMIN))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
	header("Location: /user/login.php"); die();
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

// status, active by default; '' = all (values of the personne.statut enum)
$tab_statuts = ['actif' => 'Actifs', 'inactif' => 'Inactifs', 'demande' => 'Demandes', '' => 'Tous'];
$_SESSION['user_prefs_users_statut'] ??= 'actif';
if (isset($_GET['statut']) && array_key_exists($_GET['statut'], $tab_statuts))
{
   $_SESSION['user_prefs_users_statut'] = $_GET['statut'];
}
$filters['statut'] = $_SESSION['user_prefs_users_statut'];

// order
$_SESSION['user_prefs_users_order_by'] ??= 'dateAjout';
$fields_to_order_by = ["pseudo", "groupe", "statut", "dateAjout", "date_derniere_modif", "last_login"];
if (isset($_GET['order_by']) && in_array($_GET['order_by'], $fields_to_order_by))
{
   $_SESSION['user_prefs_users_order_by'] = $_GET['order_by'];
}

$_SESSION['user_prefs_users_order_dir'] ??= 'desc';
if (!empty($_GET['order_dir']) && QueryParamValidator::isAcceptedUrlQueryValue($_GET['order_dir'], "alpha_numeric"))
{
   $_SESSION['user_prefs_users_order_dir'] = $_GET['order_dir'];
}

$get = [];

// pagination
$get['page'] = QueryParamValidator::pageFromQuery($_GET['page'] ?? '');

$_SESSION['user_prefs_users_nblignes'] ??= $tab_nblignes[0];
if (!empty($_GET['nblignes']) && in_array($_GET['nblignes'], $tab_nblignes))
{
   $_SESSION['user_prefs_users_nblignes'] = $_GET['nblignes'];
}

//dump($_SESSION);

$users_page_all = Personne::getPersonnes($filters, $_SESSION['user_prefs_users_order_by'], $_SESSION['user_prefs_users_order_dir']);
$all_results_nb = count($users_page_all);
// TODO: calculate max page no according to all results no to avoid overflow (currently replaced by reset to page 1)
//$pers_total_page_max = ceil($num_pers_total / $nbLignes);
//if ($pers_total_page_max > 0 && $page > $pers_total_page_max)
//{
//  $page = $pers_total_page_max;
//}
$users_page_current = Personne::getPersonnes($filters, $_SESSION['user_prefs_users_order_by'], $_SESSION['user_prefs_users_order_dir'], $get['page'], $_SESSION['user_prefs_users_nblignes']);

$page_users_ids = array_column($users_page_current, 'idPersonne');

// TODO: from users ids of this page build an array of their lieux and organizers
//list($idsClause, $idsParams) = $connectorPdo->buildInClause('p.idPersonne', $tab_users_ids);
//$sql_select = "SELECT
//
//    p.idPersonne, pseudo, groupe, affiliation, p.email, p.dateAjout AS p_dateAjout,
//
//    o.idOrganisateur AS idO,
//    o.nom AS o_nom,
//
//    l.idLieu AS idL,
//    l.nom AS l_nom,
//
//    e.idEvenement AS idE,
//    e.titre AS e_titre
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
//    $idsClause
//    GROUP BY p.idPersonne";
//
////echo $sql_select;
//$stmt = $connectorPdo->prepare($sql_select);
//$stmt->execute($idsParams);
//$page_results = $stmt->fetchAll(PDO::FETCH_ASSOC);


// nb even added, date latest event added and latest event months count
list($idsClause, $idsParams) = $connectorPdo->buildInClause('e.idPersonne', $page_users_ids);
$sql = "
SELECT
    idPersonne,
    COUNT(e.idEvenement) AS nb_even,
    MAX(e.dateEvenement) AS latest_event_date,
    TIMESTAMPDIFF(MONTH, MAX(e.dateEvenement), CURDATE()) AS latest_event_months_nb,
    ROUND(COUNT(e.idEvenement) / COUNT(DISTINCT YEAR(e.dateEvenement)), 0) AS events_annual_avg
FROM evenement e
WHERE $idsClause AND e.statut NOT IN ('inactif', 'propose') GROUP BY idPersonne ORDER BY idPersonne ASC";
$stmt = $connectorPdo->prepare($sql);
$stmt->execute($idsParams);
$users_even = $stmt->fetchAll(PDO::FETCH_GROUP);

$users_orgas = [];
if (!empty($page_users_ids))
{
    list($idsClause, $idsParams) = $connectorPdo->buildInClause('po.idPersonne', $page_users_ids);
    $stmt = $connectorPdo->prepare("SELECT

    po.idPersonne AS idPersonne,
    o.idOrganisateur AS o_idOrganisateur,
    o.nom AS o_nom,
    o.URL AS o_URL

    FROM personne_organisateur po
    JOIN organisateur o ON po.idOrganisateur = o.idOrganisateur AND $idsClause
    ORDER BY nom DESC");

    $stmt->execute($idsParams);

    $tab_orgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //dump($tab_orgas);
    foreach ($tab_orgas AS $eo)
    {
        $users_orgas[$eo['idPersonne']][] = [
            'idOrganisateur' => $eo['o_idOrganisateur'],
            'nom' => $eo['o_nom'],
            'url' => $eo['o_URL']
        ];
    }
}

//dump($users_orgas);

$col_fields = [
    "pseudo" => "Pseudo",
    "groupe" => "Groupe",
    "affiliations" => "Affiliations",
    "nbeven" => "Nb évén.",
    "date_dern_even" => "Dern. évén.",
    "statut" => "Statut",
    "dateAjout" => "Création",
    "last_login" => "Dern. login"];

$page_titre = "Gérer les utilisateurs";
$extra_css = ["admin/tables"];
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

            <form method="get" action="" id="ajouter_editer" style="float:left;">
                <span class="search-field">
                    <input type="search" name="terme" value="<?= sanitizeForHtml($filters['terme']) ?>" placeholder="pseudo ou email" size="24" />
                    <button type="button" class="js-clear-search-field" aria-label="Vider et relancer la recherche" title="Vider et relancer la recherche"></button>
                </span>
                <select name="statut" aria-label="Statut">
                    <?php foreach ($tab_statuts as $statut => $libelle) : ?>
                    <option value="<?= $statut ?>"<?php if ($statut === $filters['statut']) : ?> selected="selected"<?php endif; ?>><?= $libelle ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="submit" name="submit" value="Filtrer" />
            </form>
            <div class="spacer"></div>
        </div> <!-- #filters -->

        <?php // le menu du nombre de lignes rejoint la ligne de la pagination, à l'autre bout ?>
        <div class="liste-barre">
            <?= HtmlShrink::getPaginationString(
                $all_results_nb, $get['page'],
                $_SESSION['user_prefs_users_nblignes'],
                1,
                "",
                "?page=") ?>

            <ul class="menu_nb_res">
                <?php foreach ($tab_nblignes as $nb) : ?>
                    <li <?php if ($nb == $_SESSION['user_prefs_users_nblignes']) : ?>class="ici"<?php endif; ?>><a href="?<?= HtmlShrink::urlQueryArrayToString($get, ['nblignes', 'page']) ?>&amp;nblignes=<?= (int)$nb ?>"><?= (int)$nb ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <table id="ajouts">
            <tr>
                <?php foreach ($col_fields as $field => $label) : ?>

                    <th <?php if ($field == $_SESSION['user_prefs_users_order_by']) : ?>class="ici"<?php endif; ?>>
                        <?php if (in_array($field, $fields_to_order_by)) : ?>
                            <a href="?order_by=<?= $field ?>&amp;page=<?= (int) $get['page'] ?>"><?= sanitizeForHtml($label) ?></a>
                            <?php if ($field == $_SESSION['user_prefs_users_order_by']) : ?>
                                <a href="?order_dir=<?php if ($_SESSION['user_prefs_users_order_dir'] == 'asc' ) : ?>desc<?php else: ?>asc<?php endif; ?>&amp;page=<?= (int) $get['page'] ?>"><?= $icone[$_SESSION['user_prefs_users_order_dir']]; ?></a>
                            <?php endif; ?>
                        <?php else: ?>
                            <?= sanitizeForHtml($label) ?>
                        <?php endif; ?>
                    </th>
                <?php endforeach; ?>
                <th></th>
            </tr>

            <?php foreach ($users_page_current as $u) : ?>

                <?php $ue = isset($users_even[$u['idPersonne']]) ? $users_even[$u['idPersonne']][0] : null; ?>
                <tr>
                    <td style="width:20%">
                        <?php // un compte sans nom d'utilisateur se désigne par son adresse : le lien serait sinon vide ?>
                        <a href="/user/dashboard.php?idP=<?= (int)$u['idPersonne'] ?>" style="font-size:1.1em"><?= sanitizeForHtml(Personne::displayName($u['pseudo'], $u['email'])) ?></a>
                        <br><a href="mailto:<?= sanitizeForHtml($u['email']) ?>"><?= sanitizeForHtml($u['email']) ?></a>
                    </td>
                    <td><span class="profil-groupe"><?= (int) $u['groupe'] ?><?= UserLevel::getLabel((int) $u['groupe']) !== '' ? ' &middot; ' . sanitizeForHtml(UserLevel::getLabel((int) $u['groupe'])) : '' ?></span></td>
                    <?php // lieu, texte libre puis organisateurs, dans l'ordre et avec les icônes de user/dashboard.php ?>
                    <td class="affiliations">
                        <?php if ($u['idLieu']) : ?>
                            <span class="profile-affiliation"><i class="fa fa-fw fa-map-marker" aria-hidden="true" title="Lieu"></i><span class="visually-hidden">Lieu : </span><a href="/lieu/lieu.php?idL=<?= (int) $u['idLieu'] ?>"><?= sanitizeForHtml($u['l_nom']) ?></a></span><br />
                        <?php endif; ?>
                        <?php if (!empty($u['affiliation'])) : ?>
                            <?= sanitizeForHtml($u['affiliation']) ?><br />
                        <?php endif; ?>
                        <?php foreach ($users_orgas[$u['idPersonne']] ?? [] as $orga) : ?>
                            <span class="profile-affiliation"><i class="fa fa-fw fa-users" aria-hidden="true" title="Organisateur"></i><span class="visually-hidden">Organisateur : </span><a href="/organisateur/organisateur.php?idO=<?= (int) $orga['idOrganisateur'] ?>"><?= sanitizeForHtml($orga['nom']) ?></a></span><br />
                        <?php endforeach; ?>
                    </td>
                    <td><?php if ($ue != null) : ?><?= (int)$ue['nb_even'] ?><br><span style="color:lightsteelblue">(<?= sanitizeForHtml($ue['events_annual_avg']) ?>/an)</span><?php endif; ?></td>
                    <td><?php if ($ue != null) : ?>
                        <?php if ($ue['latest_event_months_nb'] > Personne::LOW_ACTIVITY_MONTHS_NB) : ?>
                            <span style="<?php if ($ue['latest_event_months_nb'] > Personne::VERY_LOW_ACTIVITY_MONTHS_NB) : ?>color:red;<?php else : ?>color:orange;<?php endif ?>"><?= DateHelper::isoToMonthYearFr($ue['latest_event_date']) ?></span>
                            <?php else : ?>
                            <span style="color:lightsteelblue"><?= DateHelper::isoToMonthYearFr($ue['latest_event_date']) ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td><?= EvenementRenderer::$iconStatus[$u['statut']] ?></td>
                    <td><?= DateHelper::isoToMonthYearFr($u['dateAjout']) ?></td>
                    <?php // jamais connecté : NULL, que DateTime lirait comme aujourd'hui ; date nulle des comptes anciens ?>
                    <td><?= !empty($u['last_login']) && !str_starts_with($u['last_login'], '0000') ?DateHelper::isoToFr($u['last_login'], 'annee', showDayOfWeek: false, shortMonth: true) : '' ?></td>
                    <td><a href="/user/edit.php?idP=<?= (int)$u['idPersonne'] ?>" title="Modifier ce compte" aria-label="Modifier ce compte"><?= $iconeEditer ?></a></td>
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
