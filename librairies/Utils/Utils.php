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
    
    public static function getBaseUrl(): string
    {
        $full_url = "http://";

        // 2. check if your server use HTTPS
        if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on") {
            $full_url = "https://";
        }

        // 3. append domain name
        return $full_url . $_SERVER["SERVER_NAME"];        
    }
}
