<?php

namespace Ladecadanse;

use Monolog\Logger;
use PDO;

/**
 * Suivi léger des bots et IP suspectes (table bot_monitor).
 *
 * Une ligne par IP, alimentée par un seul upsert à chaque page vue
 * (appelé depuis le bootstrap via register_shutdown_function pour ne pas
 * ralentir le rendu). Conçu pour un hébergement mutualisé : requêtes
 * minimales, purge probabiliste sans cron, aucun échec ne remonte à la page.
 */
class BotMonitor
{
    /** @var int durée de rétention (jours) des IP ordinaires inactives */
    public const RETENTION_DAYS = 90;

    /** @var int durée de rétention (jours) des IP ayant déclenché le honeypot */
    public const RETENTION_DAYS_HONEYPOT = 365;

    /** @var int la purge est tentée en moyenne une fois sur N appels à track() */
    public const PURGE_PROBABILITY = 500;

    /** @var int longueur max. du User-Agent stocké (anti-pollution) */
    public const USER_AGENT_MAX_LENGTH = 1024;

    /**
     * Familles de bots connues : motif regex (sur User-Agent en minuscules) => famille.
     * Évaluées dans l'ordre, avant le motif générique.
     */
    private const BOT_FAMILIES = [
        '~googlebot|google-extended|apis-google|adsbot|mediapartners-google~' => 'Google',
        '~bingbot|msnbot~' => 'Bing',
        '~amazonbot~' => 'Amazon',
        '~gptbot|oai-searchbot|chatgpt~' => 'OpenAI',
        '~claudebot|anthropic~' => 'Anthropic',
        '~perplexitybot|perplexity~' => 'Perplexity',
        '~meta-externalagent|facebookexternalhit|facebookbot~' => 'Meta',
        '~applebot~' => 'Apple',
        '~duckduckbot~' => 'DuckDuckGo',
        '~yandex~' => 'Yandex',
        '~baiduspider~' => 'Baidu',
        '~bytespider|tiktok~' => 'ByteDance',
        '~ahrefsbot~' => 'Ahrefs',
        '~semrushbot~' => 'Semrush',
        '~mj12bot~' => 'Majestic',
        '~dotbot~' => 'Moz',
        '~petalbot~' => 'Huawei',
        '~barkrowler~' => 'Babbar',
        '~ccbot~' => 'CommonCrawl',
        '~scrapy~' => 'Scrapy',
    ];

    /**
     * Motif générique : signale un bot sans famille identifiée.
     */
    private const BOT_GENERIC_PATTERN =
        '~(bot|crawl|spider|scrap|slurp|curl|wget|python-requests|python-urllib|go-http-client|java/|libwww|httpclient|headless)~';

    public function __construct(
        private readonly PDO $pdo,
        private readonly ?Logger $logger = null
    ) {
    }

    /**
     * IP du client. Uniquement REMOTE_ADDR : derrière l'infrastructure Infomaniak
     * elle est fiable, alors que X-Forwarded-For est falsifiable par le client.
     * Valide IPv4 et IPv6 ; null si absente ou invalide (CLI, tests).
     */
    public static function getClientIp(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
    }

    /**
     * Famille de bot d'après le User-Agent : nom connu (Google, OpenAI...),
     * "Autre bot" si seul le motif générique correspond, null si probable humain.
     */
    public static function detectBotFamily(string $userAgent): ?string
    {
        if ($userAgent === '') {
            return null;
        }

        $ua = mb_strtolower($userAgent);

        foreach (self::BOT_FAMILIES as $pattern => $family) {
            if (preg_match($pattern, $ua)) {
                return $family;
            }
        }

        if (preg_match(self::BOT_GENERIC_PATTERN, $ua)) {
            return 'Autre bot';
        }

        return null;
    }

    /**
     * Enregistre la page vue courante : un seul upsert par requête.
     * Aucune exception ne doit remonter : un échec DB ne casse jamais la page.
     */
    public function track(): void
    {
        try {
            $ip = self::getClientIp();
            if ($ip === null) {
                return;
            }

            $userAgent = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, self::USER_AGENT_MAX_LENGTH);
            $family = self::detectBotFamily($userAgent);

            // VALUES() est déprécié sur MySQL >= 8.0.20 (OK sur MariaDB/Infomaniak) ;
            // syntaxe de remplacement le moment venu : INSERT ... AS new ON DUPLICATE KEY UPDATE hit_count = new.hit_count + 1 ...
            $stmt = $this->pdo->prepare(
                "INSERT INTO bot_monitor (ip, user_agent, bot_family, hit_count, is_crawler_detect, first_seen)
                VALUES (:ip, :ua, :family, 1, :is_bot, NOW())
                ON DUPLICATE KEY UPDATE
                    hit_count = hit_count + 1,
                    user_agent = VALUES(user_agent),
                    bot_family = COALESCE(VALUES(bot_family), bot_family),
                    is_crawler_detect = GREATEST(is_crawler_detect, VALUES(is_crawler_detect))"
            );
            $stmt->execute([
                ':ip' => $ip,
                ':ua' => $userAgent,
                ':family' => $family,
                ':is_bot' => $family !== null ? 1 : 0,
            ]);

            $this->maybePurge();
        } catch (\Throwable $e) {
            $this->logger?->warning('BotMonitor::track a échoué : ' . $e->getMessage());
        }
    }

    /**
     * Marque l'IP courante comme scraper avéré (a suivi le lien piège).
     * Insère la ligne si l'IP est encore inconnue.
     */
    public function recordHoneypot(): void
    {
        try {
            $ip = self::getClientIp();
            if ($ip === null) {
                return;
            }

            $userAgent = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, self::USER_AGENT_MAX_LENGTH);

            $stmt = $this->pdo->prepare(
                "INSERT INTO bot_monitor (ip, user_agent, honeypot_triggered, first_seen)
                VALUES (:ip, :ua, 1, NOW())
                ON DUPLICATE KEY UPDATE honeypot_triggered = 1"
            );
            $stmt->execute([':ip' => $ip, ':ua' => $userAgent]);
        } catch (\Throwable $e) {
            $this->logger?->warning('BotMonitor::recordHoneypot a échoué : ' . $e->getMessage());
        }
    }

    /**
     * Purge probabiliste (~1 appel sur PURGE_PROBABILITY) des IP inactives,
     * exécutée dans le shutdown donc invisible pour le visiteur.
     * LIMIT pour borner le travail d'un DELETE sur hébergement mutualisé.
     */
    private function maybePurge(): void
    {
        if (random_int(1, self::PURGE_PROBABILITY) !== 1) {
            return;
        }

        $this->pdo
            ->prepare("DELETE FROM bot_monitor WHERE honeypot_triggered = 0 AND last_seen < DATE_SUB(NOW(), INTERVAL :days DAY) LIMIT 500")
            ->execute([':days' => self::RETENTION_DAYS]);

        $this->pdo
            ->prepare("DELETE FROM bot_monitor WHERE honeypot_triggered = 1 AND last_seen < DATE_SUB(NOW(), INTERVAL :days DAY) LIMIT 500")
            ->execute([':days' => self::RETENTION_DAYS_HONEYPOT]);
    }
}
