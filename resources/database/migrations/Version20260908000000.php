<?php

declare(strict_types=1);

namespace Ladecadanse\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 3.13.0 — ancien script v3-13-0_lieu-categories.sql.
 *
 * Buvette, club, maison/espace de quartier, centre socioculturel, bibliothèque, ludothèque,
 * école/conservatoire : des lieux que l'agenda accueille depuis longtemps sans savoir les dire,
 * rangés jusqu'ici sous « salle » ou « autre ».
 *
 * **L'ordre compte.** Un SET MariaDB est un masque de bits dont les positions viennent de
 * l'ordre de déclaration : `bistrot` vaut 1, `salle` 2, `restaurant` 4, et ainsi de suite. Les
 * sept valeurs sont donc **appendées après `autre`**, sans toucher aux neuf premières. Glisser
 * ne serait-ce qu'une valeur au milieu décalerait tous les bits suivants et réinterpréterait
 * silencieusement chaque ligne de la table — un cinéma deviendrait un théâtre sans qu'aucune
 * erreur ne le signale.
 *
 * L'ordre de `Ladecadanse\Lieu::CATEGORIES` n'a pas cette contrainte et diffère volontairement :
 * il sert l'affichage du formulaire et garde « autre » en dernier. Ne pas « ranger » le SET pour
 * le faire correspondre au PHP.
 *
 * La table est en MyISAM : l'ALTER la reconstruit et pose un verrou d'écriture. Négligeable sur
 * quelques centaines de lignes, mais à passer hors des heures de saisie.
 *
 * Aucune valeur n'est retirée ni renommée : les lignes existantes gardent leur typage. Relever
 * avant de migrer, puis après, et comparer — les deux sorties doivent être rigoureusement
 * identiques ; une divergence dit que l'ordre du SET a bougé :
 *
 *     SELECT categories, COUNT(*) AS nb FROM lieu GROUP BY categories ORDER BY categories;
 */
final class Version20260908000000 extends AbstractMigration
{
    use MigrationDefaults;

    public function getDescription(): string
    {
        return 'Sept valeurs ajoutées à la fin du SET lieu.categories';
    }

    public function up(Schema $schema): void
    {
        if ($this->alreadyApplied(<<<'SQL'
            SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lieu' AND COLUMN_NAME = 'categories'
               AND COLUMN_TYPE LIKE '%ecole%'
            SQL)) {
            return;
        }

        $this->addSql(<<<'SQL'
            ALTER TABLE `lieu`
                MODIFY `categories` SET(
                    'bistrot','salle','restaurant','cinema','theatre','galerie','boutique','musee','autre',
                    'buvette','club','quartier','socioculturel','bibliotheque','ludotheque','ecole'
                ) COLLATE utf8mb4_unicode_ci NOT NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Retirer les sept valeurs effacerait ces catégories des lieux qui les portent.');
    }
}
