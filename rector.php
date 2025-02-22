<?php

declare(strict_types=1);

//use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
//use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Php53\Rector\FuncCall\DirNameFileConstantToDirConstantRector;

// rector < 1
//return static function (RectorConfig $rectorConfig): void {
//    $rectorConfig->paths([
//        __DIR__ . '/',
//        __DIR__ . '/tests/apiCest.php',
//    ]);
//    $rectorConfig->skip([
//        __DIR__ . '/docker',
//        __DIR__ . '/node_modules',
//        __DIR__ . '/resouces',
//        __DIR__ . '/var',
//        __DIR__ . '/vendor',
//        __DIR__ . '/web',
//        __DIR__ . '/tests',
//    ]);
//    // register a single rule
//    //$rectorConfig->rule(InlineConstructorDefaultToPropertyRector::class);
//    // define sets of rules
//    $rectorConfig->sets([
//        LevelSetList::UP_TO_PHP_53
//    ]);
//    //$rectorConfig->sets([SetList::PHP_52]);
//};

// rector 2
return RectorConfig::configure()
                ->withPaths([
                    __DIR__ . '/',
                    __DIR__ . '/tests/apiCest.php',
                ])
                ->withSkip([
                    __DIR__ . '/docker',
                    __DIR__ . '/node_modules',
                    __DIR__ . '/resouces',
                    __DIR__ . '/var',
                    __DIR__ . '/vendor',
                    __DIR__ . '/tests',
                    //DirNameFileConstantToDirConstantRector::class,
                ])
                ->withFileExtensions(['php'])
                //->withSets([LevelSetList::UP_TO_PHP_53]);
                ->withSets([SetList::PHP_70]); // PHP_52, etc. PHP_80
