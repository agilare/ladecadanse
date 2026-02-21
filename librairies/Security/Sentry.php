<?php

namespace Ladecadanse\Security;

use Ladecadanse\UserLevel;
use Ladecadanse\Utils\SystemComponent;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\Logger;

/**
 * Lance la session et vérifie le login du visiteur
 */
class Sentry extends SystemComponent
{

    /**
     * Tableau contenant idPersonne, pseudo, mot_de_passe, groupe, email
     * Rempli dès qu'une personne se logue
     */
    private array $userdata;

    function __construct()
    {
        if (!isset($_SESSION['logged']))
        {
            $this->sessionDefaults();
        }
        else if ($_SESSION['logged'])
        {
            $this->checkSession();
        }
        else if (!empty($_COOKIE['ladecadanse_remember']))
        {
            $this->checkRemembered($_COOKIE['ladecadanse_remember']);
        }
    }

    /*
     * Si l'utilisateur est déjà loggé -> si la session est déjà remplie avec les valeurs de login
     */
    function checkSession(): bool
    {

        global $connector;

        $sql_user = "
		SELECT idPersonne, pseudo, mot_de_passe, cookie, groupe, region, email, gds
		FROM personne WHERE
		pseudo = '" . $connector->sanitize($_SESSION['user']) . "'
				AND groupe = '" . $connector->sanitize($_SESSION['Sgroupe']) . "'
				AND mot_de_passe='" . $connector->sanitize($_SESSION['pass']) . "'
				 AND statut='actif'";

        $getUser = $connector->query($sql_user);
        //	printr($this->userdata);
        //Si au moins un enregistrement de personne est trouvé
        if ($connector->getNumRows($getUser) == 1)
        {
            $this->userdata = $connector->fetchArray($getUser);

            if ($_SESSION['pass'] == $this->userdata['mot_de_passe'])
            {

                $this->_setSession($this->userdata, false, false);

                return true;
            }
            else
            {
                unset($this->userdata);
                $message = "Erreur de session (pass) : " . $_SESSION['user'] . ", sid:" . session_id();
                trigger_error($message, E_USER_ERROR);
            } // if pass
        }
        else
        {

            $message = "Erreur de session (requete) : " . $_SESSION['user'] . ", sid:" . session_id();
            trigger_error($message, E_USER_ERROR);
            unset($this->userdata);
        } //if num rows
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
    function checkLogin($user = '', $pass = '', $group = UserLevel::MEMBER, $goodRedirect = '', $badRedirect = '', $memoriser = false)
    {

        /* Appel de l'instance de la classe d'accès à la BD */
        global $connector;
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
            $sql = "
			SELECT idPersonne, pseudo, mot_de_passe, cookie, groupe, region, email, gds
			FROM personne
			WHERE pseudo = '" . $connector->sanitize($user) . "' AND groupe <= " . $group . " AND statut='actif'";

            $getUser = $connector->query($sql);

            if ($connector->getNumRows($getUser) == 1)
            {
                $this->userdata = $connector->fetchArray($getUser);

                // exception pour admin
                if ($this->userdata['groupe'] == UserLevel::SUPERADMIN)
                    $goodRedirect = "admin/index.php";

                if ((sha1($this->userdata['gds'] . sha1($pass)) == $this->userdata['mot_de_passe']) || password_verify($pass, (string) $this->userdata['mot_de_passe']))
                {
                    $connector->query("UPDATE personne SET last_login = now() WHERE idPersonne=".(int)$this->userdata['idPersonne']);
                    session_regenerate_id(true); // to avoid session fixation attack
                    $this->_setSession($this->userdata, $memoriser);
                    $logger->log('global', 'activity', "[Sentry] login of " . $_SESSION["user"], Logger::GRAN_YEAR);

                    if ($goodRedirect)
                    {
                        header("Location: " . $goodRedirect);
                        exit();
                    }

                    return true;
                }
                else
                {
                    $logger->log('global', 'activity', "[Sentry] login failed, wrong password by user " . $this->userdata['pseudo'], Logger::GRAN_YEAR);

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
                $logger->log('global', 'activity', "[Sentry] login failed, user " . $user . " not found", Logger::GRAN_YEAR);

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

    function checkRemembered($cookie)
    {
        global $connector;
        global $logger;

        $sql_getUser = "SELECT idPersonne, pseudo, mot_de_passe, cookie, groupe, region, email, gds
						FROM personne
						WHERE cookie='" . $connector->sanitize($cookie) . "'
						 AND statut='actif'";

        $getUser = $connector->query($sql_getUser);

        if ($connector->getNumRows($getUser) > 0)
        {

            $this->userdata = $connector->fetchArray($getUser);
            session_regenerate_id(true); // to avoid session fixation attack
            $this->_setSession($this->userdata, true, true);
            $logger->log('global', 'activity', "[Sentry] remembered access of " . $_SESSION["user"] . " (" . $_SESSION['Semail'] . ")", Logger::GRAN_YEAR);
            return true;
        }
        else
        {
            unset($this->userdata);

            return false;
        } // if pass
    }

//function

    function _setSession($valeurs, $memoriser, $init = true)
    {
        global $connector;

        $req_affiliation = $connector->query("
		SELECT idAffiliation
		FROM affiliation
		WHERE idPersonne='" . (int) $this->userdata["idPersonne"] . "' AND genre='lieu'");

        $tab_affiliation = $connector->fetchArray($req_affiliation);

        //remplissage des variables de session
        $_SESSION["SidPersonne"] = $this->userdata["idPersonne"];
        $_SESSION["user"] = $this->userdata["pseudo"];
        $_SESSION["pass"] = $this->userdata['mot_de_passe'];
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
			SET cookie='" . $connector->sanitize($cookie) . "' WHERE idPersonne=" . $this->userdata['idPersonne'];
            //echo $sql;
            $connector->query($sql);
        }
    }

    function updateCookie($cookie, $sauvegarder)
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

    function sessionDefaults()
    {
        $_SESSION['logged'] = false;
        $_SESSION["memoriser"] = false;
        $_SESSION["cookie"] = 0;
        $_SESSION["groupe"] = 20;
    }

    function checkGroup($groupe = UserLevel::MEMBER)
    {
        global $connector;

        if (!isset($_SESSION['user'])) {
            return false;
        }

        $getUser = $connector->query("
        SELECT idPersonne, pseudo, mot_de_passe, cookie, groupe, email, gds
        FROM personne
        WHERE pseudo = '" . $connector->sanitize($_SESSION['user']) . "' AND groupe <= " . (int) $groupe . " AND statut='actif'");

        if ($connector->getNumRows($getUser) == 1) {
            return true;
        }

        return false;
    }

    function token()
    {
        // generate a random token
        $seed = "";
        for ($i = 1; $i < 33; $i++)
        {
            $seed .= chr(random_int(0, 255));
        }
        return md5($seed);
    }

    /**
     * Détruit les données d'utilisateur de l'objet, la session et stoppe le script
     */
    function logout()
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
