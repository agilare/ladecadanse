<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Utils\DateHelper;

/**
 * Âge d'un mot de passe sur la fiche de profil (#249) : arrondi à l'année entière inférieure.
 */
final class DateHelperElapsedYearsTest extends Unit
{
    public function testLessThanAYear(): void
    {
        $this->assertSame("il y a moins d'1 an", DateHelper::elapsedYearsFr('2026-03-12 10:00:00', '2026-10-26 10:00:00'));
    }

    public function testOneFullYear(): void
    {
        $this->assertSame("il y a plus d'1 an", DateHelper::elapsedYearsFr('2025-03-12 10:00:00', '2026-10-26 10:00:00'));
    }

    public function testSeveralFullYears(): void
    {
        $this->assertSame("il y a plus de 3 ans", DateHelper::elapsedYearsFr('2023-03-12', '2026-10-26 10:00:00'));
    }

    /**
     * L'année n'est pleine qu'au jour anniversaire : la veille, c'est encore moins d'un an.
     */
    public function testYearBoundary(): void
    {
        $this->assertSame("il y a moins d'1 an", DateHelper::elapsedYearsFr('2025-10-26 10:00:00', '2026-10-25 23:59:59'));
        $this->assertSame("il y a plus d'1 an", DateHelper::elapsedYearsFr('2025-10-26 10:00:00', '2026-10-26 10:00:00'));
    }
}
