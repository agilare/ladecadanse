<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\HtmlShrink;

/**
 * Couvre l'adresse compacte, affichée sur la fiche d'un événement et d'un lieu, dans les
 * listes, dans les titres de page et dans l'export ics — huit appels pour une seule méthode.
 *
 * Couvre aussi le menu des régions de la liste des lieux.
 */
final class HtmlShrinkTest extends Unit
{
    protected function _after(): void
    {
        unset($_SESSION['region']);
    }

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

    /**
     * Le menu laissait son tampon de sortie ouvert. Son appelant, lieu/lieux.php, n'émettait pas
     * la chaîne retournée : le menu ne s'affichait que parce que PHP vide les tampons orphelins
     * en fin de script, et l'émettre l'aurait affiché deux fois.
     */
    public function testGetMenuRegionsRetourneLeMenuEtFermeSonTampon(): void
    {
        $_SESSION['region'] = 'ge';

        $level = ob_get_level();
        $html = HtmlShrink::getMenuRegions(['ge' => 'Genève', 'vd' => 'Vaud'], []);

        $this->assertStringStartsWith('<ul class="menu_region">', ltrim($html));
        $this->assertSame($level, ob_get_level(), 'tampon de sortie laissé ouvert');
    }
}
