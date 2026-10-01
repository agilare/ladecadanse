<?php

declare(strict_types=1);

namespace Ladecadanse\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 3.9.4 — ancien script v3-9-4-personne-mot-de-passe-255.sql.sql.
 */
final class Version20260307000000 extends AbstractMigration
{
    use MigrationDefaults;

    public function getDescription(): string
    {
        return 'personne.mot_de_passe en VARCHAR(255), pour les empreintes bcrypt';
    }

    public function up(Schema $schema): void
    {
        if ($this->alreadyApplied(<<<'SQL'
            SELECT MAX(CHARACTER_MAXIMUM_LENGTH) >= 255 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personne' AND COLUMN_NAME = 'mot_de_passe'
            SQL)) {
            return;
        }

        $this->addSql(<<<'SQL'
            ALTER TABLE `personne`
              CHANGE COLUMN `mot_de_passe` `mot_de_passe` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb4_unicode_ci' AFTER `pseudo`
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Raccourcir la colonne tronquerait les empreintes de mot de passe.');
    }
}
