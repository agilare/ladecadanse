<?php
/**
 *
 *
 *
 * PHP versions 4 and 5
 *
 * @category   librairie
 * @author     Michel Gaudry <michel@ladecadanse.ch>
 * @see
 */

require_once("Element.class.php");

class Lieu extends Element
{
 /**
   * @access public
   */
	function __construct()
	{
		parent::__construct();

		$this->table = "lieu";

	}


}

?>