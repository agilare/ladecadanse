<?php

require_once("app/bootstrap.php");

use Ladecadanse\UserLevel;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Personne;
use Ladecadanse\Organisateur;
use Ladecadanse\Evenement;
use Ladecadanse\EvenementRenderer;
use Ladecadanse\Lieu;
use Ladecadanse\UserSettings;
use Ladecadanse\Utils\DateHelper;
use Ladecadanse\Utils\Utils;
use Ladecadanse\Utils\Text;

// =============================================================================
// Traitement
//
// Tout se joue avant l'inclusion de _header.inc.php : les sorties en erreur
// laissaient auparavant un document tronqué, sans </main> ni pied de page.
// =============================================================================

if (!$videur->checkGroup(UserLevel::MEMBER))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
    header("Location: /user-login.php");
    die();
}

$tab_elements = ["evenement" => "Événements", "description" => "Descriptions"];

// Colonnes triables par onglet, et leur nom qualifié dans la requête.
// Le tri était auparavant commun aux deux onglets : trier les événements par
// titre puis forcer ?elements=description produisait une erreur SQL. Les noms
// sont qualifiés parce que dateAjout existe aussi dans lieu, joint plus bas.
$tab_tri = [
    "evenement" => ["dateEvenement" => "e.dateEvenement", "titre" => "e.titre", "dateAjout" => "e.dateAjout", "statut" => "e.statut"],
    "description" => ["dateAjout" => "d.dateAjout"],
];

$get = [];
$get['idP'] = isset($_GET['idP']) ? (int) $_GET['idP'] : 0;
$get['elements'] = (isset($_GET['elements']) && array_key_exists($_GET['elements'], $tab_elements)) ? $_GET['elements'] : "evenement";
$get['page'] = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$get['ordre'] = (isset($_GET['ordre']) && $_GET['ordre'] === "asc") ? "asc" : "desc";
$ordre_inverse = $get['ordre'] === "asc" ? "desc" : "asc";

// un tri inconnu retombe sur la valeur par défaut, comme page et ordre le font
// déjà, plutôt que d'interrompre la page
$get['tri'] = "dateAjout";
if (isset($_GET['tri']) && array_key_exists($_GET['tri'], $tab_tri[$get['elements']]))
{
    $get['tri'] = $_GET['tri'];
}

// borné à la liste du menu : une valeur libre laissait passer LIMIT 0, 999999
$get['nblignes'] = 100;
if (isset($_GET['nblignes']) && in_array((int) $_GET['nblignes'], $tab_nblignes, true))
{
    $get['nblignes'] = (int) $_GET['nblignes'];
}

// Propriétaire ou administrateur. La page entière leur est réservée, donc le
// même booléen commande l'accès, le lien Modifier et les actions des tableaux —
// trois tests qui divergeaient jusqu'ici (SUPERADMIN pour l'un, ADMIN pour les
// autres) alors qu'aucun autre profil ne peut atteindre cette page.
$peut_gerer = ((int) $_SESSION['SidPersonne'] === $get['idP']) || $_SESSION['Sgroupe'] <= UserLevel::ADMIN;

$erreur = null;
$profil = null;

if ($get['idP'] <= 0)
{
    header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
    $erreur = "Identifiant de personne manquant.";
}
else if (!$peut_gerer)
{
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
    $erreur = "Vous ne pouvez pas accéder à cette page.";
}
else
{
    $profil = Personne::getProfil($get['idP']);

    if ($profil === null)
    {
        header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
        $erreur = "Cette personne n'existe pas.";
    }
}

$affiliations_lieux = [];
$organisateurs = [];
$tab_evens = [];
$tab_descs = [];
$tot_elements = 0;

if ($erreur === null)
{
    $affiliations_lieux = Personne::getAffiliationsLieux($get['idP']);
    $organisateurs = Personne::getOrganisateurs($get['idP']);

    $offset = ($get['page'] - 1) * $get['nblignes'];
    // valeurs issues de la liste blanche $tab_tri, jamais de la requête
    $order_by = $tab_tri[$get['elements']][$get['tri']] . " " . $get['ordre'];

    if ($get['elements'] === "evenement")
    {
        $stmt = $connectorPdo->prepare("SELECT COUNT(*) FROM evenement WHERE idPersonne = :idP");
        $stmt->execute([':idP' => $get['idP']]);
        $tot_elements = (int) $stmt->fetchColumn();

        // le nom du lieu vient de la jointure, plus d'une requête par ligne ;
        // evenement.nomLieu reste le secours pour les lieux hors base
        $stmt = $connectorPdo->prepare("SELECT
            e.idEvenement, e.idLieu, e.statut, e.titre, e.dateEvenement, e.horaire_fin, e.nomLieu, e.dateAjout,
            l.nom AS lieu_nom
            FROM evenement e
            LEFT JOIN lieu l ON e.idLieu = l.idLieu
            WHERE e.idPersonne = :idP
            ORDER BY $order_by
            LIMIT :offset, :nblignes");
        $stmt->bindValue(':idP', $get['idP'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':nblignes', $get['nblignes'], PDO::PARAM_INT);
        $stmt->execute();
        $tab_evens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    else
    {
        $stmt = $connectorPdo->prepare("SELECT COUNT(*) FROM descriptionlieu WHERE idPersonne = :idP");
        $stmt->execute([':idP' => $get['idP']]);
        $tot_elements = (int) $stmt->fetchColumn();

        // jointure externe : une description dont le lieu a disparu reste listée
        $stmt = $connectorPdo->prepare("SELECT
            d.idLieu, d.dateAjout, d.contenu, d.type,
            l.nom AS lieu_nom
            FROM descriptionlieu d
            LEFT JOIN lieu l ON d.idLieu = l.idLieu
            WHERE d.idPersonne = :idP
            ORDER BY $order_by
            LIMIT :offset, :nblignes");
        $stmt->bindValue(':idP', $get['idP'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':nblignes', $get['nblignes'], PDO::PARAM_INT);
        $stmt->execute();
        $tab_descs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Valeurs par défaut du formulaire d'ajout d'événement, traduites en libellés lisibles.
// UserSettings ne touche jamais la base, par conception : c'est ici que les identifiants de lieu et
// d'organisateurs deviennent des noms.
$defauts_evenement = [];

if ($erreur === null)
{
    $defauts = UserSettings::eventNewDefaults($profil['settings']);

    if ($defauts['genre'] !== '')
    {
        $defauts_evenement['Catégorie'] = Evenement::genreLabel($defauts['genre']);
    }

    if ($defauts['horaire_debut'] !== '')
    {
        $defauts_evenement['Début'] = $defauts['horaire_debut'];
    }

    if ($defauts['horaire_fin'] !== '')
    {
        $defauts_evenement['Fin'] = $defauts['horaire_fin'];
    }

    if ($defauts['idLieu'] > 0)
    {
        $lieu_defaut = Lieu::getLieu($defauts['idLieu']);

        if ($lieu_defaut !== [])
        {
            $defauts_evenement['Lieu'] = $lieu_defaut['nom'];
        }
    }

    if ($defauts['idOrganisateurs'] !== [])
    {
        list($orgas_clause, $orgas_params) = $connectorPdo->buildInClause('idOrganisateur', $defauts['idOrganisateurs']);
        $stmt = $connectorPdo->prepare("SELECT nom FROM organisateur WHERE $orgas_clause ORDER BY nom");
        $stmt->execute($orgas_params);
        $noms_orgas = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if ($noms_orgas !== [])
        {
            $defauts_evenement['Organisateurs'] = implode(', ', $noms_orgas);
        }
    }

    if ($defauts['prix'] !== '')
    {
        $defauts_evenement['Prix'] = $defauts['prix'];
    }
}

// invariant : sorti de la boucle de rendu, où il était réévalué à chaque ligne
$est_editeur = $authorization->isPersonneEditor($_SESSION);

// le niveau n'est une information utile qu'à ceux qui peuvent le changer
$voit_le_groupe = $_SESSION['Sgroupe'] <= UserLevel::ADMIN;

// Icônes propres à la page, en Font Awesome comme le reste du site. Les
// équivalents globaux ($iconeEditer, $icone['depublier']) restent des PNG
// servis à d'autres pages : les migrer est un chantier distinct.
$icone_editer = '<i class="fa fa-pencil" aria-hidden="true"></i>';
$icone_depublier = '<i class="fa fa-calendar-times-o" aria-hidden="true"></i>';
$icones_onglets = ["evenement" => "fa-calendar-o", "description" => "fa-file-text-o"];

$colonnes = $get['elements'] === "evenement"
    ? ["dateEvenement" => "Date", "idLieu" => "Lieu", "titre" => "Titre", "dateAjout" => "Date d'ajout", "statut" => ""]
    : ["idLieu" => "Lieu", "contenu" => "Contenu", "type" => "Type", "dateAjout" => "Date d'ajout"];

$url_base = "?idP=" . $get['idP'] . "&amp;elements=" . $get['elements'];
$url_pagination = "?idP=" . $get['idP'] . "&elements=" . $get['elements'] . "&tri=" . $get['tri']
    . "&ordre=" . $get['ordre'] . "&nblignes=" . $get['nblignes'] . "&page=";

// =============================================================================
// Rendu
// =============================================================================

$page_titre = "profil";
include("_header.inc.php");

if ($erreur !== null)
{
    echo '<main id="contenu" class="colonne user">';
    HtmlShrink::msgErreur($erreur);
    echo '</main>';
    include("_footer.inc.php");
    exit;
}
?>

<main id="contenu" class="colonne user">

	<header id="entete_contenu">
		<h1>Compte de <em><?= sanitizeForHtml($profil['pseudo']) ?></em><?php if ($voit_le_groupe) : ?> <span class="profil-groupe">[<?= sanitizeForHtml(UserLevel::getName((int) $profil['groupe'])) ?>]</span><?php endif; ?><?php if ($profil['statut'] !== 'actif') : ?> <span class="even-statut-label statut-<?= sanitizeForHtml($profil['statut']) ?>"><?= mb_strtoupper(sanitizeForHtml($profil['statut'])) ?></span><?php endif; ?></h1>
		<div class="spacer"></div>
	</header>

	<div class="spacer"></div>

	<div id="profile">

		<table>
			<tr><th>Identifiant</th><td><?= sanitizeForHtml($profil['pseudo']) ?></td></tr>
			<tr><th>E-mail</th><td><?= sanitizeForHtml($profil['email']) ?></td></tr>
			<tr><th>Affiliations</th><td>
				<?php foreach ($affiliations_lieux as $lieu_affilie) : ?>
					<a href="/lieu/lieu.php?idL=<?= (int) $lieu_affilie['idLieu'] ?>" title="Voir la fiche du lieu : <?= sanitizeForHtml($lieu_affilie['nom']) ?>"><?= sanitizeForHtml($lieu_affilie['nom']) ?></a><br />
				<?php endforeach; ?>
				<?php // le texte libre s'ajoute au lieu, il ne s'y substitue plus ?>
				<?php if (!empty($profil['affiliation'])) : ?>
					<?= sanitizeForHtml($profil['affiliation']) ?><br />
				<?php endif; ?>
				<?php if ($organisateurs !== []) : ?>
					<?= Organisateur::getListLinkedHtml($organisateurs, isWithOrganisateurUrl: false) ?>
				<?php endif; ?>
			</td></tr>
			<tr><th>Votre signature des événements ajoutés</th><td><?= Personne::getSignatureHtml((int) $profil['idPersonne']) ?: '<em>aucune</em>' ?></td></tr>
			<?php if ($defauts_evenement !== []) : ?>
			<tr><th>Événements, valeurs par défaut</th><td>
				<?php $paires = []; foreach ($defauts_evenement as $intitule => $valeur) { $paires[] = $intitule . ' : ' . sanitizeForHtml($valeur); } ?>
				<?= implode(', ', $paires) ?>
			</td></tr>
			<?php endif; ?>
			<tr><th>Inscription</th><td><?= DateHelper::isoToFr(mb_substr((string) $profil['dateAjout'], 0, 10), 'annee', showDayOfWeek: false) ?></td></tr>
		</table>
		<?php if ($peut_gerer) : ?>
		<a class="profil-modifier" href="/user-edit.php?idP=<?= (int) $get['idP'] ?>&amp;action=editer"><i class="fa fa-pencil" aria-hidden="true"></i>&nbsp;Modifier</a>
		<?php endif; ?>
	</div>

	<?php if ($_SESSION['Sgroupe'] <= UserLevel::ACTOR) : ?>
	<nav class="tabs" aria-label="Contenus ajoutés">
		<?php foreach ($tab_elements as $cle_element => $libelle_element) : ?>
		<a href="?idP=<?= (int) $get['idP'] ?>&amp;elements=<?= $cle_element ?>&amp;tri=<?= $get['tri'] ?>&amp;ordre=<?= $get['ordre'] ?>&amp;nblignes=<?= $get['nblignes'] ?>"<?= $get['elements'] === $cle_element ? ' class="ici"' : '' ?>><i class="fa <?= $icones_onglets[$cle_element] ?>" aria-hidden="true"></i>&nbsp;<?= $libelle_element ?></a>
		<?php endforeach; ?>
	</nav>
	<?php endif; ?>

	<?= HtmlShrink::getPaginationString($tot_elements, $get['page'], $get['nblignes'], 1, "", $url_pagination) ?>

	<?php if ($tot_elements === 0) : ?>

		<p><?= $get['elements'] === "evenement" ? "Aucun événement ajouté pour le moment" : "Aucune description ajoutée pour le moment" ?></p>

	<?php else : ?>

		<ul id="menu_nb_res">
			<?php foreach ($tab_nblignes as $nbl) : ?>
			<li<?= $get['nblignes'] === $nbl ? ' class="ici"' : '' ?>><a href="?<?= Utils::urlQueryArrayToString($get, "nblignes") ?>&amp;nblignes=<?= (int) $nbl ?>"><?= (int) $nbl ?></a></li>
			<?php endforeach; ?>
		</ul>
		<div class="spacer"><!-- --></div>

		<table id="ajouts">
			<thead>
				<tr>
					<?php foreach ($colonnes as $att => $libelle) : ?>
						<?php if (!array_key_exists($att, $tab_tri[$get['elements']])) : ?>
					<th><?= $libelle ?></th>
						<?php else : ?>
					<th<?= $att === $get['tri'] ? ' class="ici"' : '' ?>><?= $att === $get['tri'] ? $icone[$get['ordre']] : '' ?><a href="<?= $url_base ?>&amp;page=<?= $get['page'] ?>&amp;tri=<?= $att ?>&amp;ordre=<?= $ordre_inverse ?>&amp;nblignes=<?= $get['nblignes'] ?>"><?= $libelle ?></a></th>
						<?php endif; ?>
					<?php endforeach; ?>
					<th></th>
				</tr>
			</thead>
			<tbody>

			<?php if ($get['elements'] === "evenement") : ?>

				<?php foreach ($tab_evens as $tab_even) : ?>
					<?php
					// un événement passé est une archive : non modifiable (sauf par un éditeur),
					// et sa ligne est atténuée pour que la disparition du bouton Éditer se comprenne
					$is_even_ancien = DateHelper::isEvenementPast($tab_even['dateEvenement'], $tab_even['horaire_fin']);
					$can_edit_even = !$is_even_ancien || $est_editeur;
					?>
				<tr<?= $is_even_ancien ? ' class="ancien"' : '' ?>>
					<td><?= DateHelper::isoToApp($tab_even['dateEvenement']) ?></td>
					<td>
						<?php if ((int) $tab_even['idLieu'] !== 0 && $tab_even['lieu_nom'] !== null) : ?>
						<a href="/lieu/lieu.php?idL=<?= (int) $tab_even['idLieu'] ?>" title="Voir la fiche du lieu : <?= sanitizeForHtml($tab_even['lieu_nom']) ?>"><?= sanitizeForHtml($tab_even['lieu_nom']) ?></a>
						<?php else : ?>
						<?= sanitizeForHtml($tab_even['nomLieu']) ?>
						<?php endif; ?>
					</td>
					<td><a href="/event/evenement.php?idE=<?= (int) $tab_even['idEvenement'] ?>" title="Voir la fiche de l'événement"><?= sanitizeForHtml($tab_even['titre']) ?></a></td>
					<td><?= DateHelper::isoToApp(mb_substr((string) $tab_even['dateAjout'], 0, 10)) ?></td>
					<td><?= EvenementRenderer::$iconStatus[$tab_even['statut']] ?></td>
					<td class="actions">
						<?php if ($tab_even['statut'] !== 'inactif') : ?>
						<?= EvenementRenderer::unpublishLinkHtml((int) $tab_even['idEvenement'], $icone_depublier, EvenementRenderer::UNPUBLISH_THEN_STATUS) ?>&nbsp;
						<?php endif; ?>
						<?php if ($can_edit_even) : ?>
						<a href="/evenement-edit.php?action=editer&amp;idE=<?= (int) $tab_even['idEvenement'] ?>" title="Éditer l'événement"><?= $icone_editer ?></a>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>

			<?php else : ?>

				<?php foreach ($tab_descs as $tab_desc) : ?>
					<?php
					$contenu = (string) $tab_desc['contenu'];
					if (mb_strlen($contenu) > 200)
					{
						$contenu = mb_substr($contenu, 0, 200) . " [...]";
					}
					?>
				<tr>
					<td><a href="/lieu/lieu.php?idL=<?= (int) $tab_desc['idLieu'] ?>" title="Voir la fiche du lieu"><?= sanitizeForHtml((string) $tab_desc['lieu_nom']) ?></a></td>
					<td class="tdleft contenu"><?= Text::lnAndUrlToHtml(sanitizeForHtml($contenu)) ?></td>
					<td><?= sanitizeForHtml($tab_desc['type']) ?></td>
					<td><?= DateHelper::isoToApp(mb_substr((string) $tab_desc['dateAjout'], 0, 10)) ?></td>
					<td class="actions">
						<a href="/lieu-text-edit.php?action=editer&amp;idL=<?= (int) $tab_desc['idLieu'] ?>&amp;idP=<?= (int) $get['idP'] ?>&amp;type=<?= sanitizeForHtml($tab_desc['type']) ?>" title="Éditer le texte"><?= $icone_editer ?></a>
					</td>
				</tr>
				<?php endforeach; ?>

			<?php endif; ?>

			</tbody>
		</table>

	<?php endif; ?>

	<?= HtmlShrink::getPaginationString($tot_elements, $get['page'], $get['nblignes'], 1, "", $url_pagination) ?>

</main>

<div id="colonne_gauche" class="colonne">
<?php
include("event/_navigation_calendrier.inc.php");
?>
</div>

<?php
include("_footer.inc.php");
?>
