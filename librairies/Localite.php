<?php

namespace Ladecadanse;

use PDO;

class Localite
{

    public static function getListByRegion(): array
    {
        global $connectorPdo;
        $stmt = $connectorPdo->prepare("SELECT canton, id, localite FROM localite WHERE id!=1 AND canton != 'fr' ORDER BY canton, localite;");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_GROUP);
    }
}
