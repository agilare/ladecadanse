<?php

declare(strict_types=1);

//use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
//use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/',
        __DIR__ . '/tests/apiCest.php',
    ]);    
    $rectorConfig->skip([
        __DIR__ . '/docker',
        __DIR__ . '/logs',
        __DIR__ . '/resouces',
        __DIR__ . '/var',
        __DIR__ . '/vendor',
        __DIR__ . '/web',
        __DIR__ . '/tests',
    ]);
    // register a single rule
    //$rectorConfig->rule(InlineConstructorDefaultToPropertyRector::class);

    // define sets of rules
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_53
    ]);
    //$rectorConfig->sets([SetList::PHP_52]);
};