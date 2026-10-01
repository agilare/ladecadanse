<?php

declare(strict_types=1);

namespace Ladecadanse\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 3.11.0 — ancien script v3-11-0_personne-add-settings.sql.
 */
final class Version20260808000000 extends AbstractMigration
{
    use MigrationDefaults;

    public function getDescription(): string
    {
        return 'Colonne personne.settings, les préférences personnelles en JSON';
    }

    public function up(Schema $schema): void
    {
        if ($this->alreadyApplied(<<<'SQL'
            SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personne' AND COLUMN_NAME = 'settings'
            SQL)) {
            return;
        }

        $this->addSql('ALTER TABLE personne ADD settings TEXT NULL DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE personne DROP settings');
    }
}
