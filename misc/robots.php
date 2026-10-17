<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/env.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config.php';

use Ladecadanse\Utils\RobotsTxt;

$robotsTxt = file_get_contents(__DIR__ . '/../robots.txt');
if ($robotsTxt === false)
{
    http_response_code(503);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

echo RobotsTxt::withSitemap(RobotsTxt::withCourantWindow($robotsTxt, (int) date('Y')), SITE_CANONICAL_URL);
