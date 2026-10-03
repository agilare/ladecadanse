<?php

declare(strict_types=1);

namespace Ladecadanse\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 3.13.0 — suivi des bots : rafales et sondeurs (`Ladecadanse\BotMonitor`).
 *
 * Six colonnes sur `bot_monitor` : la fenêtre de comptage en cours (`window_start`,
 * `window_hits`), la plus forte fenêtre observée pour l'IP (`peak_hits`, `peak_start`), et ses
 * requêtes terminées en erreur 4xx (`error_hits`, `last_error_path` — le chemin sans sa query
 * string, qui peut porter un jeton de réinitialisation). L'upsert de `BotMonitor::track()` les
 * alimente toutes d'un coup ; les vues « Rafales » et « Sondeurs » d'admin/bots.php les lisent.
 *
 * À passer avant la mise en ligne du code : les six colonnes ont un défaut, l'ancien code les
 * ignore sans dommage. L'inverse ne vaut pas : avec le nouveau code et la base en retard, aucune
 * page ne tombe — l'échec est rattrapé — mais plus rien n'est compté, et chaque page vue écrit un
 * warning dans le log. Les lignes existantes gardent leurs compteurs de fenêtre à zéro jusqu'à
 * la prochaine visite de leur IP ; rien n'est recalculé.
 *
 * `bot_monitor` est en InnoDB : pas de verrou d'écriture, l'ALTER est l'affaire d'un instant.
 */
final class Version20261003000000 extends AbstractMigration
{
    use MigrationDefaults;

    public function getDescription(): string
    {
        return 'bot_monitor : fenêtre de comptage, pic et erreurs 4xx par IP (rafales et sondeurs)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE `bot_monitor`
              ADD COLUMN `window_start` DATETIME NULL
                COMMENT 'début de la fenêtre de comptage en cours' AFTER `hit_count`,
              ADD COLUMN `window_hits` INT UNSIGNED NOT NULL DEFAULT 0
                COMMENT 'pages vues dans cette fenêtre' AFTER `window_start`,
              ADD COLUMN `peak_hits` INT UNSIGNED NOT NULL DEFAULT 0
                COMMENT 'plus forte fenêtre observée pour cette IP' AFTER `window_hits`,
              ADD COLUMN `peak_start` DATETIME NULL
                COMMENT 'début de cette plus forte fenêtre' AFTER `peak_hits`,
              ADD COLUMN `error_hits` INT UNSIGNED NOT NULL DEFAULT 0
                COMMENT 'requêtes terminées en 4xx, fichiers statiques exclus' AFTER `peak_start`,
              ADD COLUMN `last_error_path` VARCHAR(255) NULL
                COMMENT 'chemin de la dernière, sans sa query string' AFTER `error_hits`
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE `bot_monitor`
              DROP COLUMN `window_start`,
              DROP COLUMN `window_hits`,
              DROP COLUMN `peak_hits`,
              DROP COLUMN `peak_start`,
              DROP COLUMN `error_hits`,
              DROP COLUMN `last_error_path`
            SQL);
    }
}
