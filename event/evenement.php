<?php

require_once("../app/bootstrap.php");

use Ladecadanse\Evenement;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Lieu;
use Ladecadanse\Organisateur;
use Ladecadanse\UserLevel;
use Ladecadanse\Utils\Text;

if (empty($_GET['idE']) || !is_numeric($_GET['idE']))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
    exit;
}

$get['idE'] = (int) $_GET['idE'];


// EVENT AND APPENDIXES
$sql_event = "SELECT

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
  e.ref AS e_ref,
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
  e.dateAjout AS e_dateAjout,

  l.nom AS l_nom,
  l.determinant AS l_determinant,
  l.adresse AS l_adresse,
  l.quartier AS l_quartier,
  l.lat AS l_lat,
  l.lng AS l_lng,
  l.URL AS l_URL,
  lloc.localite AS lloc_localite,
  l.region AS l_region,

  s.nom AS s_nom

FROM evenement e
JOIN localite loc ON e.localite_id = loc.id
LEFT JOIN lieu l ON e.idLieu = l.idLieu
LEFT JOIN localite lloc ON l.localite_id = lloc.id
LEFT JOIN salle s ON e.idSalle = s.idSalle
WHERE e.idEvenement = :idE";

$stmt = $connectorPdo->prepare($sql_event);
$stmt->execute([':idE' => $get['idE']]);
$tab_even = $stmt->fetch(PDO::FETCH_ASSOC);

if (empty($tab_even))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
    exit;
}

$isPersonneAllowedToEdit = $authorization->isPersonneAllowedToEditEvenement($_SESSION, $tab_even);

if (!$isPersonneAllowedToEdit && in_array($tab_even['e_statut'], ['propose', 'inactif']))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
    exit;
}

// lieu, organisateurs and author details
$even_lieu = Evenement::getLieu($tab_even);
$preposition_lieu = Lieu::prepositionToPutInSentence($tab_even['l_determinant']);

$stmtOrgas = $connectorPdo->prepare("SELECT
o.idOrganisateur AS o_idOrganisateur,
o.nom AS o_nom,
o.URL AS o_URL
FROM evenement_organisateur eo
JOIN organisateur o ON eo.idOrganisateur = o.idOrganisateur AND eo.idEvenement = :idE
ORDER BY nom DESC");
$stmtOrgas->execute([':idE' => $get['idE']]);
$res_even_orgas = $stmtOrgas->fetchAll(PDO::FETCH_ASSOC);
foreach ($res_even_orgas AS $o)
{
    $tab_even_orgas[] = [
        'idOrganisateur' => $o['o_idOrganisateur'],
        'nom' => $o['o_nom'],
        'url' => $o['o_URL']
    ];
}

$stmtAuthor = $connectorPdo->prepare("SELECT pseudo, affiliation, signature, avec_affiliation FROM personne WHERE idPersonne= :idP");
$stmtAuthor->execute([':idP' => $tab_even['e_idPersonne']]);
$even_author = $stmtAuthor->fetch(PDO::FETCH_ASSOC);
// END EVENT AND APPENDIXES

// HEAD metas
$page_titre = $tab_even['e_titre'] . " " . $preposition_lieu . $even_lieu['nom'] . HtmlShrink::adresseCompacteSelonContexte($even_lieu['region'], $even_lieu['localite'], $even_lieu['quartier'], "") . ", le " . date_fr($tab_even['e_dateEvenement'], "annee", "", "", false);
$page_description = "Événement \"" . $tab_even['e_titre'] . "\" " . $preposition_lieu . $even_lieu['nom'] . " " . $even_lieu['salle'] . ", " . HtmlShrink::adresseCompacteSelonContexte($even_lieu['region'], $even_lieu['localite'], $even_lieu['quartier'], $even_lieu['adresse']).", le " . date_fr($tab_even['e_dateEvenement'], "annee", "", "", false) . " - " . afficher_debut_fin($tab_even['e_horaire_debut'], $tab_even['e_horaire_fin'], $tab_even['e_dateEvenement']). " " . sanitizeForHtml($tab_even['e_horaire_complement']);
if (!empty($tab_even['e_flyer']))
{
    $page_image = Evenement::getWebPath(Evenement::getFilePath($tab_even['e_flyer']), isWithAntiCache: true);
}
elseif (!empty($tab_even['e_image']))
{
    $page_image = Evenement::getWebPath(Evenement::getFilePath($tab_even['e_image']), isWithAntiCache: true);
}
$page_url = "event/evenement.php?idE=" .  $get['idE'];
// END HEAD metas

// build SQL
$sql_user_prefs_agenda_order = "e." . $_SESSION['user_prefs_agenda_order'] . " DESC";
if ($_SESSION['user_prefs_agenda_order'] == "horaire_debut")
{
	$sql_user_prefs_agenda_order = "e.horaire_debut ASC";
}

$sql_events_of_day = "
SELECT
idEvenement, titre, CASE WHEN (e.idLieu IS NULL OR e.idLieu = '') THEN e.nomLieu ELSE l.nom END AS lieu_nom
FROM evenement e
LEFT JOIN lieu l ON e.idLieu = l.idLieu
WHERE
  e.dateEvenement = :date AND e.statut NOT IN ('inactif', 'propose')
ORDER BY
  CASE e.genre
    WHEN 'fête' THEN 1
    WHEN 'cinéma' THEN 2
    WHEN 'théâtre' THEN 3
    WHEN 'expos' THEN 4
    WHEN 'divers' THEN 5
  END,
  $sql_user_prefs_agenda_order";

$stmtDayEvents = $connectorPdo->prepare($sql_events_of_day);
$stmtDayEvents->execute([':date' => $tab_even['e_dateEvenement']]);
$events_of_day = $stmtDayEvents->fetchAll(PDO::FETCH_ASSOC);

$index = 1;
foreach ($events_of_day as $i => $e) {
    if ($e['idEvenement'] == $get['idE']) {
        $index = $i;
        break;
    }
}

$events_siblings = [$events_of_day[$index - 1] ?? null, $events_of_day[$index + 1] ?? null];
// END PREV-NEXT NAVIGATION


include("../_header.inc.php");
?>

<main id="contenu" class="colonne vevent">

    <?php if (!empty($_SESSION['evenement-edit_flash_msg'])) :
        HtmlShrink::msgOk($_SESSION['evenement-edit_flash_msg']);
        unset($_SESSION['evenement-edit_flash_msg']);
    endif; ?>

    <header id="entete_contenu">

        <div id="entete_contenu_titre" <?php if ($tab_even['e_dateEvenement'] < $glo_auj) { echo ' class="ancien"'; } ?>>
            <span class="category"><?= sanitizeForHtml($translator->get("event-category-".$tab_even['e_genre'])); ?></span>, <a href="/index.php?courant=<?= $tab_even['e_dateEvenement'] ?>"><time datetime="<?= $tab_even['e_dateEvenement'] ?>"><?= date_fr($tab_even['e_dateEvenement'], "annee", "", "", true) ?></time></a>
        </div>

        <?php if (!empty($events_siblings[0])) : ?>
            <div class="entete_contenu_navigation"><a href="/event/evenement.php?idE=<?= $events_siblings[0]['idEvenement'] ?>" rel="prev nofollow"><span class="event-navig-link"><span class="nav_titre"><?= sanitizeForHtml($events_siblings[0]['titre']) ?></span> - <?= sanitizeForHtml($events_siblings[0]['lieu_nom']) ?>&nbsp;<i class="fa fa-arrow-up"></i></span></a></div>
        <?php endif; ?>
        <div class="spacer"></div>

    </header>


    <nav>
        <ul class="menu_actions_evenement">
            <?php if ((isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= UserLevel::MEMBER)) : ?>
                <li><a href="/event/send.php?action=share&idE=<?= (int) $get['idE'] ?>"><?= $icone['envoi_email'] ?>&nbsp;Envoyer à un ami</a></li>
            <?php endif; ?>
            <?php if ($isPersonneAllowedToEdit) : ?>
                <li><a href="/event/copy.php?idE=<?= (int) $get['idE'] ?>"><?= $iconeCopier ?>&nbsp;Copier vers d'autres dates</a></li>
                <li><a href="/evenement-edit.php?action=editer&amp;idE=<?= (int) $get['idE'] ?>"><?= $iconeEditer ?>&nbsp;Modifier</a></li>
            <?php endif; ?>
                <li><a href="/event/to-ics.php?idE=<?= (int) $get['idE'] ?>" title="Exporter au format iCalendar dans votre agenda"><i class="fa fa-calendar-plus-o fa-lg"></i>&nbsp;iCal</a></li>
        </ul>
    </nav>

    <article id="evenement">

        <div class="dtstart">
            <span class="value-title" title="<?= $tab_even['e_dateEvenement'] ?>T<?= mb_substr((string) $tab_even['e_horaire_debut'], 11, 5); ?>:00"></span>
        </div>

        <header class="titre">

            <h1 class="left summary"><?= Ladecadanse\EvenementRenderer::titreSelonStatutHtml($tab_even['e_titre'], $tab_even['e_statut'], $isPersonneAllowedToEdit) ?></h1>

            <div class="right location vcard">

                <div class="fn org"><?= Lieu::getLinkNameHtml($even_lieu['nom'], $even_lieu['idLieu'], $even_lieu['salle']) ?></div>

                <ul style="list-style-type: none;">
                    <li class="adr">
                        <?= sanitizeForHtml(HtmlShrink::adresseCompacteSelonContexte($even_lieu['region'], $even_lieu['localite'], $even_lieu['quartier'], $even_lieu['adresse'])); ?>
                    </li>
                    <?php if (!empty((float) $even_lieu['lat']) && !empty((float) $even_lieu['lng'])) : ?>
                        <li>
                            <a href="#" class="dropdown map-dropdown-link" data-target="plan"><?= $icone['plan'] ?>&nbsp;Voir sur le plan&nbsp;<i class="fa fa-caret-down fa-lg" aria-hidden="true"></i></a>
                        </li>
                    <?php endif; ?>
                    <?php if (!empty($even_lieu['url'])) : $lieu_url = Text::getUrlWithName($even_lieu['url']); ?>
                        <li><a class="url" href="<?= $lieu_url['url'] ?>" rel="external" target="_blank"><?= $lieu_url['urlName']?></a>
                        <?php if ($tab_even['e_idLieu'] == 13) : // exception pour idLieu=13 (Le Rez - Usine) ?>
                            <a href="https://rez-usine.ch" class="url" rel="external" target="_blank">rez-usine.ch</a><br>
                            <a href="http://www.ptrnet.ch" class="url" rel="external" target="_blank">ptrnet.ch</a>
                        <?php endif; ?>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="spacer"></div>

            <?php if (!empty((float) $even_lieu['lat']) && !empty((float) $even_lieu['lng'])) : ?>
                <div id="plan" style="display:none">
                    <div id="lieu-map-infowindow" style="display:none"><?= sanitizeForHtml($even_lieu['nom']) ?></div>
                    <div id="lieu-map" data-lat="<?= $even_lieu['lat'] ?>" data-lng="<?= $even_lieu['lng'] ?>"></div>
                </div>
            <?php endif; ?>
        </header>

        <figure id="illustrations">
            <?= Ladecadanse\EvenementRenderer::mainFigureHtml($tab_even['e_flyer'], $tab_even['e_image'], $tab_even['e_titre']) ?>
                <?php if ($tab_even['e_flyer'] != '' && $tab_even['e_image'] != '' ) : ?>
                <br><br>
                <a href="<?= Evenement::getWebPath(Evenement::getFilePath($tab_even['e_image']), isWithAntiCache: true) ?>" class="magnific-popup"><img src="<?= Evenement::getWebPath(Evenement::getFilePath($tab_even['e_image']), isWithAntiCache: true) ?>" alt="Illustration pour cet événement" width="160" /></a>
                <?php endif; ?>
        </figure>

        <div id="description">
            <p class="description"><?= Text::wikiToHtml(sanitizeForHtml($tab_even['e_description'])) ?></p>
            <?php if (!empty($tab_even['e_ref'])) : ?>
                <?php if (!empty($tab_even['e_description'])) : ?><hr><?php endif; ?>
                <ul class="references left" style="margin:10px 0">
                    <?= Ladecadanse\EvenementRenderer::getRefListHtml($tab_even['e_ref']) ?>
                </ul>
             <?php endif; ?>

            <?php if (!empty($tab_even_orgas)): ?>
                <?= Organisateur::getListLinkedHtml($tab_even_orgas) ?>
            <?php endif; ?>
            <div class="spacer"></div>
        </div>

        <div id="pratique">
            <table class="left" >
                <tr>
                    <th scope="row"><i class="fa fa-clock-o fa-lg" aria-label="Horaires"></i></th>
                    <td><strong><?= afficher_debut_fin($tab_even['e_horaire_debut'], $tab_even['e_horaire_fin'], $tab_even['e_dateEvenement']) ?></strong>
                        <br /><?= sanitizeForHtml($tab_even['e_horaire_complement']) ?></td>
                </tr>
                <tr>
                    <th scope="row"><i class="fa fa-money fa-lg" aria-label="Prix"></i></th><td><?= sanitizeForHtml($tab_even['e_prix']) ?></td>
                </tr>
                <tr>
                    <th scope="row"><i class="fa fa-ticket fa-lg" aria-label="Prélocations"></i></th><td><?= Text::linkify(sanitizeForHtml($tab_even['e_prelocations'])) ?></td>
                </tr>
            </table>
            <div class="spacer"></div>
        </div>
        <!-- Fin pratique -->

        <footer id="auteur">
            <?php

            // TODO: Personne::getSignature(idPersonne, signature, avec_affiliation
            $signature_auteur = "";
            if (!empty($even_author))
            {
                if ($even_author['signature'] == 'pseudo')
                {
                    $signature_auteur = "<strong>" . sanitizeForHtml($even_author['pseudo']) . "</strong> ";
                }

                if ($even_author['avec_affiliation'] == 'oui')
                {
                    $nom_affiliation = $even_author['affiliation'];

                    $stmtAuthorAffiliationLieuNom = $connectorPdo->prepare("SELECT l.nom FROM affiliation a JOIN lieu l ON a.idAffiliation = l.idLieu AND a.genre = 'lieu' WHERE a.idPersonne= :idP");
                    $stmtAuthorAffiliationLieuNom->execute([':idP' => $tab_even['e_idPersonne']]);
                    $author_affiliation_lieu_nom = $stmtAuthorAffiliationLieuNom->fetch(PDO::FETCH_ASSOC);
                    if (!empty($author_affiliation_lieu_nom))
                    {
                        $nom_affiliation = $author_affiliation_lieu_nom['nom'];
                    }

                    $signature_auteur .= "(" . sanitizeForHtml($nom_affiliation) . ")";
                }
            }
			?>

            <a class="signaler" href="/event/send.php?action=report&idE=<?= (int) $get['idE'] ?>"><i class="fa fa-flag-o fa-lg"></i>&nbsp;Signaler une erreur</a> Ajouté <?php echo ((!empty($signature_auteur)) ? "par&nbsp;" : "") . $signature_auteur ?> le&nbsp;<?= date_fr($tab_even['e_dateAjout'], "annee", "", "non") ?>
            <?php if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= UserLevel::ADMIN && !empty($tab_even['e_idPersonne'])) : ?><a href="/user.php?idP=<?= (int) $tab_even['e_idPersonne'] ?>"><?= $icone['personne'] ?></a><?php endif; ?>
        </footer> <!-- auteur -->

    </article>

    <?php if (!empty($events_siblings[1])) : ?>
    <div id="footer_navigation">
        <div class="entete_contenu_navigation">
            <a href="/event/evenement.php?idE=<?= (int)$events_siblings[1]['idEvenement'] ?>" rel="next nofollow"><span class="event-navig-link"><?= sanitizeForHtml($events_siblings[1]['titre']) ?> - <?= sanitizeForHtml($events_siblings[1]['lieu_nom']) ?>&nbsp;<i class="fa fa-arrow-down"></i></span></a>
        </div>
        <div class="spacer"><!-- --></div>
    </div>
    <?php endif; ?>

    <div class="spacer"><!-- --></div>
</main>

<div id="colonne_gauche" class="colonne">
    <?php
    $get['courant'] = $tab_even['e_dateEvenement'];
    include("../event/_navigation_calendrier.inc.php");
    ?>
</div>

<div class="spacer"><!-- --></div>

<?php
include("../_footer.inc.php");
?>
