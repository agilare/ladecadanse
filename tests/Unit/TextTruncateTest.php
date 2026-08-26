<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Utils\Text;

/**
 * Couvre `Text::truncateCharsToHtml()`, la coupe d'étiquette employée par la colonne « par »
 * de admin/events.php.
 *
 * Elle se distingue de `shortenToHtml()` sur deux points, qui sont tout son intérêt : la
 * coupe est franche (on ne recule pas jusqu'au mot précédent) et les URL ne deviennent pas
 * des liens.
 */
final class TextTruncateTest extends Unit
{
    public function testUnTexteCourtPasseTelQuel(): void
    {
        $this->assertSame('admin', Text::truncateCharsToHtml('admin', 10));
    }

    public function testUnTexteALaLimiteExacteNEstPasCoupe(): void
    {
        $this->assertSame('0123456789', Text::truncateCharsToHtml('0123456789', 10));
    }

    public function testUnTexteTropLongEstCoupeEtSuivideTroisPoints(): void
    {
        $this->assertSame('pseudo@lad…', Text::truncateCharsToHtml('pseudo@ladecadanse.ch', 10));
    }

    /**
     * La différence avec shortenToHtml(), qui reculerait jusqu'à « Jean ».
     */
    public function testLaCoupeEstFrancheEtNeReculePasJusquAuMotPrecedent(): void
    {
        $this->assertSame('Jean Pierr…', Text::truncateCharsToHtml('Jean Pierre Dupont', 10));
    }

    /**
     * L'autre différence : un pseudo qui ressemble à une adresse ne doit pas devenir un lien,
     * il est déjà à l'intérieur du lien vers le profil.
     */
    public function testUneUrlNEstPasTransformeeEnLien(): void
    {
        $html = Text::truncateCharsToHtml('www.exemple.org', 40);

        $this->assertStringNotContainsString('<a', $html);
        $this->assertSame('www.exemple.org', $html);
    }

    public function testLeTexteEstEchappe(): void
    {
        $this->assertSame('a&amp;b', Text::truncateCharsToHtml('a&b', 10));
        $this->assertStringNotContainsString('<b>', Text::truncateCharsToHtml('<b>gras</b>', 40));
    }

    /**
     * La coupe compte des caractères, pas des octets : sur des accents, `substr` rendrait
     * une chaîne tronquée au milieu d'un caractère.
     */
    public function testLaCoupeCompteDesCaracteresEtNonDesOctets(): void
    {
        $this->assertSame('ééééé…', Text::truncateCharsToHtml('ééééééé', 5));
    }
}
