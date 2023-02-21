<?php

namespace Ladecadanse;

use Ladecadanse\Element;

class Lieu extends Element
{

    function __construct()
	{
		parent::__construct();
        $this->table = "lieu";
    }

}
