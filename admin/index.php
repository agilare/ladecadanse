<?php

global $connector;
require_once("../app/bootstrap.php");

use Ladecadanse\Utils\Text;
use Ladecadanse\UserLevel;
use Ladecadanse\EvenementRenderer;

if (!$videur->checkGroup(UserLevel::ADMIN)) {
	header("Location: /user-login.php"); die();
}

$_SESSION['region_admin'] = '';
if ($_SESSION['Sgroupe'] >= UserLevel::ADMIN && !empty($_SESSION['Sregion'])) {
    $_SESSION['region_admin'] = $_SESSION['Sregion'];
}

$sql_select = "SELECT

    DATE(p.dateAjout),
    p.idPersonne, pseudo, groupe, affiliation, p.email, p.dateAjout AS p_dateAjout,
    o.nom AS o_nom,
    l.nom AS l_nom

    FROM personne p
    LEFT JOIN personne_organisateur po ON p.idPersonne = po.idPersonne
    LEFT JOIN organisateur o ON po.idOrganisateur = o.idOrganisateur
    LEFT JOIN affiliation a ON p.idPersonne = a.idPersonne AND a.genre = 'lieu'
    LEFT JOIN lieu l ON a.idAffiliation = l.idLieu
    WHERE
    p.dateAjout >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)
    ORDER BY p.dateAjout DESC, p_dateAjout ASC LIMIT 100";

//echo $sql_select;
$stmt = $connectorPdo->prepare($sql_select);
$stmt->execute();
$page_results = $stmt->fetchAll(PDO::FETCH_GROUP);

//les dates au delà de 2 jours sont dispo pour être archivées
define("JOUR_LIM", 2);

$troisJoursAvant = date("Y-m-d H:i:s", time() - (3*86400));

$page_titre = "administration";
$extra_css = ["admin/index"];
require_once '../_header.inc.php';
?>

<main id="contenu" class="colonne">

    <header id="entete_contenu">
        <h1>Tableau de bord</h1>
        <div class="spacer"></div>
    </header>

    <div id="tableaux">

        <?php if ($_SESSION['Sgroupe'] < UserLevel::ADMIN) { ?>

        <h2 style="padding:0.4em 0">Inscriptions de ces 3 derniers jours</h2>

        <table summary="Dernières inscriptions">
            <thead>
                <tr>
                    <th>Heure</th>
                    <th>Pseudo</th>
                    <th>E-mail</th>
                    <th colspan="3">Affiliations (libre, lieu, organisateur)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($page_results as $date => $users) : ?>

                    <tr>
                        <td colspan="6" style="background:#f3f3f3"><?= date_fr($date) ?></td>
                    </tr>

                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= (new DateTime($u['p_dateAjout']))->modify('+1 day')->format("H:i")?></td>
                                <td>
                                    <a href="/user.php?idP=<?= (int)$u['idPersonne'] ?>"><?= sanitizeForHtml($u['pseudo']) ?></a>
                                    <?php if ($u['groupe'] != UserLevel::ACTOR) { echo "(".$u['groupe'].")"; } ?>
                                </td>
                                <td><?= $u['email'] ?></td>
                                <td><?= sanitizeForHtml($u['affiliation']) ?></td>
                                <td><?= sanitizeForHtml($u['l_nom']) ?></td>
                                <td><?= sanitizeForHtml($u['o_nom']) ?></td>
                            </tr>
                        <?php endforeach; ?>

                <?php endforeach; ?>
            </tbody>
        </table>

    <?php } ?>

    <?php if (!empty($_SESSION['region_admin'])) { ?>
        <h3><?php echo $glo_regions[$_SESSION['region_admin']]; ?></h3>
    <?php } ?>

    <h4 style="padding:0.4em 0">Événements ajoutés ces 3 derniers jours</h4>

    <?php

    $troisJoursAvant = date("Y-m-d H:i:s", time() - (3*86400));

    $sql_region = '';
    if (!empty( $_SESSION['region_admin']))
        $sql_region = " AND region='".$connector->sanitize( $_SESSION['region_admin'])."'";

    $sql_even = "SELECT idEvenement, idLieu, idPersonne, titre,
     dateEvenement, horaire_debut, horaire_fin, genre, nomLieu, adresse, statut, flyer, dateAjout
     FROM evenement WHERE dateAjout >= DATE_SUB(CURDATE(), INTERVAL 3 DAY) ".$sql_region."
     ORDER BY dateAjout DESC, idEvenement DESC LIMIT 500";

    //echo $sql_even;

    $req_getEvenement = $connector->query($sql_even);

    if ($connector->getNumRows($req_getEvenement) > 0)
    {
    ?>
        <table summary="Derniers événements ajoutés" id="derniers_evenements_ajoutes">
        <tr>
            <th>Titre</th>
            <th>Lieu</th>
            <th>Date</th>
            <th>Catégorie</th>
            <th>Horaire</th>
            <th>Statut</th>
            <th>Ajouté</th>
            <th>par</th>
            <th>&nbsp;</th>
        </tr>
    <?php

    $pair = 0;
    while($tab_even = $connector->fetchArray($req_getEvenement))
    {
        $nomLieu = sanitizeForHtml($tab_even['nomLieu']);

        if ($tab_even['idLieu'] != 0)
        {
            $req_lieu = $connector->query("SELECT nom FROM lieu WHERE idLieu=".(int) $tab_even['idLieu']);
            $tabLieu = $connector->fetchArray($req_lieu);
            $nomLieu = "<a href=\"/lieu/lieu.php?idL=".(int) $tab_even['idLieu']."\" title=\"Voir la fiche du lieu : ".sanitizeForHtml($tabLieu['nom'])." \">".sanitizeForHtml($tabLieu['nom'])."</a>";
        }


        if ($pair % 2 == 0)
        {
            echo "<tr>";
        }
        else
        {
            echo "<tr class=\"impair\">";
        }


        echo "<td><a href=\"/event/evenement.php?idE=".(int)$tab_even['idEvenement']."\" class='titre'>".sanitizeForHtml($tab_even['titre'])."</a></td>
        <td>".$nomLieu."</td>
        <td>".date_iso2app($tab_even['dateEvenement'])."</td>";

            echo "<td>".ucfirst((string) $glo_tab_genre[$tab_even['genre']])."</td>";

            echo "<td>";

        echo afficher_debut_fin($tab_even['horaire_debut'], $tab_even['horaire_fin'], $tab_even['dateEvenement']);

        echo "</td>
        <td style='text-align: center;'>".EvenementRenderer::$iconStatus[$tab_even['statut']]."</td>";

        $datetime_dateajout = date_iso2app($tab_even['dateAjout']);
        $tab_datetime_dateajout = explode(" ", (string) $datetime_dateajout);
        echo "<td>".$tab_datetime_dateajout[1]." ".$tab_datetime_dateajout[0]."</td>";

        $nom_auteur = "-";

        if ($tab_auteur = $connector->fetchArray($connector->query("SELECT pseudo FROM personne WHERE idPersonne=".(int) $tab_even['idPersonne'])))
        {
            $nom_auteur = "<a href=\"/user.php?idP=".(int)$tab_even['idPersonne']."\"
            title=\"Voir le profile de la personne\">".sanitizeForHtml($tab_auteur['pseudo'])."</a>";
        }
        echo "<td>".$nom_auteur."</td>";

        if ($_SESSION['Sgroupe'] <= UserLevel::ADMIN) {
            echo "<td><a href=\"/evenement-edit.php?action=editer&amp;idE=".(int)$tab_even['idEvenement']."\" title=\"Éditer l'événement\">".$iconeEditer."</a></td>";
        }
        echo "</tr>";

        $pair++;
    }

    ?>
    </table>
    <?php } else { ?>
    Rien
    <?php } ?>
    <p><a href="/admin/gererEvenements.php">Gérer les événements</a></p>

    <?php if ($_SESSION['Sgroupe'] < UserLevel::ADMIN) { ?>

        <h3 style="padding:0.2em">Derniers textes ajoutés</h3>

    <table summary="Derniers textes ajoutés">
    <tr>
        <th colspan="2">Ajouté le</th>
        <th>Lieu</th>
        <th>Contenu</th>
        <th>Type</th>
        <th>par</th>
        <th>&nbsp;</th>
    </tr>

    <?php

    $sql_req = "SELECT descriptionlieu.idLieu AS idLieu, descriptionlieu.idPersonne, descriptionlieu.dateAjout, contenu, type
    FROM descriptionlieu, lieu WHERE descriptionlieu.idLieu=lieu.idLieu ".$sql_region."  ORDER BY descriptionlieu.dateAjout DESC LIMIT 5";

    //echo $sql_req;
    $req_getDes = $connector->query($sql_req);
    $pair = 0;
    while ($tab_desc = $connector->fetchArray($req_getDes))
    {

        $req_auteur = $connector->query("SELECT pseudo FROM personne WHERE idPersonne=".(int) $tab_desc['idPersonne']);
        $tabAuteur = $connector->fetchArray($req_auteur);

        $req_lieu = $connector->query("SELECT nom FROM lieu WHERE idLieu=".(int) $tab_desc['idLieu']);
        $tabLieu = $connector->fetchArray($req_lieu);
        $nomLieu = "<a href=\"/lieu/lieu.php?idL=".(int)$tab_desc['idLieu']."\" title=\"Éditer le lieu\">".sanitizeForHtml($tabLieu['nom'])."</a>";


        if ($pair % 2 == 0)
        {
            echo "<tr>";
        }
        else
        {
            echo "<tr class=\"impair\">";
        }

        $datetime_dateajout = date_iso2app($tab_desc['dateAjout']);
        $tab_datetime_dateajout = explode(" ", (string) $datetime_dateajout);
        echo "<td>".$tab_datetime_dateajout[1]."</td><td>".$tab_datetime_dateajout[0]."</td>";

        echo "<td>".$nomLieu."</td>";
        if (mb_strlen((string) $tab_desc['contenu']) > 200)
        {
            $tab_desc['contenu'] = mb_substr((string) $tab_desc['contenu'], 0, 200)." [...]";
        }
        echo "<td class=\"tdleft small\">" . Text::html_substr($tab_desc['contenu']) . "</td>";
            $nom_auteur = "<i>Ancien membre</i>";

            if ($tab_auteur = $connector->fetchArray($connector->query("SELECT pseudo FROM personne WHERE idPersonne=".(int) $tab_desc['idPersonne'])))
        {
            $nom_auteur = "<a href=\"/user.php?idP=".(int) $tab_desc['idPersonne']."\"
            title=\"Voir le profile de la personne\">".sanitizeForHtml($tab_auteur['pseudo'])."</a>";
        }
        echo "<td>".$tab_desc['type']."</td>";
        echo "<td>".$nom_auteur."</td>";
        if ($_SESSION['Sgroupe'] <= UserLevel::ADMIN) {
            echo "<td><a href=\"/lieu-text-edit.php?action=editer&amp;idL=" . (int)$tab_desc['idLieu'] . "&amp;idP=" .(int) $tab_desc['idPersonne'] . "&type=" . $tab_desc['type'] . "\" title=\"Éditer le lieu\">" . $iconeEditer . "</a></td>";
            }
        echo "</tr>";

        $pair++;
    }

    ?>
    </table>

    <?php } ?>

    </div><!-- fin tableaux -->

</main><!-- fin contenu -->

<div id="colonne_gauche" class="colonne">
    <?php
    //include("_menuAdmin.inc.php");
    ?>
</div>


<div class="spacer"><!-- --></div>
<?php
include("../_footer.inc.php");
?>
