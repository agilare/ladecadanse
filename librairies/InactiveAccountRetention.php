<?php

namespace Ladecadanse;

use Ladecadanse\Utils\LogSafe;
use Ladecadanse\Utils\Mailing;
use Monolog\Logger;
use PDO;

/**
 * Durée de conservation des comptes : un compte sans connexion depuis RETENTION_YEARS années est
 * anonymisé, NOTICE_DAYS jours après un avertissement envoyé par courriel.
 *
 * Anonymisation et non suppression, par Personne::anonymize() : la ligne survit, vidée, donc
 * aucun `evenement.idPersonne` ne se retrouve orphelin et les annonces publiées gardent leur
 * place dans l'agenda sans garder leur auteur. La LPD art. 6 al. 4 met les deux sur le même plan.
 *
 * Même mécanique que BotMonitor et EventContactRetention : déclenchement probabiliste depuis le
 * bootstrap, sans cron, aucun échec ne remonte à la page.
 *
 * Deux temps, et non un seul : effacer un compte sans prévenir se retourne contre le site le jour
 * où la personne revient. L'avertissement n'est pas imposé par les textes, la trace de son envoi
 * l'est en pratique — d'où `personne.inactivity_notified_at`, que la connexion remet à `NULL`
 * (voir Security\Sentry) pour rendre son délai complet à qui revient.
 *
 * Trois années, c'est ce que la CNIL retient pour les comptes inactifs, et c'est large pour un
 * lieu qui ferait une saison de pause.
 */
class InactiveAccountRetention
{
    /** @var int durée (années) sans connexion au terme de laquelle un compte est anonymisé */
    public const RETENTION_YEARS = 3;

    /** @var int délai (jours) entre l'avertissement et l'anonymisation */
    public const NOTICE_DAYS = 30;

    /** @var int le traitement est tenté en moyenne une fois sur N pages vues */
    public const RUN_PROBABILITY = 1000;

    /** @var int comptes anonymisés au plus par passage */
    public const PURGE_BATCH_SIZE = 20;

    /**
     * Un seul avertissement par passage : l'envoi SMTP demande une à trois secondes, et le
     * shutdown retient le processus PHP pendant ce temps. À une page vue sur mille, le rythme
     * suffit largement au flux de quelques comptes par an.
     */
    public const NOTICE_BATCH_SIZE = 1;

    public function __construct(
        private readonly PDO $pdo,
        private readonly ?Logger $logger = null
    ) {
    }

    /**
     * Condition d'inactivité, commune aux deux phases.
     *
     * `last_login` n'existe que depuis la v3.6.3 : un tiers des comptes l'a encore à `NULL`
     * sans être pour autant dormant. D'où le repli sur la dernière modification du profil, puis
     * sur la date d'inscription — sans lui, ces comptes seraient soit intouchables, soit
     * anonymisés en bloc.
     *
     * Les administrateurs (groupe <= ADMIN) sont hors du balayage : leur compte est un outil de
     * service, dont l'inactivité ne dit rien.
     *
     * `mot_de_passe <> ''` écarte les comptes déjà anonymisés, qu'Personne::anonymize() laisse
     * avec un mot de passe vide : sans ce critère, ils reviendraient à chaque passage.
     *
     * Les deux `NOT EXISTS` sont un filet, et ils ne sont pas théoriques : au 01.10.2026, la
     * production comptait 5 464 comptes au-dessus du seuil, dont **140 avaient contribué dans les
     * trois ans**. La date de connexion ment pour eux — jusqu'au correctif du même jour,
     * Sentry::checkRemembered() ne la mettait pas à jour, si bien qu'un contributeur revenant par
     * le cookie « rester connecté-e » gardait la date de sa dernière saisie de mot de passe. Le
     * correctif ne vaut que pour l'avenir ; ce que le compte a produit, lui, se lit dans le passé.
     *
     * `evenement.idPersonne` est indexé, `descriptionlieu` tient dans quelques centaines de
     * lignes : le coût de la corrélation reste négligeable pour une requête qui tourne une page
     * vue sur mille.
     */
    private function inactiveCondition(): string
    {
        $seuil = "DATE_SUB(CURDATE(), INTERVAL " . self::RETENTION_YEARS . " YEAR)";

        return "groupe > " . UserLevel::ADMIN . "
                AND mot_de_passe <> ''
                AND COALESCE(last_login, date_derniere_modif, dateAjout) < $seuil
                AND NOT EXISTS (
                    SELECT 1 FROM evenement e
                    WHERE e.idPersonne = personne.idPersonne AND e.dateAjout > $seuil
                )
                AND NOT EXISTS (
                    SELECT 1 FROM descriptionlieu d
                    WHERE d.idPersonne = personne.idPersonne AND d.dateAjout > $seuil
                )";
    }

    /**
     * Avertit les comptes inactifs qui ne l'ont pas encore été.
     *
     * L'envoi qui échoue — adresse morte, ce qui est probable après trois ans — marque quand
     * même la date : l'obligation est de prévenir, pas que le message arrive, et sans cette
     * marque le compte serait retenté à chaque passage.
     *
     * @return int nombre de comptes avertis
     */
    public function notify(Mailing $mailer, TemplateEngine $templates): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT idPersonne, email,
                    DATE_FORMAT(COALESCE(last_login, date_derniere_modif, dateAjout), '%m.%Y') AS derniere_activite
             FROM personne
             WHERE " . $this->inactiveCondition() . "
               AND inactivity_notified_at IS NULL
             LIMIT " . self::NOTICE_BATCH_SIZE
        );
        $stmt->execute();
        $comptes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $marque = $this->pdo->prepare("UPDATE personne SET inactivity_notified_at = NOW() WHERE idPersonne = :idP");

        foreach ($comptes as $compte)
        {
            $corps = $templates->render('user-inactivity-notice-mail-body', [
                'derniere_activite' => (string) $compte['derniere_activite'],
                'login_url' => SITE_CANONICAL_URL . '/user/login.php',
            ]);

            $envoye = $mailer->toUser((string) $compte['email'], 'Votre compte sur La décadanse', $corps);

            $marque->execute([':idP' => (int) $compte['idPersonne']]);

            $this->logger?->info('[inactive-account] averti', [
                'idP' => (int) $compte['idPersonne'],
                'domaine' => LogSafe::email((string) $compte['email']),
                'envoye' => $envoye,
            ]);
        }

        return count($comptes);
    }

    /**
     * Anonymise les comptes avertis depuis plus de NOTICE_DAYS et toujours inactifs.
     *
     * La condition d'inactivité est réévaluée ici : une connexion survenue depuis
     * l'avertissement a remis `inactivity_notified_at` à `NULL`, donc le compte ne remonte plus.
     * La reprendre est une ceinture de sécurité, pour le cas où une écriture aurait manqué.
     *
     * @return int nombre de comptes anonymisés
     */
    public function purge(): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT idPersonne
             FROM personne
             WHERE " . $this->inactiveCondition() . "
               AND inactivity_notified_at < DATE_SUB(NOW(), INTERVAL " . self::NOTICE_DAYS . " DAY)
             LIMIT " . self::PURGE_BATCH_SIZE
        );
        $stmt->execute();
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($ids as $idPersonne)
        {
            // ni nom ni adresse : le journal les garderait quatorze mois, ce qui referait le
            // lien que l'anonymisation vient de couper
            if (Personne::anonymize((int) $idPersonne))
            {
                $this->logger?->info('[inactive-account] anonymisé', ['idP' => (int) $idPersonne]);
            }
        }

        return count($ids);
    }

    /**
     * Les comptes qui franchiraient le seuil, pour l'écran d'administration : ceux qui attendent
     * leur avertissement, puis ceux qui attendent leur anonymisation.
     *
     * @return array<int, array{idPersonne: int, pseudo: string, email: string, groupe: int, derniere_activite: ?string, inactivity_notified_at: ?string}>
     */
    public function pending(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT idPersonne, pseudo, email, groupe,
                    COALESCE(last_login, date_derniere_modif, dateAjout) AS derniere_activite,
                    inactivity_notified_at
             FROM personne
             WHERE " . $this->inactiveCondition() . "
             ORDER BY inactivity_notified_at IS NULL, derniere_activite"
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Traitement probabiliste (~1 appel sur RUN_PROBABILITY), exécuté dans le shutdown donc
     * invisible pour le visiteur. Aucune exception ne doit remonter.
     *
     * La purge avant l'avertissement : elle ne coûte que du SQL, et un échec d'envoi ne doit pas
     * empêcher les anonymisations dues.
     *
     * Le Mailing arrive par une fabrique et non construit : le tirage échoue 999 fois sur 1000,
     * et charger PHPMailer à chaque page vue pour ne presque jamais s'en servir serait du gâchis.
     *
     * @param callable(): Mailing $mailerFactory
     */
    public function maybeRun(callable $mailerFactory, TemplateEngine $templates): void
    {
        if (random_int(1, self::RUN_PROBABILITY) !== 1) {
            return;
        }

        try {
            $this->purge();
        } catch (\Throwable $e) {
            $this->logger?->warning('InactiveAccountRetention::purge a échoué : ' . $e->getMessage());
        }

        try {
            $this->notify($mailerFactory(), $templates);
        } catch (\Throwable $e) {
            $this->logger?->warning('InactiveAccountRetention::notify a échoué : ' . $e->getMessage());
        }
    }
}
