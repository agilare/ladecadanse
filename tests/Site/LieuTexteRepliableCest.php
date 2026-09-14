<?php

use Tests\Support\SiteTester;
use Tests\Support\TestEnv;

use Codeception\Util\HttpCode;

/**
 * Contrat de balisage des textes repliables des fiches lieu et organisateur.
 *
 * Le repli est plafonné par le `max-height` de global.css, et posé par le serveur dès le HTML
 * servi : c'est ce qui distingue ce mécanisme de la librairie read-smore qu'il remplace, laquelle
 * raccourcissait le texte *après* le rendu, sous les yeux du visiteur.
 *
 * Ce partage des rôles repose sur trois invariants que seul le serveur peut garantir :
 *
 * 1. le texte est servi en entier, jamais tronqué en PHP — le contenu est du HTML riche produit
 *    par TinyMCE, le couper laisserait des balises ouvertes, et le référencement y perdrait ;
 * 2. la bascule est un *frère* du bloc plafonné et jamais son enfant — à l'intérieur, elle serait
 *    rognée avec le texte, tout comme le lien « Lire la suite » des cartes d'événement ;
 * 3. son `aria-controls` désigne bien l'identifiant du bloc : c'est par là que global.js apparie
 *    les deux, et une page peut porter plusieurs descriptions et une présentation.
 *
 * PhpBrowser n'exécute pas de JS et ne calcule aucune mise en page : ni le repli, ni le fondu, ni
 * la bascule elle-même ne s'observent ici. Seul le contrat serveur est testé.
 *
 * Lecture seule : uniquement des GET, aucune écriture, aucune connexion (les fiches sont publiques).
 */
class LieuTexteRepliableCest
{
    /** doit rester cohérent avec $texteRepliableSeuil de _texte_repliable.inc.php */
    private const SEUIL_CARACTERES = 550;

    private string $urlLieu = '';

    public function _before(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_TEST_LIEU_ID_WITH_LONG_TEXT');

        $this->urlLieu = '/lieu/lieu.php?idL=' . TestEnv::getInt('LADECADANSE_TEST_LIEU_ID_WITH_LONG_TEXT');
    }

    public function longTextIsServedCollapsedButComplete(SiteTester $I)
    {
        $I->amOnPage($this->urlLieu);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeElement('#descriptions .texte-repliable .texte-repliable__contenu');
        $I->seeElement('#descriptions .texte-repliable__bascule');

        $contenu = $I->grabTextFrom('#descriptions .texte-repliable__contenu');

        // le seuil décide de *poser* la bascule, il ne borne pas ce qui est envoyé : un texte
        // qui repasse sous cette longueur trahit une troncature côté serveur
        $I->assertGreaterThan(
            self::SEUIL_CARACTERES,
            mb_strlen(trim($contenu)),
            'le texte servi est plus court que le seuil qui a pourtant fait poser la bascule : il a été tronqué'
        );
    }

    public function toggleStaysOutsideTheClippedBlock(SiteTester $I)
    {
        $I->amOnPage($this->urlLieu);

        $bloc = $I->grabTextFrom('#descriptions .texte-repliable');

        $I->assertStringNotContainsString(
            'Lire la suite',
            $bloc,
            'la bascule est passée dans le bloc plafonné : le max-height va la rogner avec le texte'
        );
    }

    public function toggleAndBlockAreWiredTogether(SiteTester $I)
    {
        $I->amOnPage($this->urlLieu);

        $blocId = $I->grabAttributeFrom('#descriptions .texte-repliable', 'id');
        $cible = $I->grabAttributeFrom('#descriptions .texte-repliable__bascule', 'aria-controls');

        $I->assertNotEmpty($blocId, 'le bloc repliable n\'a pas d\'identifiant : global.js ne pourra pas l\'apparier');
        $I->assertSame($cible, $blocId, 'aria-controls ne désigne pas le bloc : la bascule restera sans effet');
    }

    /**
     * Sans cette classe, aucun repli ne s'applique : le texte s'affiche entier plutôt que tronqué
     * sans moyen de le déplier. Le script est inline et dans le <head> pour poser la classe avant
     * la première image peinte — un module ES arriverait trop tard et le texte sauterait.
     */
    public function collapsingIsGatedOnTheJsMarker(SiteTester $I)
    {
        $I->amOnPage($this->urlLieu);

        $I->seeInSource("document.documentElement.classList.add('js');");
    }
}
