<?php

declare(strict_types=1);

namespace Ladecadanse\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 3.8.0 — ancien script v3-8-0-evenement-add-index.sql.
 */
final class Version20250628000000 extends AbstractMigration
{
    use MigrationDefaults;

    public function getDescription(): string
    {
        return 'Index composite evenement.idx_ev_date_statut_genre_ajout';
    }

    public function up(Schema $schema): void
    {
        if ($this->alreadyApplied(<<<'SQL'
            SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evenement' AND INDEX_NAME = 'idx_ev_date_statut_genre_ajout'
            SQL)) {
            return;
        }

        $this->addSql('CREATE INDEX idx_ev_date_statut_genre_ajout ON evenement (dateEvenement, statut, genre, dateAjout DESC)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_ev_date_statut_genre_ajout ON evenement');
    }
}
