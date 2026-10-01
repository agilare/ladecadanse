<?php

declare(strict_types=1);

namespace Ladecadanse\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 3.11.0 — ancien script v3-11-0_lieu-lat-lng-decimal.sql.
 *
 * La simple précision de FLOAT perdait environ 50 cm sur des coordonnées genevoises.
 */
final class Version20260801000000 extends AbstractMigration
{
    use MigrationDefaults;

    public function getDescription(): string
    {
        return 'lieu.lat et lieu.lng de FLOAT(10,6) à DECIMAL(10,7)';
    }

    public function up(Schema $schema): void
    {
        if ($this->alreadyApplied(<<<'SQL'
            SELECT MAX(DATA_TYPE) = 'decimal' FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lieu' AND COLUMN_NAME = 'lat'
            SQL)) {
            return;
        }

        $this->addSql(<<<'SQL'
            ALTER TABLE lieu
              MODIFY `lat` DECIMAL(10,7) NOT NULL DEFAULT 0,
              MODIFY `lng` DECIMAL(10,7) NOT NULL DEFAULT 0
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Revenir à FLOAT perdrait la précision des coordonnées.');
    }
}
