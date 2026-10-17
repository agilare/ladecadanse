<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Utils\ConditionalRequest;

final class ConditionalRequestTest extends Unit
{
    private const string ETAG = '"abc123"';
    private const int LAST_MODIFIED = 1_760_000_000;

    /**
     * @dataProvider fournirIfNoneMatch
     */
    public function testIfNoneMatch(string $ifNoneMatch, bool $attendu): void
    {
        $this->assertSame($attendu, ConditionalRequest::isNotModified(self::ETAG, self::LAST_MODIFIED, $ifNoneMatch, ''));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function fournirIfNoneMatch(): iterable
    {
        yield 'identique' => ['"abc123"', true];
        yield 'faible' => ['W/"abc123"', true];
        yield 'suffixe gzip de mod_deflate' => ['"abc123-gzip"', true];
        yield 'suffixe br' => ['"abc123-br"', true];
        yield 'dans une liste' => ['"autre", "abc123"', true];
        yield 'différent' => ['"autre"', false];
    }

    public function testUnEtagDifferentNeRetombePasSurLaDate(): void
    {
        $this->assertFalse(ConditionalRequest::isNotModified(self::ETAG, self::LAST_MODIFIED, '"autre"', gmdate('D, d M Y H:i:s', self::LAST_MODIFIED) . ' GMT'));
    }

    public function testIfModifiedSinceALaDateDuContenu(): void
    {
        $this->assertTrue(ConditionalRequest::isNotModified(self::ETAG, self::LAST_MODIFIED, '', gmdate('D, d M Y H:i:s', self::LAST_MODIFIED) . ' GMT'));
    }

    public function testIfModifiedSinceAnterieurAuContenu(): void
    {
        $this->assertFalse(ConditionalRequest::isNotModified(self::ETAG, self::LAST_MODIFIED, '', gmdate('D, d M Y H:i:s', self::LAST_MODIFIED - 60) . ' GMT'));
    }

    public function testSansDateDuContenuIfModifiedSinceEstIgnore(): void
    {
        $this->assertFalse(ConditionalRequest::isNotModified(self::ETAG, null, '', gmdate('D, d M Y H:i:s') . ' GMT'));
    }

    public function testSansEnTeteConditionnelLaReponseEstComplete(): void
    {
        $this->assertFalse(ConditionalRequest::isNotModified(self::ETAG, self::LAST_MODIFIED, '', ''));
    }
}
