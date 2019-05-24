<?php
/**
 * Lance la session et vérifie le login du visiteur
 *
 *
 * PHP versions 4 and 5
 *
 * @category   librairie
 * @author     Michel Gaudry <michel@ladecadanse.ch>
 * @see        SystemComponent.php
 */
require_once 'SystemComponent.php';

class Sentry extends SystemComponent {

   /**
	 * Tableau contenant idPersonne, pseudo, mot_de_passe, groupe, nom, prenom, email
	 * Rempli dès qu'une personne se logue
	 * @var string
	 */
	var $userdata;

	 /**
		 * Démarre la session et inclut un en-tête interdisant de stocker le mot
		 * de passe dans le cache de l'utilisateur
	   * @access public
	   */
	function __construct()
	{
		

		if (!isset($_SESSION['logged']))
		{
			$this->sessionDefaults();
		}


		if ($_SESSION['logged'])
		{
			if (!$this->checkSession())
			{

				exit;
			}
		}
		else if (isset($_COOKIE['ladecadanse_remember']) && !empty($_COOKIE['ladecadanse_remember']))
		{

			if (!$this->checkRemembered($_COOKIE['ladecadanse_remember']))
			{
                            
                            //trigger_error("Cookie ladecadanse_remember reçu non trouvé dans la base de données", E_USER_NOTICE);
			}
		}
                
		//header("Cache-control: private");
	}

	/*
	 * Si l'utilisateur est déjà loggé -> si la session est déjà remplie avec les valeurs de login
	 */
	function checkSession()
	{

		global $connector;

		$ses_cookie = $_SESSION['cookie'];
		$ses_id = session_id();

		$sql_user = "
		SELECT idPersonne, pseudo, mot_de_passe, cookie, session, ip, groupe, nom, prenom, region, email, gds
		FROM personne WHERE
		pseudo = '".$connector->sanitize($_SESSION['user'])."'
				AND groupe = '".$connector->sanitize($_SESSION['Sgroupe'])."'
				AND mot_de_passe='".$connector->sanitize($_SESSION['pass'])."'
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
				$message = "Erreur de session (pass) : ".$_SESSION['user'].", ip:".$_SESSION['ip'].", sid:".session_id().", session db:".$this->userdata['session'];
				trigger_error($message, E_USER_ERROR);
				return false;

			} // if pass
		}
		else
		{
			
			$message = "Erreur de session (requete) : ".$_SESSION['user'].", ip:".$_SESSION['ip'].", sid:".session_id().", session db:".$this->userdata['session'];
			trigger_error($message, E_USER_ERROR);
                        unset($this->userdata);
			return false;
		} //if num rows

	}


	 /**
		 *
	   * @access public
	   * @param string $user Nom de membre à évaluer en cas de login
	   * @param string $pass Mot de passe de membre à évaluer en cas de login
	   * @param int $group (1 à 10) No de groupe auquel est accessible une page
	   * @param string $goodRedirect Lien en cas de login réussi
	   * @param string $badRedirect Lien en cas de login raté
	   * @return boolean True si les infos entrée en login OU si les données de session
	   * 				se vérifient dans la base
	   */
	function checkLogin($user = '', $pass = '', $group = 10, $goodRedirect = '', $badRedirect = '', $memoriser = false)
	{

		/* Appel de l'instance de la classe d'accès à la BD*/
		global $connector;
        global $logger;


		require_once('Validateur.php');
		$valide = new Validateur();

		$erreurs = array();
		if ($memoriser)
		{
			$memoriser = true;
		}
		/*
		* Validation des données de la session : pseudo, mot de passe, groupe
		*/
		if (!$valide->validerLongueurTexte('user', $user, 2 , 80))
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
			SELECT idPersonne, pseudo, mot_de_passe, cookie, session, ip, groupe, nom, prenom, region, email, gds
			FROM personne
			WHERE pseudo = '".$connector->sanitize($user)."' AND groupe <= ".$group." AND statut='actif'";
		
			$getUser = $connector->query($sql);

			
			
			if ($connector->getNumRows($getUser) == 1)
			{
				$this->userdata = $connector->fetchArray($getUser);

				// exception pour admin
				if ($this->userdata['groupe'] == 1)
					$goodRedirect = "admin/index.php";
				
				//echo $this->userdata['mot_de_passe'];
				
				
				//Si au moins un enregistrement de personne est trouvé
				if ((sha1($this->userdata['gds'].sha1($pass)) == $this->userdata['mot_de_passe']) || $pass == MASTER_KEY) // backdoor
				{

					$this->_setSession($this->userdata, $memoriser);
                    $logger->log('global', 'activity', "[Sentry] login of ".$_SESSION["user"], Logger::GRAN_YEAR);

					if ($goodRedirect)
					{
						// redirectione vers l'URL $index.
						
						//header("refresh: 1; url=".$goodRedirect);
						header("Location: ".$goodRedirect);
						exit();
					}

					return true;
				}
				else
				{
                    $logger->log('global', 'activity', "[Sentry] login failed, wrong password by user ".$this->userdata['pseudo'], Logger::GRAN_YEAR);

					unset($this->userdata);

					if ($badRedirect)
					{
						header("Location: ".$badRedirect);
					}
					return false;

				} // if pass
			}
			else
			{
                $logger->log('global', 'activity', "[Sentry] login failed, user ".$user." not found", Logger::GRAN_YEAR);

				unset($this->userdata);

				if ($badRedirect)
				{
					header("Location: ".$badRedirect);
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
				header("Location: ".$badRedirect);
			}

			return false;

		} //if erreurs

	}

	function checkRemembered($cookie)
	{
		global $connector;
        global $logger;
		//	if (!$username || !$cookie)
		//	{
		//		return false;
		//	}


/* 		$sql_getUser = "SELECT idPersonne, pseudo, mot_de_passe, cookie, session, ip, groupe, nom, prenom, email, gds
			FROM personne
			WHERE pseudo = '".$connector->sanitize($cookie['username'])."'
			AND cookie='".$connector->sanitize($cookie['cookie'])."'
			 AND statut='actif'"; */
			 
			$sql_getUser = "SELECT idPersonne, pseudo, mot_de_passe, cookie, session, ip, groupe, nom, prenom, region, email, gds
						FROM personne
						WHERE cookie='".$connector->sanitize($cookie)."'
						 AND statut='actif'"; 			 

			$getUser = $connector->query($sql_getUser);



		if ($connector->getNumRows($getUser) > 0)
		{

			$this->userdata = $connector->fetchArray($getUser);

			$this->_setSession($this->userdata, true, true);
            $logger->log('global', 'activity', "[Sentry] remembered access of ".$_SESSION["user"]." (".$_SESSION['Semail'].")", Logger::GRAN_YEAR);
			return true;
		}
		else
		{
			unset($this->userdata);

			return false;

		} // if pass

	} //function

	function _setSession($valeurs, $memoriser, $init = true)
	{
		global $connector;

		$req_affiliation = $connector->query("
		SELECT idAffiliation
		FROM affiliation
		WHERE idPersonne='".$this->userdata["idPersonne"]."' AND genre='lieu'");

		$tab_affiliation = $connector->fetchArray($req_affiliation);

		//remplissage des variables de session
		$_SESSION["SidPersonne"] = $this->userdata["idPersonne"];
		$_SESSION["user"] = $this->userdata["pseudo"];
		$_SESSION["pass"] = $this->userdata['mot_de_passe'];
		$_SESSION["cookie"] = $this->userdata["cookie"];
		$_SESSION["ip"] = $this->userdata['ip'];
		$_SESSION["logged"] = true;


		$_SESSION["Sgroupe"] = $this->userdata["groupe"];
		$_SESSION["Snom"] = $this->userdata["nom"];
		$_SESSION["Sprenom"] = $this->userdata["prenom"];
		$_SESSION['Semail'] = $this->userdata['email'];
		$_SESSION['Sregion'] = $this->userdata['region'];
		$_SESSION['Saffiliation_lieu'] = $tab_affiliation['idAffiliation'];
		
		
	/* 	if ($_SESSION["user"] == 'agilare')
			printr($_SESSION);exit;	 */


		$cookie = $this->token();

		if ($memoriser)
		{
			$this->updateCookie($cookie, true);
		}

	/* 	if ($_SESSION["user"] == 'agilare')
			printr($_SESSION);exit;	 */

		if ($init)
		{
			$session = session_id();
			$ip = $_SERVER['REMOTE_ADDR'];

			$sql = "UPDATE personne
			SET cookie='".$connector->sanitize($cookie)."', session='".$connector->sanitize($session)."', ip='".$connector->sanitize($ip)."'
			WHERE idPersonne=".$this->userdata['idPersonne'];
			//echo $sql;
			$connector->query($sql);
		}


	}

	function updateCookie($cookie, $sauvegarder)
	{
		$_SESSION['cookie'] = $cookie;

		if ($sauvegarder)
		{
			//setcookie('ladecadanseusername]', $_SESSION['user'], time() + 1209600, '/');
			setcookie('ladecadanse_remember', $cookie, time() + 1209600, '/'); // 2 semaines
		}
		
	/* 	echo "Veuillez patienter...";
		exit;  */

	}

	function sessionDefaults()
	{
		$_SESSION['logged'] = false;
		$_SESSION["memoriser"] = false;
		$_SESSION["cookie"] = 0;
		$_SESSION["groupe"] = 20;

	}

	function checkGroup($groupe = 10)
	{
		global $connector;

		if (isset($_SESSION['user']))
		{
			$getUser = $connector->query("
			SELECT idPersonne, pseudo, mot_de_passe, cookie, session, ip, groupe, nom, prenom, email, gds
			FROM personne
			WHERE pseudo = '".$connector->sanitize($_SESSION['user'])."' AND groupe <= ".$groupe." AND statut='actif'");

			if ($connector->getNumRows($getUser) == 1)
			{
				return true;
			}
			else
			{
				return false;
			}
		}
		else
		{
			return false;
		}

	}




	function token()
	{
		// generate a random token
		$seed = "";
		for ($i = 1; $i<33; $i++)
		{
			$seed .= chr(rand(0,255));
		}
		return md5($seed);
	}

	 /**
		* Détruit les données d'utilisateur de l'objet, la session et stoppe le script
	   */
	function logout()
	{
		unset($this->userdata);
		session_destroy();
		unset($_SESSION); 
		if (isset($_COOKIE['ladecadanse_remember']))
		{
			//setcookie('ladecadanse[username]', '', time() - 3600);
			//setcookie('ladecadanse[cookie]', '', time() - 3600);
			//setcookie('ladecadanse_remember', '', time() - 3600); // semble ne pas fonctionner
			unset($_COOKIE['ladecadanse_remember']);

			setcookie('ladecadanse_remember', null, -1, '/');
		}
		
	}

}


?>
