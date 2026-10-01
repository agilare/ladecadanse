<?php

/**
 * Configuration de Doctrine Migrations, lue par vendor/bin/doctrine-migrations depuis la racine
 * du dépôt. La connexion est dans migrations-db.php. Voir resources/database/README.md.
 */

return [
    'migrations_paths' => [
        'Ladecadanse\Migrations' => __DIR__ . '/resources/database/migrations',
    ],
    'table_storage' => [
        'table_name' => 'doctrine_migration_versions',
    ],
    // Sur MariaDB, chaque instruction DDL valide implicitement la transaction en cours, et la
    // plupart des tables sont en MyISAM : envelopper les migrations dans une transaction
    // promettrait un retour arrière qui n'aurait pas lieu. Une migration interrompue laisse
    // en place ce qui a déjà passé — d'où des migrations courtes, et un mysqldump avant la prod.
    'all_or_nothing' => false,
    'transactional' => false,
    // une seule plateforme, MariaDB : le garde-fou généré dans chaque classe n'apporterait rien
    'check_database_platform' => false,
    'custom_template' => __DIR__ . '/resources/database/migration.tpl',
];
