<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Ladecadanse\Utils\RobotsTxt;

$robotsTxt = file_get_contents(__DIR__ . '/../robots.txt');
if ($robotsTxt === false)
{
    http_response_code(503);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

echo RobotsTxt::withCourantWindow($robotsTxt, (int) date('Y'));
