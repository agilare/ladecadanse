<?php

declare(strict_types=1);

namespace Ladecadanse\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 3.13.0 — ancien script v3-13-0_evenement-purge-contact.sql : durée de conservation des
 * coordonnées des propositions anonymes.
 *
 * Le formulaire public « Proposer un événement » garde l'adresse du visiteur dans
 * `evenement.user_email` et sa note à l'administrateur dans `evenement.remarque`. Rien ne les
 * effaçait : elles restaient attachées à des événements passés depuis des années. La durée de
 * conservation est de deux ans après la date de l'événement.
 *
 * Cette migration est le rattrapage. La suite est tenue par l'application :
 * `Ladecadanse\EventContactRetention` purge au fil des pages vues les événements qui franchissent
 * le seuil, mais seulement sur une fenêtre de 90 jours — `evenement` est en MyISAM, et un UPDATE
 * qui parcourrait toute la table à chaque passage la verrouillerait d'autant. Tout ce qui est plus
 * ancien que la fenêtre n'est effacé que par cette migration.
 *
 * `remarque` est effacée aussi sans adresse : même nature de donnée. `date_derniere_modif` n'est
 * pas touchée : le contenu de l'événement ne change pas. L'UPDATE est idempotent, d'où l'absence
 * de détection : sur une base où l'ancien script a déjà tourné, il ne touche aucune ligne.
 *
 * Indépendante du code : peut passer avant la mise en ligne, avec elle ou après. MyISAM : verrou
 * d'écriture le temps de l'UPDATE, à passer hors des heures de saisie.
 */
final class Version20260930000000 extends AbstractMigration
{
    use MigrationDefaults;

    public function getDescription(): string
    {
        return 'evenement.user_email et remarque effacés sur les événements de plus de deux ans';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE evenement
               SET user_email = NULL, remarque = NULL
             WHERE dateEvenement < DATE_SUB(CURDATE(), INTERVAL 2 YEAR)
               AND (user_email IS NOT NULL OR remarque IS NOT NULL)
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Les adresses et les remarques effacées ne sont conservées nulle part.');
    }
}
