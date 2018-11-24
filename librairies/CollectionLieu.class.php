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
require_once("Collection.class.php");

class CollectionLieu extends Collection {
    /**
     *
     * @var string
     */

    /**
     * Démarre la session et inclut un en-tête interdisant de stocker le mot
     * de passe dans le cache de l'utilisateur
     * @access public
     */
    function __construct($connector) {
        $this->connector = $connector;
    }

}
