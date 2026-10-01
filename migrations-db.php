<?php

/**
 * Connexion de Doctrine Migrations, construite depuis app/db.config.php — la même source que
 * DbConnectorPdo et bin/prod-copy.php : aucun mot de passe ne vit ailleurs.
 *
 * L'entrée se choisit par la variable d'environnement LADECADANSE_DB, « default » à défaut :
 *
 *   LADECADANSE_DB=prod composer db:status              (Git Bash)
 *   $env:LADECADANSE_DB='prod'; composer db:status      (PowerShell)
 *
 * Une entrée peut porter un compte réservé aux migrations, `migration_user` et
 * `migration_password` : le compte de l'application n'a souvent que SELECT, INSERT, UPDATE et
 * DELETE, quand une migration a besoin d'ALTER, de CREATE, d'INDEX et de DROP.
 */

$configFile = __DIR__ . '/app/db.config.php';

if (!is_file($configFile)) {
    throw new RuntimeException('app/db.config.php est introuvable : le créer à partir de app/db.config_model.php.');
}

/** @var array<string, array<string, string>> $configs */
$configs = require $configFile;

$entry = getenv('LADECADANSE_DB') ?: 'default';

if (!isset($configs[$entry])) {
    throw new RuntimeException(sprintf(
        'LADECADANSE_DB vaut « %s », absente de app/db.config.php (entrées : %s).',
        $entry,
        implode(', ', array_keys($configs))
    ));
}

$config = $configs[$entry];

// DbConnectorPdo concatène « host » tel quel dans son DSN, si bien que le port s'y glisse
// (« 127.0.0.1;port=3307 », pour le tunnel SSH vers la production). DBAL veut deux paramètres.
$hostParts = explode(';', $config['host']);
$host = array_shift($hostParts);
$port = null;

foreach ($hostParts as $part) {
    [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
    if (trim($key) === 'port') {
        $port = (int) $value;
    }
}

return array_filter([
    'driver' => 'pdo_mysql',
    'host' => $host,
    'port' => $port,
    'dbname' => $config['dbname'],
    'user' => $config['migration_user'] ?? $config['user'],
    'password' => $config['migration_password'] ?? $config['password'],
    'charset' => 'utf8mb4',
], static fn ($value): bool => $value !== null);
