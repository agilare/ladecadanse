<?php

namespace Ladecadanse;

use Ladecadanse\Collection;

class CollectionLieu extends Collection {

    function __construct($connector) {
        $this->connector = $connector;
    }

}
