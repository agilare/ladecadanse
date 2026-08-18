<?php

require_once("../app/bootstrap.php");

use Ladecadanse\UserLevel;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Personne;
use Ladecadanse\Organisateur;
use Ladecadanse\Evenement;
use Ladecadanse\EvenementRenderer;
use Ladecadanse\Lieu;
use Ladecadanse\UserSettings;
use Ladecadanse\Utils\DateHelper;
use Ladecadanse\Utils\Text;

// =============================================================================
// Traitement
//
// Tout se joue avant l'inclusion de _header.inc.php : les sorties en erreur
// laissaient auparavant un document tronqué, sans </main> ni pied de page.
// =============================================================================

if (!$authorization->checkGroup(UserLevel::MEMBER))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
    header("Location: /user/login.php");
    die();
}

// Message déposé par user-edit.php avant sa redirection. Lu et effacé dès le
// traitement : une sortie en erreur plus bas ne doit pas le laisser en session,
// où il resurgirait à la visite suivante.
$flash_msg = (string) ($_SESSION['user_flash_msg'] ?? "");
unset($_SESSION['user_flash_msg']);

$tab_elements = ["evenement" => "Événements", "description" => "Descriptions"];

// Colonnes triables par onglet, et leur nom qualifié dans la requête.
// Le tri était auparavant commun aux deux onglets : trier les événements par
// titre puis forcer ?elements=description produisait une erreur SQL. Les noms
// sont qualifiés parce que dateAjout existe aussi dans lieu, joint plus bas.
$tab_tri = [
    "evenement" => [
        "titre" => "e.titre",
        "dateEvenement" => "e.dateEvenement",
        "genre" => "e.genre",
        "horaire" => "e.horaire_debut",
        "dateAjout" => "e.dateAjout",
        "statut" => "e.statut",
    ],
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

// Le 50 de $tab_nblignes n'a pas de sens ici : la page en affiche déjà 100 par défaut,
// et proposer moins que la valeur par défaut n'aide personne.
$tab_nblignes_profil = [100, 250, 500];

// borné à la liste du menu : une valeur libre laissait passer LIMIT 0, 999999
$get['nblignes'] = 100;
if (isset($_GET['nblignes']) && in_array((int) $_GET['nblignes'], $tab_nblignes_profil, true))
{
    $get['nblignes'] = (int) $_GET['nblignes'];
}

// filtre par titre, propre à l'onglet Événements
$get['terme'] = ($get['elements'] === "evenement" && isset($_GET['terme'])) ? trim((string) $_GET['terme']) : "";

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
$signature = "";
$tab_evens = [];
$tab_descs = [];
// Total par onglet, filtre exclu : c'est le compteur porté par les onglets eux-mêmes, qui
// annoncent ce que chacun contient. $tot_elements, lui, commande la pagination et suit le filtre.
$totaux = ["evenement" => 0, "description" => 0];
$tot_elements = 0;

if ($erreur === null)
{
    $affiliations_lieux = Personne::getAffiliationsLieux($get['idP']);
    $organisateurs = Personne::getOrganisateurs($get['idP']);
    $signature = Personne::getSignatureHtml($get['idP']);

    $stmt = $connectorPdo->prepare("SELECT COUNT(*) FROM evenement WHERE idPersonne = :idP");
    $stmt->execute([':idP' => $get['idP']]);
    $totaux['evenement'] = (int) $stmt->fetchColumn();

    $stmt = $connectorPdo->prepare("SELECT COUNT(*) FROM descriptionlieu WHERE idPersonne = :idP");
    $stmt->execute([':idP' => $get['idP']]);
    $totaux['description'] = (int) $stmt->fetchColumn();

    $tot_elements = $totaux[$get['elements']];

    $offset = ($get['page'] - 1) * $get['nblignes'];
    // valeurs issues de la liste blanche $tab_tri, jamais de la requête
    $order_by = $tab_tri[$get['elements']][$get['tri']] . " " . $get['ordre'];

    if ($get['elements'] === "evenement")
    {
        $filtre_titre = $get['terme'] === "" ? "" : " AND LOWER(e.titre) LIKE LOWER(:terme)";
        $params_titre = $get['terme'] === "" ? [] : [':terme' => "%" . $get['terme'] . "%"];

        // le filtre s'applique au décompte comme à la liste, sans quoi la
        // pagination annoncerait des pages vides
        if ($get['terme'] !== "")
        {
            $stmt = $connectorPdo->prepare("SELECT COUNT(*) FROM evenement e WHERE e.idPersonne = :idP" . $filtre_titre);
            $stmt->execute([':idP' => $get['idP']] + $params_titre);
            $tot_elements = (int) $stmt->fetchColumn();
        }

        // le nom du lieu vient de la jointure, plus d'une requête par ligne ;
        // evenement.nomLieu reste le secours pour les lieux hors base
        $stmt = $connectorPdo->prepare("SELECT
            e.idEvenement, e.idLieu, e.statut, e.titre, e.genre, e.dateEvenement,
            e.horaire_debut, e.horaire_fin, e.nomLieu, e.dateAjout,
            l.nom AS lieu_nom
            FROM evenement e
            LEFT JOIN lieu l ON e.idLieu = l.idLieu
            WHERE e.idPersonne = :idP" . $filtre_titre . "
            ORDER BY $order_by
            LIMIT :offset, :nblignes");
        $stmt->bindValue(':idP', $get['idP'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':nblignes', $get['nblignes'], PDO::PARAM_INT);

        foreach ($params_titre as $nom_param => $valeur_param)
        {
            $stmt->bindValue($nom_param, $valeur_param, PDO::PARAM_STR);
        }

        $stmt->execute();
        $tab_evens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    else
    {
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

// Les actions des tableaux reprennent les icônes globales, celles-là mêmes qu'affichent les autres
// écrans de gestion (admin/gererEvenements.php) : même geste, même image. Les onglets, eux, sont
// propres à la page et suivent le Font Awesome du reste du site.
$icone_editer = $iconeEditer;
$icone_depublier = $icone['depublier'];
$icones_onglets = ["evenement" => "fa-calendar-o", "description" => "fa-file-text-o"];

$colonnes = $get['elements'] === "evenement"
    ? ["titre" => "Titre", "idLieu" => "Lieu", "dateEvenement" => "Date", "genre" => "Catégorie", "horaire" => "Horaire", "dateAjout" => "Date d'ajout", "statut" => "Statut"]
    : ["idLieu" => "Lieu", "contenu" => "Contenu", "type" => "Type", "dateAjout" => "Date d'ajout"];

// Fragment commun à tous les liens de la page : identité, onglet, pagination et
// filtre. Construit à la main plutôt qu'avec HtmlShrink::urlQueryArrayToString(), qui
// n'encode pas les valeurs — un terme de recherche contenant & ou un espace
// casserait les liens.
$url_params = "idP=" . $get['idP'] . "&elements=" . $get['elements'] . "&nblignes=" . $get['nblignes'];

if ($get['terme'] !== "")
{
    $url_params .= "&terme=" . urlencode($get['terme']);
}

// HtmlShrink::getPaginationString() concatène l'URL telle quelle dans le href
$url_pagination = "?" . $url_params . "&tri=" . $get['tri'] . "&ordre=" . $get['ordre'] . "&page=";

// =============================================================================
// Rendu
// =============================================================================

$page_titre = "profil";
include("../_header.inc.php");

if ($erreur !== null)
{
    echo '<main id="contenu" class="colonne user">';
    HtmlShrink::msgErreur($erreur);
    echo '</main>';
    include("../_footer.inc.php");
    exit;
}
?>

<main id="contenu" class="colonne user">

	<?php if ($flash_msg !== "") : ?>
	<?php HtmlShrink::msgOk($flash_msg); ?>
	<?php endif; ?>

	<header id="entete_contenu">
		<h1>Compte de <em><?= sanitizeForHtml($profil['pseudo']) ?></em><?php if ($voit_le_groupe) : ?> <span class="profil-groupe">[<?= sanitizeForHtml(UserLevel::getName((int) $profil['groupe'])) ?>]</span><?php endif; ?><?php if ($profil['statut'] !== 'actif') : ?> <span class="even-statut-label statut-<?= sanitizeForHtml($profil['statut']) ?>"><?= mb_strtoupper(sanitizeForHtml($profil['statut'])) ?></span><?php endif; ?></h1>
		<div class="spacer"></div>
	</header>

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
			<tr><th>Votre signature des événements ajoutés</th><td><?= $signature ?: '<em>aucune</em>' ?></td></tr>
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
		<a href="?idP=<?= (int) $get['idP'] ?>&amp;elements=<?= $cle_element ?>&amp;tri=<?= $get['tri'] ?>&amp;ordre=<?= $get['ordre'] ?>&amp;nblignes=<?= $get['nblignes'] ?>"<?= $get['elements'] === $cle_element ? ' class="ici"' : '' ?>><i class="fa <?= $icones_onglets[$cle_element] ?>" aria-hidden="true"></i>&nbsp;<?= $libelle_element ?><sup><?= $totaux[$cle_element] ?></sup></a>
		<?php endforeach; ?>
	</nav>
	<?php endif; ?>

	<?php if ($get['elements'] === "evenement") : ?>

	<?php // l'état du tableau voyage en champs cachés : filtrer ne doit pas perdre le tri ni la pagination ?>
	<form method="get" action="" class="filtre-liste">
		<input type="hidden" name="idP" value="<?= (int) $get['idP'] ?>" />
		<input type="hidden" name="elements" value="evenement" />
		<input type="hidden" name="tri" value="<?= sanitizeForHtml($get['tri']) ?>" />
		<input type="hidden" name="ordre" value="<?= sanitizeForHtml($get['ordre']) ?>" />
		<input type="hidden" name="nblignes" value="<?= (int) $get['nblignes'] ?>" />
		<span class="search-field">
			<input type="search" name="terme" value="<?= sanitizeForHtml($get['terme']) ?>" placeholder="Titre" size="24" aria-label="Filtrer par titre" />
			<button type="button" class="js-clear-search-field" aria-label="Vider et relancer la recherche" title="Vider et relancer la recherche"></button>
		</span>
		<?php // sans name : un champ nommé « submit » masque form.submit(), et le bouton de vidage ne relançait alors plus la recherche ?>
		<input type="submit" value="Filtrer" />
		<?php // l'onglet annonce le total, le filtre annonce ce qu'il en retient ?>
		<?php if ($get['terme'] !== "" && $tot_elements > 0) : ?>
		<span class="filtre-resultat"><?= $tot_elements ?> sur <?= $totaux['evenement'] ?></span>
		<?php endif; ?>
	</form>
	<div class="spacer"><!-- --></div>

	<?php endif; ?>

	<?php // pagination et choix du nombre de lignes sur une même ligne, l'un à gauche, l'autre à droite ?>
	<div class="liste-barre">
		<?= HtmlShrink::getPaginationString($tot_elements, $get['page'], $get['nblignes'], 1, "", $url_pagination) ?>
		<?php // le choix du nombre de lignes ne sert que sur les événements, seule liste qui peut être longue ?>
		<?php if ($get['elements'] === "evenement" && $tot_elements > 0) : ?>
		<div id="order_navigation">
			<ul>
				<li><i class="fa fa-list-ol" aria-hidden="true" title="Nombre de lignes"></i></li>
				<?php foreach ($tab_nblignes_profil as $nbl) : ?>
				<li><a href="?idP=<?= (int) $get['idP'] ?>&amp;elements=<?= $get['elements'] ?>&amp;tri=<?= $get['tri'] ?>&amp;ordre=<?= $get['ordre'] ?><?= $get['terme'] === "" ? '' : '&amp;terme=' . urlencode($get['terme']) ?>&amp;nblignes=<?= (int) $nbl ?>"<?= $get['nblignes'] === $nbl ? ' class="selected"' : '' ?> rel="nofollow"><?= (int) $nbl ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php endif; ?>
	</div>

	<?php if ($tot_elements === 0) : ?>

		<p><?php if ($get['elements'] !== "evenement") : ?>Aucune description ajoutée pour le moment<?php elseif ($get['terme'] !== "") : ?>Aucun événement ne correspond à ce titre<?php else : ?>Aucun événement ajouté pour le moment<?php endif; ?></p>

	<?php else : ?>

		<table id="ajouts">
			<thead>
				<tr>
					<?php foreach ($colonnes as $att => $libelle) : ?>
						<?php if (!array_key_exists($att, $tab_tri[$get['elements']])) : ?>
					<th><?= $libelle ?></th>
						<?php else : ?>
					<th<?= $att === $get['tri'] ? ' class="ici"' : '' ?>><?= $att === $get['tri'] ? $icone[$get['ordre']] : '' ?><a href="?<?= sanitizeForHtml($url_params) ?>&amp;page=<?= $get['page'] ?>&amp;tri=<?= $att ?>&amp;ordre=<?= $ordre_inverse ?>"><?= $libelle ?></a></th>
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
					<td class="tdleft"><a href="/event/evenement.php?idE=<?= (int) $tab_even['idEvenement'] ?>" title="Voir la fiche de l'événement"><?= sanitizeForHtml($tab_even['titre']) ?></a></td>
					<td>
						<?php if ((int) $tab_even['idLieu'] !== 0 && $tab_even['lieu_nom'] !== null) : ?>
						<a href="/lieu/lieu.php?idL=<?= (int) $tab_even['idLieu'] ?>" title="Voir la fiche du lieu : <?= sanitizeForHtml($tab_even['lieu_nom']) ?>"><?= sanitizeForHtml($tab_even['lieu_nom']) ?></a>
						<?php else : ?>
						<?= sanitizeForHtml($tab_even['nomLieu']) ?>
						<?php endif; ?>
					</td>
					<td><?= DateHelper::isoToApp($tab_even['dateEvenement']) ?></td>
					<td><?= sanitizeForHtml(Evenement::genreLabel($tab_even['genre'])) ?></td>
					<td class="horaire"><?= EvenementRenderer::schedulesToHhMm((string) $tab_even['horaire_debut'], (string) $tab_even['horaire_fin'], (string) $tab_even['dateEvenement']) ?></td>
					<td><?= DateHelper::isoToApp(mb_substr((string) $tab_even['dateAjout'], 0, 10)) ?></td>
					<?php // nommée pour que le CSS puisse l'exclure de l'atténuation des lignes passées ?>
					<td class="statut"><?= EvenementRenderer::$iconStatus[$tab_even['statut']] ?></td>
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
					// les descriptions sont saisies en HTML : on n'en garde que le texte,
					// sinon la cellule héritait de balises ouvertes et de mise en forme
					$contenu = trim(html_entity_decode(strip_tags((string) $tab_desc['contenu']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

					if (mb_strlen($contenu) > 120)
					{
						$contenu = mb_substr($contenu, 0, 120) . " […]";
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
include("../event/_navigation_calendrier.inc.php");
?>
</div>

<?php
include("../_footer.inc.php");
?>
