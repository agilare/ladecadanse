<?php

declare(strict_types=1);

namespace Ladecadanse\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 3.11.0 — ancien script v3-11-0_bot_monitor-create-table.sql.
 *
 * Suivi des bots et IP suspectes : une ligne par IP, agrégée par upsert à chaque page vue. En
 * InnoDB, et non en MyISAM comme les tables historiques : le verrouillage par ligne évite que
 * les upserts concurrents et les lectures du tableau de bord se bloquent.
 */
final class Version20260716000000 extends AbstractMigration
{
    use MigrationDefaults;

    public function getDescription(): string
    {
        return 'Table bot_monitor';
    }

    public function up(Schema $schema): void
    {
        if ($this->alreadyApplied(<<<'SQL'
            SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bot_monitor'
            SQL)) {
            return;
        }

        // bot_family : famille normalisée (Google, Bing, OpenAI…) calculée à l'insertion, pour
        // agréger sans GROUP BY sur le TEXT ; idx_last_seen sert la purge des IP inactives,
        // idx_family les statistiques par famille de bots
        $this->addSql(<<<'SQL'
            CREATE TABLE `bot_monitor` (
                `ip` VARCHAR(45) NOT NULL,
                `user_agent` TEXT NULL,
                `bot_family` VARCHAR(50) NULL,
                `hit_count` INT UNSIGNED NOT NULL DEFAULT 1,
                `honeypot_triggered` TINYINT(1) NOT NULL DEFAULT 0,
                `is_crawler_detect` TINYINT(1) NOT NULL DEFAULT 0,
                `first_seen` DATETIME NOT NULL,
                `last_seen` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`ip`),
                KEY `idx_last_seen` (`last_seen`),
                KEY `idx_family` (`is_crawler_detect`, `bot_family`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE `bot_monitor`');
    }
}
