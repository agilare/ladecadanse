<?php

namespace Tests\Support;

use Dotenv\Dotenv;

/**
 * Accès centralisé aux variables de `tests/.env` (cf. `tests/.env_model`).
 *
 * Évite de dupliquer `Dotenv::createImmutable()` dans chaque `_before` (comme le
 * fait ApiCest) et permet à un Cest de se déclarer « non configuré » plutôt que
 * d'échouer sur une variable absente.
 */
final class TestEnv
{
    private static bool $loaded = false;

    public static function get(string $key): string
    {
        self::load();

        return isset($_ENV[$key]) ? trim((string) $_ENV[$key]) : '';
    }

    public static function getInt(string $key): int
    {
        return (int) self::get($key);
    }

    /**
     * @return string[] les clés demandées qui sont absentes ou vides
     */
    public static function missing(string ...$keys): array
    {
        return array_values(array_filter($keys, static fn (string $key): bool => self::get($key) === ''));
    }

    private static function load(): void
    {
        if (self::$loaded)
        {
            return;
        }

        // safeLoad : ne lève pas si tests/.env est absent ; les Cest concernés
        // seront simplement marqués « skipped » (cf. SiteTester::skipUnlessConfigured)
        Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();
        self::$loaded = true;
    }
}
