<?php

declare(strict_types=1);

namespace Ladecadanse\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 3.13.0 — colonne `personne.session_epoch`, pour « Se déconnecter des autres appareils ».
 *
 * Copiée en session à la connexion, comparée à chaque requête par `Sentry::checkSession()` :
 * l'incrémenter ferme toutes les sessions ouvertes du compte, que la suppression des jetons
 * « Rester connecté-e » laissait vivre jusqu'à une heure d'inactivité.
 *
 * À passer avant la mise en ligne du code, qui lit la colonne à chaque page vue d'une personne
 * connectée. Une session ouverte avant la mise à jour n'a pas la valeur en session ; elle est
 * lue comme 0, le défaut, et survit. L'`ALTER` reconstruit `personne` (MyISAM), quelques
 * centaines de lignes.
 */
final class Version20261006120000 extends AbstractMigration
{
    use MigrationDefaults;

    public function getDescription(): string
    {
        return 'personne.session_epoch, compteur qui ferme toutes les sessions d’un compte';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE personne
              ADD COLUMN session_epoch INT UNSIGNED NOT NULL DEFAULT 0
              COMMENT 'incrémenté pour fermer toutes les sessions ouvertes du compte'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE personne DROP COLUMN session_epoch');
    }
}
