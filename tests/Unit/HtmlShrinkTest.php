<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\HtmlShrink;

/**
 * Couvre l'adresse compacte, affichée sur la fiche d'un événement et d'un lieu, dans les
 * listes, dans les titres de page et dans l'export ics — huit appels pour une seule méthode.
 *
 * Couvre aussi le menu des régions de la liste des lieux, et la cellule « Note » des listes de
 * lieux et d'organisateurs.
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

    /**
     * Une fiche sans note garde sa cellule, vide : sans elle, la ligne aurait une colonne de
     * moins que l'en-tête et les compteurs mensuels se décaleraient.
     *
     * @dataProvider fournirNotesVides
     */
    public function testUneNoteAbsenteLaisseLaCelluleVide(?string $note): void
    {
        $this->assertSame('<td class="admin-note"></td>', HtmlShrink::getAdminNoteCell($note));
    }

    /** @return iterable<string, array{?string}> */
    public static function fournirNotesVides(): iterable
    {
        yield 'NULL en base' => [null];
        yield 'chaîne vide' => [''];
        yield 'blancs seuls' => ["  \r\n "];
    }

    /**
     * La note est du texte brut saisi dans un formulaire : ce qui ressemble à du HTML s'affiche
     * tel quel, et les sauts de ligne sont rendus — dans le texte du desktop comme dans
     * l'infobulle du mobile.
     */
    public function testUneNoteEstEchappeeEtGardeSesSautsDeLigne(): void
    {
        $html = HtmlShrink::getAdminNoteCell("Relancé le 12.03\r\n<script>alert(1)</script> & co");
        $echappee = "Relancé le 12.03<br />\r\n&lt;script&gt;alert(1)&lt;/script&gt; &amp; co";

        $this->assertStringContainsString('<div class="admin-note-texte">' . $echappee . '</div>', $html);
        $this->assertStringContainsString('<span class="tooltiptext">' . $echappee . '</span>', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    /**
     * La longueur se compte en caractères et non en octets : deux cents lettres accentuées,
     * quatre cents octets en UTF-8, tiennent encore dans l'extrait.
     */
    public function testUneNoteDeLaLongueurDeLExtraitNeReplieRien(): void
    {
        $note = str_repeat('é', HtmlShrink::ADMIN_NOTE_EXCERPT_LENGTH);

        $html = HtmlShrink::getAdminNoteCell($note);

        $this->assertStringContainsString('<div class="admin-note-texte">' . $note . '</div>', $html);
        $this->assertStringNotContainsString('<details>', $html);
    }

    /**
     * Au-delà, la suite se replie. La coupure tombe au milieu de « coupure » : le mot passe
     * entier dans la suite plutôt que d'être tranché entre les deux. L'infobulle du mobile,
     * elle, porte la note entière.
     */
    public function testUneNoteLongueReplieSaSuiteSansCouperDeMot(): void
    {
        $debut = str_repeat('a', HtmlShrink::ADMIN_NOTE_EXCERPT_LENGTH - 5);

        $html = HtmlShrink::getAdminNoteCell($debut . ' coupure en fin');

        $this->assertStringContainsString(
            '<div class="admin-note-texte">' . $debut . '<details><summary>Suite</summary>coupure en fin</details></div>',
            $html
        );
        $this->assertStringContainsString('<span class="tooltiptext">' . $debut . ' coupure en fin</span>', $html);
    }

    /** Un mot plus long que l'extrait ne laisse aucun blanc où reculer : il est coupé net. */
    public function testUnMotPlusLongQueLExtraitEstCoupeNet(): void
    {
        $html = HtmlShrink::getAdminNoteCell(str_repeat('x', HtmlShrink::ADMIN_NOTE_EXCERPT_LENGTH + 10));

        $this->assertStringContainsString(
            '<div class="admin-note-texte">' . str_repeat('x', HtmlShrink::ADMIN_NOTE_EXCERPT_LENGTH)
            . '<details><summary>Suite</summary>' . str_repeat('x', 10) . '</details></div>',
            $html
        );
    }

    /**
     * Sur mobile, l'icône et son infobulle remplacent le texte. Sans tabindex, l'infobulle ne
     * s'ouvrirait qu'au survol : ni au toucher, ni au clavier.
     */
    public function testLInfobulleDuMobileSOuvreAuFocus(): void
    {
        $this->assertStringContainsString(
            '<span class="tooltip tooltip-texte" tabindex="0"><i class="fa fa-info-circle" aria-hidden="true"></i>',
            HtmlShrink::getAdminNoteCell('Contact : la programmatrice')
        );
    }
}
