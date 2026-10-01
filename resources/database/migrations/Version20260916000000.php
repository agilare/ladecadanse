<?php

declare(strict_types=1);

namespace Ladecadanse\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 3.13.0 — ancien script v3-13-0_lieu-organisateur-add-admin_note.sql.
 *
 * Texte brut, lu et écrit par les seuls administrateurs (niveau ADMIN) : fieldset « Admin » des
 * formulaires d'édition, colonne « Note » des deux listes, <details> de la fiche. NULL dit « pas
 * de note » ; les formulaires y écrivent NULL plutôt qu'une chaîne vide.
 *
 * TEXT et non MEDIUMTEXT : 65 535 octets, soit au moins 16 383 caractères en utf8mb4, quand la
 * validation plafonne la note à 2 000. Rangée juste avant la date d'ajout ; MariaDB ne connaît
 * pas BEFORE, d'où les AFTER.
 *
 * Rétrocompatible : une colonne qui accepte NULL ne dérange pas le code d'avant, la migration
 * peut donc passer avant la mise en ligne. Pas après : les formulaires écrivent la colonne.
 */
final class Version20260916000000 extends AbstractMigration
{
    use MigrationDefaults;

    public function getDescription(): string
    {
        return 'Colonnes lieu.admin_note et organisateur.admin_note';
    }

    public function up(Schema $schema): void
    {
        if ($this->alreadyApplied(<<<'SQL'
            SELECT COUNT(*) = 2 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('lieu', 'organisateur') AND COLUMN_NAME = 'admin_note'
            SQL)) {
            return;
        }

        $this->addSql('ALTER TABLE `lieu` ADD `admin_note` TEXT COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `URL`');
        $this->addSql('ALTER TABLE `organisateur` ADD `admin_note` TEXT COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `statut`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `lieu` DROP `admin_note`');
        $this->addSql('ALTER TABLE `organisateur` DROP `admin_note`');
    }
}
