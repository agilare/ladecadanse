<?php
declare(strict_types=1);

namespace Ladecadanse;

class RegionConfig
{
    private const string DEFAULT = 'ge';
    private const int COOKIE_DURATION = 36_000; // 10 h

    public function __construct(private readonly array $regions)
    {
    }

    /**
     * Fixe la région courante en session et, si elle vient de la query string, la
     * mémorise dans un cookie.
     *
     * Ces effets de bord (écriture de $_SESSION, envoi d'un cookie) vivaient dans le
     * constructeur ; les isoler ici rend `new RegionConfig(...)` inerte et laisse
     * l'appelant décider quand les déclencher (#158). Le cookie et la query string sont
     * passés en paramètres plutôt que lus depuis les superglobales, pour rendre la
     * méthode testable.
     *
     * Priorité, du plus faible au plus fort : défaut « ge », cookie, puis query string.
     */
    public function setPersistentRegion(array $cookie, array $get): void
    {
        // 1. défaut
        if (empty($_SESSION['region']))
        {
            $_SESSION['region'] = self::DEFAULT;
        }

        // 2. cookie
        if (!empty($cookie['ladecadanse_region']))
        {
            $_SESSION['region'] = $cookie['ladecadanse_region'];
        }

        if (empty($get['region']))
        {
            return;
        }

        // 3. query string
        $getRegion = strip_tags((string) $get['region']);
        if (array_key_exists($getRegion, $this->regions))
        {
            $_SESSION['region'] = $getRegion;
            $cookieOptions = [
                'expires' => time() + self::COOKIE_DURATION,
                'path' => '/',
                //'domain' => '.example.com', // leading dot for compatibility or use subdomain
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax'
            ];

            setcookie("ladecadanse_region", $getRegion, $cookieOptions);
        }
    }

    /**
     * Trois fragments d'URL pré-assemblés pour propager la région dans les liens
     * (`region=ge`, `region=ge&amp;`, `?region=ge`), ou trois chaînes vides sur la
     * région par défaut.
     *
     * @deprecated Renvoyer trois variantes pré-collées force l'appelant à choisir la
     * bonne selon sa position dans l'URL. Préférer composer le lien à partir de la
     * seule région (`$_SESSION['region']`), par exemple via un futur helper de
     * construction d'URL, plutôt que de dépendre de ces fragments.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    public function getAppVars(): array
    {
        if ($_SESSION['region'] != self::DEFAULT)
        {
            return ['region=' . $_SESSION['region'], 'region=' . $_SESSION['region'] . "&amp;", '?region=' . $_SESSION['region']];
        }
        return ['', '', ''];
    }
}
