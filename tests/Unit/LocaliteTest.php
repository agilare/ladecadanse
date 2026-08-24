<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Localite;

/**
 * Couvre les <option> du select « Localité/quartier », partagé par les formulaires d'ajout et
 * d'édition d'événement, le formulaire de masse de l'administration et celui des lieux.
 *
 * Depuis la 3.12.0, la France n'est plus une entrée greffée en dur sur ce select : c'est un
 * canton ('rf') de la table `localite`, comme « Autre » ('hs').
 */
final class LocaliteTest extends Unit
{
    /** @var list<array{id: int, localite: string, canton: string}> */
    private const array LOCALITES = [
        ['id' => 44, 'localite' => 'Genève', 'canton' => 'ge'],
        ['id' => 529, 'localite' => 'Nyon', 'canton' => 'vd'],
        ['id' => 1065, 'localite' => 'Autre', 'canton' => 'rf'],
        ['id' => 1, 'localite' => 'Autre', 'canton' => 'hs'],
    ];

    private const array QUARTIERS = ['Pâquis', 'Servette'];

    public function testChaqueCantonOuvreUnOptgroupPorteSonLibelle(): void
    {
        $html = Localite::renderOptions(self::LOCALITES, self::QUARTIERS, '');

        $this->assertStringContainsString('<optgroup label="Genève">', $html);
        $this->assertStringContainsString('<optgroup label="Vaud">', $html);
        $this->assertStringContainsString('<optgroup label="France">', $html);
        $this->assertStringContainsString('<optgroup label="Autre">', $html);
        $this->assertSame(4, substr_count($html, '</optgroup>'));
    }

    /**
     * La localité française « Autre » est une option ordinaire, avec l'id de sa ligne :
     * c'est ce qui permet d'ajouter des localités françaises au fur et à mesure.
     */
    public function testLaFranceEstUneLocaliteCommeUneAutre(): void
    {
        $html = Localite::renderOptions(self::LOCALITES, self::QUARTIERS, '');

        $this->assertStringContainsString('<optgroup label="France"><option value="1065">Autre</option>', $html);
        $this->assertStringNotContainsString('value="rf"', $html);
        $this->assertStringNotContainsString('value="hs"', $html);
    }

    public function testGeneveEstSuivieDeSesQuartiers(): void
    {
        $html = Localite::renderOptions(self::LOCALITES, self::QUARTIERS, '');

        $this->assertStringContainsString('<option value="44">Genève</option>', $html);
        $this->assertStringContainsString('<option value="44_Pâquis">Genève - Pâquis</option>', $html);
        $this->assertStringContainsString('<option value="44_Servette">Genève - Servette</option>', $html);
    }

    public function testLaLocaliteEnregistreeEstPreselectionnee(): void
    {
        $html = Localite::renderOptions(self::LOCALITES, self::QUARTIERS, '1065');

        $this->assertStringContainsString('<option value="1065" selected="selected">Autre</option>', $html);
        $this->assertSame(1, substr_count($html, 'selected="selected"'));
    }

    /**
     * Chargement d'une fiche existante : la localité et le quartier arrivent dans deux
     * colonnes distinctes.
     */
    public function testLeQuartierEnregistreEstPreselectionne(): void
    {
        $html = Localite::renderOptions(self::LOCALITES, self::QUARTIERS, '44', 'Servette');

        $this->assertStringContainsString('<option value="44_Servette" selected="selected">', $html);
        $this->assertStringNotContainsString('<option value="44" selected="selected">', $html);
        $this->assertSame(1, substr_count($html, 'selected="selected"'));
    }

    /**
     * Réaffichage après une erreur de saisie : la valeur postée porte le quartier avec elle.
     */
    public function testLaValeurPosteeComposeeRetrouveSonQuartier(): void
    {
        $html = Localite::renderOptions(self::LOCALITES, self::QUARTIERS, '44_Pâquis');

        $this->assertStringContainsString('<option value="44_Pâquis" selected="selected">', $html);
        $this->assertSame(1, substr_count($html, 'selected="selected"'));
    }

    public function testGeneveSansQuartierNeSelectionneAucunQuartier(): void
    {
        $html = Localite::renderOptions(self::LOCALITES, self::QUARTIERS, '44');

        $this->assertStringContainsString('<option value="44" selected="selected">Genève</option>', $html);
        $this->assertSame(1, substr_count($html, 'selected="selected"'));
    }

    public function testAucuneLocaliteChoisieNeSelectionneRien(): void
    {
        $html = Localite::renderOptions(self::LOCALITES, self::QUARTIERS, '');

        $this->assertStringStartsWith('<option value=""></option>', $html);
        $this->assertStringNotContainsString('selected="selected"', $html);
    }

    /**
     * Le tri des cantons suit self::CANTONS, sans quoi Fribourg passerait avant Genève et les
     * <optgroup> d'un select de lieux s'ouvriraient deux fois.
     */
    public function testLOrdreDesCantonsSuitLeurDeclaration(): void
    {
        $this->assertSame(
            "CASE canton WHEN 'ge' THEN 0 WHEN 'vd' THEN 1 WHEN 'fr' THEN 2 WHEN 'rf' THEN 3 WHEN 'hs' THEN 4 ELSE 5 END",
            Localite::sqlOrdreCantons()
        );
    }
}
