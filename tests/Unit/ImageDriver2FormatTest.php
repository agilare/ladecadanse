<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Utils\ImageDriver2;

/**
 * Couvre le format d'écriture des dérivées (#170) : le paramètre $outputMimeType, et le cas
 * palettisé qui faisait échouer imagewebp() en silence.
 */
final class ImageDriver2FormatTest extends Unit
{
    private string $dir;

    protected function _before(): void
    {
        $this->dir = sys_get_temp_dir() . '/ldd_driver_' . uniqid();
        mkdir($this->dir);
        $GLOBALS['rep_images_even'] = $this->dir . '/';
    }

    protected function _after(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $path)
        {
            unlink($path);
        }
        @rmdir($this->dir);
    }

    /** Une image quelconque, assez chargée pour que la compression ait un sens. */
    private function writeSource(string $nom, int $width, int $height, string $format): string
    {
        $image = imagecreatetruecolor($width, $height);
        mt_srand(1);
        for ($i = 0; $i < 200; $i++)
        {
            imagefilledellipse(
                $image,
                mt_rand(0, $width),
                mt_rand(0, $height),
                mt_rand(5, 60),
                mt_rand(5, 60),
                imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255))
            );
        }

        $path = $this->dir . '/' . $nom;
        match ($format) {
            'png' => imagepng($image, $path),
            'gif' => imagegif($image, $path),
            'jpg' => imagejpeg($image, $path, 90),
        };

        return $path;
    }

    public function testOutputMimeTypeWritesWebpFromAPng(): void
    {
        $source = $this->writeSource('source.png', 600, 850, 'png');

        $driver = new ImageDriver2('evenement');
        $written = $driver->processImageFromPath($source, 's_sortie.png.webp', 150, 240, '', 0, 'image/webp');

        $this->assertTrue($written, (string) $driver->getErreur());
        $this->assertSame('image/webp', getimagesize($this->dir . '/s_sortie.png.webp')['mime']);
    }

    /**
     * Sans $outputMimeType, la dérivée garde le format du fichier reçu : lieu et organisateur
     * n'ont pas encore basculé et ne doivent rien voir changer.
     */
    public function testWithoutOutputMimeTypeTheDerivativeKeepsTheSourceFormat(): void
    {
        $source = $this->writeSource('source2.png', 600, 850, 'png');

        $driver = new ImageDriver2('evenement');
        $written = $driver->processImageFromPath($source, 's_sortie2.png', 150, 240);

        $this->assertTrue($written, (string) $driver->getErreur());
        $this->assertSame('image/png', getimagesize($this->dir . '/s_sortie2.png')['mime']);
    }

    /**
     * Régression : imagewebp() refuse une image palettisée, et le redimensionnement en produit
     * une pour les GIF. La miniature sortait vide, et l'erreur n'était lue par personne.
     */
    public function testAPalettedGifStillProducesAValidWebp(): void
    {
        $source = $this->writeSource('source.gif', 600, 800, 'gif');

        $driver = new ImageDriver2('evenement');
        $written = $driver->processImageFromPath($source, 's_sortie.gif.webp', 150, 240, '', 0, 'image/webp');

        $this->assertTrue($written, (string) $driver->getErreur());

        $produced = $this->dir . '/s_sortie.gif.webp';
        $this->assertGreaterThan(0, filesize($produced));
        $this->assertSame('image/webp', getimagesize($produced)['mime']);
    }

    /** La boîte est respectée sur la largeur pour une image portrait. */
    public function testAPortraitThumbnailFitsTheRequestedWidth(): void
    {
        $source = $this->writeSource('portrait.png', 600, 850, 'png');

        $driver = new ImageDriver2('evenement');
        $driver->processImageFromPath($source, 's_portrait.png.webp', 150, 240, '', 0, 'image/webp');

        [$width, $height] = getimagesize($this->dir . '/s_portrait.png.webp');
        $this->assertSame(150, $width);
        $this->assertLessThanOrEqual(240, $height);
    }
}
