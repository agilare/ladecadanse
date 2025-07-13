<?php

namespace Ladecadanse\Utils;

class Utils
{
    public static function urlQueryArrayToString(array $get, $sauf = ""): string
    {
        $afficher = "";

        if (!is_array($sauf))
        {
            foreach ($get as $nom => $valeur)
            {
                if ($nom != $sauf && !($nom == 'tri_agenda' && in_array($valeur, ['', 'dateAjout'])) && !($nom == 'genre' && empty($valeur)))
                {
                    $afficher .= $nom . "=" . $valeur . "&";
                }
            }
        }
        else
        {
            foreach ($get as $nom => $valeur)
            {
                if (!in_array($nom, $sauf) && !($nom == 'tri_agenda' && in_array($valeur, ['', 'dateAjout'])) && !($nom == 'genre' && empty($valeur)))
                {
                    $afficher .= $nom . "=" . $valeur . "&";
                }
            }
        }

        return mb_substr(sanitizeForHtml($afficher), 0, -5);
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
