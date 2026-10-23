<?php

declare(strict_types=1);

namespace Ladecadanse\Seo;

use DateTimeImmutable;

final readonly class SitemapEntry
{
    public function __construct(
        public string $path,
        public ?DateTimeImmutable $lastModified,
    ) {
    }
}
