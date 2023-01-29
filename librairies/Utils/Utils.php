<?php

namespace Ladecadanse\Utils;

class Utils
{
    public static function urlQueryArrayToString($get, $sauf = "")
    {
        $afficher = "";

        if (!is_array($sauf))
        {
            foreach ($get as $nom => $valeur)
            {
                if ($nom != $sauf)
                {
                    $afficher .= $nom . "=" . $valeur . "&amp;";
                }
            }
        }
        else
        {
            foreach ($get as $nom => $valeur)
            {
                if (!in_array($nom, $sauf))
                {
                    $afficher .= $nom . "=" . $valeur . "&amp;";
                }
            }
        }
        $afficher = mb_substr($afficher, 0, -5);

        return $afficher;
    }
}
