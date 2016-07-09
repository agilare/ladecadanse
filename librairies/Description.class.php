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

class Description extends Element {



 /**
 	 * Démarre la session et inclut un en-tête interdisant de stocker le mot
	 * de passe dans le cache de l'utilisateur
   * @access public
   */
	function Description()
	{
		$this->table = 'descriptionlieu';
		$this->nom = 'description';
	}

	function loadByType($type)
	{
		$sql = "SELECT * FROM ".$this->table." WHERE id".ucfirst($this->table)."=".$this->id." AND type='".mysql_real_escape_string($type)."'";
		//echo $sql;
		$res = $this->connector->query($sql);
		$this->valeurs = $this->connector->fetchAssoc($res);
	}



}

?>