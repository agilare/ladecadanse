<?php
/**
 * 
 * Classe de base pour toutes les autres classes du site
 *
 * PHP versions 4 and 5
 *
 * @category   librairie
 * @package    NomPaquetage
 * @author     Michel Gaudry <michel@ladecadanse.ch>
 * @see        DbConnector.php, ImageDriver.php, Sentry.php, Validator.php
 */
class SystemComponent {

   /**
     * Tableau contenant les paramètres de base
     *
     * @var string
     */
	var $settings;
	
	
   /**
   	 * Renvoie le tableau contenant les paramètres de la classe
     * @access public
     * @return Method available since Release 1.2.0
     */	
	function getSettings() {

		//system variables
		$settings['siteDir'] = 'http://www.darksite.ch/ladecadanse/';
		
		//database variables
/* 		$settings['dbhost'] = 'localhost';
		$settings['dbusername'] = 'ladecadanse';
		$settings['dbpassword'] = 'la2973de';
		$settings['dbname'] = 'ladecadanse'; */
		
		// !!! AVANT UPLOAD vers www.ladecadanse.ch, METTRE LES VALEURS CI-DESSOUS		
		$settings['dbhost'] = '';
		$settings['dbusername'] = 'ladecadanse';
		$settings['dbpassword'] = 'la2973de';
		$settings['dbname'] = 'ladecadanse_test';
		
		return $settings;
	}
	

}
?>
