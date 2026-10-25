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
     * @var int durée (minutes) de la fenêtre fixe de comptage des rafales. La saturation d'un
     * hébergement mutualisé se joue en minutes, et un humain ne dépasse pas quelques dizaines
     * de pages dans ce laps. La changer rend les pics déjà stockés incomparables aux nouveaux.
     */
    public const WINDOW_MINUTES = 10;

    /** @var int seuil par défaut de la vue "rafales" : pages vues dans la plus forte fenêtre */
    public const BURST_THRESHOLD = 100;

    /** @var int seuil par défaut de la vue "sondeurs" : requêtes terminées en erreur 4xx */
    public const ERROR_THRESHOLD = 10;

    /** @var int longueur max. du chemin en erreur stocké (colonne last_error_path) */
    public const ERROR_PATH_MAX_LENGTH = 255;

    /**
     * Fichiers statiques : leur 404 passe lui aussi par misc/error.php, mais une image ou une
     * feuille de style manquante dit quelque chose du site, pas du visiteur.
     */
    private const STATIC_ASSET_PATTERN = '~\.(?:jpe?g|png|gif|webp|avif|svg|ico|css|m?js|map|woff2?|ttf)$~i';

    /** @var string famille enregistrée pour un User-Agent qui se dit robot sans qu'on le connaisse */
    public const FAMILY_OTHER_BOT = 'Autre bot';

    /** @var string famille enregistrée pour un User-Agent sans signature de navigateur : node, curl, agrégateurs… */
    public const FAMILY_SCRIPT = 'Script';

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
        '~meta-externalagent|facebookbot~' => 'Meta',
        // les aperçus de liens de Facebook, WhatsApp et Instagram, pas la collecte pour l'IA :
        // famille à part, pour ne pas lire un partage comme un entraînement
        '~facebookexternalhit~' => 'Facebook (aperçus)',
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

    /**
     * Signature d'un navigateur : tous commencent par « Mozilla/ » depuis vingt ans, Opera
     * excepté dans ses vieilles versions. Un User-Agent qui ne commence pas ainsi est un
     * programme, quoi qu'il dise d'autre.
     */
    private const BROWSER_PATTERN = '~^(mozilla|opera)/~';

    // Genres de visiteur, calculés à l'affichage d'après ce que la ligne a enregistré : ce que
    // l'adresse annonce (famille, signature de navigateur) et ce qu'elle a fait (piège, volume).
    public const KIND_SCRAPER = 'scraper';
    public const KIND_SEARCH = 'search';
    public const KIND_AI = 'ai';
    public const KIND_SEO = 'seo';
    public const KIND_BOT = 'bot';
    public const KIND_SCRIPT = 'script';
    public const KIND_SUSPECT = 'suspect';
    public const KIND_HUMAN = 'human';

    /** @var array<string, string> famille déclarée => genre ; une famille absente d'ici est un robot « autre » */
    private const FAMILY_KINDS = [
        'Google' => self::KIND_SEARCH,
        'Bing' => self::KIND_SEARCH,
        'Apple' => self::KIND_SEARCH,
        'DuckDuckGo' => self::KIND_SEARCH,
        'Yandex' => self::KIND_SEARCH,
        'Baidu' => self::KIND_SEARCH,
        'Huawei' => self::KIND_SEARCH,
        'OpenAI' => self::KIND_AI,
        'Anthropic' => self::KIND_AI,
        'Perplexity' => self::KIND_AI,
        'Meta' => self::KIND_AI,
        'Amazon' => self::KIND_AI,
        'ByteDance' => self::KIND_AI,
        'CommonCrawl' => self::KIND_AI,
        'Ahrefs' => self::KIND_SEO,
        'Semrush' => self::KIND_SEO,
        'Majestic' => self::KIND_SEO,
        'Moz' => self::KIND_SEO,
        'Babbar' => self::KIND_SEO,
        'Scrapy' => self::KIND_SCRIPT,
        self::FAMILY_SCRIPT => self::KIND_SCRIPT,
    ];

    /**
     * Libellé et explication de chaque genre, pour le tableau de bord.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const KIND_LABELS = [
        self::KIND_SCRAPER => ['Scraper', "A suivi le lien piège du pied de page, interdit par robots.txt : aspire le site sans en respecter les règles, quel que soit le navigateur qu'il annonce."],
        self::KIND_SEARCH => ['Moteur de recherche', "Robot déclaré d'un moteur de recherche : indexe pour amener des visiteurs, et respecte robots.txt. La famille se lit dans le User-Agent, qui peut être usurpé."],
        self::KIND_AI => ['Robot IA', "Robot déclaré d'un service d'IA : collecte des pages pour entraîner un modèle ou répondre à ses utilisateurs, sans guère renvoyer de visiteurs. Se pilote par robots.txt quand il le respecte."],
        self::KIND_SEO => ['Outil SEO', "Explore le site pour vendre des données aux référenceurs : aucun visiteur en retour."],
        self::KIND_BOT => ['Autre robot', "Se présente comme un robot (bot, crawler, spider…) d'une famille que le site ne connaît pas."],
        self::KIND_SCRIPT => ['Script', "User-Agent sans signature de navigateur (node, curl, n8n, agrégateurs…) : un programme qui ne se cache pas, mais ne dit pas ce qu'il fait."],
        self::KIND_SUSPECT => ['Navigateur suspect', "Se présente comme un navigateur, sans marque de robot ni passage par le piège, mais avec un volume ou un rythme que peu de visiteurs atteignent. Peut aussi être une adresse partagée : réseau mobile, école, entreprise."],
        self::KIND_HUMAN => ['Navigateur', "Se présente comme un navigateur et n'a rien fait d'anormal : un visiteur, jusqu'à preuve du contraire."],
    ];

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
     * Famille de bot d'après le User-Agent : nom connu (Google, OpenAI...), FAMILY_OTHER_BOT si
     * seul le motif générique correspond, FAMILY_SCRIPT s'il n'a pas la signature d'un
     * navigateur (vide compris), null si probable humain.
     */
    public static function detectBotFamily(string $userAgent): ?string
    {
        $ua = mb_strtolower($userAgent);

        foreach (self::BOT_FAMILIES as $pattern => $family) {
            if (preg_match($pattern, $ua)) {
                return $family;
            }
        }

        if (preg_match(self::BOT_GENERIC_PATTERN, $ua)) {
            return self::FAMILY_OTHER_BOT;
        }

        if (!preg_match(self::BROWSER_PATTERN, $ua)) {
            return self::FAMILY_SCRIPT;
        }

        return null;
    }

    /**
     * Genre d'une ligne de bot_monitor, pour le tableau de bord : le piège l'emporte sur tout,
     * puis ce que le User-Agent déclare, puis le volume. Un robot déclaré n'est jamais
     * « suspect » sur son volume : visiter beaucoup est son métier.
     *
     * @param array<string, mixed> $row ligne avec honeypot_triggered, bot_family, is_crawler_detect, hit_count, peak_hits
     * @param int $suspectThreshold pages vues au-delà desquelles un navigateur devient suspect
     */
    public static function classify(array $row, int $suspectThreshold): string
    {
        if (!empty($row['honeypot_triggered'])) {
            return self::KIND_SCRAPER;
        }

        $family = $row['bot_family'] ?? null;
        if ($family !== null) {
            return self::familyKind((string) $family);
        }

        // marquée robot sans famille : une ligne d'avant l'enregistrement de la famille
        if (!empty($row['is_crawler_detect'])) {
            return self::KIND_BOT;
        }

        if ((int) ($row['hit_count'] ?? 0) > $suspectThreshold || (int) ($row['peak_hits'] ?? 0) >= self::BURST_THRESHOLD) {
            return self::KIND_SUSPECT;
        }

        return self::KIND_HUMAN;
    }

    /**
     * Genre d'une famille déclarée, pour les statistiques par famille.
     */
    public static function familyKind(?string $family): string
    {
        if ($family === null) {
            return self::KIND_BOT;
        }

        return self::FAMILY_KINDS[$family] ?? self::KIND_BOT;
    }

    /**
     * Ce qu'une adresse annonce par son User-Agent : sa famille de robot, « navigateur » ou
     * « script ». Complète le genre d'un scraper, qui se déguise ou non.
     */
    public static function declaredIdentity(?string $family, string $userAgent): string
    {
        if ($family !== null && $family !== self::FAMILY_SCRIPT) {
            return $family;
        }

        return preg_match(self::BROWSER_PATTERN, mb_strtolower($userAgent)) ? 'navigateur' : 'script';
    }

    /**
     * Chemin demandé, sans sa query string : elle peut porter un jeton
     * (user/reset2.php?token=…) ou une saisie de recherche, qui n'ont rien à faire ici.
     * Sous un ErrorDocument d'Apache, REQUEST_URI reste l'URI d'origine, pas misc/error.php.
     */
    public static function getRequestPath(): string
    {
        $path = explode('?', (string) ($_SERVER['REQUEST_URI'] ?? ''), 2)[0];

        return mb_substr(mb_scrub($path), 0, self::ERROR_PATH_MAX_LENGTH);
    }

    /**
     * Statut de la réponse : le plus élevé de celui que l'application a posé
     * (_erreur_http.inc.php) et de celui qu'Apache transmet à son ErrorDocument
     * (misc/error.php), où PHP se croit en 200. REDIRECT_STATUS se lit par sa valeur,
     * jamais par sa présence : certaines SAPI le posent à 200 sur toute requête.
     */
    public static function getResponseStatus(): int
    {
        return max((int) http_response_code(), (int) ($_SERVER['REDIRECT_STATUS'] ?? 0));
    }

    /**
     * Requête à compter comme erreur du visiteur : un 4xx (les 5xx sont nos fautes, pas
     * les siennes) sur autre chose qu'un fichier statique. C'est ce que produit un sondeur
     * de failles (/wp-login.php, /.env) ou un scraper qui itère sur des identifiants.
     */
    public static function isErrorHit(int $status, string $path): bool
    {
        return $status >= 400 && $status < 500 && !preg_match(self::STATIC_ASSET_PATTERN, $path);
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

            $path = self::getRequestPath();
            $isError = self::isErrorHit(self::getResponseStatus(), $path);

            // constante entière interpolée : la durée sert deux fois, et un marqueur nommé
            // ne se réutilise pas en prepares natifs (HY093)
            $windowIsOver = sprintf('window_start IS NULL OR window_start <= NOW() - INTERVAL %d MINUTE', self::WINDOW_MINUTES);

            // VALUES() est déprécié sur MySQL >= 8.0.20 (OK sur MariaDB/Infomaniak) ;
            // syntaxe de remplacement le moment venu : INSERT ... AS new ON DUPLICATE KEY UPDATE hit_count = new.hit_count + 1 ...
            //
            // Les affectations s'évaluent de gauche à droite, chacune voyant le résultat des
            // précédentes, et l'ordre des quatre lignes de fenêtre fait partie de la logique :
            // window_hits lit l'ancien window_start, peak_start compare le nouveau window_hits
            // à l'ancien peak_hits. Les réordonner fausse les pics sans lever d'erreur.
            $stmt = $this->pdo->prepare(
                "INSERT INTO bot_monitor (ip, user_agent, bot_family, hit_count, is_crawler_detect, first_seen,
                    window_start, window_hits, peak_hits, peak_start, error_hits, last_error_path)
                VALUES (:ip, :ua, :family, 1, :is_bot, NOW(), NOW(), 1, 1, NOW(), :is_error, :error_path)
                ON DUPLICATE KEY UPDATE
                    hit_count = hit_count + 1,
                    user_agent = VALUES(user_agent),
                    bot_family = COALESCE(VALUES(bot_family), bot_family),
                    is_crawler_detect = GREATEST(is_crawler_detect, VALUES(is_crawler_detect)),
                    window_hits = IF($windowIsOver, 1, window_hits + 1),
                    window_start = IF($windowIsOver, NOW(), window_start),
                    peak_start = IF(window_hits > peak_hits, window_start, peak_start),
                    peak_hits = GREATEST(peak_hits, window_hits),
                    error_hits = error_hits + VALUES(error_hits),
                    last_error_path = COALESCE(VALUES(last_error_path), last_error_path)"
            );
            $stmt->execute([
                ':ip' => $ip,
                ':ua' => $userAgent,
                ':family' => $family,
                ':is_bot' => $family !== null ? 1 : 0,
                ':is_error' => $isError ? 1 : 0,
                ':error_path' => $isError ? $path : null,
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
