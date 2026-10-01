<?php

declare(strict_types=1);

namespace Ladecadanse\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 3.12.0 — ancien script v3-12-0_localite-france.sql.
 *
 * La France sort de son exception : elle devient un canton comme un autre dans la table
 * `localite`, où l'on pourra ajouter des localités françaises au fur et à mesure.
 *
 * Avant : les événements et les lieux « en France » comme ceux « ailleurs » pointaient tous sur
 * la localité 1 (« Autre »), seule la colonne `region` ('rf' ou 'hs') les distinguait, et les
 * deux entrées étaient proposées en dur par les formulaires ($glo_tab_ailleurs).
 * Après : « autre localité en France » est une localité de canton 'rf', « Autre » garde l'id 1
 * et prend le canton 'hs'. Les trois formulaires n'offrent plus que des localités.
 *
 * Les instructions partagent une variable de connexion (LAST_INSERT_ID()) : Doctrine les passe
 * toutes sur la même, ce que l'ancien script demandait de faire à la main, « d'une traite ».
 */
final class Version20260825000000 extends AbstractMigration
{
    use MigrationDefaults;

    public function getDescription(): string
    {
        return 'localite.npa en VARCHAR(6), localité « Ailleurs en France », localité 1 renommée en canton hs';
    }

    public function up(Schema $schema): void
    {
        if ($this->alreadyApplied(<<<'SQL'
            SELECT COUNT(*) FROM localite WHERE canton = 'rf' AND localite = 'Ailleurs en France'
            SQL)) {
            return;
        }

        // 1. NPA : un code postal français compte 5 chiffres, dont certains commencent par un zéro
        //    (01000 Bourg-en-Bresse). VARCHAR le conserve, INT le perdrait — et la largeur d'un
        //    INT(4) n'était de toute façon qu'un affichage, jamais une limite de valeur.
        $this->addSql("ALTER TABLE `localite` MODIFY `npa` VARCHAR(6) NOT NULL DEFAULT ''");

        // 2. La localité fourre-tout du canton 'rf' : une commune française pas encore listée.
        //    Son libellé n'est jamais affiché dans une adresse (cf. Localite::LOCALITES_FOURRE_TOUT) ;
        //    le renommer suppose de renommer la constante avec.
        $this->addSql(<<<'SQL'
            INSERT INTO `localite` (`localite`, `commune`, `npa`, `canton`, `regions_covered`)
            VALUES ('Ailleurs en France', 'Autre', '0', 'rf', 'ge,rf')
            SQL);

        $this->addSql('SET @id_autre_france = LAST_INSERT_ID()');

        // 3. Les événements et les lieux déjà situés en France la rejoignent
        $this->addSql("UPDATE `evenement` SET `localite_id` = @id_autre_france WHERE `localite_id` = 1 AND `region` = 'rf'");
        $this->addSql("UPDATE `lieu` SET `localite_id` = @id_autre_france WHERE `localite_id` = 1 AND `region` = 'rf'");

        // 4. La localité 1 ne désigne plus que « ailleurs » : son canton vide devient 'hs', ce qui la
        //    fait entrer dans les <optgroup> construits depuis la colonne `canton`. Elle aussi est une
        //    localité fourre-tout, jamais affichée dans une adresse.
        $this->addSql("UPDATE `localite` SET `localite` = 'Hors Genève, Vaud et France', `canton` = 'hs' WHERE `id` = 1");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            "Les événements et les lieux déplacés vers « Ailleurs en France » ne se distinguent plus de ceux qui l'ont été ensuite."
        );
    }
}
