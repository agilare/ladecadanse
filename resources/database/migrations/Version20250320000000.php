<?php

declare(strict_types=1);

namespace Ladecadanse\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 3.7.0 — ancien script v3-6-3_localite-add-regions_covered.sql.
 *
 * Les communes du district de Nyon sont rattachées aux régions Genève et Vaud à la fois.
 */
final class Version20250320000000 extends AbstractMigration
{
    use MigrationDefaults;

    public function getDescription(): string
    {
        return 'Colonne localite.regions_covered, et les communes du district de Nyon rattachées à ge,vd';
    }

    public function up(Schema $schema): void
    {
        if ($this->alreadyApplied(<<<'SQL'
            SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'localite' AND COLUMN_NAME = 'regions_covered'
            SQL)) {
            return;
        }

        $this->addSql(<<<'SQL'
            ALTER TABLE `localite` ADD `regions_covered` SET('ge', 'vd', 'rf', 'hs') NULL DEFAULT NULL AFTER `canton`
            SQL);

        $this->addSql(<<<'SQL'
            UPDATE localite
               SET regions_covered = 'ge,vd'
             WHERE commune IN (
                   'Arnex-sur-Nyon', 'Arzier-Le Muids', 'Bassins', 'Begnins', 'Bogis-Bossey', 'Borex',
                   'Bursinel', 'Bursins', 'Burtigny', 'Chavannes-de-Bogis', 'Chavannes-des-Bois',
                   'Chéserex', 'Coinsins', 'Commugny', 'Coppet', 'Crans-près-Céligny', 'Crassier',
                   'Duillier', 'Dully', 'Essertines-sur-Rolle', 'Eysins', 'Founex', 'Genolier', 'Gilly',
                   'Gingins', 'Givrins', 'Gland', 'Grens', 'La Rippe', 'Le Vaud', 'Longirod', 'Luins',
                   'Marchissy', 'Mies', 'Mont-sur-Rolle', 'Nyon', 'Perroy', 'Prangins', 'Rolle',
                   'Saint-Cergue', 'Saint-George', 'Signy-Avenex', 'Tannay', 'Tartegnin', 'Trélex',
                   'Vich', 'Vinzel'
             )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `localite` DROP `regions_covered`');
    }
}
