<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\HtmlShrink;

final class UrlCanoniqueTest extends Unit
{
    public function testScriptSeulQuandAucunParametre(): void
    {
        $this->assertSame('lieu/lieux.php', HtmlShrink::urlCanonique('lieu/lieux.php'));
        $this->assertSame('lieu/lieux.php', HtmlShrink::urlCanonique('lieu/lieux.php', ['page' => null]));
    }

    public function testParametresAssemblesSansLesNuls(): void
    {
        $this->assertSame(
            'lieu/lieu.php?idL=42&page=3',
            HtmlShrink::urlCanonique('lieu/lieu.php', ['idL' => 42, 'periode' => null, 'page' => 3])
        );
    }

    public function testSeulNullRetireLeParametre(): void
    {
        $this->assertSame('page.php?a=0&b=', HtmlShrink::urlCanonique('page.php', ['a' => 0, 'b' => '']));
    }
}
