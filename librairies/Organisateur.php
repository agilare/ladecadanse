<?php

namespace Ladecadanse;

use Ladecadanse\Element;


class Organisateur extends Element
{
 /**
   * @access public
   */
	function __construct()
	{
        parent::__construct();
		$this->table = "organisateur";
	}
}