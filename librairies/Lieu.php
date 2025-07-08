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

    public static function prepositionToPutInSentence($preposition): string
    {
        $result = $preposition;

        if ($preposition == '')
        {
            return "- ";
        }

        // if "au", "chez", etc. add a separation
        if (!in_array(trim($preposition), ["l'", "à l'"]))
        {
            $result .= " ";
        }
        return $result;
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
