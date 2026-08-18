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
        $this->assertStringContainsString('2 Mo', (string) $this->validateur->getErreur('f'));
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
