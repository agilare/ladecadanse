<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Organisateur;

/**
 * Couvre les <option> du select multiple « Organisateur(s) », partagé par les formulaires
 * d'événement.
 *
 * `data-nom` et `data-complement` ne sont pas décoratifs : le select2 « with complement »
 * les lit pour afficher l'URL de l'organisateur sous son nom.
 */
final class OrganisateurOptionsTest extends Unit
{
    /** @var list<array{idOrganisateur: int, nom: string, URL: string|null}> */
    private const array ORGANISATEURS = [
        ['idOrganisateur' => 7, 'nom' => 'Cave12', 'URL' => 'https://cave12.org'],
        ['idOrganisateur' => 9, 'nom' => 'La Gravière', 'URL' => null],
    ];

    public function testChaqueOrganisateurPorteSonNomEtSonUrl(): void
    {
        $html = Organisateur::renderOptions(self::ORGANISATEURS);

        $this->assertStringContainsString('data-nom="Cave12" data-complement="https://cave12.org" value="7"', $html);
        $this->assertStringContainsString('>Cave12</option>', $html);
    }

    public function testUneUrlAbsenteDonneUnComplementVide(): void
    {
        $html = Organisateur::renderOptions(self::ORGANISATEURS);

        $this->assertStringContainsString('data-nom="La Gravière" data-complement="" value="9"', $html);
    }

    public function testLesOrganisateursChoisisSontSelectionnes(): void
    {
        $html = Organisateur::renderOptions(self::ORGANISATEURS, [9]);

        $this->assertStringContainsString('value="9" selected="selected"', $html);
        $this->assertSame(1, substr_count($html, 'selected="selected"'));
    }

    /**
     * Les ids viennent tantôt de $_POST (chaînes), tantôt d'une requête (entiers) : la
     * comparaison ne doit pas dépendre du type.
     */
    public function testUnIdPosteEnChaineSelectionneAussi(): void
    {
        $html = Organisateur::renderOptions(self::ORGANISATEURS, ['7']);

        $this->assertStringContainsString('value="7" selected="selected"', $html);
    }

    public function testSansChoixAucuneOptionNEstSelectionnee(): void
    {
        $html = Organisateur::renderOptions(self::ORGANISATEURS);

        $this->assertStringNotContainsString('selected', $html);
    }

    /**
     * L'option vide de l'ancien select de l'administration n'a plus lieu d'être : sur un
     * select multiple, elle ne servait qu'à faire poster un organisateur d'id 0.
     */
    public function testAucuneOptionVideNEstEmise(): void
    {
        $html = Organisateur::renderOptions(self::ORGANISATEURS);

        $this->assertStringNotContainsString('value="0"', $html);
        $this->assertStringNotContainsString('value=""', $html);
    }

    public function testLesNomsSontEchappes(): void
    {
        $html = Organisateur::renderOptions([
            ['idOrganisateur' => 1, 'nom' => 'Rock & <i>Roll</i>', 'URL' => 'https://x.ch/?a=1&b=2'],
        ]);

        $this->assertStringNotContainsString('<i>', $html);
        $this->assertStringContainsString('Rock &amp; ', $html);
        $this->assertStringContainsString('a=1&amp;b=2', $html);
    }
}
