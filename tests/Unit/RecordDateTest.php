<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Dto\RecordDate;

final class RecordDateTest extends Unit
{
    public function testLaPremiereDateValideLEmporte(): void
    {
        $record = RecordDate::fromRow(['id' => 3, 'modif' => '2026-10-02 10:00:00', 'ajout' => '2026-01-01 10:00:00'], 'id', 'modif', 'ajout');

        $this->assertSame(3, $record->id);
        $this->assertSame('2026-10-02 10:00:00', $record->modifiedAt?->format('Y-m-d H:i:s'));
    }

    public function testUneDateNulleDeMysqlRetombeSurLaSuivante(): void
    {
        $record = RecordDate::fromRow(['id' => '3', 'modif' => '0000-00-00 00:00:00', 'ajout' => '2026-01-01 10:00:00'], 'id', 'modif', 'ajout');

        $this->assertSame(3, $record->id);
        $this->assertSame('2026-01-01 10:00:00', $record->modifiedAt?->format('Y-m-d H:i:s'));
    }

    public function testSansDateValideLaFicheNEstPasDatee(): void
    {
        $record = RecordDate::fromRow(['id' => 3, 'modif' => '0000-00-00 00:00:00', 'ajout' => null], 'id', 'modif', 'ajout');

        $this->assertNull($record->modifiedAt);
    }
}
