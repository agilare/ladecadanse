<?php

require_once("../app/bootstrap.php");

use Ladecadanse\UserLevel;
use Ladecadanse\Utils\DateHelper;
use Ladecadanse\Utils\QueryParamValidator;
use Ladecadanse\HtmlShrink;

if (!$authorization->checkGroup(UserLevel::ADMIN))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
	header("Location: /user-login.php"); die();
}

// vues du dashboard : onglet courant via ?view=
$views = [
    'scrapers' => "Scrapers avérés (honeypot)",
    'suspects' => "Humains suspects",
    'officiels' => "Statistiques des bots"
];
$view = isset($_GET['view']) && isset($views[$_GET['view']]) ? $_GET['view'] : 'scrapers';

// seuil de hits pour la vue "humains suspects", modifiable par filtre
$suspect_threshold = defined('BOT_MONITORING_SUSPECT_THRESHOLD') ? BOT_MONITORING_SUSPECT_THRESHOLD : 150;
if (!empty($_GET['seuil']) && QueryParamValidator::isAcceptedUrlQueryValue($_GET['seuil'], "int"))
{
    $suspect_threshold = (int) $_GET['seuil'];
}

// pagination
$get = [];
$get['page'] = !empty($_GET['page']) ? QueryParamValidator::validateUrlQueryValue($_GET['page'], "int", 1) : 1;

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
    $stmt->bindValue(':threshold', $suspect_threshold, PDO::PARAM_INT);
    $stmt->execute();
    $all_results_nb = (int) $stmt->fetchColumn();

    $stmt = $connectorPdo->prepare(
        "SELECT ip, user_agent, hit_count, first_seen, last_seen
        FROM bot_monitor
        WHERE is_crawler_detect = 0 AND honeypot_triggered = 0 AND hit_count > :threshold
        ORDER BY hit_count DESC
        LIMIT :limit OFFSET :offset"
    );
    $stmt->bindValue(':threshold', $suspect_threshold, PDO::PARAM_INT);
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

        <?php if ($view == 'suspects') : ?>
            <div id="filters">
                <form method="get" action="">
                    <input type="hidden" name="view" value="suspects" />
                    <label for="seuil">Seuil de pages vues :</label>
                    <input type="number" id="seuil" name="seuil" value="<?= (int) $suspect_threshold ?>" min="1" size="6" />
                    <input type="submit" value="Filtrer" />
                </form>
                <p class="bots-avertissement">Attention aux faux positifs : les IP partagées (réseaux mobiles, entreprises)
                    et les appels AJAX peuvent gonfler le nombre de pages vues d'une IP légitime.</p>
            </div>
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
                    <th>Dernière visite</th>
                </tr>
                <?php foreach ($rows as $r) : ?>
                    <tr>
                        <td><?= sanitizeForHtml($r['bot_family'] ?? 'Inconnu') ?></td>
                        <td><?= (int) $r['nb_ips'] ?></td>
                        <td><?= (int) $r['total_hits'] ?></td>
                        <td><?= DateHelper::isoToApp($r['derniere_visite']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            </div>

        <?php else : ?>

            <?= HtmlShrink::getPaginationString($all_results_nb, $get['page'], $nblignes, 1, "", "?view=" . $view . "&page=") ?>

            <div class="bots-table-wrapper">
            <table id="ajouts">
                <tr>
                    <th>IP</th>
                    <th>User-Agent</th>
                    <?php if ($view == 'scrapers') : ?><th>Famille</th><?php endif; ?>
                    <th>Pages vues</th>
                    <th>Première visite</th>
                    <th>Dernière visite</th>
                </tr>
                <?php foreach ($rows as $r) : ?>
                    <tr>
                        <td class="bots-ip"><?= sanitizeForHtml($r['ip']) ?></td>
                        <td class="bots-ua" title="<?= sanitizeForHtml((string) $r['user_agent']) ?>"><?= sanitizeForHtml(mb_substr((string) $r['user_agent'], 0, 90)) ?></td>
                        <?php if ($view == 'scrapers') : ?>
                            <td><?= sanitizeForHtml($r['bot_family'] ?? '—') ?></td>
                        <?php endif; ?>
                        <td><?= (int) $r['hit_count'] ?></td>
                        <td><?= DateHelper::isoToApp($r['first_seen']) ?></td>
                        <td><?= DateHelper::isoToApp($r['last_seen']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            </div>

            <?= HtmlShrink::getPaginationString($all_results_nb, $get['page'], $nblignes, 1, "", "?view=" . $view . "&page=") ?>

        <?php endif; ?>

    </section>

</main>

<div id="colonne_gauche" class="colonne">
</div>

<div class="spacer"><!-- --></div>
<?php
include("../_footer.inc.php");
?>
