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

// vues filtrées par un seuil, modifiable par filtre : libellé du champ et valeur par défaut
$threshold_views = [
    'suspects' => ["Seuil de pages vues", defined('BOT_MONITORING_SUSPECT_THRESHOLD') ? BOT_MONITORING_SUSPECT_THRESHOLD : 150],
    'rafales' => ["Pic minimal (pages vues en " . BotMonitor::WINDOW_MINUTES . " min)", BotMonitor::BURST_THRESHOLD],
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

if ($view == 'scrapers')
{
    $stmt = $connectorPdo->query("SELECT COUNT(*) FROM bot_monitor WHERE honeypot_triggered = 1");
    $all_results_nb = (int) $stmt->fetchColumn();

    $stmt = $connectorPdo->prepare(
        "SELECT ip, user_agent, bot_family, hit_count, is_crawler_detect, first_seen, last_seen
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
        "SELECT ip, user_agent, hit_count, first_seen, last_seen
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
        "SELECT ip, user_agent, bot_family, hit_count, peak_hits, peak_start, last_seen
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
    $window_is_open = sprintf('> NOW() - INTERVAL %d MINUTE', BotMonitor::WINDOW_MINUTES);
    $rows_now = $connectorPdo->query(
        "SELECT ip, user_agent, bot_family, window_hits, window_start, hit_count
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
        "SELECT ip, user_agent, hit_count, error_hits, last_error_path, last_seen
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
            <li><strong><?= (int) $totals['nb_crawlers'] ?></strong> bots détectés</li>
            <li><strong><?= (int) $totals['nb_honeypot'] ?></strong> piégées (honeypot)</li>
        </ul>

        <nav class="tabs">
            <?php foreach ($views as $view_key => $view_label) : ?>
                <a href="?view=<?= $view_key ?>" <?php if ($view_key == $view) : ?>class="ici"<?php endif; ?>><?= sanitizeForHtml($view_label) ?></a>
            <?php endforeach; ?>
        </nav>

        <?php if (isset($threshold_views[$view])) : ?>
            <div id="filters">
                <form method="get" action="">
                    <input type="hidden" name="view" value="<?= $view ?>" />
                    <label for="seuil"><?= sanitizeForHtml($threshold_label) ?> :</label>
                    <input type="number" id="seuil" name="seuil" value="<?= (int) $threshold ?>" min="1" size="6" />
                    <input type="submit" value="Filtrer" />
                </form>
                <?php if ($view == 'suspects') : ?>
                    <p class="bots-avertissement">Attention aux faux positifs : les IP partagées (réseaux mobiles, entreprises)
                        et les appels AJAX peuvent gonfler le nombre de pages vues d'une IP légitime.</p>
                <?php elseif ($view == 'rafales') : ?>
                    <p class="bots-avertissement">Fenêtres fixes de <?= BotMonitor::WINDOW_MINUTES ?> minutes : une rafale à cheval sur deux fenêtres
                        est sous-estimée, et seul le plus fort pic de chaque IP est gardé. Les IP partagées additionnent leurs visiteurs.</p>
                <?php else : ?>
                    <p class="bots-avertissement">Requêtes terminées en erreur 4xx (page inexistante, accès refusé, identifiant inconnu),
                        fichiers statiques exclus. Un lien périmé suffit à en produire quelques-unes : c'est le dernier chemin
                        demandé qui distingue un sondeur d'un visiteur égaré.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($view == 'rafales') : ?>

            <h2 class="bots-titre">En ce moment</h2>

            <?php if (empty($rows_now)) : ?>
                <p>Aucune visite anonyme dans les <?= BotMonitor::WINDOW_MINUTES ?> dernières minutes.</p>
            <?php else : ?>
                <div class="bots-table-wrapper">
                <table class="bots-now">
                    <tr>
                        <th>IP</th>
                        <th>User-Agent</th>
                        <th>Famille</th>
                        <th>Fenêtre en cours</th>
                        <th>Ouverte à</th>
                        <th>Pages vues</th>
                    </tr>
                    <?php foreach ($rows_now as $r) : ?>
                        <tr>
                            <td class="bots-ip"><?= sanitizeForHtml($r['ip']) ?></td>
                            <td class="bots-ua" title="<?= sanitizeForHtml((string) $r['user_agent']) ?>"><?= sanitizeForHtml(mb_substr((string) $r['user_agent'], 0, 90)) ?></td>
                            <td><?= sanitizeForHtml($r['bot_family'] ?? '—') ?></td>
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
                    <th>Famille</th>
                    <th>IP distinctes</th>
                    <th>Pages vues</th>
                    <th title="Plus forte fenêtre de <?= BotMonitor::WINDOW_MINUTES ?> minutes d'une même IP">Pic (<?= BotMonitor::WINDOW_MINUTES ?> min)</th>
                    <th>Dernière visite</th>
                </tr>
                <?php foreach ($rows as $r) : ?>
                    <tr>
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
                    <th>User-Agent</th>
                    <?php if ($view == 'scrapers' || $view == 'rafales') : ?><th>Famille</th><?php endif; ?>
                    <?php if ($view == 'rafales') : ?>
                        <th>Pic (<?= BotMonitor::WINDOW_MINUTES ?> min)</th>
                        <th>Date du pic</th>
                    <?php elseif ($view == 'sondeurs') : ?>
                        <th>Erreurs</th>
                        <th title="Part des erreurs dans les pages vues">Part</th>
                        <th>Dernier chemin en erreur</th>
                    <?php endif; ?>
                    <th>Pages vues</th>
                    <?php if ($view == 'scrapers' || $view == 'suspects') : ?><th>Première visite</th><?php endif; ?>
                    <th>Dernière visite</th>
                </tr>
                <?php foreach ($rows as $r) : ?>
                    <tr>
                        <td class="bots-ip"><?= sanitizeForHtml($r['ip']) ?></td>
                        <td class="bots-ua" title="<?= sanitizeForHtml((string) $r['user_agent']) ?>"><?= sanitizeForHtml(mb_substr((string) $r['user_agent'], 0, 90)) ?></td>
                        <?php if ($view == 'scrapers' || $view == 'rafales') : ?>
                            <td><?= sanitizeForHtml($r['bot_family'] ?? '—') ?></td>
                        <?php endif; ?>
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
