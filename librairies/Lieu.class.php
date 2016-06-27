<? /**/ ?>
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
	function Lieu()
	{
		parent::Element();

		$this->table = "lieu";

	}


}

?>