<?php

namespace Ladecadanse\Security;

use Ladecadanse\UserLevel;
use Ladecadanse\Utils\DbConnector;
use Ladecadanse\Utils\Validateur;

/**
 * Lance la session et vérifie le login du visiteur
 */
class Sentry
{

    /**
     * Tableau contenant idPersonne, pseudo, mot_de_passe, groupe, email
     * Rempli dès qu'une personne se logue
     */
    private array $userdata;

    function __construct(private readonly DbConnector $connector)
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
    function checkSession(): bool
    {
        if (empty($_SESSION['SidPersonne']) || empty($_SESSION['pass_fingerprint']))
        {
            return false;
        }

        $sql_user = "
		SELECT idPersonne, pseudo, mot_de_passe, cookie, groupe, region, email, gds
		FROM personne
		WHERE idPersonne = " . (int) $_SESSION['SidPersonne'] . " AND statut='actif'";

        $getUser = $this->connector->query($sql_user);

        if ($this->connector->getNumRows($getUser) != 1)
        {
            unset($this->userdata);

            return false;
        }

        $userdata = $this->connector->fetchArray($getUser);

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
     *
     * @access public
     * @param string $user Nom de membre à évaluer en cas de login
     * @param string $pass Mot de passe de membre à évaluer en cas de login
     * @param int $group (1 à 12) No de groupe auquel est accessible une page
     * @param string $goodRedirect Lien en cas de login réussi
     * @param string $badRedirect Lien en cas de login raté
     * @return boolean True si les infos entrée en login OU si les données de session
     * 				se vérifient dans la base
     */
    function checkLogin($user = '', $pass = '', $group = UserLevel::MEMBER, $goodRedirect = '', $badRedirect = '', $memoriser = false): bool
    {
        global $logger;

        $valide = new Validateur();

        $erreurs = [];
        if ($memoriser)
        {
            $memoriser = true;
        }
        /*
         * Validation des données de la session : pseudo, mot de passe, groupe
         */
        if (!$valide->validerLongueurTexte('user', $user, 2, 80))
        {
            $erreurs['user'] = $valide->lastError();
        }

        if (!$valide->validerLongueurTexte('pass', $pass, 4, 50))
        {
            $erreurs['pass'] = $valide->lastError();
        }

        if (!$valide->validerNombre('group', $group))
        {
            $erreurs['group'] = $valide->lastError();
        }

        if (count($erreurs) === 0)
        {
            $isEmail = filter_var($user, FILTER_VALIDATE_EMAIL) !== false;

            if ($isEmail)
            {
                $safeUser = $this->connector->sanitize($user);
                $sql = "
				SELECT idPersonne, pseudo, mot_de_passe, cookie, groupe, region, email, gds
				FROM personne
				WHERE (pseudo = '$safeUser' OR email = '$safeUser') AND groupe <= " . $group . " AND statut='actif'";

                $getUser = $this->connector->query($sql);

                if ($this->connector->getNumRows($getUser) > 1)
                {
                    $logger->warning('[Sentry] login failed, ambiguous email', ['email' => $user]);
                    unset($this->userdata);
                    if ($badRedirect)
                    {
                        $redirectAmbigu = preg_replace('/msg=[^&]*/', 'msg=email_ambigu', $badRedirect);
                        header("Location: " . $redirectAmbigu);
                    }
                    return false;
                }
            }
            else
            {
                $sql = "
				SELECT idPersonne, pseudo, mot_de_passe, cookie, groupe, region, email, gds
				FROM personne
				WHERE pseudo = '" . $this->connector->sanitize($user) . "' AND groupe <= " . $group . " AND statut='actif'";

                $getUser = $this->connector->query($sql);
            }

            if ($this->connector->getNumRows($getUser) == 1)
            {
                $this->userdata = $this->connector->fetchArray($getUser);

                $isPassCorrectOldMethod = sha1($this->userdata['gds'] . sha1($pass)) === $this->userdata['mot_de_passe'];
                $isPassCorrectNewMethod = password_verify($pass, $this->userdata['mot_de_passe']);

                // pass matches one of the 2 methods
                if ($isPassCorrectOldMethod || $isPassCorrectNewMethod)
                {
                    $sql_update_pass = '';
                    if ($isPassCorrectOldMethod || password_needs_rehash($this->userdata['mot_de_passe'], PASSWORD_DEFAULT)) //
                    {
                        $newPassHash = password_hash($pass, PASSWORD_DEFAULT);
                        $sql_update_pass =  ", mot_de_passe = '". $newPassHash. "',  gds=''";
                        $this->userdata['mot_de_passe'] = $newPassHash;
                    }

                    $this->connector->query("UPDATE personne SET last_login = now() $sql_update_pass WHERE idPersonne=".(int)$this->userdata['idPersonne']);
                    session_regenerate_id(true); // to avoid session fixation attack
                    $this->initSession($memoriser);
                    $logger->info('[Sentry] login', ['user' => $_SESSION["user"]]);


                    // exception pour admin
                    if ($this->userdata['groupe'] == UserLevel::SUPERADMIN)
                    {
                        $goodRedirect = "admin/index.php";
                    }

                    if ($goodRedirect)
                    {
                        header("Location: " . $goodRedirect);
                        exit();
                    }

                    return true;
                }
                else
                {
                    $logger->warning('[Sentry] login failed, wrong password', ['user' => $this->userdata['pseudo']]);

                    unset($this->userdata);

                    if ($badRedirect)
                    {
                        header("Location: " . $badRedirect);
                    }
                    return false;
                } // if pass
            }
            else
            {
                $logger->warning('[Sentry] login failed, user not found', ['user' => $user]);

                unset($this->userdata);

                if ($badRedirect)
                {
                    header("Location: " . $badRedirect);
                }

                return false;
            } //if num rows
        }
        else
        {

            unset($this->userdata);
            //Redirection vers $badRedirect s'il existe
            if ($badRedirect)
            {
                header("Location: " . $badRedirect);
            }

            return false;
        } //if erreurs
    }

    function checkRemembered(string $cookie): bool
    {
        global $logger;

        $sql_getUser = "SELECT idPersonne, pseudo, mot_de_passe, cookie, groupe, region, email, gds
						FROM personne
						WHERE cookie='" . $this->connector->sanitize($cookie) . "'
						 AND statut='actif'";

        $getUser = $this->connector->query($sql_getUser);

        if ($this->connector->getNumRows($getUser) > 0)
        {

            $this->userdata = $this->connector->fetchArray($getUser);
            session_regenerate_id(true); // to avoid session fixation attack
            $this->initSession(true, true);
            $logger->info('[Sentry] remembered access', ['user' => $_SESSION["user"], 'email' => $_SESSION['Semail']]);
            return true;
        }
        else
        {
            unset($this->userdata);

            return false;
        } // if pass
    }

//function

    /**
     * Remplit les variables de session à partir de $this->userdata.
     *
     * @param bool $memoriser pose le cookie « se souvenir de moi »
     * @param bool $init      renouvelle le jeton opaque stocké en base
     */
    function initSession(bool $memoriser, bool $init = true): void
    {
        $req_affiliation = $this->connector->query("
		SELECT idAffiliation
		FROM affiliation
		WHERE idPersonne='" . (int) $this->userdata["idPersonne"] . "' AND genre='lieu'");

        $tab_affiliation = $this->connector->fetchArray($req_affiliation);

        //remplissage des variables de session
        $_SESSION["SidPersonne"] = $this->userdata["idPersonne"];
        $_SESSION["user"] = $this->userdata["pseudo"];
        $_SESSION['pass_fingerprint'] = self::passFingerprint($this->userdata['mot_de_passe']);
        $_SESSION["cookie"] = $this->userdata["cookie"];
        $_SESSION["logged"] = true;

        $_SESSION["Sgroupe"] = $this->userdata["groupe"];
        $_SESSION['Semail'] = $this->userdata['email'];
        $_SESSION['Sregion'] = $this->userdata['region'];
        $_SESSION['Saffiliation_lieu'] = $tab_affiliation['idAffiliation'] ?? 0;

        /* 	if ($_SESSION["user"] == 'agilare')
          printr($_SESSION);exit; */


        $cookie = $this->token();

        if ($memoriser)
        {
            $this->updateCookie($cookie, true);
        }

        /* 	if ($_SESSION["user"] == 'agilare')
          printr($_SESSION);exit; */

        if ($init)
        {
            $sql = "UPDATE personne
			SET cookie='" . $this->connector->sanitize($cookie) . "' WHERE idPersonne=" . (int)$this->userdata['idPersonne'];
            //echo $sql;
            $this->connector->query($sql);
        }
    }

    function updateCookie(string $cookie, bool $sauvegarder): void
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

            setcookie('ladecadanse_remember', (string) $cookie, $cookieOptions);
        }

        /* 	echo "Veuillez patienter...";
          exit; */
    }

    function sessionDefaults(): void
    {
        $_SESSION['logged'] = false;
        $_SESSION["memoriser"] = false;
        $_SESSION["cookie"] = 0;
        $_SESSION["groupe"] = 20;
    }

    /**
     * Jeton opaque de 32 caractères hexadécimaux, à la mesure de personne.cookie
     */
    function token(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Détruit les données d'utilisateur de l'objet, la session et stoppe le script
     */
    function logout(): void
    {
        unset($this->userdata);
        session_regenerate_id(true); // to avoid session fixation attack
        session_destroy();
        unset($_SESSION);
        if (isset($_COOKIE['ladecadanse_remember']))
        {
            //setcookie('ladecadanse[username]', '', time() - 3600);
            //setcookie('ladecadanse[cookie]', '', time() - 3600);
            //setcookie('ladecadanse_remember', '', time() - 3600); // semble ne pas fonctionner
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
