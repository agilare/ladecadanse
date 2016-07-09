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

class Collection {

   /**
 *
 * @var string
 */
	var $nom;
	var $elements = array();
	var $connector;

 /**
 	 * Démarre la session et inclut un en-tête interdisant de stocker le mot
	 * de passe dans le cache de l'utilisateur
   * @access public
   */
	function Collection()
	{
		global $connector;
		$this->connector = $connector;
	}


	function setElement($id, $element)
	{
		$this->elements[$id] = $element;
	}

	function getElement($id)
	{
		return $this->elements[$id];
	}

	function setElements($elements)
	{
		$this->elements = $elements;
	}

	function getElements()
	{
		return $this->elements;
	}

	function getNbElements()
	{
		return count($this->elements);
	}



}

?>