<?php

require_once("../app/bootstrap.php");

use Ladecadanse\UserLevel;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Utils\Utils;
use Ladecadanse\Utils\Text;
use Ladecadanse\EvenementRenderer;

if (!$videur->checkGroup(UserLevel::ADMIN))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
	header("Location: /user-login.php"); die();
}


$tab_listes = ["evenement" => "Événements", "organisateur" => "Organisateurs", "description" => "Descriptions", "personne" => "Personnes"];

$get = [];

if (!empty($_GET['element']))
{

	if (array_key_exists($_GET['element'], $tab_listes))
	{
		$get['element'] = $_GET['element'];
	}
	else
	{
		echo "element faux";
		exit;
	}
}
else
{
	$get['element'] = "evenements";
}

if ($get['element'] != 'personne')
{
    header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request"); exit;
}


$get['page'] = 1;
if (isset($_GET['page']))
{
	$get['page'] = Validateur::validateUrlQueryValue($_GET['page'], "int", 1);
}

$tab_tris = ["dateAjout", "date_ajout", "idOrganisateur", "date_derniere_modif", "statut", "date_debut", "date_fin", "id", "titre", "groupe", "pseudo", "idPersonne"];

$get['tri_gerer'] = "dateAjout";
if (isset($_GET['tri_gerer']))
{
	$get['tri_gerer'] = Validateur::validateUrlQueryValue($_GET['tri_gerer'], "enum", 1, $tab_tris);

}

$tab_ordre = ["asc", "desc"];
$get['ordre'] = "desc";
$ordre_inverse = "asc";
if (isset($_GET['ordre']))
{
	$get['ordre'] = Validateur::validateUrlQueryValue($_GET['ordre'], "enum", 1, $tab_ordre);
	if ($get['ordre'] == "asc")
	{
		$ordre_inverse = "desc";
	}
	else if ($get['ordre'] == "desc")
	{
		$ordre_inverse = "asc";
	}
}

$get['nblignes'] = 500;
if (!empty($_GET['nblignes']))
{
	$get['nblignes'] = Validateur::validateUrlQueryValue($_GET['nblignes'], "int", 1);
}


$get['terme'] = '';
if (!empty($_GET['terme']))
{
	$get['terme'] = $_GET['terme'];
}


$_SESSION['region_admin'] = '';
if ($_SESSION['Sgroupe'] >= UserLevel::ADMIN && !empty($_SESSION['Sregion']) && in_array($get['element'], ['lieu'])) {
    $_SESSION['region_admin'] = $_SESSION['Sregion'];
}


$sql_where_region = '';
$titre_region = '';
if (!empty($_SESSION['region_admin']))
{
    $sql_where_region = " WHERE region='".$connector->sanitize($_SESSION['region_admin'])."' ";
    $titre_region = " - ".$glo_regions[$_SESSION['region_admin']];
}

$sql_terme = '';
if (!empty($get['terme']))
    $sql_terme = " WHERE ( LOWER(pseudo) like LOWER('%".$connector->sanitize($get['terme'])."%') OR LOWER(email) like LOWER('%".$connector->sanitize($get['terme'])."%')) ";

$sql_pers = "
SELECT
    idPersonne, pseudo, email, groupe, affiliation, statut, DATE(dateAjout) AS dateAjout, date_derniere_modif, DATE(last_login) AS last_login
FROM personne
".$sql_terme."
ORDER BY ".$get['tri_gerer']." ".$get['ordre'];

$req_pers_total = $connector->query($sql_pers);
$num_pers_total = $connector->getNumRows($req_pers_total);

$pers_total_page_max = ceil($num_pers_total / $get['nblignes']);
if ($pers_total_page_max > 0 && $get['page'] > $pers_total_page_max)
    $get['page'] = $pers_total_page_max;

$sql_pers .= " LIMIT " . ((int) $get['page'] - 1) * (int) $get['nblignes'] . ", " . (int) $get['nblignes'];

$req_pers = $connector->query($sql_pers);

$th_lieu = ["pseudo" => "Pseudo",  "email" => "E-mail",  "groupe" => "Groupe",
    "affiliations" => "Affiliations",
    "nbeven" => "Nb even",
    "date_dern_even" => "Date dern. éven.",
    "dateAjout" => "Création",
    "statut" => "Statut",
    "last_login" => "Dern. login"];

$page_titre = "Gérer les ". lcfirst($tab_listes[$get['element']]);
require_once '../_header.inc.php';
?>

<main id="contenu" class="colonne">

	<header id="entete_contenu">
		<h1>Gérer les <?= $tab_listes[$get['element']].$titre_region; ?></h1>
        <div class="spacer"></div>
	</header>

    <section id="default">

        <div>
            <form method="get" action="" id="ajouter_editer" style="float:left;width:40%;">
                <input type="hidden" name="page" value="<?= (int)$get['page']; ?>" />
                <input type="hidden" name="nblignes" value="<?= (int)$get['nblignes']; ?>" />
                <input type="hidden" name="tri_gerer" value="<?= sanitizeForHtml($get['tri_gerer']) ?>" />
                <input type="hidden" name="element" value="<?= sanitizeForHtml($get['element']) ?>" />
                <input type="hidden" name="ordre" value="<?= sanitizeForHtml($get['ordre']) ?>" />
                <input type="text" name="terme" value="<?= sanitizeForHtml($get['terme']) ?>" placeholder="pseudo ou email" size="20" />
                <input type="submit" name="submit" value="Filtrer" />
            </form>

            <ul class="menu_nb_res">
                <?php foreach ($tab_nblignes as $nb) : ?>
                    <li <?php if ($nb == $get['nblignes']) : ?>class="ici"<?php endif; ?>><a href="/admin/gerer.php?<?= Utils::urlQueryArrayToString($get, "nblignes") ?>&amp;nblignes=<?= (int)$nb ?>"  ><?= (int)$nb ?></a></li>
                <?php endforeach; ?>
            </ul>
            <div class="spacer"></div>
        </div>

        <div class="spacer"></div>

        <?= HtmlShrink::getPaginationString($num_pers_total, $get['page'], $get['nblignes'], 1, "", "?element=" . $get['element'] . "&tri_gerer=" . $get['tri_gerer'] . "&ordre=" . $get['ordre'] . "&nblignes=" . (int) $get['nblignes'] . "&terme=" . $get['terme'] . "&page=") ?>

        <table id="ajouts">
            <tr>
                <?php foreach ($th_lieu as $att => $th) : ?>
                    <th <?php if ($att == $get['tri_gerer']) : ?>class="ici" <?php $icone[$get['ordre']]; endif; ?>>
                        <a href="?element=personne&amp;page=<?= (int) $get['page'] ?>&amp;tri_gerer=<?= $att ?>&amp;ordre=<?= $ordre_inverse ?>&amp;nblignes=<?= (int) $get['nblignes'] ?>"><?= $th ?></a>
                </th>
                <?php endforeach; ?>
                <th></th>
            </tr>

            <?php while ($tab_pers = $connector->fetchArray($req_pers)) : ?>
            <tr>
                <td style='width:20%'><a href="/user.php?idP=<?= (int) $tab_pers['idPersonne'] ?>"><?= sanitizeForHtml($tab_pers['pseudo']) ?></a></td>
                <td><a href="mailto:<?= sanitizeForHtml($tab_pers['email']) ?>"><?= sanitizeForHtml($tab_pers['email']) ?></a></td>
                <td><?= $tab_pers['groupe'] ?></td>
                <td><?= $tab_pers['affiliation'] ?></td>
                <td><?= "-" ?></td>
                <td><?= "-" ?></td>
                <td><?= date_iso2app($tab_pers['dateAjout']) ?></td>
                <td><?= EvenementRenderer::$iconStatus[$tab_pers['statut']] ?></td>
                <td><?= date_iso2app($tab_pers['last_login']) ?></td>
                <td><a href="/user-edit.php?action=editer&amp;idP=<?= (int)$tab_pers['idPersonne'] ?>"><?= $iconeEditer ?></a></td>
            </tr>
            <?php endwhile; ?>

        </table>

        <?= HtmlShrink::getPaginationString($num_pers_total, $get['page'], $get['nblignes'], 1, "", "?element=" . $get['element'] . "&tri_gerer=" . $get['tri_gerer'] . "&ordre=" . $get['ordre'] . "&nblignes=" . $get['nblignes'] . "&terme=" . $get['terme'] . "&page="); ?>

    </section>

</main>

<div id="colonne_gauche" class="colonne">
</div>

<div class="spacer"><!-- --></div>
<?php
include("../_footer.inc.php");
?>
