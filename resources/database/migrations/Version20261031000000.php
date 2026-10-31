<?php

declare(strict_types=1);

namespace Ladecadanse\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 3.14.0 — colonne `personne.mailing_opted_out_at`, la date à laquelle la personne a demandé à ne
 * plus recevoir les mailings.
 *
 * Le pied de chaque mailing invite à écrire pour se désinscrire ; l'administrateur reporte la
 * demande ici, à la main. Une valeur suffit à écarter le compte d'`admin/mailing.php`, quel que
 * soit le CSV collé. Les messages de service (réinitialisation, avertissement d'inactivité,
 * notifications d'événement) ne sont pas concernés.
 *
 * À passer avant la mise en ligne du code, qui lit la colonne à chaque aperçu de mailing.
 */
final class Version20261031000000 extends AbstractMigration
{
    use MigrationDefaults;

    public function getDescription(): string
    {
        return 'personne.mailing_opted_out_at, date de la demande de désinscription des mailings';
    }

    public function up(Schema $schema): void
    {
        if ($this->alreadyApplied("SHOW COLUMNS FROM personne LIKE 'mailing_opted_out_at'")) {
            return;
        }

        $this->addSql(<<<'SQL'
            ALTER TABLE personne
              ADD COLUMN mailing_opted_out_at DATETIME NULL DEFAULT NULL
              COMMENT 'demande de ne plus recevoir les mailings ; NULL = aucune demande'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE personne DROP COLUMN mailing_opted_out_at');
    }
}
