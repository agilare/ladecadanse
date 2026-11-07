<?php

/*
 * Routeur du serveur intégré de PHP, qui ignore le .htaccess :
 *
 *     php -S localhost:8000 -t . bin/router.php
 *
 * Rejoue les règles de htaccess/50-routage.conf qui servent les pages depuis pages/ sans
 * que leurs URL le montrent, et pose ce qu'Apache pose autour du script (SCRIPT_NAME,
 * répertoire courant) : les include relatifs et $nom_page en dépendent. Une URL inconnue
 * reçoit pages/misc/error.php, comme sous l'ErrorDocument. Les autres règles du .htaccess
 * (pare-feu, 301 des anciennes URL, protections) ne sont pas rejouées : la mesure qui
 * compte reste Apache.
 */

/**
 * @return string|false|null le script de pages/ à exécuter, false pour laisser le serveur
 *                           intégré servir l'URL telle quelle, null si la réponse est faite
 */
function routeToPages(string $root): string|false|null
{
    $path = rawurldecode((string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH));

    if (str_starts_with($path, '/pages/')) {
        $query = (string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        header('Location: ' . substr($path, 6) . ($query !== '' ? '?' . $query : ''), true, 301);
        return null;
    }

    $script = ['/' => '/index.php', '/robots.txt' => '/misc/robots.php'][$path] ?? null;
    $pathInfo = '';

    if ($script === null) {
        // fichier statique ou page restée à la racine : le serveur intégré s'en charge
        if (is_file($root . $path)) {
            return false;
        }
        if (preg_match('#^(/.+?\.php)(/.*)?$#', $path, $m)) {
            if (is_file($root . $m[1])) {
                return false;
            }
            [$script, $pathInfo] = [$m[1], $m[2] ?? ''];
        } elseif (str_ends_with($path, '/')) {
            $script = $path . 'index.php';
        }
    }

    if ($script === null || !is_file($root . '/pages' . $script)) {
        http_response_code(404);
        $_SERVER['REDIRECT_STATUS'] = '404';
        [$script, $pathInfo] = ['/misc/error.php', ''];
    }

    $file = $root . '/pages' . $script;
    $_SERVER['SCRIPT_NAME'] = '/pages' . $script;
    $_SERVER['SCRIPT_FILENAME'] = $file;
    $_SERVER['PHP_SELF'] = '/pages' . $script . $pathInfo;
    if ($pathInfo !== '') {
        $_SERVER['PATH_INFO'] = $pathInfo;
    }
    chdir(dirname($file));

    return $file;
}

// require au niveau global, comme Apache : les pages comptent sur leurs variables globales
$routedPage = routeToPages(dirname(__DIR__));
if ($routedPage === false) {
    return false;
}
if ($routedPage !== null) {
    require $routedPage;
}
