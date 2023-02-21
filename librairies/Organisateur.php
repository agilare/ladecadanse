<?php

namespace Ladecadanse;

use Ladecadanse\Element;


class Organisateur extends Element
{

    function __construct()
	{
        parent::__construct();
		$this->table = "organisateur";
	}
}