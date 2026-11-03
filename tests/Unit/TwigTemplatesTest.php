<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Security\Authorization;
use Ladecadanse\Security\AuthorizationRepository;
use Ladecadanse\Twig\LadecadanseExtension;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Compiler un template vérifie les noms des fonctions et des filtres, et les arguments nommés :
 * un helper renommé casse ce test plutôt que la page en ligne.
 */
final class TwigTemplatesTest extends Unit
{
    private const string TEMPLATES_DIR = __DIR__ . '/../../templates';

    public static function templateProvider(): iterable
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::TEMPLATES_DIR, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($files as $file)
        {
            if (!str_ends_with($file->getFilename(), '.twig'))
            {
                continue;
            }
            $name = substr($file->getPathname(), strlen(self::TEMPLATES_DIR) + 1);
            yield $name => [$name];
        }
    }

    /**
     * @dataProvider templateProvider
     */
    public function testLeTemplateCompile(string $name): void
    {
        $twig = new Environment(new FilesystemLoader(self::TEMPLATES_DIR), ['strict_variables' => true]);
        $twig->addExtension(new LadecadanseExtension(new Authorization($this->createStub(AuthorizationRepository::class)), []));

        $this->assertNotEmpty($twig->compileSource($twig->getLoader()->getSourceContext($name)));
    }
}
