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
    // 'prod' est lue en lecture seule. L'offre Infomaniak n'ouvrant pas MySQL à
    // l'extérieur, « host » vise un tunnel SSH ouvert à part, le port se glissant
    // dans la valeur — DbConnectorPdo la concatène telle quelle dans le DSN :
    //
    //   ssh -N -L 3307:XXXXXX.myd.infomaniak.com:3306 utilisateur@exemple.ch
    //   'host' => '127.0.0.1;port=3307',
    //
    // 'ssh' et 'ssh_uploads' servent à la phase fichiers, qui rapatrie les images
    // par « ssh … tar » plutôt que par des milliers de requêtes HTTP. Le chemin est
    // celui de web/uploads sur le serveur ; --ssh et --ssh-path le surchargent.
    'prod' => [
        'host' => '',
        'dbname' => '',
        'user' => '',
        'password' => '',
        'ssh' => '', // utilisateur@hote
        'ssh_uploads' => '', // /home/clients/…/sites/exemple.ch/web/uploads
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
