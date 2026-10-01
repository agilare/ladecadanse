<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Utils\LogSafe;

/**
 * Masquage des adresses destinées aux journaux applicatifs (traitement T10 de
 * 40_Donnees_personnelles.md, dépôt ladecadanse-docs).
 */
final class LogSafeTest extends Unit
{
    public function testUneAdresseNeGardeQueSonDomaine(): void
    {
        $this->assertSame('***@example.ch', LogSafe::email('jean.dupont@example.ch'));
    }

    /**
     * Les champs de connexion et de réinitialisation acceptent l'un ou l'autre : un nom
     * d'utilisateur traverse sans être touché, sinon les journaux perdraient ce qui les
     * rend lisibles.
     */
    public function testUnNomDUtilisateurPasseIntact(): void
    {
        $this->assertSame('michel', LogSafe::email('michel'));
        $this->assertSame('Le Rez de L’Usine', LogSafe::email('Le Rez de L’Usine'));
    }

    /**
     * Une saisie forgée peut porter plusieurs arobases : c'est ce qui suit le dernier
     * qui est le domaine, et le reste doit disparaître.
     */
    public function testLeDernierArobaseFaitFoi(): void
    {
        $this->assertSame('***@vrai.ch', LogSafe::email('a@leurre.ch@vrai.ch'));
    }

    public function testValeursVides(): void
    {
        $this->assertSame('', LogSafe::email(''));
        $this->assertSame('', LogSafe::email(null));
        $this->assertSame('', LogSafe::email('   '));
    }

    /**
     * Rien de ce qui précède l'arobase ne doit survivre, pas même une partie locale
     * vide ou un domaine absent : la méthode est appelée sur des saisies libres.
     */
    public function testAdressesIncompletes(): void
    {
        $this->assertSame('***@example.ch', LogSafe::email('@example.ch'));
        $this->assertSame('***@', LogSafe::email('jean@'));
    }
}
