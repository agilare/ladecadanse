<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;

/**
 * Redirections des pages déplacées, dans `htaccess/50-routage.conf`.
 *
 * C'est la seule partie du `.htaccess` qui se périme quand le dépôt bouge, et elle ne se
 * teste pas depuis la suite `site` : `php -S` ignore le `.htaccess`, et sur Apache la
 * redirection ne vaut qu'une fois `composer config:build` passé. On relit donc le fragment
 * lui-même, comme un test unitaire relit déjà le `.sql` des localités fourre-tout.
 *
 * Une page renommée sans sa 301 casse les signets et le référencement en silence : ce test
 * est là pour que l'oubli se voie.
 */
final class RoutageTest extends Unit
{
    /** Ancienne URL => nouvelle, telles que la page a bougé */
    private const array REDIRECTIONS = [
        'user-login\.php'                => '/user/login.php',
        'user-register\.php'             => '/user/register.php',
        'user-reset\.php'                => '/user/reset.php',
        'user-reset2\.php'               => '/user/reset2.php',
        'user\.php'                      => '/user/dashboard.php',
        'evenement-search\.php'          => '/event/search.php',
        'evenement\.php'                 => '/event/evenement.php',
        'lieux\.php'                     => '/lieu/lieux.php',
        'organisateurs\.php'             => '/organisateur/organisateurs.php',
        'admin/gererEvenements\.php'     => '/admin/events.php',
    ];

    private function routage(): string
    {
        return (string) file_get_contents(__DIR__ . '/../../htaccess/50-routage.conf');
    }

    /**
     * @dataProvider fournirRedirections
     */
    public function testChaquePageDeplaceeGardeSaRedirection(string $ancienne, string $nouvelle): void
    {
        $this->assertStringContainsString(
            'RewriteRule ^' . $ancienne . ' ' . $nouvelle . ' [NC,R=301,L]',
            $this->routage(),
            "La redirection de $ancienne vers $nouvelle manque dans htaccess/50-routage.conf"
        );
    }

    /** @return iterable<string, array{string, string}> */
    public static function fournirRedirections(): iterable
    {
        foreach (self::REDIRECTIONS as $ancienne => $nouvelle)
        {
            yield $ancienne => [$ancienne, $nouvelle];
        }
    }

    /**
     * Les cibles des redirections doivent exister : une 301 vers une page absente vaut un 404,
     * en pire — le navigateur mémorise la redirection.
     */
    public function testLesCiblesDesRedirectionsExistent(): void
    {
        foreach (self::REDIRECTIONS as $nouvelle)
        {
            $this->assertFileExists(
                __DIR__ . '/../..' . $nouvelle,
                "La cible $nouvelle d'une redirection 301 n'existe pas"
            );
        }
    }
}
