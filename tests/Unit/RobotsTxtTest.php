<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Utils\RobotsTxt;

/**
 * Fenêtre des `?courant=` ouverte aux robots, réécrite à chaque requête de robots.txt (#226).
 */
final class RobotsTxtTest extends Unit
{
    private function robotsTxt(): string
    {
        return (string) file_get_contents(__DIR__ . '/../../robots.txt');
    }

    public function testLaFenetreSuitLAnneeEnCoursDansChaqueGroupe(): void
    {
        $robots = RobotsTxt::withCourantWindow($this->robotsTxt(), 2031);

        $this->assertSame(2, substr_count($robots, "Allow: /index.php?courant=2031-\nAllow: /index.php?courant=2032-\n"));
        $this->assertSame(4, substr_count($robots, 'Allow: /index.php?courant='));
    }

    public function testSeulesLesLignesDeLaFenetreChangent(): void
    {
        $sansFenetre = static fn(string $robots): string => (string) preg_replace('/^Allow: \/index\.php\?courant=.*\n/m', '', $robots);

        $this->assertSame(
            $sansFenetre($this->robotsTxt()),
            $sansFenetre(RobotsTxt::withCourantWindow($this->robotsTxt(), 2031))
        );
    }

    public function testLesFinsDeLigneWindowsSontConservees(): void
    {
        $robots = RobotsTxt::withCourantWindow(str_replace("\n", "\r\n", $this->robotsTxt()), 2031);

        $this->assertSame(2, substr_count($robots, "Allow: /index.php?courant=2031-\r\nAllow: /index.php?courant=2032-\r\n"));
        $this->assertStringNotContainsString("\r\r", $robots);
    }
}
