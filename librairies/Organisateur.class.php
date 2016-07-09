<?php
/**
 *
 *
 *
 * PHP versions 4
 *
 * @category   librairie
 * @author     Michel Gaudry <michel@ladecadanse.ch>
 * @see
 */

require_once("Element.class.php");

class Organisateur extends Element
{
 /**
   * @access public
   */
	function Organisateur()
	{
		parent::Element();

		$this->table = "organisateur";

	}


}

?>