<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Lieu;

/**
 * Couvre la colonne `categories` (un SET, donc une liste séparée par des virgules) des
 * deux côtés : le <select> multiple du formulaire d'édition, et l'affichage en clair des
 * pages de lieu, que deux pages composaient chacune de leur côté à un espace près.
 */
final class LieuCategoriesTest extends Unit
{
    public function testLaColonneEstTraduiteEnLibellesSepares(): void
    {
        $this->assertSame('bistrot, cinéma', Lieu::categoriesEnClair('bistrot,cinema'));
    }

    /** MySQL rend le SET sans espaces, mais une valeur saisie à la main peut en porter. */
    public function testLesEspacesAutourDesCodesSontIgnores(): void
    {
        $this->assertSame('salle, théâtre', Lieu::categoriesEnClair('salle , theatre'));
    }

    public function testUneColonneVideOuNulleNeRendRien(): void
    {
        $this->assertSame('', Lieu::categoriesEnClair(''));
        $this->assertSame('', Lieu::categoriesEnClair(null));
    }

    /**
     * Une ligne écrite avant un renommage de catégorie doit rester affichable : le code
     * inconnu passe tel quel, là où un accès direct au tableau des libellés levait une
     * erreur d'index.
     */
    public function testUnCodeInconnuPasseTelQuel(): void
    {
        $this->assertSame('bistrot, cabaret', Lieu::categoriesEnClair('bistrot,cabaret'));
    }

    public function testLeSelectCocheLesSeulesCategoriesDuLieu(): void
    {
        $html = Lieu::getCategoriesOptionsHtml(['salle', 'cinema']);

        $this->assertStringContainsString('<option value="salle" selected="selected">salle</option>', $html);
        $this->assertStringContainsString('<option value="cinema" selected="selected">cinéma</option>', $html);
        $this->assertStringContainsString('<option value="bistrot">bistrot</option>', $html);
        $this->assertSame(2, substr_count($html, 'selected="selected"'));
    }

    public function testLeSelectProposeTouteLaListeMemeSansSelection(): void
    {
        $html = Lieu::getCategoriesOptionsHtml([]);

        $this->assertSame(count(Lieu::CATEGORIES), substr_count($html, '<option '));
        $this->assertStringNotContainsString('selected', $html);
    }

    /**
     * La constante et le SET de la colonne portent la même liste, à l'ordre près. Un code
     * ajouté d'un seul côté ne se voit pas : proposé par le formulaire mais absent du SET,
     * il s'enregistre vide ou lève une erreur SQL selon le `sql_mode` ; présent en base mais
     * absent de la constante, il s'affiche en code brut faute de libellé.
     */
    public function testLaConstanteEtLeSetDuSchemaPortentLaMemeListe(): void
    {
        // seule la liste doit coïncider : les deux ordres diffèrent volontairement, celui
        // de la constante servant l'affichage et gardant « autre » en dernier
        $constante = array_keys(Lieu::CATEGORIES);
        $schema = $this->categoriesDuSchema();
        sort($constante);
        sort($schema);

        $this->assertSame($constante, $schema, "Lieu::CATEGORIES et le SET de `lieu`.`categories` ne portent pas les mêmes valeurs");
    }

    /**
     * L'ordre du SET, lui, ne se touche pas : MariaDB en fait un masque de bits dont les
     * positions viennent de l'ordre de déclaration. Insérer une valeur au milieu, ou même
     * « ranger » la liste pour la faire correspondre à celle de la constante, décalerait
     * tous les bits suivants et réinterpréterait silencieusement chaque ligne de la table —
     * un cinéma deviendrait un théâtre sans qu'aucune erreur ne le signale. Les neuf
     * premières valeurs sont celles de la 3.13.0 ; les suivantes s'appendent.
     */
    public function testLesNeufPremieresValeursDuSetNeBougentPas(): void
    {
        $this->assertSame(
            ['bistrot', 'salle', 'restaurant', 'cinema', 'theatre', 'galerie', 'boutique', 'musee', 'autre'],
            array_slice($this->categoriesDuSchema(), 0, 9),
            "L'ordre du SET a bougé : les lignes déjà en base ne disent plus la même chose"
        );
    }

    /**
     * Les valeurs du SET `categories`, dans l'ordre où le schéma de référence les déclare.
     *
     * @return list<string>
     */
    private function categoriesDuSchema(): array
    {
        $schema = (string) file_get_contents(__DIR__ . '/../../resources/database/ladecadanse.sql');

        $this->assertSame(1, preg_match('/`categories` set\(([^)]+)\)/i', $schema, $declaration));

        preg_match_all("/'([^']+)'/", $declaration[1], $valeurs);

        return $valeurs[1];
    }
}
