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

require_once("Element.class.php");

class Commentaire extends Element {


 /**
 	 * Démarre la session et inclut un en-tête interdisant de stocker le mot
	 * de passe dans le cache de l'utilisateur
   * @access public
   */
	function Commentaire()
	{
		$this->table = "commentaire";
	}


}

?>