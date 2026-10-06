<?php

namespace Ladecadanse\Security;

use Ladecadanse\UserLevel;
use Ladecadanse\Utils\LogSafe;
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
    private const USER_COLUMNS = 'idPersonne, pseudo, mot_de_passe, groupe, region, email, gds, session_epoch';

    private const REMEMBER_COOKIE = 'ladecadanse_remember';

    /**
     * Tableau contenant idPersonne, pseudo, mot_de_passe, groupe, email
     * Rempli dès qu'une personne se logue
     *
     * @var array<string, mixed>
     */
    private array $userdata;

    private readonly RememberTokens $rememberTokens;

    public function __construct(private readonly PDO $pdo, private readonly LoggerInterface $logger)
    {
        $this->rememberTokens = new RememberTokens($pdo);

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
        else if (!empty($_COOKIE[self::REMEMBER_COOKIE]) && is_string($_COOKIE[self::REMEMBER_COOKIE]))
        {
            $this->checkRemembered($_COOKIE[self::REMEMBER_COOKIE]);
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

        // « Se déconnecter des autres appareils » a incrémenté le compteur depuis l'ouverture
        // de cette session ; une session antérieure à la colonne n'a pas la clé, lue comme 0
        if ((int) ($_SESSION['session_epoch'] ?? 0) !== (int) $userdata['session_epoch'])
        {
            unset($this->userdata);

            return false;
        }

        $this->userdata = $userdata;

        /*
         * Rafraîchit groupe, e-mail, région et affiliation : un changement fait par
         * un administrateur prend effet à la requête suivante.
         */
        $this->initSession();

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
            $_SESSION['session_epoch'],
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
        // haute de l'identifiant suit la colonne email (100) et non pseudo (80) : l'adresse
        // est le seul identifiant d'un compte sans pseudo, et une adresse de plus de 80
        // caractères rendait ce compte inaccessible. Celle du mot de passe doit rester >= à
        // celle des formulaires qui en fixent un (user/register.php, user/reset2.php : 100),
        // sinon on crée des comptes dont le mot de passe est refusé ici avant d'être vérifié.
        if (mb_strlen($user) < 2 || mb_strlen($user) > 100 || mb_strlen($pass) < 4 || mb_strlen($pass) > 100)
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
            // Le nombre de comptes dit l'ampleur de l'ambiguïté, le domaine dit où
            // chercher : ensemble ils suffisent au diagnostic sans porter l'adresse.
            $this->logger->notice('[Sentry] login failed, ambiguous email', ['domaine' => LogSafe::email($user), 'comptes' => count($comptes)]);
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
            // La saisie peut être un nom d'utilisateur comme une adresse : LogSafe ne
            // touche que la seconde. Garder la saisie a une valeur de sécurité — repérer
            // un bourrage d'identifiants — que le seul domaine conserve.
            $this->logger->notice('[Sentry] login failed, user not found', ['user' => LogSafe::email($user)]);
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
            // le numéro de compte accompagne le nom d'utilisateur, qui est facultatif : sans
            // lui, une ligne de journal ne dit plus de qui elle parle
            $this->logger->notice('[Sentry] login failed, wrong password', ['user' => $this->userdata['pseudo'], 'idP' => (int) $this->userdata['idPersonne']]);
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
                "UPDATE personne SET last_login = NOW(), inactivity_notified_at = NULL, mot_de_passe = :hash, gds = ''
                 WHERE idPersonne = :idP"
            );
            $stmt->execute([':hash' => $this->userdata['mot_de_passe'], ':idP' => (int) $this->userdata['idPersonne']]);
        }
        else
        {
            // inactivity_notified_at repart à NULL : qui revient après un avertissement
            // d'inactivité retrouve son délai complet (voir InactiveAccountRetention)
            $stmt = $this->pdo->prepare("UPDATE personne SET last_login = NOW(), inactivity_notified_at = NULL WHERE idPersonne = :idP");
            $stmt->execute([':idP' => (int) $this->userdata['idPersonne']]);
        }

        session_regenerate_id(true); // to avoid session fixation attack
        $this->initSession();

        // un nouveau jeton par appareil : ceux des autres appareils restent valides
        if ($memoriser)
        {
            $this->setRememberCookie($this->rememberTokens->issue((int) $this->userdata['idPersonne']));
        }

        $this->logger->info('[Sentry] login', ['user' => $_SESSION["user"], 'idP' => (int) $_SESSION['SidPersonne']]);

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
        $token = $this->rememberTokens->consume($cookie);
        $userdata = false;

        if ($token !== null)
        {
            $stmt = $this->pdo->prepare(
                "SELECT " . self::USER_COLUMNS . "
                 FROM personne
                 WHERE idPersonne = :idP AND statut = 'actif'"
            );
            $stmt->execute([':idP' => $token['user_id']]);
            $userdata = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($token === null || $userdata === false)
        {
            unset($this->userdata);

            if ($token !== null)
            {
                $this->rememberTokens->revoke($token['cookie']);
            }

            // un cookie qui ne mène plus à rien coûterait sinon une requête à chaque page
            $this->forgetRememberCookie();

            return false;
        }

        $this->userdata = $userdata;

        /*
         * Un retour par le cookie est une connexion : sans cette mise à jour, `last_login`
         * restait figé à la dernière saisie du mot de passe. Le cookie vivant quinze jours,
         * quelqu'un qui repasse à ce rythme sans jamais se déconnecter paraissait inactif
         * depuis des années — et la durée de conservation des comptes
         * (Ladecadanse\InactiveAccountRetention) l'aurait anonymisé alors qu'il contribue.
         */
        $stmt = $this->pdo->prepare("UPDATE personne SET last_login = NOW(), inactivity_notified_at = NULL WHERE idPersonne = :idP");
        $stmt->execute([':idP' => (int) $userdata['idPersonne']]);

        session_regenerate_id(true); // to avoid session fixation attack
        $this->initSession();
        $this->setRememberCookie($token['cookie']);
        // l'adresse n'apportait rien que l'identifiant ne dise déjà
        $this->logger->info('[Sentry] remembered access', ['user' => $_SESSION["user"], 'idP' => (int) $_SESSION['SidPersonne']]);

        return true;
    }

    /**
     * Remplit les variables de session à partir de $this->userdata.
     */
    public function initSession(): void
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
        $_SESSION['session_epoch'] = (int) $this->userdata['session_epoch'];
        $_SESSION["logged"] = true;

        $_SESSION["Sgroupe"] = $this->userdata["groupe"];
        $_SESSION['Semail'] = $this->userdata['email'];
        $_SESSION['Sregion'] = $this->userdata['region'];
        $_SESSION['Saffiliation_lieu'] = $idAffiliation === false ? 0 : $idAffiliation;
    }

    /**
     * Oublie tous les appareils mémorisés d'un compte dont le mot de passe vient de changer.
     *
     * Qui change son mot de passe parce qu'il le croit compromis doit pouvoir compter que les
     * autres appareils sont déconnectés : la session ouverte ailleurs l'est déjà par
     * l'empreinte (checkSession()), le cookie ne l'était pas. Si c'est la personne connectée qui
     * change le sien depuis un appareil mémorisé, celui-ci reçoit un jeton neuf et le reste.
     */
    public function revokeRememberedDevices(int $idPersonne): void
    {
        $this->rememberTokens->revokeAll($idPersonne);

        if (!empty($_SESSION['logged']) && (int) ($_SESSION['SidPersonne'] ?? 0) === $idPersonne && !empty($_COOKIE[self::REMEMBER_COOKIE]))
        {
            $this->setRememberCookie($this->rememberTokens->issue($idPersonne));
        }
    }

    /**
     * Ferme les sessions ouvertes du compte et oublie ses appareils mémorisés.
     *
     * Les jetons seuls ne suffisent pas : une session déjà ouverte sur un autre appareil
     * survivrait jusqu'à une heure d'inactivité, et indéfiniment tant qu'on y navigue. Le
     * compteur `session_epoch` la ferme à sa requête suivante (checkSession()). Si c'est la
     * personne connectée qui le demande, sa propre session et son appareil restent ouverts.
     */
    public function logoutOtherDevices(int $idPersonne): void
    {
        $stmt = $this->pdo->prepare("UPDATE personne SET session_epoch = session_epoch + 1 WHERE idPersonne = :idP");
        $stmt->execute([':idP' => $idPersonne]);

        if (!empty($_SESSION['logged']) && (int) ($_SESSION['SidPersonne'] ?? 0) === $idPersonne)
        {
            $stmt = $this->pdo->prepare("SELECT session_epoch FROM personne WHERE idPersonne = :idP");
            $stmt->execute([':idP' => $idPersonne]);
            $_SESSION['session_epoch'] = (int) $stmt->fetchColumn();
        }

        $this->revokeRememberedDevices($idPersonne);
    }

    private function setRememberCookie(string $cookie): void
    {
        setcookie(self::REMEMBER_COOKIE, $cookie, [
            'expires' => strtotime('+' . RememberTokens::LIFETIME_DAYS . ' days'),
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    private function forgetRememberCookie(): void
    {
        unset($_COOKIE[self::REMEMBER_COOKIE]);

        /*
         * Le path doit reprendre celui de setRememberCookie() : un cookie ne s'efface que par
         * un Set-Cookie de mêmes nom, domaine et path. Sans path, PHP prend le
         * répertoire du script appelant, et le navigateur garde intact le cookie posé
         * sur '/'. L'oubli est resté invisible tant que la page de déconnexion était à
         * la racine du site.
         */
        setcookie(self::REMEMBER_COOKIE, '', [
            'expires' => 1,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    public function sessionDefaults(): void
    {
        $_SESSION['logged'] = false;
        $_SESSION["memoriser"] = false;
        $_SESSION["groupe"] = 20;
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

        if (isset($_COOKIE[self::REMEMBER_COOKIE]))
        {
            // cet appareil seulement : les autres restent connectés
            if (is_string($_COOKIE[self::REMEMBER_COOKIE]))
            {
                $this->rememberTokens->revoke($_COOKIE[self::REMEMBER_COOKIE]);
            }

            $this->forgetRememberCookie();
        }
    }
}
