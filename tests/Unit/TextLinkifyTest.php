<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Utils\Text;

/**
 * Couvre `Text::lnAndUrlToHtml()`, qui rend le champ description des fiches.
 *
 * La méthode reçoit désormais du texte BRUT et l'échappe elle-même : c'est ce
 * changement de contrat qui corrige les liens cassés par une entité HTML
 * avalée dans le href.
 */
final class TextLinkifyTest extends Unit
{
    public function testLesSautsDeLigneDeviennentDesBr(): void
    {
        $this->assertSame("un<br />\ndeux", Text::lnAndUrlToHtml("un\ndeux"));
    }

    public function testLeTexteEstEchappe(): void
    {
        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', Text::lnAndUrlToHtml('<script>alert(1)</script>'));
    }

    public function testUneUrlNueDevientUnLien(): void
    {
        $html = Text::lnAndUrlToHtml('Prélocs sur https://www.petzi.ch/fr/events/57328-undertown-no-drama');

        $this->assertStringContainsString('href="https://www.petzi.ch/fr/events/57328-undertown-no-drama"', $html);
        $this->assertStringContainsString('Billetterie · undertown-no-drama', $html);
    }

    /**
     * Le défaut historique : le texte arrivait déjà échappé, et le guillemet
     * devenu &quot; était happé par l'URL — le lien pointait sur
     * .../HumanitySoundSystem&quot; et ne menait nulle part.
     */
    public function testUneUrlEntreGuillemetsGardeUnHrefPropre(): void
    {
        $html = Text::lnAndUrlToHtml('voir "https://www.facebook.com/HumanitySoundSystem"');

        $this->assertStringContainsString('href="https://www.facebook.com/HumanitySoundSystem"', $html);
        $this->assertStringNotContainsString('SoundSystem&amp;quot;', $html);
    }

    public function testLaPonctuationDeFinDePhraseResteDansLeTexte(): void
    {
        $html = Text::lnAndUrlToHtml('Programme sur www.labelsuisse.ch.');

        $this->assertStringContainsString('href="https://www.labelsuisse.ch"', $html);
        $this->assertStringEndsWith('</a>.', $html);
    }

    public function testUneAdresseEmailDevientUnLienMailto(): void
    {
        $this->assertStringContainsString(
            'href="mailto:info@ladecadanse.ch"',
            Text::lnAndUrlToHtml('Contact : info@ladecadanse.ch')
        );
    }

    public function testUnTexteSansLienNeProduitAucuneBalise(): void
    {
        $this->assertSame('No limit, prix libre.', Text::lnAndUrlToHtml('No limit, prix libre.'));
    }
}
