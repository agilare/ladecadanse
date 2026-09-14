<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Evenement;

/**
 * Couvre le nommage des miniatures WebP (#170) et le repli sur les miniatures historiques,
 * qui est ce qui rend la migration auto-portante : aucune conversion préalable n'est requise.
 */
final class EvenementThumbnailTest extends Unit
{
    private string $dir;

    protected function _before(): void
    {
        $this->dir = sys_get_temp_dir() . '/ldd_thumb_' . uniqid();
        mkdir($this->dir);
        Evenement::$systemDirPath = $this->dir . '/';
    }

    protected function _after(): void
    {
        $this->removeTree($this->dir);
    }

    private function removeTree(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $path)
        {
            is_dir($path) ? $this->removeTree($path) : unlink($path);
        }
        @rmdir($dir);
    }

    private function touchFile(string $relativePath): void
    {
        $full = $this->dir . '/' . $relativePath;
        @mkdir(dirname($full), 0777, true);
        file_put_contents($full, 'x');
    }

    /**
     * L'extension est ajoutée, pas substituée : la base ne stocke qu'un nom par image, et le
     * nom complet dit de quel original la miniature sort.
     */
    public function testThumbFileNameAppendsTheWebpExtension(): void
    {
        $this->assertSame('s_521249_2026-09-08.jpg.webp', Evenement::thumbFileName('521249_2026-09-08.jpg'));
        $this->assertSame('s_521249_2026-09-08.png.webp', Evenement::thumbFileName('521249_2026-09-08.png'));
    }

    public function testThumbPathServesTheWebpWhenItExists(): void
    {
        $this->touchFile('s_521249_2026-09-08.jpg.webp');

        $this->assertSame('s_521249_2026-09-08.jpg.webp', Evenement::getThumbFilePath('521249_2026-09-08.jpg'));
    }

    /** Une image antérieure au passage au WebP garde la miniature écrite à l'époque. */
    public function testThumbPathFallsBackToTheLegacyThumbnail(): void
    {
        $this->touchFile('s_400000_2026-09-08.png');

        $this->assertSame('s_400000_2026-09-08.png', Evenement::getThumbFilePath('400000_2026-09-08.png'));
    }

    /** Rien sur le disque : le chemin historique est rendu, et c'est le 404 habituel. */
    public function testThumbPathReturnsTheLegacyPathWhenNothingIsOnDisk(): void
    {
        $this->assertSame('s_999_2026-09-08.jpg', Evenement::getThumbFilePath('999_2026-09-08.jpg'));
    }

    /**
     * Les fiches d'une année révolue vivent dans un sous-répertoire : le repli doit chercher
     * le WebP au même endroit que l'image, pas à la racine.
     */
    public function testThumbPathFollowsTheYearlyArchiving(): void
    {
        $this->touchFile('2020/s_123_2020-03-15.jpg.webp');

        $this->assertSame('2020/s_123_2020-03-15.jpg.webp', Evenement::getThumbFilePath('123_2020-03-15.jpg'));
        $this->assertSame('2020/s_124_2020-03-15.jpg', Evenement::getThumbFilePath('124_2020-03-15.jpg'));
    }

    /** La suppression doit emporter les deux formes, sans quoi le WebP resterait orphelin. */
    public function testRemovingAnImageErasesBothThumbnailForms(): void
    {
        $this->touchFile('521249_2026-09-08.jpg');
        $this->touchFile('s_521249_2026-09-08.jpg.webp');
        $this->touchFile('s_521249_2026-09-08.jpg');

        Evenement::rmImageAndItsMiniature('521249_2026-09-08.jpg');

        $this->assertFileDoesNotExist($this->dir . '/521249_2026-09-08.jpg');
        $this->assertFileDoesNotExist($this->dir . '/s_521249_2026-09-08.jpg.webp');
        $this->assertFileDoesNotExist($this->dir . '/s_521249_2026-09-08.jpg');
    }

    /** La copie d'un événement répété doit emporter la miniature WebP. */
    public function testCopyingAnEventCarriesTheWebpThumbnail(): void
    {
        $this->touchFile('100_2026-09-08.jpg');
        $this->touchFile('s_100_2026-09-08.jpg.webp');

        Evenement::safeCopyWithMiniature('100_2026-09-08.jpg', '200_2026-09-09.jpg');

        $this->assertFileExists($this->dir . '/200_2026-09-09.jpg');
        $this->assertFileExists($this->dir . '/s_200_2026-09-09.jpg.webp');
    }

    /** Un événement d'avant la bascule se copie encore, avec sa miniature d'origine. */
    public function testCopyingStillCarriesALegacyThumbnail(): void
    {
        $this->touchFile('300_2026-09-08.png');
        $this->touchFile('s_300_2026-09-08.png');

        Evenement::safeCopyWithMiniature('300_2026-09-08.png', '400_2026-09-09.png');

        $this->assertFileExists($this->dir . '/400_2026-09-09.png');
        $this->assertFileExists($this->dir . '/s_400_2026-09-09.png');
        $this->assertFileDoesNotExist($this->dir . '/s_400_2026-09-09.png.webp');
    }
}
