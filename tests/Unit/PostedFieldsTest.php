<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Utils\PostedFields;

final class PostedFieldsTest extends Unit
{
    public function testOnlyNamedFieldsAreRead(): void
    {
        $this->assertSame(
            ['email' => 'a@example.ch'],
            PostedFields::scalars(['email' => 'a@example.ch', 'groupe' => '1'], ['email'])
        );
    }

    public function testMissingAndArrayFieldsAreOmitted(): void
    {
        $this->assertSame([], PostedFields::scalars(['pseudo' => ['x']], ['pseudo', 'email']));
    }

    public function testValuesAreStringsAndNotTrimmed(): void
    {
        $this->assertSame(
            ['groupe' => '8', 'motdepasse' => ' avec espaces '],
            PostedFields::scalars(['groupe' => 8, 'motdepasse' => ' avec espaces '], ['groupe', 'motdepasse'])
        );
    }
}
