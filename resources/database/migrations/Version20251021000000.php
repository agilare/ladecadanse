<?php

declare(strict_types=1);

namespace Ladecadanse\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 3.9.2 — ancien script v3-9-2-evenement-add-index.sql.
 */
final class Version20251021000000 extends AbstractMigration
{
    use MigrationDefaults;

    public function getDescription(): string
    {
        return 'Index evenement.idx_ev_idPersonne';
    }

    public function up(Schema $schema): void
    {
        if ($this->alreadyApplied(<<<'SQL'
            SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evenement' AND INDEX_NAME = 'idx_ev_idPersonne'
            SQL)) {
            return;
        }

        $this->addSql('ALTER TABLE evenement ADD INDEX idx_ev_idPersonne (idPersonne)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement DROP INDEX idx_ev_idPersonne');
    }
}
