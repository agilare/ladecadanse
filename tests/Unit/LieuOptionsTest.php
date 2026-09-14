<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Lieu;

/**
 * Couvre les <option> du select « Lieu », partagé par les formulaires d'ajout et d'édition
 * d'événement et par le formulaire d'édition groupée de l'administration.
 *
 * Les salles suivent leur lieu sous une valeur composée « idLieu_idSalle » : c'est elle que
 * les traitements de formulaire redécoupent, et c'est elle que le select réaffiche telle
 * quelle après une erreur de saisie.
 */
final class LieuOptionsTest extends Unit
{
    /** @var list<array{idLieu: int, nom: string, canton: string, salles: list<array{idSalle: int, nom: string}>}> */
    private const array LIEUX = [
        ['idLieu' => 12, 'nom' => 'L\'Usine', 'canton' => 'ge', 'salles' => [
            ['idSalle' => 3, 'nom' => 'Le Zoo'],
            ['idSalle' => 4, 'nom' => 'PTR'],
        ]],
        ['idLieu' => 20, 'nom' => 'Le Bourg', 'canton' => 'vd', 'salles' => []],
    ];

    public function testChaqueCantonOuvreUnOptgroupPorteSonLibelle(): void
    {
        $html = Lieu::renderOptions(self::LIEUX, '');

        $this->assertStringContainsString('<optgroup label="Genève">', $html);
        $this->assertStringContainsString('<optgroup label="Vaud">', $html);
        $this->assertSame(2, substr_count($html, '</optgroup>'));
    }

    public function testLesSallesSuiventLeurLieuSousUneValeurComposee(): void
    {
        $html = Lieu::renderOptions(self::LIEUX, '');

        $this->assertStringContainsString('value="12_3"', $html);
        $this->assertStringContainsString('value="12_4"', $html);
        $this->assertStringContainsString('&nbsp;– Le Zoo</option>', $html);
    }

    public function testUnLieuSansSalleNEmetQuUneOption(): void
    {
        $html = Lieu::renderOptions(self::LIEUX, '');

        $this->assertSame(1, substr_count($html, 'value="20"'));
        $this->assertStringNotContainsString('value="20_', $html);
    }

    public function testLeLieuChoisiEstSelectionne(): void
    {
        $html = Lieu::renderOptions(self::LIEUX, '12');

        $this->assertStringContainsString('<option value="12" selected="selected">', $html);
        $this->assertSame(1, substr_count($html, 'selected="selected"'));
    }

    /**
     * Chargement d'un événement existant : lieu et salle arrivent dans deux colonnes.
     * Seule la salle est cochée, sans quoi le select simple porterait deux options
     * sélectionnées et le navigateur ne garderait que la dernière.
     */
    public function testUneSalleChoisieEnDeuxColonnesSelectionneLaSalleSeule(): void
    {
        $html = Lieu::renderOptions(self::LIEUX, '12', '3');

        $this->assertStringContainsString('<option style="font-style:italic;color:#444;" value="12_3" selected="selected">', $html);
        $this->assertSame(1, substr_count($html, 'selected="selected"'));
    }

    /**
     * Réaffichage après une erreur de saisie : la valeur postée arrive composée.
     */
    public function testUneValeurComposeePosteeSelectionneLaSalle(): void
    {
        $html = Lieu::renderOptions(self::LIEUX, '12_4');

        $this->assertStringContainsString('value="12_4" selected="selected"', $html);
        $this->assertSame(1, substr_count($html, 'selected="selected"'));
    }

    /**
     * evenement.idSalle vaut 0 quand aucune salle n'est rattachée : c'est un vide, pas un id.
     */
    public function testIdSalleZeroNeSelectionneAucuneSalle(): void
    {
        $html = Lieu::renderOptions(self::LIEUX, '12', '0');

        $this->assertStringContainsString('<option value="12" selected="selected">', $html);
        $this->assertSame(1, substr_count($html, 'selected="selected"'));
    }

    public function testSansChoixLaPremiereOptionEstVideEtRienNEstSelectionne(): void
    {
        $html = Lieu::renderOptions(self::LIEUX, '');

        $this->assertStringStartsWith('<option value=""></option>', $html);
        $this->assertStringNotContainsString('selected', $html);
    }

    public function testLesNomsSontEchappes(): void
    {
        $html = Lieu::renderOptions([
            ['idLieu' => 1, 'nom' => 'Chez <b>Bob</b> & Co', 'canton' => 'ge', 'salles' => []],
        ], '');

        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringContainsString('&amp; Co', $html);
    }
}
