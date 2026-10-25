<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\HtmlShrink;

/**
 * Bouton « i » et sa bulle d'explication (HtmlShrink::helpPopover()).
 */
final class HtmlShrinkHelpPopoverTest extends Unit
{
    public function testButtonTargetsItsOwnPopover(): void
    {
        $html = HtmlShrink::helpPopover('hits', 'Pages vues', 'Requêtes <em>servies</em>.');

        $this->assertStringContainsString('<button type="button" class="help-trigger" popovertarget="help-hits"', $html);
        $this->assertStringContainsString('<div id="help-hits" class="help-popover" popover>Requêtes <em>servies</em>.</div>', $html);
    }

    /**
     * Sans JavaScript ni API Popover, le title du bouton reste : le même texte, sans balises,
     * échappé pour l'attribut.
     */
    public function testTitleCarriesTheTextWithoutTags(): void
    {
        $html = HtmlShrink::helpPopover('hits', 'Pages vues', 'Requêtes <em>servies</em> & comptées.');

        $this->assertStringContainsString('title="Requêtes servies &amp; comptées."', $html);
        $this->assertStringContainsString('aria-label="Explication : Pages vues"', $html);
    }
}
