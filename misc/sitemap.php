<?php

declare(strict_types=1);

use Ladecadanse\Repository\EvenementRepository;
use Ladecadanse\Repository\LieuRepository;
use Ladecadanse\Repository\OrganisateurRepository;
use Ladecadanse\Seo\SitemapGenerator;
use Ladecadanse\Utils\HttpFileCache;

require_once __DIR__ . '/../vendor/autoload.php';

$cache = new HttpFileCache(
    __DIR__ . '/../var/cache/sitemap.xml',
    21600,
    'application/xml; charset=utf-8',
    SitemapGenerator::contentDate(...)
);
$cache->serveIfFresh();

require_once __DIR__ . '/../app/bootstrap.php';

$pdo = $connectorPdo->getPDO();
$generator = new SitemapGenerator(
    new EvenementRepository($pdo),
    new LieuRepository($pdo),
    new OrganisateurRepository($pdo),
    SITE_CANONICAL_URL,
    dirname(__DIR__)
);

$cache->storeAndServe($generator->generate($glo_auj_6h));
