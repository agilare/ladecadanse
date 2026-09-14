<?php

/**
 * Piège à scrapers ("honeypot").
 *
 * Cette page n'est liée que par un lien invisible dans le pied de page et
 * interdite dans robots.txt : seuls les robots qui ignorent robots.txt
 * (la population visée) la chargent. L'IP est alors marquée honeypot_triggered
 * dans bot_monitor. Réponse 204 sans contenu pour ne pas éveiller les soupçons.
 */

require_once __DIR__ . '/app/bootstrap.php';

use Ladecadanse\BotMonitor;

if (defined('BOT_MONITORING_ENABLED') && BOT_MONITORING_ENABLED) {
    (new BotMonitor($connectorPdo->getPDO(), $logger))->recordHoneypot();
}

header($_SERVER['SERVER_PROTOCOL'] . ' 204 No Content');
header('X-Robots-Tag: noindex, nofollow');
exit;
