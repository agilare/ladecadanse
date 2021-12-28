<?php

namespace Ladecadanse;

use Ladecadanse\Element;

class Evenement extends Element
{

    /**
     * Démarre la session et inclut un en-tête interdisant de stocker le mot
     * de passe dans le cache de l'utilisateur
     * @access public
     */
    function __construct() {
        global $connector;
        $this->table = "evenement";
        $this->connector = $connector;
    }

}
