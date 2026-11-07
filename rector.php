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
    // Répertoires listés un à un plutôt que la racine + withSkip() : le skip de
    // Rector ne filtre qu'après le parcours, donc partir de la racine fait
    // traverser vendor/ (13 000 fichiers PHP) et var/ pour finalement tout
    // jeter — plus de 5 minutes, au-delà du timeout de composer. Y ajouter
    // les nouveaux répertoires de code.
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/librairies',
        __DIR__ . '/pages',
    ])
    // les scripts de configuration de la racine : migrations.php, rector.php…
    ->withRootFiles()
    ->withFileExtensions(['php'])
    // à côté du cache de PHPStan ; var/ est ignoré par git
    ->withCache(__DIR__ . '/var/cache/rector')
    // tous les sets jusqu'à la version PHP de composer.json (8.4 aujourd'hui) :
    // contrairement à SetList::PHP_84, rien à retoucher au prochain palier.
    // Comme withRootFiles(), suppose un lancement depuis la racine du projet
    ->withPhpSets();
