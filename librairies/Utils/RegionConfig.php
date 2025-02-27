<?php
declare(strict_types=1);

namespace Ladecadanse\Utils;

class RegionConfig
{
    private const DEFAULT = 'ge';
    private const COOKIE_DURATION = 36_000; // 10 h

    public function __construct(array $regions)
    {
        // 1. default
        if (empty($_SESSION['region']))
        {
            $_SESSION['region'] = self::DEFAULT;
        }

        // 2. cookie
        if (!empty($_COOKIE['ladecadanse_region']))
        {
            $_SESSION['region'] = $_COOKIE['ladecadanse_region'];
        }

        // 3. query
        $getRegion = filter_input(INPUT_GET, "region", FILTER_SANITIZE_STRING);
        if (array_key_exists($getRegion, $regions))
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
     * legacy
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
