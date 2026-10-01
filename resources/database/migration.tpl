<?php

declare(strict_types=1);

namespace <namespace>;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * À décrire : ce qui change, pourquoi, et ce qu'il faut savoir avant de la passer en production
 * (verrou sur une table MyISAM, dépendance au code livré avec…).
 */
final class <className> extends AbstractMigration
{
    use MigrationDefaults;

    public function getDescription(): string
    {
        return '';
    }

    /**
     * Le SQL s'écrit à la main, une instruction par addSql() : $schema n'est pas utilisé.
     */
    public function up(Schema $schema): void
    {
        // Garde-fou, à retirer en écrivant le SQL : une classe vide passée par db:migrate serait
        // enregistrée comme appliquée, et son SQL, ajouté ensuite, ne tournerait jamais.
        $this->abortIf(true, 'Migration à écrire : ' . self::class);

        // $this->addSql('ALTER TABLE …');
    }

    /**
     * L'inverse de up(), quand il existe. Une migration qui ne se défait pas le dit :
     * $this->throwIrreversibleMigrationException('…'), plutôt qu'un inverse approximatif.
     */
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
