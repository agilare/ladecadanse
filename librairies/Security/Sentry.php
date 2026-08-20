<?php

namespace Ladecadanse\Security;

use Ladecadanse\UserLevel;
use PDO;
use Psr\Log\LoggerInterface;

/**
 * Lance la session et vérifie le login du visiteur.
 *
 * Toutes les requêtes passent par PDO et des requêtes préparées (#122) : la classe
 * n'échappe plus elle-même les valeurs, et le pilote se charge du typage.
 */
class Sentry
{
    /**
     * Colonnes du compte lues à chaque vérification. Listées une fois : les trois
     * points d'entrée (session, formulaire, cookie) doivent remplir le même tableau,
     * puisque tous trois débouchent sur initSession().
     */
    private const USER_COLUMNS = 'idPersonne, pseudo, mot_de_passe, cookie, groupe, region, email, gds';

    /**
     * Tableau contenant idPersonne, pseudo, mot_de_passe, groupe, email
     * Rempli dès qu'une personne se logue
     *
     * @var array<string, mixed>
     */
    private array $userdata;

    public function __construct(private readonly PDO $pdo, private readonly LoggerInterface $logger)
    {
        if (!isset($_SESSION['logged']))
        {
            $this->sessionDefaults();
        }

        if ($_SESSION['logged'])
        {
            /*
             * Une session qui ne se vérifie plus (compte désactivé ou supprimé, mot de
             * passe changé ailleurs) doit être vidée : sans quoi elle conserve ses
             * variables, et seul Authorization::checkGroup() barre encore la route.
             */
            if (!$this->checkSession())
            {
                $this->clearUserSession();
            }
        }
        else if (!empty($_COOKIE['ladecadanse_remember']))
        {
            $this->checkRemembered($_COOKIE['ladecadanse_remember']);
        }
    }

    /**
     * Empreinte du mot de passe stockée en session.
     *
     * La session ne garde pas le hash lui-même : une empreinte suffit à détecter qu'il a
     * changé, donc à invalider les sessions ouvertes ailleurs, sans exposer de quoi tenter
     * une attaque hors ligne si le stockage des sessions venait à fuir.
     *
     * Publique : une page qui change le mot de passe de la personne connectée doit
     * rafraîchir l'empreinte, sans quoi elle se déconnecterait elle-même (user-edit.php).
     */
    public static function passFingerprint(string $motDePasse): string
    {
        return hash('sha256', $motDePasse);
    }

    /*
     * Si l'utilisateur est déjà loggé -> si la session est déjà remplie avec les valeurs de login
     */
    public function checkSession(): bool
    {
        if (empty($_SESSION['SidPersonne']) || empty($_SESSION['pass_fingerprint']))
        {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "SELECT " . self::USER_COLUMNS . "
             FROM personne
             WHERE idPersonne = :idP AND statut = 'actif'"
        );
        $stmt->execute([':idP' => (int) $_SESSION['SidPersonne']]);
        $userdata = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userdata === false)
        {
            unset($this->userdata);

            return false;
        }

        if (!hash_equals($_SESSION['pass_fingerprint'], self::passFingerprint($userdata['mot_de_passe'])))
        {
            unset($this->userdata);

            return false;
        }

        $this->userdata = $userdata;

        /*
         * Rafraîchit groupe, e-mail, région et affiliation : un changement fait par
         * un administrateur prend effet à la requête suivante.
         */
        $this->initSession(false, false);

        return true;
    }

    /**
     * Retire l'identité du visiteur de la session, sans toucher à ses préférences
     * (région, tri de l'agenda...) ni poser le cookie de suivi propre à une
     * déconnexion volontaire, que gère logout().
     */
    private function clearUserSession(): void
    {
        unset(
            $this->userdata,
            $_SESSION['SidPersonne'],
            $_SESSION['user'],
            $_SESSION['pass_fingerprint'],
            $_SESSION['Sgroupe'],
            $_SESSION['Semail'],
            $_SESSION['Sregion'],
            $_SESSION['Saffiliation_lieu']
        );

        // évite de conserver un identifiant de session périmé
        if (session_status() === PHP_SESSION_ACTIVE)
        {
            session_regenerate_id(true);
        }

        $this->sessionDefaults();
    }

    /**
     * Vérifie un couple identifiant / mot de passe saisi dans un formulaire, et ouvre
     * la session si tout concorde.
     *
     * @param string $user         Nom de membre ou e-mail à évaluer
     * @param string $pass         Mot de passe à évaluer
     * @param int    $group        (1 à 12) Niveau de groupe le moins privilégié accepté
     * @param string $goodRedirect Lien en cas de login réussi
     * @param string $badRedirect  Lien en cas de login raté
     * @param bool   $memoriser    Poser le cookie de connexion persistante
     */
    public function checkLogin(string $user = '', string $pass = '', int $group = UserLevel::MEMBER, string $goodRedirect = '', string $badRedirect = '', bool $memoriser = false): bool
    {
        // Bornes de sûreté : la validation fine des saisies revient au formulaire, ici on
        // écarte seulement ce qui n'a aucune chance de correspondre à un compte. La borne
        // haute du mot de passe doit rester >= à celle des formulaires qui en fixent un
        // (user/register.php, user-reset2.php : 100), sinon on crée des comptes dont le
        // mot de passe est refusé ici avant même d'être vérifié.
        if (mb_strlen($user) < 2 || mb_strlen($user) > 80 || mb_strlen($pass) < 4 || mb_strlen($pass) > 100)
        {
            unset($this->userdata);

            if ($badRedirect)
            {
                header("Location: " . $badRedirect);
            }

            return false;
        }

        /*
         * Une adresse e-mail peut être partagée par plusieurs comptes : elle n'identifie
         * alors personne, et c'est l'identifiant qu'il faut saisir.
         */
        $estEmail = filter_var($user, FILTER_VALIDATE_EMAIL) !== false;

        // deux marqueurs pour la même valeur : les requêtes préparées natives (PDO est
        // configuré sans émulation) refusent qu'un marqueur nommé serve deux fois
        $stmt = $this->pdo->prepare(
            "SELECT " . self::USER_COLUMNS . "
             FROM personne
             WHERE " . ($estEmail ? "(pseudo = :pseudo OR email = :email)" : "pseudo = :pseudo") . "
               AND groupe <= :groupe AND statut = 'actif'"
        );

        $params = [':pseudo' => $user, ':groupe' => $group];

        if ($estEmail)
        {
            $params[':email'] = $user;
        }

        $stmt->execute($params);
        $comptes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($estEmail && count($comptes) > 1)
        {
            $this->logger->warning('[Sentry] login failed, ambiguous email', ['email' => $user]);
            unset($this->userdata);

            if ($badRedirect)
            {
                header("Location: " . preg_replace('/msg=[^&]*/', 'msg=email_ambigu', $badRedirect));
            }

            return false;
        }

        // pseudo est unique : plus d'une ligne ne peut venir que d'un e-mail, traité ci-dessus
        if (count($comptes) !== 1)
        {
            $this->logger->warning('[Sentry] login failed, user not found', ['user' => $user]);
            unset($this->userdata);

            if ($badRedirect)
            {
                header("Location: " . $badRedirect);
            }

            return false;
        }

        $this->userdata = $comptes[0];

        $isPassCorrectOldMethod = hash_equals((string) $this->userdata['mot_de_passe'], sha1($this->userdata['gds'] . sha1($pass)));
        $isPassCorrectNewMethod = password_verify($pass, (string) $this->userdata['mot_de_passe']);

        if (!$isPassCorrectOldMethod && !$isPassCorrectNewMethod)
        {
            $this->logger->warning('[Sentry] login failed, wrong password', ['user' => $this->userdata['pseudo']]);
            unset($this->userdata);

            if ($badRedirect)
            {
                header("Location: " . $badRedirect);
            }

            return false;
        }

        // le stockage historique (sha1 salé) et les hash devenus trop faibles sont remis à
        // niveau ici, à la seule occasion où le mot de passe est connu en clair
        if ($isPassCorrectOldMethod || password_needs_rehash((string) $this->userdata['mot_de_passe'], PASSWORD_DEFAULT))
        {
            $this->userdata['mot_de_passe'] = password_hash($pass, PASSWORD_DEFAULT);

            $stmt = $this->pdo->prepare(
                "UPDATE personne SET last_login = NOW(), mot_de_passe = :hash, gds = ''
                 WHERE idPersonne = :idP"
            );
            $stmt->execute([':hash' => $this->userdata['mot_de_passe'], ':idP' => (int) $this->userdata['idPersonne']]);
        }
        else
        {
            $stmt = $this->pdo->prepare("UPDATE personne SET last_login = NOW() WHERE idPersonne = :idP");
            $stmt->execute([':idP' => (int) $this->userdata['idPersonne']]);
        }

        session_regenerate_id(true); // to avoid session fixation attack
        $this->initSession($memoriser);
        $this->logger->info('[Sentry] login', ['user' => $_SESSION["user"]]);

        // exception pour admin ; chemin absolu, sinon la destination dépend du dossier
        // de la page de connexion (« user/admin/index.php » depuis user/login.php)
        if ((int) $this->userdata['groupe'] === UserLevel::SUPERADMIN)
        {
            $goodRedirect = "/admin/index.php";
        }

        if ($goodRedirect)
        {
            header("Location: " . $goodRedirect);
            exit();
        }

        return true;
    }

    public function checkRemembered(string $cookie): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT " . self::USER_COLUMNS . "
             FROM personne
             WHERE cookie = :cookie AND statut = 'actif'"
        );
        $stmt->execute([':cookie' => $cookie]);
        $userdata = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userdata === false)
        {
            unset($this->userdata);

            return false;
        }

        $this->userdata = $userdata;
        session_regenerate_id(true); // to avoid session fixation attack
        $this->initSession(true, true);
        $this->logger->info('[Sentry] remembered access', ['user' => $_SESSION["user"], 'email' => $_SESSION['Semail']]);

        return true;
    }

    /**
     * Remplit les variables de session à partir de $this->userdata.
     *
     * @param bool $memoriser pose le cookie « rester connecté-e »
     * @param bool $init      renouvelle le jeton opaque stocké en base
     */
    public function initSession(bool $memoriser, bool $init = true): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT idAffiliation FROM affiliation WHERE idPersonne = :idP AND genre = 'lieu'"
        );
        $stmt->execute([':idP' => (int) $this->userdata['idPersonne']]);
        $idAffiliation = $stmt->fetchColumn();

        //remplissage des variables de session
        $_SESSION["SidPersonne"] = $this->userdata["idPersonne"];
        $_SESSION["user"] = $this->userdata["pseudo"];
        $_SESSION['pass_fingerprint'] = self::passFingerprint($this->userdata['mot_de_passe']);
        $_SESSION["cookie"] = $this->userdata["cookie"];
        $_SESSION["logged"] = true;

        $_SESSION["Sgroupe"] = $this->userdata["groupe"];
        $_SESSION['Semail'] = $this->userdata['email'];
        $_SESSION['Sregion'] = $this->userdata['region'];
        $_SESSION['Saffiliation_lieu'] = $idAffiliation === false ? 0 : $idAffiliation;

        // un simple rafraîchissement de session ne renouvelle rien : inutile de tirer un jeton
        if (!$memoriser && !$init)
        {
            return;
        }

        $cookie = $this->token();

        if ($memoriser)
        {
            $this->updateCookie($cookie, true);
        }

        if ($init)
        {
            $stmt = $this->pdo->prepare("UPDATE personne SET cookie = :cookie WHERE idPersonne = :idP");
            $stmt->execute([':cookie' => $cookie, ':idP' => (int) $this->userdata['idPersonne']]);
        }
    }

    public function updateCookie(string $cookie, bool $sauvegarder): void
    {
        $_SESSION['cookie'] = $cookie;

        if ($sauvegarder)
        {
            $cookieOptions = [
                'expires' => strtotime('+15 days'),
                'path' => '/',
                //'domain' => '.example.com', // leading dot for compatibility or use subdomain
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax'
            ];

            setcookie('ladecadanse_remember', $cookie, $cookieOptions);
        }
    }

    public function sessionDefaults(): void
    {
        $_SESSION['logged'] = false;
        $_SESSION["memoriser"] = false;
        $_SESSION["cookie"] = 0;
        $_SESSION["groupe"] = 20;
    }

    /**
     * Jeton opaque de 32 caractères hexadécimaux, à la mesure de personne.cookie
     */
    public function token(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Détruit les données d'utilisateur de l'objet et la session
     */
    public function logout(): void
    {
        unset($this->userdata);
        session_regenerate_id(true); // to avoid session fixation attack
        session_destroy();
        unset($_SESSION);

        if (isset($_COOKIE['ladecadanse_remember']))
        {
            unset($_COOKIE['ladecadanse_remember']);

            setcookie('ladecadanse_remember', '', ['expires' => 1, 'secure' => true, 'httponly' => true]);
        }

        // used (only) to inform in _header.inc.php, one time, to Matomo that the users logged out
        setcookie('just_logged_out', '1', [
            'expires' => time() + 3, // durée très courte, en secondes
            'path' => '/',
            'secure' => true, // true si HTTPS
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}
