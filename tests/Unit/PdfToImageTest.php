<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Utils\PdfToImage;
use RuntimeException;

/**
 * Rendu serveur de la 1re page d'un PDF (import par URL d'evenement-edit).
 *
 * Le rendu lui-même demande Imagick et Ghostscript, absents du poste de
 * développement : les tests qui en dépendent se sautent d'eux-mêmes plutôt que
 * de rougir pour une raison d'environnement. Ce qui est couvert partout, c'est
 * la reconnaissance du format et le refus — deux décisions prises avant qu'aucun
 * décodeur n'entre en jeu, et les seules qui gardent Ghostscript à distance.
 */
final class PdfToImageTest extends Unit
{
    public function testReconnaitUnPdfASaSignature(): void
    {
        $this->assertTrue(PdfToImage::estUnPdf("%PDF-1.7\nreste du document"));
        $this->assertTrue(PdfToImage::estUnPdf('%PDF-1.4'));
    }

    public function testNeConfondPasUneImageAvecUnPdf(): void
    {
        $this->assertFalse(PdfToImage::estUnPdf("\x89PNG\r\n\x1a\n"));
        $this->assertFalse(PdfToImage::estUnPdf(''));
        // la signature doit être en tête : un PDF précédé de quoi que ce soit
        // n'en est pas un, et Imagick ne doit pas avoir à en juger
        $this->assertFalse(PdfToImage::estUnPdf("GIF89a%PDF-1.7"));
    }

    public function testRefuseCeQuiNestPasUnPdfSansAppelerImagick(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("n'est pas un PDF");

        PdfToImage::convertirPremierePage("\x89PNG\r\n\x1a\n");
    }

    public function testEstDisponibleNeLevePasQuandImagickManque(): void
    {
        // le formulaire interroge cette méthode à chaque affichage : elle doit
        // répondre, y compris là où l'extension n'est pas installée
        $this->assertIsBool(PdfToImage::estDisponible());
    }

    public function testMessageOrienteVersLEnvoiDeFichierQuandImagickManque(): void
    {
        if (PdfToImage::estDisponible())
        {
            $this->markTestSkipped('Imagick est disponible : le refus ne se produit pas ici');
        }

        try
        {
            PdfToImage::convertirPremierePage("%PDF-1.7\nfaux document");
            $this->fail('Un PDF sans Imagick doit être refusé');
        }
        catch (RuntimeException $e)
        {
            // l'utilisateur doit repartir avec la marche à suivre, pas avec un
            // constat de panne : l'autre voie, elle, fonctionne toujours
            $this->assertStringContainsString('Envoyer', $e->getMessage());
        }
    }

    public function testRendLaPremierePageEnWebpEtElleSeule(): void
    {
        if (!PdfToImage::estDisponible())
        {
            $this->markTestSkipped('Imagick ou son décodeur PDF est absent de cet environnement');
        }

        $webp = PdfToImage::convertirPremierePage(self::pdfDeuxPagesRougePuisBleue());

        $infos = getimagesizefromstring($webp);

        $this->assertNotFalse($infos, 'Le rendu doit être une image lisible');
        $this->assertSame('image/webp', $infos['mime']);
        $this->assertLessThanOrEqual(1600, $infos[0], 'La largeur est plafonnée');

        // La page 1 est rouge, la page 2 bleue : la couleur dit à elle seule
        // laquelle a été rendue. Un simple « c'est une image » ne le dirait pas.
        $image = imagecreatefromstring($webp);
        $this->assertNotFalse($image);

        $couleur = imagecolorat($image, (int) (imagesx($image) / 2), (int) (imagesy($image) / 2));
        $rouge = ($couleur >> 16) & 0xFF;
        $bleu = $couleur & 0xFF;

        $this->assertGreaterThan($bleu, $rouge, 'C’est la première page qui doit être rendue, pas la seconde');
    }

    /**
     * PDF minimal à deux pages, la première rouge et la seconde bleue.
     *
     * Construit ici plutôt que déposé en fixture : le fichier resterait un
     * binaire opaque dans le dépôt, alors que ces quelques objets se lisent. Les
     * décalages de la table xref sont calculés, un PDF sans xref valable étant
     * reconstruit en silence par Ghostscript — ce qui ferait passer le test pour
     * de mauvaises raisons.
     */
    private static function pdfDeuxPagesRougePuisBleue(): string
    {
        $contenu = static fn (string $couleur): string => "$couleur 20 20 160 160 re f";

        $objets = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R 5 0 R] /Count 2 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] /Contents 4 0 R >>',
            '<< /Length ' . strlen($contenu('1 0 0 rg')) . " >>\nstream\n" . $contenu('1 0 0 rg') . "\nendstream",
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] /Contents 6 0 R >>',
            '<< /Length ' . strlen($contenu('0 0 1 rg')) . " >>\nstream\n" . $contenu('0 0 1 rg') . "\nendstream",
        ];

        $pdf = "%PDF-1.4\n";
        $decalages = [];

        foreach ($objets as $index => $corps)
        {
            $decalages[] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n" . $corps . "\nendobj\n";
        }

        $debutXref = strlen($pdf);
        $pdf .= 'xref' . "\n" . '0 ' . (count($objets) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        foreach ($decalages as $decalage)
        {
            $pdf .= sprintf("%010d 00000 n \n", $decalage);
        }

        $pdf .= 'trailer' . "\n" . '<< /Size ' . (count($objets) + 1) . ' /Root 1 0 R >>' . "\n";
        $pdf .= 'startxref' . "\n" . $debutXref . "\n" . '%%EOF';

        return $pdf;
    }
}
