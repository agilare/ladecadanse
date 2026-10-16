<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Utils\HttpFileCache;

final class HttpFileCacheTest extends Unit
{
    private string $directory;

    protected function _before(): void
    {
        $this->directory = sys_get_temp_dir() . '/http-file-cache-' . uniqid();
    }

    protected function _after(): void
    {
        array_map(unlink(...), glob($this->directory . '/*') ?: []);
        @rmdir($this->directory);
    }

    public function testUnContenuEcritEstRelu(): void
    {
        $cache = $this->cache(ttl: 60);

        $this->assertTrue($cache->write('<xml/>'));
        $this->assertSame('<xml/>', $cache->read());
    }

    public function testUnContenuExpireNEstPasRelu(): void
    {
        $cache = $this->cache(ttl: 0);
        $cache->write('<xml/>');

        $this->assertNull($cache->read());
    }

    public function testUnCacheAbsentNEstPasRelu(): void
    {
        $this->assertNull($this->cache(ttl: 60)->read());
    }

    public function testLEcritureNeLaissePasDeFichierTemporaire(): void
    {
        $this->cache(ttl: 60)->write('<xml/>');

        $this->assertSame([$this->directory . '/cache.xml'], glob($this->directory . '/*'));
    }

    private function cache(int $ttl): HttpFileCache
    {
        return new HttpFileCache($this->directory . '/cache.xml', $ttl, 'application/xml', static fn (string $content): ?int => null);
    }
}
