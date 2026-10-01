<?php

declare(strict_types=1);

namespace Ladecadanse\Migrations;

/**
 * Commun à toutes les migrations du répertoire.
 *
 * Un trait et non une classe parente : Doctrine tient pour une migration toute classe déclarée
 * dans ce répertoire et sous cet espace de noms, une classe abstraite comprise.
 */
trait MigrationDefaults
{
    /**
     * Sur MariaDB, chaque instruction DDL valide implicitement la transaction, et la plupart des
     * tables sont en MyISAM : une transaction ne défait rien. Doctrine, s'il en ouvrait une, se
     * plaindrait en fin de migration de ne plus la trouver.
     */
    public function isTransactional(): bool
    {
        return false;
    }

    /**
     * Pour les migrations écrites avant Doctrine, passées à la main depuis un script .sql : une
     * base peut déjà en porter l'effet — toutes, si elle a été créée depuis ladecadanse.sql.
     * Une migration qui le détecte n'exécute rien et Doctrine l'enregistre quand même, ce qui
     * adopte une base à n'importe quelle version par un simple `composer db:migrate`.
     *
     * Doctrine enchaîne ce message d'un avertissement, « did not result in any SQL
     * statements » : il est attendu.
     *
     * @param string $query renvoie une valeur non nulle, non vide et différente de 0 si l'effet est en place
     */
    protected function alreadyApplied(string $query): bool
    {
        if (!(bool) $this->connection->fetchOne($query)) {
            return false;
        }

        $this->write('Déjà en place dans cette base : enregistrée sans rien exécuter.');

        return true;
    }
}
