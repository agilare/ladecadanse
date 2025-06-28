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

    public static function getLinkNameHtml(string $nom, ?int $idLieu, ?string $salle = null): string
    {
        $result = sanitizeForHtml($nom);

        if ($idLieu)
        {
            $result = '<a href="/lieu.php?idL=' . $idLieu . '">' . $result . '</a>';
            if ($salle)
            {
                $result .= " - " . sanitizeForHtml($salle);
            }
        }

        return $result;
    }

}
