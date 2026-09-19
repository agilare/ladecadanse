<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Security\SecurityToken;

/**
 * Jeton CSRF des formulaires d'édition réservés aux personnes connectées : lieu, salle et
 * texte d'un lieu, organisateur, événement, copie d'événement, profil, édition groupée.
 *
 * Le jeton de session n'existe qu'une fois qu'un de ces formulaires, ou un lien « Dépublier »,
 * a été rendu, puisque c'est getToken() qui le crée. Avant cela, un envoi sans jeton comparait
 * deux chaînes vides, et passait le contrôle.
 */
final class SecurityTokenTest extends Unit
{
    private const string JETON = '9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08';

    protected function _before(): void
    {
        unset($_SESSION['token']);
    }

    protected function _after(): void
    {
        unset($_SESSION['token']);
    }

    public function testLeJetonDeLaSessionEstAccepte(): void
    {
        $this->assertTrue(SecurityToken::check(self::JETON, self::JETON));
    }

    public function testUnAutreJetonEstRefuse(): void
    {
        $this->assertFalse(SecurityToken::check(str_repeat('0', 64), self::JETON));
        $this->assertFalse(SecurityToken::check(substr(self::JETON, 0, 32), self::JETON));
        $this->assertFalse(SecurityToken::check(self::JETON . '0', self::JETON));
        $this->assertFalse(SecurityToken::check('', self::JETON));
    }

    /**
     * Sans jeton en session, rien ne passe : ni un envoi sans jeton, qui comparait deux
     * chaînes vides, ni un jeton quelconque.
     *
     * @dataProvider fournirJetonsDeSessionInvalides
     */
    public function testSansJetonDeSessionRienNePasse(mixed $session): void
    {
        $this->assertFalse(SecurityToken::check('', $session));
        $this->assertFalse(SecurityToken::check(self::JETON, $session));
    }

    /** @return iterable<string, array{mixed}> */
    public static function fournirJetonsDeSessionInvalides(): iterable
    {
        // ce que les pages passent quand $_SESSION['token'] n'existe pas encore
        yield 'chaîne vide' => [''];
        yield 'null' => [null];
        yield 'tableau' => [[]];
        yield 'entier' => [0];
    }

    /**
     * Un POST forgé peut envoyer un tableau (« token[]=… ») à la place d'une chaîne : il est
     * refusé, sans TypeError ni avertissement.
     *
     * @dataProvider fournirJetonsRecusInvalides
     */
    public function testUnJetonRecuQuiNEstPasUneChaineEstRefuse(mixed $recu): void
    {
        $this->assertFalse(SecurityToken::check($recu, self::JETON));
    }

    /** @return iterable<string, array{mixed}> */
    public static function fournirJetonsRecusInvalides(): iterable
    {
        yield 'tableau' => [[self::JETON]];
        yield 'null' => [null];
    }

    public function testGetTokenCreeLeJetonUneSeuleFois(): void
    {
        $jeton = SecurityToken::getToken();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $jeton);
        $this->assertSame($jeton, $_SESSION['token']);
        $this->assertSame($jeton, SecurityToken::getToken());
        $this->assertTrue(SecurityToken::check($jeton, $_SESSION['token']));
    }
}
