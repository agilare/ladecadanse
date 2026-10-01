<?php

namespace Ladecadanse\Utils;

/**
 * Fenêtre des `?courant=` ouverts aux robots dans robots.txt : l'année en cours et la suivante.
 */
class RobotsTxt
{
    private const string FENETRE = '/^Allow: \/index\.php\?courant=\d{4}-(\r?\n)(?:Allow: \/index\.php\?courant=\d{4}-\r?\n)*/m';

    public static function withCourantWindow(string $robotsTxt, int $year): string
    {
        $fenetre = sprintf('Allow: /index.php?courant=%d-$1Allow: /index.php?courant=%d-$1', $year, $year + 1);

        return preg_replace(self::FENETRE, $fenetre, $robotsTxt) ?? $robotsTxt;
    }
}
