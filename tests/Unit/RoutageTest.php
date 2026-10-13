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
        'organisateur-edit\.php'         => '/organisateur/edit.php',
        'lieu-edit\.php'                 => '/lieu/edit.php',
        'lieu-text-edit\.php'            => '/lieu/text-edit.php',
        'user-edit\.php'                 => '/user/edit.php',
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

    public function testRobotsTxtPasseParLeScriptQuiCalculeLesAnnees(): void
    {
        $this->assertStringContainsString('RewriteRule ^robots\.txt$ /misc/robots.php [L]', $this->routage());
        $this->assertFileExists(__DIR__ . '/../../pages/misc/robots.php');
    }

    /**
     * Les pages vivent sous `pages/` mais leurs URL n'en portent pas la trace : trois règles
     * font la jonction. Qu'une seule disparaisse et tout le site répond 404 — sans que rien
     * ne le signale à qui teste sous `php -S`, qui ignore le `.htaccess`.
     */
    public function testLesPagesSontServiesDepuisPages(): void
    {
        $routage = str_replace("\r\n", "\n", $this->routage());

        $this->assertStringContainsString(
            "RewriteCond %{THE_REQUEST} ^\\S+\\s/pages/ [NC]\nRewriteRule ^pages/(.*)$ /$1 [R=301,L]",
            $routage,
            "La 301 de /pages/… vers l'URL propre manque : chaque page aurait deux adresses"
        );
        $this->assertStringContainsString('RewriteRule ^$ pages/index.php [L]', $routage, "L'accueil n'est plus servi");
        $this->assertStringContainsString(
            "RewriteCond $1 !^pages/\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteRule ^(.+?\\.php)(/.*)?$ pages/$1$2 [L]",
            $routage,
            'La réécriture vers pages/ manque : toutes les pages répondraient 404'
        );
        $this->assertFileExists(__DIR__ . '/../../pages/index.php');
    }

    /** Une cible d'ErrorDocument absente ne lève rien : Apache sert sa propre page */
    public function testLesErrorDocumentVisentLeFichierSousPages(): void
    {
        $erreurs = (string) file_get_contents(__DIR__ . '/../../htaccess/60-erreurs.conf');

        $this->assertSame(4, substr_count($erreurs, 'ErrorDocument'));
        $this->assertSame(4, substr_count($erreurs, ' /pages/misc/error.php'));
        $this->assertFileExists(__DIR__ . '/../../pages/misc/error.php');
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
                __DIR__ . '/../../pages' . $nouvelle,
                "La cible $nouvelle d'une redirection 301 n'existe pas"
            );
        }
    }
}
