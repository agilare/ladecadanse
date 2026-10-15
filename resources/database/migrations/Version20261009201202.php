<?php

declare(strict_types=1);

namespace Ladecadanse\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 3.14.0 — les douze tables encore en MyISAM passent en InnoDB (#228).
 *
 * MyISAM verrouille la table entière à chaque écriture — un UPDATE sur `evenement` gelait
 * l'agenda le temps de l'écriture —, laisse des tables à réparer après une coupure, et ne connaît
 * ni transactions ni clés étrangères. InnoDB verrouille par ligne, rejoue son journal au
 * démarrage, autorise `mysqldump --single-transaction`, et ouvre la voie aux transactions sur les
 * écritures multi-tables (#161) et aux clés étrangères (#164) — ni les unes ni les autres ne sont
 * posées ici. `bot_monitor`, `fichierrecu`, `lieu_fichierrecu`, `localite` et `remember_token`
 * y étaient déjà.
 *
 * Un `ALTER TABLE … ENGINE=InnoDB` par table, index FULLTEXT compris : InnoDB les reconstruit dans
 * la copie, avec sa colonne cachée `FTS_DOC_ID`. Les supprimer pour les recréer ensuite coûterait
 * une seconde reconstruction d'`evenement`, le premier `ADD FULLTEXT` InnoDB reconstruisant la
 * table. Seules les tables encore en MyISAM sont converties (lues dans `information_schema`), les
 * plus petites d'abord : interrompue sur `evenement`, la migration n'est pas enregistrée, et le
 * passage suivant ne reprend que ce qui manque. `--dry-run` montre ce qui reste.
 *
 * Ce qui change pour la recherche (`event/search.php`) : le calcul de pertinence — InnoDB dérive
 * de BM25, le classement par score diffère — et la liste de mots vides du serveur, 36 mots dont
 * `de`, `en`, `la` et de l'anglais, contre quelque 540 mots anglais pour MyISAM. La longueur
 * minimale des termes ne change pas en production, où `ft_min_word_len` et
 * `innodb_ft_min_token_size` valent tous deux 3 ; sur un poste où `ft_min_word_len` garde son
 * défaut de 4, les mots de trois lettres deviennent cherchables.
 *
 * Indépendante du code, mais à passer dans une fenêtre de maintenance : un changement de moteur
 * force `ALGORITHM=COPY`, chaque table est verrouillée en écriture le temps de sa copie, et
 * `evenement` en est l'essentiel. `mysqldump` avant, `SHOW TABLE STATUS` après — moteur, et
 * `AUTO_INCREMENT` d'`evenement`, que la reconstruction préserve (vérifié sur 10.11.15 ; le
 * compteur InnoDB est persistant depuis MariaDB 10.2.4, MDEV-6076). Voir UPGRADE.md.
 *
 * `down()` rend les tables à MyISAM, index FULLTEXT compris. Il cessera de valoir le jour où des
 * clés étrangères seront posées : MyISAM les ignore.
 */
final class Version20261009201202 extends AbstractMigration
{
    use MigrationDefaults;

    /** Les douze tables encore en MyISAM en 3.13.0, les plus petites d'abord et `evenement` en dernier. */
    private const TABLES = [
        'affiliation',
        'descriptionlieu',
        'evenement_organisateur',
        'lieu_organisateur',
        'personne_organisateur',
        'personne_evenement',
        'salle',
        'organisateur',
        'personne',
        'user_reset_requests',
        'lieu',
        'evenement',
    ];

    public function getDescription(): string
    {
        return 'Les douze tables MyISAM passent en InnoDB, index FULLTEXT compris';
    }

    public function up(Schema $schema): void
    {
        $myisam = $this->connection->fetchFirstColumn(sprintf(
            "SELECT TABLE_NAME FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND ENGINE = 'MyISAM' AND TABLE_NAME IN ('%s')",
            implode("','", self::TABLES)
        ));

        if ($myisam === []) {
            $this->write('Déjà en place dans cette base : enregistrée sans rien exécuter.');

            return;
        }

        foreach (array_intersect(self::TABLES, $myisam) as $table) {
            $this->addSql("ALTER TABLE `$table` ENGINE=InnoDB");
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::TABLES as $table) {
            $this->addSql("ALTER TABLE `$table` ENGINE=MyISAM");
        }
    }
}
