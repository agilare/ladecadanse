<?php

declare(strict_types=1);

namespace Ladecadanse\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 3.13.0 — table `remember_token`, les jetons « Rester connecté-e » (`Ladecadanse\Security\RememberTokens`).
 *
 * Une ligne par appareil, là où l'unique colonne `personne.cookie` ne gardait que le dernier :
 * se connecter sur un second appareil déconnectait le premier. Le cookie porte
 * `selector:validator` ; la table ne garde que l'empreinte SHA-256 du validator, si bien qu'une
 * fuite de la base ne livre aucun jeton utilisable.
 *
 * Pas de clé étrangère : `personne` est en MyISAM. Les lignes d'un compte anonymisé sont
 * effacées par `Personne::anonymize()`, celles d'un mot de passe changé par
 * `Sentry::revokeRememberedDevices()`.
 *
 * À passer avant la mise en ligne du code : création d'une table vide, que l'ancien code ignore.
 * L'inverse fait tomber toute connexion. `personne.cookie` reste en place, inerte, jusqu'à une
 * migration ultérieure ; les cookies qu'il validait ne sont plus reconnus, et chaque personne
 * mémorisée se reconnecte une fois.
 */
final class Version20261006000000 extends AbstractMigration
{
    use MigrationDefaults;

    public function getDescription(): string
    {
        return 'Table remember_token, un jeton « Rester connecté-e » par appareil';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE `remember_token` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `user_id` smallint(5) unsigned NOT NULL COMMENT 'personne.idPersonne',
              `selector` char(24) NOT NULL COMMENT 'partie publique du cookie, sert à retrouver la ligne',
              `validator_hash` char(64) NOT NULL COMMENT 'SHA-256 de la partie secrète du cookie',
              `expires` datetime NOT NULL,
              `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `last_used` datetime NULL DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `rt_selector` (`selector`),
              KEY `rt_user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE `remember_token`');
    }
}
