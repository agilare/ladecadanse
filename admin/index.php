<?php

global $connector;
require_once("../app/bootstrap.php");

use Ladecadanse\Utils\Text;
use Ladecadanse\UserLevel;
use Ladecadanse\EvenementRenderer;
use Ladecadanse\Evenement;
use Ladecadanse\Lieu;

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
    o.idOrganisateur AS idO,
    o.nom AS o_nom,
    l.idLieu AS idL,
    l.nom AS l_nom,
    e.idEvenement AS idE,
    e.titre AS e_titre

    FROM personne p
    LEFT JOIN personne_organisateur po ON p.idPersonne = po.idPersonne
    LEFT JOIN organisateur o ON po.idOrganisateur = o.idOrganisateur
    LEFT JOIN affiliation a ON p.idPersonne = a.idPersonne AND a.genre = 'lieu'
    LEFT JOIN lieu l ON a.idAffiliation = l.idLieu
    LEFT JOIN evenement e ON e.idEvenement = (
        SELECT MAX(e2.idEvenement)
        FROM evenement e2
        WHERE e2.idPersonne = p.idPersonne
    )
    WHERE
    p.dateAjout >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
    GROUP BY p.idPersonne
    ORDER BY p.dateAjout DESC, e.dateAjout DESC LIMIT 100";

//echo $sql_select;
$stmt = $connectorPdo->prepare($sql_select);
$stmt->execute();
$page_results = $stmt->fetchAll(PDO::FETCH_GROUP);


$sql_region = '';
if (!empty($_SESSION['region_admin']))
{
    $sql_region = " AND region='" . $connector->sanitize($_SESSION['region_admin']) . "'";
}


$stmt = $connectorPdo->prepare("SELECT
  DATE(e.dateAjout) as e_dateAjout_day,
  e.idEvenement as e_idEvenement,
  e.titre as e_titre,
  e.genre AS e_genre,
  e.dateEvenement as e_dateEvenement,
  e.horaire_debut as e_horaire_debut,
  e.horaire_fin as e_horaire_fin,
  e.dateAjout as e_dateAjout,
  e.idLieu AS e_idLieu,
  e.idSalle AS e_idSalle,
  e.nomLieu AS e_nomLieu,
  e.adresse AS e_adresse,
  e.quartier AS e_quartier,
  loc.localite AS e_localite,
  e.urlLieu AS e_urlLieu,
  e.flyer e_flyer,
  e.image e_image,
  e.statut e_statut,

  l.nom AS l_nom,
  l.adresse AS l_adresse,
  l.quartier AS l_quartier,
  l.URL AS l_URL    ,
  lloc.localite AS lloc_localite,

  s.nom AS s_nom,

  p.idPersonne AS idPersonne,
  p.pseudo AS pseudo

FROM evenement e
JOIN personne p ON e.idPersonne = p.idPersonne
LEFT JOIN lieu l ON e.idLieu = l.idLieu
LEFT JOIN localite lloc ON l.localite_id = lloc.id
LEFT JOIN salle s ON e.idSalle = s.idSalle
JOIN localite loc on e.localite_id = loc.id
WHERE e.dateAjout >= DATE_SUB(CURDATE(), INTERVAL 2 DAY) ".$sql_region." ORDER BY e.dateAjout DESC LIMIT 0, 200");

$stmt->execute();
$tab_latest_events = $stmt->fetchAll(PDO::FETCH_GROUP);


$lieux_desc_latest = [];
if ($_SESSION['Sgroupe'] < UserLevel::ADMIN) {
    $stmt = $connectorPdo->prepare("
        SELECT
        dl.idLieu AS idLieu,
        dl.idPersonne,
        DATE(dl.dateAjout) AS dateAjout,
        contenu,
        type,
        l.nom AS l_nom,
        p.pseudo AS pseudo
        FROM descriptionlieu dl
        JOIN lieu l ON dl.idLieu = l.idLieu
        JOIN personne p ON dl.idPersonne = p.idPersonne
        WHERE 1 ".$sql_region." ORDER BY dl.dateAjout DESC LIMIT 5");

    $stmt->execute();
    $lieux_desc_latest = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

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

        <?php if ($_SESSION['Sgroupe'] < UserLevel::ADMIN) : ?>

        <h2 style="padding:0.4em 0">Inscriptions des 3 derniers jours</h2>

        <table summary="Dernières inscriptions">
            <thead>
                <tr>
                    <th>Heure</th>
                    <th>Pseudo</th>
                    <th>E-mail</th>
                    <th colspan="3">Affiliations (libre, lieu, organisateur)</th>
                    <th>Dernier éven. ajouté</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($page_results as $date => $users) : ?>

                    <tr>
                        <td colspan="7" style="background:#f3f3f3;font-weight: bold"><?= date_fr($date) ?></td>
                    </tr>

                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= (new DateTime($u['p_dateAjout']))->format("H:i")?></td>
                                <td>
                                    <a href="/user.php?idP=<?= (int)$u['idPersonne'] ?>"><?= sanitizeForHtml($u['pseudo']) ?></a>
                                    <?php if ($u['groupe'] != UserLevel::ACTOR) { echo "(".sanitizeForHtml($u['groupe']).")"; } ?>
                                </td>
                                <td><?= $u['email'] ?></td>
                                <td><?= sanitizeForHtml($u['affiliation']) ?></td>
                                <td>
                                    <?php if ($u['idL']) : ?>
                                        <a href="/lieu/lieu.php?idL=<?= (int) $u['idL'] ?>"><?= sanitizeForHtml($u['l_nom']) ?></a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['idO']) : ?>
                                        <a href="/organisateur/organisateur.php?idO=<?= (int) $u['idO'] ?>"><?= sanitizeForHtml($u['o_nom']) ?></a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['idE']) : ?>
                                        <a href="/event/evenement.php?idE=<?= (int) $u['idE'] ?>"><?= sanitizeForHtml($u['e_titre']) ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                <?php endforeach; ?>
            </tbody>
        </table>

    <?php endif; ?>

    <?php if (!empty($_SESSION['region_admin'])) { ?>
        <p><?php echo $glo_regions[$_SESSION['region_admin']]; ?></p>
    <?php } ?>

    <h2 style="padding:0.4em 0">Événements ajoutés ces 3 derniers jours</h2>

        <table summary="Derniers événements ajoutés" id="derniers_evenements_ajoutes" style="max-height:500px;">
            <thead>
                <tr>
                    <th>Heure</th>
                    <th>Titre</th>
                    <th>Lieu</th>
                    <th>Date</th>
                    <th>Catégorie</th>
                    <th style="width:100px">Horaire</th>
                    <th>Statut</th>
                    <th>par</th>
                    <th>&nbsp;</th>
                </tr>
            </thead>
            <tbody>

                <?php foreach ($tab_latest_events as $date => $events) : ?>

                    <tr>
                        <td colspan="10" style="background:#f3f3f3;font-weight: bold"><?= date_fr($date) ?></td>
                    </tr>

                    <?php foreach ($events as $event) :
                        $even_lieu = Evenement::getLieu($event);
                        ?>
                    <tr>
                        <td><?= (new DateTime($event['e_dateAjout']))->format("H:i") ?></td>
                        <td><a href="/event/evenement.php?idE=<?= (int)$event['e_idEvenement'] ?>" class='titre'><?= sanitizeForHtml($event['e_titre']) ?></a></td>
                        <td><?= Lieu::getLinkNameHtml($even_lieu['nom'], $even_lieu['idLieu'], $even_lieu['salle']) ?></td>
                        <td><a href="/index.php?courant=<?= sanitizeForHtml($event['e_dateEvenement']) ?>"><?= date_iso2app($event['e_dateEvenement']) ?></a></td>
                        <td><?= ucfirst($glo_tab_genre[$event['e_genre']]) ?></td>
                        <td><?= afficher_debut_fin($event['e_horaire_debut'], $event['e_horaire_fin'], $event['e_dateEvenement']) ?></td>
                        <td style='text-align: center;'><?= EvenementRenderer::$iconStatus[$event['e_statut']] ?></td>
                        <td><a href="/user.php?idP=<?= (int)$event['idPersonne'] ?>"><?= sanitizeForHtml($event['pseudo']) ?></a></td>
                        <td>
                            <?php if ($_SESSION['Sgroupe'] <= UserLevel::ADMIN) : ?>
                                <a href="/evenement-edit.php?idE=<?= (int)$event['e_idEvenement'] ?>&amp;action=editer"><?= $iconeEditer ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>

            </tbody>
        </table>

    <?php if ($_SESSION['Sgroupe'] < UserLevel::ADMIN) { ?>

        <h2 style="padding:0.2em">Derniers textes ajoutés à des lieux</h2>

        <table summary="Derniers textes ajoutés" style="max-height:200px;">

            <tr>
                <th>Type</th>
                <th>Lieu</th>
                <th>Contenu</th>
                <th>par</th>
                <th>le</th>
                <th>&nbsp;</th>
            </tr>

            <?php foreach ($lieux_desc_latest as $desc) :
                if (mb_strlen((string) $desc['contenu']) > 200)
                {
                    $desc['contenu'] = mb_substr((string) $desc['contenu'], 0, 200)." [...]";
                }
                ?>

                <tr>
                    <td><?= sanitizeForHtml($desc['type']) ?></td>
                    <td><a href="/lieu/lieu.php?idL=<?= (int)$desc['idLieu'] ?>"><?= sanitizeForHtml($desc['l_nom']) ?></a></td>
                    <td class="tdleft small"><?= Text::html_substr($desc['contenu']) ?></td>
                    <td><a href="/user.php?idP=<?= (int) $desc['idPersonne'] ?>"><?= sanitizeForHtml($desc['pseudo']) ?></a></td>
                    <td><?= date_fr($desc['dateAjout']) ?></td>
                    <td><a href="/lieu-text-edit.php?action=editer&amp;idL=<?= (int)$desc['idLieu'] ?>&amp;idP=<?= (int) $desc['idPersonne'] ?>&amp;type=<?= $desc['type'] ?>"><?= $iconeEditer ?></a></td>
               </tr>
            <?php endforeach; ?>
    </table>

    <?php } ?>

    </div><!-- #tableaux -->

</main>

<div id="colonne_gauche" class="colonne">
</div>


<div class="spacer"><!-- --></div>
<?php
include("../_footer.inc.php");
?>
