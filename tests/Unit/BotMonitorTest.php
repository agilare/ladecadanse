<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\BotMonitor;

/**
 * Lecture d'un User-Agent, et genre d'une ligne de bot_monitor pour le tableau de bord.
 */
final class BotMonitorTest extends Unit
{
    private const FIREFOX = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0';

    public function testUserAgentWithoutBrowserSignatureIsAScript(): void
    {
        $this->assertSame(BotMonitor::FAMILY_SCRIPT, BotMonitor::detectBotFamily('node'));
        $this->assertSame(BotMonitor::FAMILY_SCRIPT, BotMonitor::detectBotFamily('n8n'));
        $this->assertSame(BotMonitor::FAMILY_SCRIPT, BotMonitor::detectBotFamily('OazisDigest/1.0 (+https://oazis.app)'));
        $this->assertSame(BotMonitor::FAMILY_SCRIPT, BotMonitor::detectBotFamily(''));
    }

    public function testBrowserWithoutBotMarkerIsNotABot(): void
    {
        $this->assertNull(BotMonitor::detectBotFamily(self::FIREFOX));
        $this->assertNull(BotMonitor::detectBotFamily('Opera/9.80 (Windows NT 6.1) Presto/2.12.388 Version/12.18'));
    }

    public function testGenericBotMarkerInsideABrowserSignature(): void
    {
        $this->assertSame(BotMonitor::FAMILY_OTHER_BOT, BotMonitor::detectBotFamily('Mozilla/5.0 (compatible; UnknownBot/1.0; +https://example.org/bot)'));
        $this->assertSame(BotMonitor::FAMILY_OTHER_BOT, BotMonitor::detectBotFamily('Mozilla/5.0 (X11; Linux x86_64) HeadlessChrome/126.0.0.0 Safari/537.36'));
    }

    public function testKnownFamilies(): void
    {
        $this->assertSame('Google', BotMonitor::detectBotFamily('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'));
        $this->assertSame('Meta', BotMonitor::detectBotFamily('meta-externalagent/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/crawler)'));
        // les aperçus de liens ne sont pas la collecte pour l'IA
        $this->assertSame('Facebook (aperçus)', BotMonitor::detectBotFamily('facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)'));
    }

    public function testHoneypotOutranksEverything(): void
    {
        $row = ['honeypot_triggered' => 1, 'bot_family' => 'Google', 'is_crawler_detect' => 1, 'hit_count' => 3, 'peak_hits' => 1];

        $this->assertSame(BotMonitor::KIND_SCRAPER, BotMonitor::classify($row, 150));
    }

    public function testDeclaredFamilyGivesItsKindWhateverTheVolume(): void
    {
        $this->assertSame(BotMonitor::KIND_SEARCH, BotMonitor::classify($this->row('Google', 100000), 150));
        $this->assertSame(BotMonitor::KIND_AI, BotMonitor::classify($this->row('OpenAI', 100000), 150));
        $this->assertSame(BotMonitor::KIND_SEO, BotMonitor::classify($this->row('Ahrefs', 10), 150));
        $this->assertSame(BotMonitor::KIND_SCRIPT, BotMonitor::classify($this->row(BotMonitor::FAMILY_SCRIPT, 10), 150));
        $this->assertSame(BotMonitor::KIND_BOT, BotMonitor::classify($this->row(BotMonitor::FAMILY_OTHER_BOT, 10), 150));
        // une famille ajoutée à la détection avant d'être rangée dans un genre
        $this->assertSame(BotMonitor::KIND_BOT, BotMonitor::classify($this->row('Inconnue', 10), 150));
    }

    public function testBrowserBecomesSuspectOnVolumeOrBurst(): void
    {
        $this->assertSame(BotMonitor::KIND_HUMAN, BotMonitor::classify($this->row(null, 150), 150));
        $this->assertSame(BotMonitor::KIND_SUSPECT, BotMonitor::classify($this->row(null, 151), 150));
        $this->assertSame(BotMonitor::KIND_SUSPECT, BotMonitor::classify($this->row(null, 10, BotMonitor::BURST_THRESHOLD), 150));
        $this->assertSame(BotMonitor::KIND_HUMAN, BotMonitor::classify($this->row(null, 10, BotMonitor::BURST_THRESHOLD - 1), 150));
    }

    public function testCrawlerFlagWithoutFamilyIsStillABot(): void
    {
        // ligne enregistrée avant que la famille ne le soit
        $row = $this->row(null, 10);
        $row['is_crawler_detect'] = 1;

        $this->assertSame(BotMonitor::KIND_BOT, BotMonitor::classify($row, 150));
    }

    public function testDeclaredIdentityOfAScraper(): void
    {
        $this->assertSame('navigateur', BotMonitor::declaredIdentity(null, self::FIREFOX));
        $this->assertSame('script', BotMonitor::declaredIdentity(null, 'node'));
        $this->assertSame('script', BotMonitor::declaredIdentity(BotMonitor::FAMILY_SCRIPT, 'node'));
        $this->assertSame('Google', BotMonitor::declaredIdentity('Google', self::FIREFOX));
    }

    public function testEveryFamilyKindHasALabel(): void
    {
        foreach (['Google', 'OpenAI', 'Ahrefs', 'Scrapy', BotMonitor::FAMILY_SCRIPT, BotMonitor::FAMILY_OTHER_BOT, null] as $family)
        {
            $this->assertArrayHasKey(BotMonitor::familyKind($family), BotMonitor::KIND_LABELS);
        }
    }

    /** @return array<string, mixed> */
    private function row(?string $family, int $hits, int $peak = 1): array
    {
        return [
            'honeypot_triggered' => 0,
            'bot_family' => $family,
            'is_crawler_detect' => $family === null ? 0 : 1,
            'hit_count' => $hits,
            'peak_hits' => $peak,
        ];
    }
}
