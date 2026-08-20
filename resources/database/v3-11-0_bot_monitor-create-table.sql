-- Table de suivi des bots et IP suspectes : une ligne par IP, agrégée par upsert à chaque page vue.
-- InnoDB (et non MyISAM comme les tables historiques) : écriture à chaque visite,
-- le verrouillage par ligne évite que les upserts concurrents et les lectures du dashboard se bloquent.
CREATE TABLE `bot_monitor` (
    `ip` VARCHAR(45) NOT NULL,
    `user_agent` TEXT NULL,
    `bot_family` VARCHAR(50) NULL, -- famille normalisée (Google, Bing, OpenAI...) calculée à l'insertion, pour agréger sans GROUP BY sur le TEXT
    `hit_count` INT UNSIGNED NOT NULL DEFAULT 1,
    `honeypot_triggered` TINYINT(1) NOT NULL DEFAULT 0,
    `is_crawler_detect` TINYINT(1) NOT NULL DEFAULT 0,
    `first_seen` DATETIME NOT NULL,
    `last_seen` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`ip`),
    KEY `idx_last_seen` (`last_seen`), -- pour la purge des IP inactives
    KEY `idx_family` (`is_crawler_detect`, `bot_family`) -- pour les statistiques par famille de bots
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
