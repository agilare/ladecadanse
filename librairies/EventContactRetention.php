<?php

namespace Ladecadanse;

use Monolog\Logger;
use PDO;

/**
 * Durée de conservation des coordonnées laissées par les propositions anonymes d'événement.
 *
 * Le formulaire public garde l'adresse du visiteur (`evenement.user_email`) et sa note à
 * l'administrateur (`evenement.remarque`). Deux ans après la date de l'événement, les deux
 * sont effacées.
 *
 * Même mécanique que BotMonitor : purge probabiliste sans cron, appelée depuis le bootstrap via
 * register_shutdown_function, aucun échec ne remonte à la page. `bin/` n'est pas déployé et
 * l'hébergement n'a pas de crontab gérée par le dépôt.
 *
 * La purge ne parcourt qu'une fenêtre de WINDOW_DAYS jours avant le seuil : `evenement` est en
 * MyISAM, et un UPDATE sur tous les événements de plus de deux ans — l'essentiel de la table —
 * la verrouillerait à chaque passage pour n'y trouver presque rien. Ce qui précède la fenêtre
 * relève du rattrapage `resources/database/v3-13-0_evenement-purge-contact.sql`.
 */
class EventContactRetention
{
    /** @var int durée de conservation (années) après la date de l'événement */
    public const RETENTION_YEARS = 2;

    /** @var int profondeur (jours) de la fenêtre parcourue avant le seuil */
    public const WINDOW_DAYS = 90;

    /** @var int la purge est tentée en moyenne une fois sur N pages vues */
    public const PURGE_PROBABILITY = 1000;

    /** @var int lignes modifiées au plus par passage */
    public const BATCH_SIZE = 500;

    public function __construct(
        private readonly PDO $pdo,
        private readonly ?Logger $logger = null
    ) {
    }

    /**
     * Efface adresse et remarque des événements de la fenêtre qui ont franchi le seuil.
     * Les bornes viennent des constantes, d'où l'interpolation.
     *
     * @return int nombre d'événements modifiés
     */
    public function purge(): int
    {
        $seuil = "DATE_SUB(CURDATE(), INTERVAL " . self::RETENTION_YEARS . " YEAR)";

        $stmt = $this->pdo->prepare(
            "UPDATE evenement
            SET user_email = NULL, remarque = NULL
            WHERE dateEvenement >= DATE_SUB($seuil, INTERVAL " . self::WINDOW_DAYS . " DAY)
              AND dateEvenement < $seuil
              AND (user_email IS NOT NULL OR remarque IS NOT NULL)
            LIMIT " . self::BATCH_SIZE
        );
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Purge probabiliste (~1 appel sur PURGE_PROBABILITY), exécutée dans le shutdown donc
     * invisible pour le visiteur. Aucune exception ne doit remonter.
     */
    public function maybePurge(): void
    {
        if (random_int(1, self::PURGE_PROBABILITY) !== 1) {
            return;
        }

        try {
            $this->purge();
        } catch (\Throwable $e) {
            $this->logger?->warning('EventContactRetention::purge a échoué : ' . $e->getMessage());
        }
    }
}
