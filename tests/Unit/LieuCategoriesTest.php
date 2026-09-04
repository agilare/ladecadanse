<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Lieu;

/**
 * Couvre la colonne `categories` (un SET, donc une liste séparée par des virgules) des
 * deux côtés : le <select> multiple du formulaire d'édition, et l'affichage en clair des
 * pages de lieu, que deux pages composaient chacune de leur côté à un espace près.
 */
final class LieuCategoriesTest extends Unit
{
    public function testLaColonneEstTraduiteEnLibellesSepares(): void
    {
        $this->assertSame('bistrot, cinéma', Lieu::categoriesEnClair('bistrot,cinema'));
    }

    /** MySQL rend le SET sans espaces, mais une valeur saisie à la main peut en porter. */
    public function testLesEspacesAutourDesCodesSontIgnores(): void
    {
        $this->assertSame('salle, théâtre', Lieu::categoriesEnClair('salle , theatre'));
    }

    public function testUneColonneVideOuNulleNeRendRien(): void
    {
        $this->assertSame('', Lieu::categoriesEnClair(''));
        $this->assertSame('', Lieu::categoriesEnClair(null));
    }

    /**
     * Une ligne écrite avant un renommage de catégorie doit rester affichable : le code
     * inconnu passe tel quel, là où un accès direct au tableau des libellés levait une
     * erreur d'index.
     */
    public function testUnCodeInconnuPasseTelQuel(): void
    {
        $this->assertSame('bistrot, cabaret', Lieu::categoriesEnClair('bistrot,cabaret'));
    }

    public function testLeSelectCocheLesSeulesCategoriesDuLieu(): void
    {
        $html = Lieu::getCategoriesOptionsHtml(['salle', 'cinema']);

        $this->assertStringContainsString('<option value="salle" selected="selected">salle</option>', $html);
        $this->assertStringContainsString('<option value="cinema" selected="selected">cinéma</option>', $html);
        $this->assertStringContainsString('<option value="bistrot">bistrot</option>', $html);
        $this->assertSame(2, substr_count($html, 'selected="selected"'));
    }

    public function testLeSelectProposeTouteLaListeMemeSansSelection(): void
    {
        $html = Lieu::getCategoriesOptionsHtml([]);

        $this->assertSame(count(Lieu::CATEGORIES), substr_count($html, '<option '));
        $this->assertStringNotContainsString('selected', $html);
    }
}
