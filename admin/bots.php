<?php

require_once("../app/bootstrap.php");

use Ladecadanse\BotMonitor;
use Ladecadanse\UserLevel;
use Ladecadanse\Utils\DateHelper;
use Ladecadanse\Utils\QueryParamValidator;
use Ladecadanse\HtmlShrink;

if (!$authorization->checkGroup(UserLevel::ADMIN))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
	header("Location: /user/login.php"); die();
}

// vues du dashboard : onglet courant via ?view=
$views = [
    'scrapers' => "Scrapers avérés (honeypot)",
    'suspects' => "Humains suspects",
    'rafales' => "Rafales",
    'sondeurs' => "Sondeurs",
    'officiels' => "Statistiques des bots"
];
$view = isset($_GET['view']) && isset($views[$_GET['view']]) ? $_GET['view'] : 'scrapers';

$window = BotMonitor::WINDOW_MINUTES;
$suspect_default = defined('BOT_MONITORING_SUSPECT_THRESHOLD') ? BOT_MONITORING_SUSPECT_THRESHOLD : 150;

// vues filtrées par un seuil, modifiable par filtre : libellé du champ et valeur par défaut
$threshold_views = [
    'suspects' => ["Seuil de pages vues", $suspect_default],
    'rafales' => ["Pic minimal (pages vues en $window min)", BotMonitor::BURST_THRESHOLD],
    'sondeurs' => ["Erreurs minimales", BotMonitor::ERROR_THRESHOLD],
];
[$threshold_label, $threshold] = $threshold_views[$view] ?? ['', 0];

// le seuil choisi suit la pagination, sans quoi la page 2 retombait sur le seuil par défaut
$pagination_url = "?view=" . $view;
if (isset($threshold_views[$view]) && !empty($_GET['seuil']) && QueryParamValidator::isAcceptedUrlQueryValue($_GET['seuil'], "int"))
{
    $threshold = (int) $_GET['seuil'];
    $pagination_url .= "&seuil=" . $threshold;
}
$pagination_url .= "&page=";

// pagination
$get = [];
$get['page'] = QueryParamValidator::pageFromQuery($_GET['page'] ?? '');

$_SESSION['user_prefs_bots_nblignes'] ??= $tab_nblignes[0];
if (!empty($_GET['nblignes']) && in_array($_GET['nblignes'], $tab_nblignes))
{
   $_SESSION['user_prefs_bots_nblignes'] = $_GET['nblignes'];
}
$nblignes = (int) $_SESSION['user_prefs_bots_nblignes'];
$offset = ($get['page'] - 1) * $nblignes;

// ce que chaque onglet montre : une phrase toujours visible, puis le détail replié
$help = [
    'scrapers' => [
        "Adresses qui ont suivi le lien invisible du pied de page, interdit par robots.txt : seuls des robots qui aspirent le site sans en respecter les règles le font.",
        "<p>Le piège est un lien hors écran, présent dans le pied de chaque page, ignoré des lecteurs d'écran et interdit dans <code>robots.txt</code>. Aucun visiteur ne le voit, un robot honnête ne le suit pas.</p>
        <p><strong>Type</strong> : « navigateur », le robot se fait passer pour Firefox ou Chrome ; « script », il ne s'en cache pas ; une famille (Google…), un robot qui se déclare mais ignore <code>robots.txt</code>, ou un usurpateur.</p>
        <p><strong>Que faire</strong> : bloquer l'adresse ou sa plage. Une même campagne change souvent d'adresse en gardant son User-Agent et ses horaires : chercher ses sœurs dans les autres onglets. Ces lignes sont conservées un an.</p>"
    ],
    'suspects' => [
        "Adresses qui se présentent comme un navigateur, sans marque de robot ni passage par le piège, mais avec plus de $threshold pages sur la période : un volume que peu de visiteurs atteignent.",
        "<p><strong>Faux positifs</strong> : une adresse partagée (réseau mobile, école, entreprise, VPN) additionne ses utilisateurs, et les appels en arrière-plan du site (calendrier, recherche) comptent comme des pages.</p>
        <p><strong>Pour trancher</strong> : l'étalement entre première et dernière visite (un habitué s'étale, un robot se concentre), le User-Agent complet en survolant la cellule (une version de navigateur vieille d'un an trahit un script), et le rythme dans l'onglet Rafales.</p>
        <p>Les programmes qui ne se cachent pas — <code>node</code>, <code>curl</code>, outils d'automatisation, agrégateurs — ne sont pas ici : classés « Script », ils comptent dans Statistiques des bots.</p>"
    ],
    'rafales' => [
        "Qui charge le site le plus vite : pages vues par fenêtre de $window minutes, tous visiteurs confondus, robots déclarés compris.",
        "<p><strong>En ce moment</strong> : les fenêtres ouvertes dans les $window dernières minutes, sans seuil. Pendant un incident, c'est ici que cela se voit.</p>
        <p><strong>Plus forts pics</strong> : la plus forte fenêtre de chaque adresse sur la période, et sa date.</p>
        <p><strong>Ordres de grandeur</strong> : un visiteur lit quelques pages par minute ; 100 pages en $window minutes est déjà un robot ou un réseau partagé ; 600, une requête par seconde.</p>
        <p><strong>Limites</strong> : fenêtres fixes, donc une rafale à cheval sur deux fenêtres est sous-estimée jusqu'à moitié ; un seul pic gardé par adresse ; un opérateur à plusieurs adresses est divisé d'autant ; une adresse partagée additionne.</p>"
    ],
    'sondeurs' => [
        "Adresses dont les requêtes finissent en erreur 4xx — page inexistante, accès refusé, identifiant inconnu : sondeurs de failles, scrapers qui itèrent sur des identifiants, ou robots déjà bloqués.",
        "<p><strong>Comptées</strong> : les erreurs 4xx hors fichiers statiques — une image manquante dit quelque chose du site, pas du visiteur.</p>
        <p><strong>Lire le dernier chemin</strong> : <code>/wp-login.php</code>, <code>/.env</code>, <code>/xmlrpc.php</code>, quelqu'un cherche un WordPress ou des secrets, sans objet ici ; <code>/event/evenement.php</code>, une itération sur des identifiants ; un chemin ordinaire avec une part proche de 100 %, une adresse ou un User-Agent que le serveur refuse, la page d'erreur étant comptée.</p>
        <p>Un visiteur égaré en produit quelques-unes : c'est le volume et le chemin qui font le sondeur.</p>"
    ],
    'officiels' => [
        "Les robots qui se déclarent dans leur User-Agent, regroupés par famille : moteurs de recherche, IA, outils SEO, scripts et autres.",
        "<p>La famille vient du User-Agent, qui s'usurpe : un « Googlebot » se vérifie par résolution inverse de son adresse.</p>
        <p><strong>Leviers</strong> : <code>robots.txt</code> pour ceux qui le respectent — les moteurs, la plupart des IA déclarées — et le blocage par User-Agent dans la configuration du serveur pour les autres.</p>
        <p><strong>Pic</strong> : la plus forte fenêtre de $window minutes d'une adresse de la famille.</p>"
    ],
];

/**
 * Cellule « Type » d'une ligne : son genre, et en dessous ce que l'adresse annonce quand cela
 * le précise — la famille d'un robot, ou pour un scraper le déguisement qu'il porte.
 *
 * @param array<string, mixed> $r
 */
function botKindCell(array $r, int $suspectThreshold): string
{
    $kind = BotMonitor::classify($r, $suspectThreshold);
    [$label, $hint] = BotMonitor::KIND_LABELS[$kind];

    $family = $r['bot_family'] ?? null;
    $detail = '';
    if ($kind === BotMonitor::KIND_SCRAPER)
    {
        $detail = BotMonitor::declaredIdentity($family, (string) ($r['user_agent'] ?? ''));
    }
    elseif ($family !== null && !in_array($family, [BotMonitor::FAMILY_SCRIPT, BotMonitor::FAMILY_OTHER_BOT], true))
    {
        $detail = $family;
    }

    return '<td class="bots-kind">'
        . '<span class="bots-kind-badge bots-kind-badge--' . $kind . '" title="' . sanitizeForHtml($hint) . '">' . sanitizeForHtml($label) . '</span>'
        . ($detail !== '' ? '<span class="bots-kind-detail">' . sanitizeForHtml($detail) . '</span>' : '')
        . '</td>';
}

/** en-tête de colonne avec son bouton d'explication ; $id doit être unique dans la page */
function botTh(string $label, string $id, string $help): string
{
    return '<th>' . sanitizeForHtml($label) . ' ' . HtmlShrink::helpPopover($id, $label, $help) . '</th>';
}

// textes des bulles communs à plusieurs tableaux
$help_ua = "Signature envoyée par le client, coupée à 90 caractères : survoler la cellule pour la lire en entier. Elle se choisit librement, un robot peut copier celle d'un navigateur.";
$help_kind = "Ce que l'adresse annonce (User-Agent) combiné à ce qu'elle a fait (piège, volume). Survoler le libellé pour sa définition ; la légende complète est sous « Comment lire ce tableau de bord ».";
$help_hits = "Requêtes servies par PHP sur la période de conservation, pages d'erreur comprises : une adresse refusée par le serveur continue d'être comptée.";
$help_peak = "Plus forte fenêtre de $window minutes de cette adresse sur la période.";

// compteurs globaux (une seule requête agrégée)
$totals = $connectorPdo->query(
    "SELECT COUNT(*) AS nb_ips,
        COALESCE(SUM(hit_count), 0) AS nb_hits,
        COALESCE(SUM(honeypot_triggered), 0) AS nb_honeypot,
        COALESCE(SUM(is_crawler_detect), 0) AS nb_crawlers
    FROM bot_monitor"
)->fetch(PDO::FETCH_ASSOC);

$all_results_nb = 0;
$rows = [];
$rows_now = [];

// colonnes que le type d'une ligne demande, en plus de celles que la vue affiche
$kind_columns = "honeypot_triggered, is_crawler_detect, bot_family, hit_count, peak_hits";

if ($view == 'scrapers')
{
    $stmt = $connectorPdo->query("SELECT COUNT(*) FROM bot_monitor WHERE honeypot_triggered = 1");
    $all_results_nb = (int) $stmt->fetchColumn();

    $stmt = $connectorPdo->prepare(
        "SELECT ip, user_agent, first_seen, last_seen, $kind_columns
        FROM bot_monitor
        WHERE honeypot_triggered = 1
        ORDER BY hit_count DESC
        LIMIT :limit OFFSET :offset"
    );
    $stmt->bindValue(':limit', $nblignes, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
elseif ($view == 'suspects')
{
    $stmt = $connectorPdo->prepare(
        "SELECT COUNT(*) FROM bot_monitor
        WHERE is_crawler_detect = 0 AND honeypot_triggered = 0 AND hit_count > :threshold"
    );
    $stmt->bindValue(':threshold', $threshold, PDO::PARAM_INT);
    $stmt->execute();
    $all_results_nb = (int) $stmt->fetchColumn();

    $stmt = $connectorPdo->prepare(
        "SELECT ip, user_agent, first_seen, last_seen, $kind_columns
        FROM bot_monitor
        WHERE is_crawler_detect = 0 AND honeypot_triggered = 0 AND hit_count > :threshold
        ORDER BY hit_count DESC
        LIMIT :limit OFFSET :offset"
    );
    $stmt->bindValue(':threshold', $threshold, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $nblignes, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
elseif ($view == 'rafales')
{
    // toutes les IP, bots déclarés compris : une surcharge ne dépend pas de qui la cause
    $stmt = $connectorPdo->prepare("SELECT COUNT(*) FROM bot_monitor WHERE peak_hits >= :threshold");
    $stmt->bindValue(':threshold', $threshold, PDO::PARAM_INT);
    $stmt->execute();
    $all_results_nb = (int) $stmt->fetchColumn();

    $stmt = $connectorPdo->prepare(
        "SELECT ip, user_agent, peak_start, last_seen, $kind_columns
        FROM bot_monitor
        WHERE peak_hits >= :threshold
        ORDER BY peak_hits DESC, last_seen DESC
        LIMIT :limit OFFSET :offset"
    );
    $stmt->bindValue(':threshold', $threshold, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $nblignes, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // fenêtres encore ouvertes, sans seuil : pendant un incident, c'est la question posée.
    // La condition sur last_seen, impliquée par l'autre, est là pour idx_last_seen.
    $window_is_open = sprintf('> NOW() - INTERVAL %d MINUTE', $window);
    $rows_now = $connectorPdo->query(
        "SELECT ip, user_agent, window_hits, window_start, $kind_columns
        FROM bot_monitor
        WHERE last_seen $window_is_open AND window_start $window_is_open
        ORDER BY window_hits DESC
        LIMIT 10"
    )->fetchAll(PDO::FETCH_ASSOC);
}
elseif ($view == 'sondeurs')
{
    $stmt = $connectorPdo->prepare("SELECT COUNT(*) FROM bot_monitor WHERE error_hits >= :threshold");
    $stmt->bindValue(':threshold', $threshold, PDO::PARAM_INT);
    $stmt->execute();
    $all_results_nb = (int) $stmt->fetchColumn();

    $stmt = $connectorPdo->prepare(
        "SELECT ip, user_agent, error_hits, last_error_path, last_seen, $kind_columns
        FROM bot_monitor
        WHERE error_hits >= :threshold
        ORDER BY error_hits DESC, last_seen DESC
        LIMIT :limit OFFSET :offset"
    );
    $stmt->bindValue(':threshold', $threshold, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $nblignes, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
else // officiels
{
    // agrégat par famille normalisée (couvert par idx_family), tient sur une page
    $stmt = $connectorPdo->query(
        "SELECT bot_family,
            COUNT(*) AS nb_ips,
            SUM(hit_count) AS total_hits,
            MAX(peak_hits) AS pic_max,
            MAX(last_seen) AS derniere_visite
        FROM bot_monitor
        WHERE is_crawler_detect = 1
        GROUP BY bot_family
        ORDER BY total_hits DESC"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $all_results_nb = count($rows);
}

$page_titre = "Monitoring des bots";
$extra_css = ["admin/tables", "admin/bots"];
require_once '../_header.inc.php';
?>

<main id="contenu" class="colonne">

	<header id="entete_contenu">
		<h1>Monitoring des bots</h1>
        <div class="spacer"></div>
	</header>

    <section id="default">

        <ul class="bots-totaux">
            <li><strong><?= (int) $totals['nb_ips'] ?></strong> IP suivies</li>
            <li><strong><?= (int) $totals['nb_hits'] ?></strong> pages vues</li>
            <li><strong><?= (int) $totals['nb_crawlers'] ?></strong> robots déclarés</li>
            <li><strong><?= (int) $totals['nb_honeypot'] ?></strong> scrapers piégés</li>
        </ul>

        <details class="bots-help bots-help--global">
            <summary>Comment lire ce tableau de bord</summary>
            <div class="bots-help-body">
                <p>Une ligne par adresse IP, pour les visites sans compte connecté. Chaque ligne combine ce que l'adresse
                    <strong>annonce</strong> — son User-Agent, qui se choisit librement — et ce qu'elle <strong>fait</strong> :
                    passage par le piège, volume, rythme, erreurs. Le type est recalculé à chaque visite.</p>
                <dl class="bots-legend">
                    <?php foreach (BotMonitor::KIND_LABELS as $kind => [$label, $hint]) : ?>
                        <dt><span class="bots-kind-badge bots-kind-badge--<?= $kind ?>"><?= sanitizeForHtml($label) ?></span></dt>
                        <dd><?= sanitizeForHtml($hint) ?></dd>
                    <?php endforeach; ?>
                </dl>
                <p>Les pages vues comptent les requêtes servies par PHP, pages d'erreur comprises ; les images et feuilles de style
                    ne comptent pas. Une adresse sans visite depuis <?= BotMonitor::RETENTION_DAYS ?> jours est purgée,
                    <?= BotMonitor::RETENTION_DAYS_HONEYPOT ?> pour une adresse piégée.</p>
            </div>
        </details>

        <nav class="tabs">
            <?php foreach ($views as $view_key => $view_label) : ?>
                <a href="?view=<?= $view_key ?>" <?php if ($view_key == $view) : ?>class="ici"<?php endif; ?>><?= sanitizeForHtml($view_label) ?></a>
            <?php endforeach; ?>
        </nav>

        <details class="bots-help">
            <summary><?= sanitizeForHtml($help[$view][0]) ?></summary>
            <div class="bots-help-body"><?= $help[$view][1] ?></div>
        </details>

        <?php if (isset($threshold_views[$view])) : ?>
            <div id="filters">
                <form method="get" action="">
                    <input type="hidden" name="view" value="<?= $view ?>" />
                    <label for="seuil"><?= sanitizeForHtml($threshold_label) ?> :</label>
                    <input type="number" id="seuil" name="seuil" value="<?= (int) $threshold ?>" min="1" size="6" />
                    <input type="submit" value="Filtrer" />
                </form>
            </div>
        <?php endif; ?>

        <?php if ($view == 'rafales') : ?>

            <h2 class="bots-titre">En ce moment</h2>

            <?php if (empty($rows_now)) : ?>
                <p>Aucune visite anonyme dans les <?= $window ?> dernières minutes.</p>
            <?php else : ?>
                <div class="bots-table-wrapper">
                <table class="bots-now">
                    <tr>
                        <th>IP</th>
                        <?= botTh("User-Agent", 'now-ua', $help_ua) ?>
                        <?= botTh("Type", 'now-kind', $help_kind) ?>
                        <?= botTh("Fenêtre en cours", 'now-window', "Pages vues depuis l'ouverture de la fenêtre de $window minutes en cours.") ?>
                        <?= botTh("Ouverte à", 'now-open', "Début de la fenêtre en cours ; elle se ferme $window minutes plus tard, à la requête suivante.") ?>
                        <?= botTh("Pages vues", 'now-hits', $help_hits) ?>
                    </tr>
                    <?php foreach ($rows_now as $r) : ?>
                        <tr>
                            <td class="bots-ip"><?= sanitizeForHtml($r['ip']) ?></td>
                            <td class="bots-ua" title="<?= sanitizeForHtml((string) $r['user_agent']) ?>"><?= sanitizeForHtml(mb_substr((string) $r['user_agent'], 0, 90)) ?></td>
                            <?= botKindCell($r, $suspect_default) ?>
                            <td><?= (int) $r['window_hits'] ?></td>
                            <td><?= DateHelper::isoToApp($r['window_start']) ?></td>
                            <td><?= (int) $r['hit_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                </div>
            <?php endif; ?>

            <h2 class="bots-titre">Plus forts pics</h2>

        <?php endif; ?>

        <?php if (empty($rows)) : ?>

            <p>Aucun résultat pour cette vue.</p>

        <?php elseif ($view == 'officiels') : ?>

            <div class="bots-table-wrapper">
            <table id="ajouts">
                <tr>
                    <?= botTh("Type", 'fam-kind', "Ce que fait cette famille de robots : légende sous « Comment lire ce tableau de bord ».") ?>
                    <th>Famille</th>
                    <?= botTh("IP distinctes", 'fam-ips', "Adresses distinctes vues sous cette famille sur la période.") ?>
                    <?= botTh("Pages vues", 'fam-hits', $help_hits) ?>
                    <?= botTh("Pic ($window min)", 'fam-peak', "Plus forte fenêtre de $window minutes d'une adresse de la famille.") ?>
                    <th>Dernière visite</th>
                </tr>
                <?php foreach ($rows as $r) : ?>
                    <?php $kind = BotMonitor::familyKind($r['bot_family']); [$label, $hint] = BotMonitor::KIND_LABELS[$kind]; ?>
                    <tr>
                        <td class="bots-kind"><span class="bots-kind-badge bots-kind-badge--<?= $kind ?>" title="<?= sanitizeForHtml($hint) ?>"><?= sanitizeForHtml($label) ?></span></td>
                        <td><?= sanitizeForHtml($r['bot_family'] ?? 'Inconnu') ?></td>
                        <td><?= (int) $r['nb_ips'] ?></td>
                        <td><?= (int) $r['total_hits'] ?></td>
                        <td><?= (int) $r['pic_max'] ?></td>
                        <td><?= DateHelper::isoToApp($r['derniere_visite']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            </div>

        <?php else : ?>

            <?= HtmlShrink::getPaginationString($all_results_nb, $get['page'], $nblignes, 1, "", $pagination_url) ?>

            <div class="bots-table-wrapper">
            <table id="ajouts">
                <tr>
                    <th>IP</th>
                    <?= botTh("User-Agent", 'ua', $help_ua) ?>
                    <?= botTh("Type", 'kind', $help_kind) ?>
                    <?php if ($view == 'rafales') : ?>
                        <?= botTh("Pic ($window min)", 'peak', $help_peak) ?>
                        <?= botTh("Date du pic", 'peak-date', "Début de cette fenêtre.") ?>
                    <?php elseif ($view == 'sondeurs') : ?>
                        <?= botTh("Erreurs", 'errors', "Requêtes terminées en erreur 4xx, fichiers statiques exclus.") ?>
                        <?= botTh("Part", 'share', "Erreurs rapportées aux pages vues. Proche de 100 % : l'adresse ne voit que des refus ou des pages inexistantes.") ?>
                        <?= botTh("Dernier chemin en erreur", 'path', "Chemin de la dernière requête en erreur, sans sa query string. Écrit par le visiteur : à lire, jamais à suivre.") ?>
                    <?php endif; ?>
                    <?= botTh("Pages vues", 'hits', $help_hits) ?>
                    <?php if ($view == 'scrapers' || $view == 'suspects') : ?>
                        <?= botTh("Première visite", 'first', "Première requête conservée. Après " . BotMonitor::RETENTION_DAYS . " jours sans visite la ligne est purgée, et une adresse qui revient repart de zéro.") ?>
                    <?php endif; ?>
                    <th>Dernière visite</th>
                </tr>
                <?php foreach ($rows as $r) : ?>
                    <tr>
                        <td class="bots-ip"><?= sanitizeForHtml($r['ip']) ?></td>
                        <td class="bots-ua" title="<?= sanitizeForHtml((string) $r['user_agent']) ?>"><?= sanitizeForHtml(mb_substr((string) $r['user_agent'], 0, 90)) ?></td>
                        <?= botKindCell($r, $suspect_default) ?>
                        <?php if ($view == 'rafales') : ?>
                            <td><?= (int) $r['peak_hits'] ?></td>
                            <td><?= DateHelper::isoToApp($r['peak_start']) ?></td>
                        <?php elseif ($view == 'sondeurs') : ?>
                            <td><?= (int) $r['error_hits'] ?></td>
                            <td><?= (int) round(100 * (int) $r['error_hits'] / max(1, (int) $r['hit_count'])) ?>&nbsp;%</td>
                            <?php // chemin choisi par le visiteur : jamais affiché sans échappement ?>
                            <td class="bots-path"><?= sanitizeForHtml((string) $r['last_error_path']) ?></td>
                        <?php endif; ?>
                        <td><?= (int) $r['hit_count'] ?></td>
                        <?php if ($view == 'scrapers' || $view == 'suspects') : ?>
                            <td><?= DateHelper::isoToApp($r['first_seen']) ?></td>
                        <?php endif; ?>
                        <td><?= DateHelper::isoToApp($r['last_seen']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            </div>

            <?= HtmlShrink::getPaginationString($all_results_nb, $get['page'], $nblignes, 1, "", $pagination_url) ?>

        <?php endif; ?>

    </section>

</main>

<div id="colonne_gauche" class="colonne">
</div>

<div class="spacer"><!-- --></div>
<?php
include("../_footer.inc.php");
?>
