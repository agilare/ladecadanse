<?php

declare(strict_types=1);

namespace Ladecadanse\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 3.14.0 — colonne `personne.password_updated`, date du dernier changement de mot de passe (#249).
 *
 * Écrite par user/pwd-edit.php, user/reset2.php et l'inscription, lue par la fiche de profil. Les
 * comptes existants gardent NULL : la date d'inscription n'est pas celle du mot de passe, la recopier
 * affirmerait une chose fausse.
 *
 * À passer avant la mise en ligne du code, qui l'écrit et la lit. L'`ALTER` ajoute une colonne
 * NULL à `personne`, quelques milliers de lignes : instantané.
 */
final class Version20261026000000 extends AbstractMigration
{
    use MigrationDefaults;

    public function getDescription(): string
    {
        return 'personne.password_updated, date du dernier changement de mot de passe';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE personne
              ADD COLUMN password_updated DATETIME NULL DEFAULT NULL AFTER mot_de_passe
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE personne DROP COLUMN password_updated');
    }
}
