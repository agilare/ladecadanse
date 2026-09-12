<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use InvalidArgumentException;
use Ladecadanse\EventCategory;

/**
 * Les catégories d'événement, et le repli de celles qui sont en préversion.
 *
 * Les méthodes éprouvées ici prennent leur audience en paramètre plutôt que de consulter
 * le drapeau : les deux branches se testent donc sans définir de constante ni poser de
 * session, là où FeatureFlagTest doit déclarer une constante par état.
 */
final class EventCategoryTest extends Unit
{
    public function testLaListePubliqueEcarteLesCategoriesEnPreversion(): void
    {
        $this->assertSame(
            ['fête', 'cinéma', 'théâtre', 'expos', 'divers'],
            array_keys(EventCategory::selectable(false))
        );
    }

    public function testLaListeEnPreversionLesInsereALeurPlace(): void
    {
        $this->assertSame(
            ['fête', 'concerts', 'cinéma', 'théâtre', 'expos', 'cours', 'divers'],
            array_keys(EventCategory::selectable(true))
        );
    }

    public function testHorsPreversionChaqueCategorieSeRangeSousSonRepli(): void
    {
        $this->assertSame('fête', EventCategory::visible('concerts', false));
        $this->assertSame('divers', EventCategory::visible('cours', false));
    }

    public function testEnPreversionLaCategorieEstRenduTelleQuelle(): void
    {
        $this->assertSame('concerts', EventCategory::visible('concerts', true));
        $this->assertSame('cours', EventCategory::visible('cours', true));
    }

    /**
     * Les catégories sont stockées en varchar : d'anciens événements en portent qui ne
     * sont plus dans la liste. Le repli ne les connaît pas et ne doit pas les inventer —
     * c'est categoryLabel() qui décide ensuite de leur libellé.
     */
    public function testUneCategorieInconnuePasseTelleQuelle(): void
    {
        $this->assertSame('soirée', EventCategory::visible('soirée', false));
        $this->assertSame('', EventCategory::visible(null, false));
        $this->assertSame('', EventCategory::visible('', false));
    }

    /** Les appelants ne savent pas toujours si la valeur vient de la base ou d'un repli. */
    public function testLeRepliEstIdempotent(): void
    {
        $uneFois = EventCategory::visible('concerts', false);

        $this->assertSame($uneFois, EventCategory::visible($uneFois, false));
    }

    public function testAucuneCategorieNeSeReplieEnChaine(): void
    {
        foreach (EventCategory::PREVIEW_FALLBACKS as $preview => $fallback)
        {
            $this->assertArrayHasKey($fallback, EventCategory::ALL, "Le repli de « {$preview} » doit être une catégorie");
            $this->assertArrayNotHasKey($fallback, EventCategory::PREVIEW_FALLBACKS, "Le repli de « {$preview} » ne doit pas se replier à son tour");
        }
    }

    /**
     * La propriété dont dépend tout l'ordonnancement de l'agenda : hors préversion,
     * « concerts » partage le rang de « fête » et « cours » celui de « divers ». Sans ce
     * partage, MySQL rendrait toutes les fêtes puis tous les concerts, et le groupe
     * fusionné par PDO::FETCH_GROUP repartirait en arrière au milieu.
     */
    public function testHorsPreversionUneCategorieRepliePartageLeRangDeSonRepli(): void
    {
        $this->assertSame(
            "CASE e.genre WHEN 'fête' THEN 1 WHEN 'concerts' THEN 1 WHEN 'cinéma' THEN 2"
                . " WHEN 'théâtre' THEN 3 WHEN 'expos' THEN 4 WHEN 'cours' THEN 5 WHEN 'divers' THEN 5 END",
            EventCategory::sqlOrderByCategory('e.genre', false)
        );
    }

    public function testEnPreversionChaqueCategorieASonPropreRang(): void
    {
        $this->assertSame(
            "CASE e.genre WHEN 'fête' THEN 1 WHEN 'concerts' THEN 2 WHEN 'cinéma' THEN 3"
                . " WHEN 'théâtre' THEN 4 WHEN 'expos' THEN 5 WHEN 'cours' THEN 6 WHEN 'divers' THEN 7 END",
            EventCategory::sqlOrderByCategory('e.genre', true)
        );
    }

    /** Aucun CASE en préversion : le plan d'exécution reste celui d'aujourd'hui. */
    public function testEnPreversionLaColonneEstLueSansDetour(): void
    {
        $this->assertSame('e.genre', EventCategory::sqlVisibleCategory('e.genre', true));
    }

    public function testHorsPreversionLaColonneEstReplieeEnSql(): void
    {
        $this->assertSame(
            "CASE e.genre WHEN 'concerts' THEN 'fête' WHEN 'cours' THEN 'divers' ELSE e.genre END",
            EventCategory::sqlVisibleCategory('e.genre', false)
        );
    }

    /**
     * Les deux fragments sont concaténés et non liés : aucun appelant ne leur donne
     * d'entrée utilisateur, mais rien dans leur signature ne l'imposait.
     */
    public function testUnNomDeColonneInattenduEstRefuse(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EventCategory::sqlOrderByCategory("e.genre' UNION SELECT 1 -- ", true);
    }

    public function testUnNomDeColonneNonQualifieEstAccepte(): void
    {
        $this->assertStringStartsWith('CASE genre WHEN', EventCategory::sqlOrderByCategory('genre', true));
    }

    /**
     * Un événement classé « concerts » que son auteur non administrateur vient modifier :
     * le bouton « fêtes » garde son libellé et son rang, mais poste la valeur stockée.
     */
    public function testLeFormulaireConserveLaCategorieStockeeALaPlaceDeSonRepli(): void
    {
        $selectable = EventCategory::selectableForEdit('concerts', false);

        $this->assertSame(['concerts', 'cinéma', 'théâtre', 'expos', 'divers'], array_keys($selectable));
        $this->assertSame('fêtes', $selectable['concerts']);
    }

    public function testLaSubstitutionNaPasLieuEnPreversion(): void
    {
        $this->assertSame(
            EventCategory::selectable(true),
            EventCategory::selectableForEdit('concerts', true)
        );
    }

    public function testUneCategorieDejaPubliqueOuInconnueNeSubstitueRien(): void
    {
        $publique = EventCategory::selectable(false);

        $this->assertSame($publique, EventCategory::selectableForEdit('cinéma', false));
        $this->assertSame($publique, EventCategory::selectableForEdit('soirée', false));
        $this->assertSame($publique, EventCategory::selectableForEdit(null, false));
    }

    /**
     * Les cinq catégories historiques gardent l'ancre qu'elles avaient, dérivée du
     * libellé par stripAccents() seul ; seule la nouvelle en avait besoin, son libellé
     * portant des barres obliques.
     */
    public function testLesAncresHistoriquesNeBougentPas(): void
    {
        $this->assertSame('fetes', EventCategory::anchor('fêtes'));
        $this->assertSame('cine', EventCategory::anchor('ciné'));
        $this->assertSame('theatre', EventCategory::anchor('théâtre'));
        $this->assertSame('expos', EventCategory::anchor('expos'));
        $this->assertSame('divers', EventCategory::anchor('divers'));
    }

    public function testUneAncreNePorteNiAccentNiBarreOblique(): void
    {
        $this->assertSame('cours-ateliers-stages', EventCategory::anchor('cours/ateliers/stages'));
    }
}
