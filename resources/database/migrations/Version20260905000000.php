<?php

declare(strict_types=1);

namespace Ladecadanse\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 3.13.0 — ancien script v3-13-0_lieu-colonnes.sql, issue #117.
 *
 * Renommages, colonnes retirées et valeurs par défaut, dans le même geste que la réécriture de
 * `lieu/edit.php` et de `LieuEdition`. À passer avec la mise en ligne du code : les deux
 * renommages sont lus par les pages d'événement (`l.preposition_nom`) et par les listes de
 * lieux (`FIND_IN_SET(..., categories)`).
 *
 * L'ordre des clauses compte : `categories` et `preposition_nom` doivent exister avant que
 * `logo` puisse être placé après elles, d'où deux instructions plutôt qu'une.
 */
final class Version20260905000000 extends AbstractMigration
{
    use MigrationDefaults;

    public function getDescription(): string
    {
        return 'lieu : determinant → preposition_nom, categorie → categories, adresse en VARCHAR(255), colonnes facultatives à NULL, photo2 et actif supprimées';
    }

    public function up(Schema $schema): void
    {
        if ($this->alreadyApplied(<<<'SQL'
            SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lieu' AND COLUMN_NAME = 'preposition_nom'
            SQL)) {
            return;
        }

        // - `determinant` disait mal ce que la colonne porte : « au », « chez », « à l' » sont des
        //   prépositions, pas des déterminants. NULL y dit « pas de préposition », que la chaîne
        //   vide disait déjà mais sans le distinguer d'une valeur jamais renseignée ;
        // - un lieu porte plusieurs catégories, la colonne est un SET : le singulier trompait ;
        // - 100 caractères ne suffisaient plus aux adresses les plus longues (bâtiment, étage) ;
        // - NOT NULL DEFAULT 0 faisait porter deux sens à 0 pour lat et lng : « pas de
        //   coordonnées » et un point réel au large du golfe de Guinée ;
        // - `photo2`, seconde photo jamais proposée par aucun formulaire ni lue par aucune page ;
        // - `actif`, doublon inerte de `statut`, resté à 1 partout et lu nulle part.
        $this->addSql(<<<'SQL'
            ALTER TABLE `lieu`
                CHANGE `determinant` `preposition_nom` VARCHAR(40) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `nom`,
                CHANGE `categorie` `categories`
                    SET('bistrot','salle','restaurant','cinema','theatre','galerie','boutique','musee','autre')
                    COLLATE utf8mb4_unicode_ci NOT NULL AFTER `preposition_nom`,
                MODIFY `adresse` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                MODIFY `lat` DECIMAL(10,7) NULL DEFAULT NULL,
                MODIFY `lng` DECIMAL(10,7) NULL DEFAULT NULL,
                MODIFY `horaire_general` MEDIUMTEXT COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
                MODIFY `URL` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
                MODIFY `photo1` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
                DROP `photo2`,
                DROP `actif`
            SQL);

        // Le logo appartient à l'identité du lieu : il se range avec le nom et les catégories,
        // et non entre les deux photos.
        $this->addSql(<<<'SQL'
            ALTER TABLE `lieu`
                MODIFY `logo` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `categories`
            SQL);

        // Les lignes existantes portent 0 là où « pas de coordonnées » se dit désormais NULL.
        // La condition exige les deux à la fois : le formulaire ne les accepte que par paire.
        $this->addSql('UPDATE `lieu` SET `lat` = NULL, `lng` = NULL WHERE `lat` = 0 AND `lng` = 0');

        // Même chose pour les colonnes texte, où la chaîne vide était le seul « non renseigné »
        // disponible.
        $this->addSql("UPDATE `lieu` SET `preposition_nom` = NULL WHERE `preposition_nom` = ''");
        $this->addSql("UPDATE `lieu` SET `horaire_general` = NULL WHERE `horaire_general` = ''");
        $this->addSql("UPDATE `lieu` SET `URL` = NULL WHERE `URL` = ''");
        $this->addSql("UPDATE `lieu` SET `logo` = NULL WHERE `logo` = ''");
        $this->addSql("UPDATE `lieu` SET `photo1` = NULL WHERE `photo1` = ''");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Les colonnes photo2 et actif ont été supprimées avec leur contenu.');
    }
}
