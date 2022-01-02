<?php

namespace Ladecadanse;

use Ladecadanse\Collection;

class LieuCollection extends Collection {

    function __construct($connector) {
        $this->connector = $connector;
    }

}
