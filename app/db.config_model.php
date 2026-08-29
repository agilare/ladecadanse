<?php
// same values as DB_HOST, etc. constants in env.php
return [
    'default' => [
        'host' => 'localhost',
        'dbname' => 'ladecadanse',
        'user' => '',
        'password' => '',
    ],

    // Les deux connexions de « composer prod-copy » (bin/prod-copy.php), qui
    // fabrique une copie locale anonymisée de la production. Inutiles tant qu'on
    // ne s'en sert pas : le reste du site n'ouvre que 'default'.
    //
    // 'prod' est lue en lecture seule. L'accès MySQL distant s'active côté
    // Infomaniak, sur son adresse IP.
    'prod' => [
        'host' => '',
        'dbname' => '',
        'user' => '',
        'password' => '',
    ],
    // 'prod_copy' est créée par le script — ne pas la créer à la main. Son
    // utilisateur local a donc besoin du droit CREATE (et DROP avec --force).
    'prod_copy' => [
        'host' => 'localhost',
        'dbname' => 'ladecadanse_prod_copy',
        'user' => '',
        'password' => '',
    ]
];
