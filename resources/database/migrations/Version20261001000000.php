<?php

declare(strict_types=1);

namespace Ladecadanse\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 3.13.0 — durée de conservation des comptes : colonne qui retient la date de l'avertissement
 * d'inactivité.
 *
 * Un compte sans connexion depuis trois ans est anonymisé, un mois après un avertissement envoyé
 * par courriel (`Ladecadanse\InactiveAccountRetention`). Cette colonne porte la date de cet envoi,
 * sans quoi l'avertissement repartirait à chaque passage de la purge.
 *
 * `NULL` a deux sens, que le code distingue : jamais averti, ou averti puis revenu — la connexion
 * remet la colonne à `NULL` en même temps qu'elle met `last_login` à jour, ce qui rend au compte
 * son délai complet.
 *
 * Colonne plutôt que `personne.settings`, qui est un JSON de préférences : un état tenu par le
 * système n'a pas à y vivre, et une requête sur ce champ ne saurait pas s'indexer.
 *
 * Indépendante du code : la colonne peut exister sans que la purge tourne, l'inverse serait une
 * erreur SQL. La passer donc avant la mise en ligne. MyISAM : l'ALTER reconstruit la table, mais
 * `personne` tient dans quelques centaines de lignes.
 */
final class Version20261001000000 extends AbstractMigration
{
    use MigrationDefaults;

    public function getDescription(): string
    {
        return 'personne.inactivity_notified_at, date de l’avertissement avant anonymisation';
    }

    public function up(Schema $schema): void
    {
        if ($this->alreadyApplied("SHOW COLUMNS FROM personne LIKE 'inactivity_notified_at'")) {
            return;
        }

        $this->addSql(<<<'SQL'
            ALTER TABLE personne
              ADD COLUMN inactivity_notified_at DATETIME NULL DEFAULT NULL
              COMMENT 'avertissement avant anonymisation pour inactivité ; NULL = jamais averti, ou revenu depuis'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE personne DROP COLUMN inactivity_notified_at');
    }
}
