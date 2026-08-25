<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\HtmlShrink;

/**
 * Couvre l'adresse compacte, affichée sur la fiche d'un événement et d'un lieu, dans les
 * listes, dans les titres de page et dans l'export ics — huit appels pour une seule méthode.
 */
final class HtmlShrinkTest extends Unit
{
    /**
     * Les deux localités fourre-tout ne nomment aucun lieu : dans une adresse elles
     * n'apprendraient rien de plus que la région, affichée juste après.
     */
    public function testLAdresseTaitLaLocaliteFourreToutDeFrance(): void
    {
        $this->assertSame(
            '12 rue Test - France',
            HtmlShrink::adresseCompacteSelonContexte('rf', 'Ailleurs en France', '', '12 rue Test')
        );
    }

    public function testLAdresseTaitLaLocaliteFourreToutHorsZone(): void
    {
        $this->assertSame(
            '12 rue Test',
            HtmlShrink::adresseCompacteSelonContexte('hs', 'Hors Genève, Vaud et France', '', '12 rue Test')
        );
    }

    public function testUneVraieCommuneFrancaiseResteAffichee(): void
    {
        $this->assertSame(
            '12 rue Test - Annemasse - France',
            HtmlShrink::adresseCompacteSelonContexte('rf', 'Annemasse', '', '12 rue Test')
        );
    }

    /**
     * Genève ne se répète pas : ni entre le quartier et la localité, ni entre la localité et
     * la région.
     */
    public function testGeneveNestPasRepetee(): void
    {
        $this->assertSame(
            '12 rue Test (Pâquis) - Genève',
            HtmlShrink::adresseCompacteSelonContexte('ge', 'Genève', 'Pâquis', '12 rue Test')
        );
    }
}
