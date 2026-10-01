<?php

declare(strict_types=1);

namespace Ladecadanse\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 3.13.0 — ancien script v3-13-0_personne-evenement-create-table.sql, favoris personnels (#98).
 *
 * Une ligne par couple (personne, événement). La clé primaire composite rend l'ajout
 * idempotent, l'INSERT IGNORE de event/favorites.php s'appuyant dessus pour ne pas dupliquer
 * un favori déjà posé. L'index sur idEvenement sert le sens inverse, compter ou lister qui a
 * mis un événement en favori.
 *
 * À passer avant la mise en ligne du code, ou avec elle : création d'une table vide, ni verrou
 * ni durée à prévoir, et l'ancien code ne la connaît pas.
 */
final class Version20260926000000 extends AbstractMigration
{
    use MigrationDefaults;

    public function getDescription(): string
    {
        return 'Table personne_evenement, les favoris personnels';
    }

    public function up(Schema $schema): void
    {
        if ($this->alreadyApplied(<<<'SQL'
            SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personne_evenement'
            SQL)) {
            return;
        }

        $this->addSql(<<<'SQL'
            CREATE TABLE `personne_evenement` (
              `idPersonne` smallint(5) unsigned NOT NULL,
              `idEvenement` mediumint(8) unsigned NOT NULL,
              `dateAjout` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`idPersonne`, `idEvenement`),
              KEY `pe_idEvenement` (`idEvenement`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE `personne_evenement`');
    }
}
