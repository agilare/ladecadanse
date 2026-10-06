<?php

namespace Ladecadanse\Security;

use PDO;

/**
 * Jetons « Rester connecté-e », un par appareil (table `remember_token`).
 *
 * Le cookie porte `selector:validator`. Le selector retrouve la ligne ; le validator n'est
 * stocké que sous forme d'empreinte et se compare par hash_equals(). Lire la table ne suffit
 * donc pas à se faire passer pour quelqu'un, et la comparaison ne se joue pas dans un WHERE,
 * dont la durée pourrait trahir le secret.
 */
class RememberTokens
{
    /** Durée de vie d'un jeton, prolongée à chaque usage. */
    public const LIFETIME_DAYS = 30;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Crée le jeton d'un nouvel appareil et renvoie la valeur du cookie.
     */
    public function issue(int $userId): string
    {
        // le ménage des jetons expirés suit les connexions, assez rares pour qu'il ne coûte rien
        $this->pdo->exec("DELETE FROM remember_token WHERE expires < NOW()");

        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));

        $stmt = $this->pdo->prepare(
            "INSERT INTO remember_token (user_id, selector, validator_hash, expires, created)
             VALUES (:userId, :selector, :hash, NOW() + INTERVAL " . self::LIFETIME_DAYS . " DAY, NOW())"
        );
        $stmt->execute([':userId' => $userId, ':selector' => $selector, ':hash' => hash('sha256', $validator)]);

        return $selector . ':' . $validator;
    }

    /**
     * Valide le cookie et, s'il est bon, renouvelle son validator.
     *
     * Un validator qui ne correspond pas n'efface pas la ligne : deux requêtes parties en même
     * temps avec le même cookie font échouer la seconde, et l'appareil légitime serait déconnecté.
     *
     * @return array{user_id: int, cookie: string}|null le compte, et la nouvelle valeur du cookie
     */
    public function consume(string $cookie): ?array
    {
        $parts = self::parse($cookie);

        if ($parts === null)
        {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, user_id, validator_hash, expires > NOW() AS valid FROM remember_token WHERE selector = :selector"
        );
        $stmt->execute([':selector' => $parts['selector']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false || !hash_equals((string) $row['validator_hash'], hash('sha256', $parts['validator'])))
        {
            return null;
        }

        if (!(bool) $row['valid'])
        {
            $stmt = $this->pdo->prepare("DELETE FROM remember_token WHERE id = :id");
            $stmt->execute([':id' => (int) $row['id']]);

            return null;
        }

        $validator = bin2hex(random_bytes(32));

        $stmt = $this->pdo->prepare(
            "UPDATE remember_token
             SET validator_hash = :hash, expires = NOW() + INTERVAL " . self::LIFETIME_DAYS . " DAY, last_used = NOW()
             WHERE id = :id"
        );
        $stmt->execute([':hash' => hash('sha256', $validator), ':id' => (int) $row['id']]);

        return ['user_id' => (int) $row['user_id'], 'cookie' => $parts['selector'] . ':' . $validator];
    }

    /**
     * Oublie l'appareil qui porte ce cookie, et lui seul.
     */
    public function revoke(string $cookie): void
    {
        $parts = self::parse($cookie);

        if ($parts === null)
        {
            return;
        }

        $stmt = $this->pdo->prepare("DELETE FROM remember_token WHERE selector = :selector");
        $stmt->execute([':selector' => $parts['selector']]);
    }

    /**
     * Nombre d'appareils sur lesquels le compte reste connecté.
     */
    public function countActive(int $userId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM remember_token WHERE user_id = :userId AND expires > NOW()");
        $stmt->execute([':userId' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Oublie tous les appareils du compte.
     */
    public function revokeAll(int $userId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM remember_token WHERE user_id = :userId");
        $stmt->execute([':userId' => $userId]);
    }

    /**
     * @return array{selector: string, validator: string}|null null si le cookie n'a pas la forme attendue
     */
    private static function parse(string $cookie): ?array
    {
        // l'ancien format, 32 caractères hexadécimaux sans deux-points, tombe ici
        if (!preg_match('/^([0-9a-f]{24}):([0-9a-f]{64})$/', $cookie, $m))
        {
            return null;
        }

        return ['selector' => $m[1], 'validator' => $m[2]];
    }
}
