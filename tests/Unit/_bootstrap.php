<?php

declare(strict_types=1);

/*
 * Amorce de la suite unit, chargée automatiquement par Codeception.
 *
 * app/config.php n'est pas inclus tel quel : il lit $_SERVER['REQUEST_URI'] et
 * instancie une partie du contexte applicatif, ce qui n'a pas de sens hors
 * requête HTTP. On se contente donc des constantes dont dépendent les classes
 * testées, avec les mêmes valeurs.
 */

if (!defined('UPLOAD_MAX_FILESIZE')) {
    define('UPLOAD_MAX_FILESIZE', 3145728); // 3 Mo
}

if (!defined('UPLOAD_MAX_MEGAPIXELS')) {
    define('UPLOAD_MAX_MEGAPIXELS', 40);
}
