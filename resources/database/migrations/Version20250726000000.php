<?php

declare(strict_types=1);

namespace Ladecadanse\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 3.9.0 — ancien script v3-9-0-evenement-add-fulltext-index.sql.
 *
 * La base de la recherche : sans ces index, les MATCH … AGAINST échouent.
 */
final class Version20250726000000 extends AbstractMigration
{
    use MigrationDefaults;

    public function getDescription(): string
    {
        return 'Index FULLTEXT sur evenement.titre, nomLieu, description et sur lieu.nom';
    }

    public function up(Schema $schema): void
    {
        if ($this->alreadyApplied(<<<'SQL'
            SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evenement' AND INDEX_NAME = 'ft_evenement_titre'
            SQL)) {
            return;
        }

        $this->addSql(<<<'SQL'
            ALTER TABLE evenement
              ADD FULLTEXT INDEX ft_evenement_titre (titre),
              ADD FULLTEXT INDEX ft_evenement_nomLieu (nomLieu),
              ADD FULLTEXT INDEX ft_evenement_description (description)
            SQL);

        $this->addSql('ALTER TABLE lieu ADD FULLTEXT idx_lieu_fulltext (nom)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE evenement
              DROP INDEX ft_evenement_titre,
              DROP INDEX ft_evenement_nomLieu,
              DROP INDEX ft_evenement_description
            SQL);

        $this->addSql('ALTER TABLE lieu DROP INDEX idx_lieu_fulltext');
    }
}
