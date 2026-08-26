<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Utils\Validateur;

/**
 * Couvre le validateur de formulaire à état (issue #153).
 */
final class ValidateurTest extends Unit
{
    private Validateur $validateur;

    protected function _before(): void
    {
        $this->validateur = new Validateur();
    }

    public function testLongueurTexteWithinBounds(): void
    {
        $this->assertTrue($this->validateur->validerLongueurTexte('f', 'ab', 2, 5));
    }

    public function testLongueurTexteTooShort(): void
    {
        $this->assertFalse($this->validateur->validerLongueurTexte('f', 'a', 2, 5));
    }

    public function testLongueurTexteTooLong(): void
    {
        $this->assertFalse($this->validateur->validerLongueurTexte('f', 'abcdef', 2, 5));
    }

    public function testEmailValid(): void
    {
        $this->assertTrue($this->validateur->validerEmail('e', 'a@b.com'));
    }

    public function testEmailInvalid(): void
    {
        $this->assertFalse($this->validateur->validerEmail('e', 'nope'));
    }

    public function testUrlPrependsSchemeAndValidates(): void
    {
        $this->assertTrue($this->validateur->validerURL('u', 'example.com'));
    }

    public function testUrlFullValid(): void
    {
        $this->assertTrue($this->validateur->validerURL('u', 'https://x.org/path?q=1'));
    }

    public function testUrlInvalid(): void
    {
        $this->assertFalse($this->validateur->validerURL('u', 'pas une url'));
    }

    public function testNombreValid(): void
    {
        $this->assertTrue($this->validateur->validerNombre('n', '42'));
    }

    public function testNombreInvalid(): void
    {
        $this->assertFalse($this->validateur->validerNombre('n', 'x'));
    }

    public function testFichierRejectsDisallowedMime(): void
    {
        $file = ['name' => 'photo.exe', 'type' => 'application/x-msdownload', 'tmp_name' => '/none', 'error' => 0];
        $this->assertFalse($this->validateur->validerFichier($file, 'f', ['image/jpeg'], false));
    }

    public function testFichierRejectsPhpInName(): void
    {
        $file = ['name' => 'shell.php.jpg', 'type' => 'image/jpeg', 'tmp_name' => '/none', 'error' => 0];
        $this->assertFalse($this->validateur->validerFichier($file, 'f', ['image/jpeg'], false));
        $this->assertNotFalse($this->validateur->getErreur('f'));
    }

    public function testFichierReportsUploadErrorCode(): void
    {
        $file = ['name' => 'big.jpg', 'type' => 'image/jpeg', 'tmp_name' => '/none', 'error' => UPLOAD_ERR_INI_SIZE];
        $this->assertFalse($this->validateur->validerFichier($file, 'f', ['image/jpeg'], false));
        // le message annonce la limite en vigueur, et non une valeur écrite en dur
        $this->assertStringContainsString('3 Mo', (string) $this->validateur->getErreur('f'));
    }

    /**
     * Écrit une image dans un fichier temporaire et renvoie l'entrée $_FILES
     * correspondante. $type est le MIME *déclaré*, celui que le client choisit.
     *
     * @return array<string, mixed>
     */
    private function fichierImage(int $largeur, int $hauteur, string $nom = 'photo.png', string $type = 'image/png'): array
    {
        $chemin = tempnam(sys_get_temp_dir(), 'ldd_test_');
        $image = imagecreatetruecolor($largeur, $hauteur);
        imagepng($image, $chemin);

        $this->fichiersTemporaires[] = $chemin;

        return ['name' => $nom, 'type' => $type, 'tmp_name' => $chemin, 'error' => 0, 'size' => filesize($chemin)];
    }

    /** @var array<string> */
    private array $fichiersTemporaires = [];

    protected function _after(): void
    {
        foreach ($this->fichiersTemporaires as $chemin)
        {
            @unlink($chemin);
        }

        $this->fichiersTemporaires = [];
    }

    public function testFichierImageRejectsPdf(): void
    {
        $chemin = tempnam(sys_get_temp_dir(), 'ldd_test_');
        file_put_contents($chemin, "%PDF-1.7\nreste du document");
        $this->fichiersTemporaires[] = $chemin;

        // le navigateur est censé l'avoir converti : s'il arrive ici, c'est que
        // JavaScript n'a pas tourné, et le message doit le dire
        $file = ['name' => 'flyer.pdf', 'type' => 'application/pdf', 'tmp_name' => $chemin, 'error' => 0, 'size' => filesize($chemin)];

        $this->assertFalse($this->validateur->validerFichierImage($file, 'f', ['image/jpeg', 'image/png'], false));
        $this->assertStringContainsString('navigateur', (string) $this->validateur->getErreur('f'));
    }

    public function testFichierImageRejectsOversizedDimensions(): void
    {
        // Un en-tête PNG suffit : getimagesize() ne lit rien d'autre, et
        // fabriquer réellement l'image ferait exploser la mémoire du test — ce
        // qui est précisément le risque que ce plafond écarte côté serveur.
        $chemin = tempnam(sys_get_temp_dir(), 'ldd_test_');
        file_put_contents(
            $chemin,
            "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . pack('NN', 9000, 9000) . "\x08\x02\x00\x00\x00" . pack('N', 0)
        );
        $this->fichiersTemporaires[] = $chemin;

        $file = ['name' => 'enorme.png', 'type' => 'image/png', 'tmp_name' => $chemin, 'error' => 0, 'size' => filesize($chemin)];

        $this->assertFalse($this->validateur->validerFichierImage($file, 'f', ['image/png'], false));
        $this->assertStringContainsString('mégapixels', (string) $this->validateur->getErreur('f'));
    }

    public function testFichierImageRejectsForgedMimeType(): void
    {
        // le trou que validerFichier() laissait ouvert : le type est déclaré par
        // le client, il suffisait de l'annoncer « image/png » pour passer
        $chemin = tempnam(sys_get_temp_dir(), 'ldd_test_');
        file_put_contents($chemin, "ceci n'est pas une image");
        $this->fichiersTemporaires[] = $chemin;

        $file = ['name' => 'faux.png', 'type' => 'image/png', 'tmp_name' => $chemin, 'error' => 0, 'size' => filesize($chemin)];

        $this->assertFalse($this->validateur->validerFichierImage($file, 'f', ['image/png'], false));
        $this->assertStringContainsString('image', (string) $this->validateur->getErreur('f'));
    }

    public function testFichierImageRejectsRealMimeOutsideWhitelist(): void
    {
        // un vrai PNG, annoncé en JPEG : c'est le contenu qui décide
        $file = $this->fichierImage(10, 10, 'photo.jpg', 'image/jpeg');

        $this->assertFalse($this->validateur->validerFichierImage($file, 'f', ['image/jpeg'], false));
        $this->assertStringContainsString('image/png', (string) $this->validateur->getErreur('f'));
    }

    public function testFichierImageAcceptsEmptyOptionalField(): void
    {
        $this->assertTrue($this->validateur->validerFichierImage(['name' => ''], 'f', ['image/png'], false));
        $this->assertSame(0, $this->validateur->nbErreurs());
    }

    public function testLastErrorWorksWithStringKeys(): void
    {
        // Régression : lastError() indexait numériquement un tableau à clés
        // textuelles et renvoyait null (cf. Security/Sentry.php).
        $this->validateur->validerLongueurTexte('user', '', 2, 80);
        $last = $this->validateur->lastError();
        $this->assertNotNull($last);
        $this->assertStringContainsString('trop court', $last);
    }

    public function testErrorAccessors(): void
    {
        $this->assertSame(0, $this->validateur->nbErreurs());
        $this->assertNull($this->validateur->getMsgNbErreurs());

        $this->validateur->setErreur('champ', 'boom');
        $this->assertSame(1, $this->validateur->nbErreurs());
        $this->assertSame('boom', $this->validateur->getErreur('champ'));
        $this->assertFalse($this->validateur->getErreur('absent'));
        $this->assertSame('<div class="msg">boom</div>', $this->validateur->getHtmlErreur('champ'));
        $this->assertSame('', $this->validateur->getHtmlErreur('absent'));
        $this->assertSame('Il y a une erreur', $this->validateur->getMsgNbErreurs());

        $this->validateur->setErreur('champ2', 'boom2');
        $this->assertSame('Il y a 2 erreurs', $this->validateur->getMsgNbErreurs());
        $this->assertCount(2, $this->validateur->getErreurs());
    }

    public function testValiderMandatoryEmptyFails(): void
    {
        $this->assertFalse($this->validateur->valider('', 'titre', 'texte', 1, 80, true));
    }

    public function testValiderEmailField(): void
    {
        $this->assertTrue($this->validateur->valider('a@b.com', 'email', 'email', 4, 250, true));
        $this->assertFalse((new Validateur())->valider('nope', 'email', 'email', 4, 250, true));
    }

    public function testValiderUrlField(): void
    {
        $this->assertTrue($this->validateur->valider('http://x.org', 'URL', 'url', 2, 100, false));
    }
}
