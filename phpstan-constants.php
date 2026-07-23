<?php

/**
 * Fichier chargé uniquement par PHPStan (bootstrapFiles dans phpstan.neon).
 * Déclare les constantes définies au runtime par l'application, que l'analyse
 * statique ne peut pas voir : __ROOT__ (app/config.php) et CSP_NONCE
 * (app/bootstrap.php). N'est jamais inclus par l'application elle-même.
 */

define('__ROOT__', __DIR__);
define('CSP_NONCE', '');
