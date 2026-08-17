<?php

use Tests\Support\SiteTester;

use Codeception\Util\HttpCode;

/**
 * Contrat de balisage des descriptions de cartes d'événement.
 *
 * La hauteur du bloc est plafonnée par le `line-clamp` de global.css, pas par
 * PHP : seul le navigateur connaît les lignes réellement rendues, celles des
 * sauts de ligne comme celles du retour à la ligne automatique. Ce partage des
 * rôles repose sur deux invariants que le serveur doit garantir, et qu'une
 * régression a déjà cassés une fois (des blocs de 15 sauts de ligne rendus sur
 * une soixantaine de lignes) :
 *
 * 1. le paragraphe porte `js-description-clamp`, la prise du CSS et du JS ;
 * 2. le lien « Lire la suite » est un *frère* du paragraphe et jamais son
 *    enfant — dans le paragraphe, le clamp le rognerait avec le texte.
 *
 * PhpBrowser n'exécute pas de JS et ne calcule aucune mise en page : le clamp
 * lui-même ne s'observe qu'en navigateur. Seul le contrat serveur est testé ici.
 */
class AccueilDescriptionCest
{
    private const CLAMPED_PARAGRAPH_PATTERN = '#<p class="js-description-clamp">(.*?)</p>#s';

    public function homeDescriptionsKeepTheirClampMarkup(SiteTester $I)
    {
        $I->amOnPage('/');
        $I->seeResponseCodeIs(HttpCode::OK);

        $html = $I->grabPageSource();

        // journée sans événement : il n'y a pas de carte à inspecter
        if (!str_contains($html, 'class="evenement-short"'))
        {
            return;
        }

        $I->seeElement('.evenement-short .description p.js-description-clamp');

        $found = preg_match_all(self::CLAMPED_PARAGRAPH_PATTERN, $html, $matches);
        $I->assertGreaterThan(0, $found, 'aucun paragraphe de description trouvé alors que la page porte des cartes');

        foreach ($matches[1] as $paragraph)
        {
            $I->assertStringNotContainsString(
                'js-lire-la-suite',
                $paragraph,
                'le lien « Lire la suite » est repassé dans le <p> : le line-clamp va le rogner avec le texte'
            );
        }
    }
}
