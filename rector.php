<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

/*
 * Pistes non activées, à monter d'un niveau à la fois si besoin :
 * ->withDeadCodeLevel(15) et ->withCodeQualityLevel(15). Prévoir alors de
 * skipper RecastingRemovalRector : il retire les (int) défensifs autour de
 * $get['idE'] & co. en contexte SQL, à rebours de la convention du projet.
 */

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/',
    ])
    // même périmètre que phpstan.neon et psalm.xml ; .claude abrite les
    // worktrees, soit autant de copies complètes du projet
    ->withSkip([
        __DIR__ . '/.claude',
        __DIR__ . '/docker',
        __DIR__ . '/node_modules',
        __DIR__ . '/resources',
        __DIR__ . '/tests',
        __DIR__ . '/var',
        __DIR__ . '/vendor',
        __DIR__ . '/web',
    ])
    ->withFileExtensions(['php'])
    // à côté du cache de PHPStan ; var/ est ignoré par git
    ->withCache(__DIR__ . '/var/cache/rector')
    // tous les sets jusqu'à la version PHP de composer.json (8.4 aujourd'hui) :
    // contrairement à SetList::PHP_84, rien à retoucher au prochain palier
    ->withPhpSets();
