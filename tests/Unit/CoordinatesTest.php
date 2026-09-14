<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Utils\Coordinates;

/**
 * Couvre les trois règles des coordonnées d'un lieu, qui vivaient jusqu'ici en trois
 * endroits : la normalisation de la virgule décimale dans LieuEdition, les bornes dans
 * sa vérification, et le « 0 en base veut dire pas de coordonnées » dans une closure du
 * gabarit du formulaire.
 */
final class CoordinatesTest extends Unit
{
    public function testLaVirguleDecimaleDesClaviersEuropeensEstAcceptee(): void
    {
        $coordinates = Coordinates::fromInput('46,2043907', ' 6,1431577 ');

        $this->assertSame('46.2043907', $coordinates->latInput());
        $this->assertSame('6.1431577', $coordinates->lngInput());
        $this->assertSame([], $coordinates->errors());
    }

    /**
     * La colonne a longtemps été NOT NULL DEFAULT 0 : un lieu sans coordonnées y porte
     * 0.0000000, qui ne doit pas remplir le formulaire d'un point au large du golfe de
     * Guinée.
     */
    public function testUnZeroEnBaseSeLitCommeUneAbsenceDeCoordonnees(): void
    {
        $coordinates = Coordinates::fromDatabase('0.0000000', '0.0000000');

        $this->assertTrue($coordinates->isEmpty());
        $this->assertSame('', $coordinates->latInput());
        $this->assertSame('', $coordinates->lngInput());
    }

    public function testUneColonneNulleSeLitAussiCommeUneAbsence(): void
    {
        $this->assertTrue(Coordinates::fromDatabase(null, null)->isEmpty());
    }

    /** DECIMAL(10,7) rend « 46.2043900 » ; le champ montre la forme la plus courte. */
    public function testLesZerosDeQueueDeLaColonneNeRemontentPasDansLeChamp(): void
    {
        $coordinates = Coordinates::fromDatabase('46.2043900', '6.1431577');

        $this->assertSame('46.20439', $coordinates->latInput());
        $this->assertSame('6.1431577', $coordinates->lngInput());
    }

    /**
     * Le plan n'est affiché que si les deux sont connues : une seule ne sert à rien, et
     * l'erreur désigne celle qui manque.
     */
    public function testUneCoordonneeSeuleEstRefusee(): void
    {
        $this->assertSame(['lng'], array_keys(Coordinates::fromInput('46.2043907', '')->errors()));
        $this->assertSame(['lat'], array_keys(Coordinates::fromInput('', '6.1431577')->errors()));
    }

    public function testLesDeuxChampsVidesSontAcceptes(): void
    {
        $coordinates = Coordinates::fromInput('', '');

        $this->assertTrue($coordinates->isEmpty());
        $this->assertSame([], $coordinates->errors());
        $this->assertNull($coordinates->latForDatabase());
        $this->assertNull($coordinates->lngForDatabase());
    }

    public function testLesBornesDuGlobeSontVerifiees(): void
    {
        $this->assertArrayHasKey('lat', Coordinates::fromInput('91', '6.14')->errors());
        $this->assertArrayHasKey('lng', Coordinates::fromInput('46.20', '181')->errors());
        $this->assertSame([], Coordinates::fromInput('-90', '180')->errors());
    }

    public function testUneSaisieNonNumeriqueEstRefuseeEtReaffichee(): void
    {
        $coordinates = Coordinates::fromInput('quarante-six', '6.1431577');

        $this->assertArrayHasKey('lat', $coordinates->errors());
        // ré-affichée telle quelle : l'auteur doit retrouver ce qu'il a tapé
        $this->assertSame('quarante-six', $coordinates->latInput());
        // et surtout pas écrite en base comme un 0 qui se ferait passer pour un point réel
        $this->assertNull($coordinates->latForDatabase());
    }

    public function testLesValeursEcritesEnBaseSontDesNombres(): void
    {
        $coordinates = Coordinates::fromInput('46,2043907', '6.1431577');

        $this->assertSame(46.2043907, $coordinates->latForDatabase());
        $this->assertSame(6.1431577, $coordinates->lngForDatabase());
    }
}
