<?php
//declare(strict_types=1);

/**
 * Convertit une date au format MySQL au format franÃ§ais
 *
 * @param: string $d Date au format mysql, par exemple 2005-05-24 (avec ou sans l'heure)
 * @param: string $format ('tout', 'heure', 'annee') Choix de la conversion
 * @see: categorie affichage
 * @return: string Date au format voulu
 * @todo: renvoi de la date sans l'heure, sans l'année
 */
function date_fr($d, $format = "", $affMois = "", $jour_sem = "", $html = true)
{

    $Jour = ["dimanche", "lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi"];
    $Mois = ["", "janvier", "février", "mars", "avril", "mai", "juin", "juillet", "août", "septembre", "octobre", "novembre", "décembre"];

    $temps = explode(" ", (string) $d);

    $hhmmss = "";

    // si l'heure est présente et que le choix est la date complÃ¨te ou seulement l'heure
    if (isset($temps[1]) && ($format == "tout" || $format == "heure"))
    {
        $hhmmss = explode(":", $temps[1]);
        $hhmmss = " " . $hhmmss[0] . "h" . $hhmmss[1];
    }

    // si c'est "tout" ou "annee"
    if ($format != "heure")
    {

        $tab0 = explode(" ", (string) $d);
        $tab = explode("-", $tab0[0]);

        //debogage
        /* 	foreach($tab as $val) {
          if(!is_numeric($val))
          echo "La valeur de la date n'est pas un chiffre : ".$val;
          } */

        if ($format == "annee")
        {
            $hhmmss = " " . $tab[0];
        }
        //printr($tab);
        $ts = mktime(0, 0, 0, $tab[1], $tab[2], $tab[0]);
        $mm = $tab[1];
        $nj = date("j", $ts);

        if ($nj == 1)
        {
            if ($html)
            {
                $nj .= "<sup>er</sup>";
            }
            else
            {
                $nj .= "er";
            }
        }


        $nomMois = '';
        if ($affMois == '')
        {
            $nomMois = $Mois[date("n", $ts)];
        }


        $date_formatee = $nj;

        if ($html)
        {
            $date_formatee .= "&nbsp;";
        }
        else
        {
            $date_formatee .= " ";
        }

        $date_formatee .= $nomMois . $hhmmss;

        if ($jour_sem == "")
        {
            $date_formatee = $Jour[date("w", $ts)] . " " . $date_formatee;
        }

        return $date_formatee;
    }

    unset($Jour, $Mois);

    return $hhmmss;
}

function mois2fr($mois)
{
    $Mois = ["", "janvier", "février", "mars", "avril", "mai", "juin", "juillet", "août", "septembre", "octobre", "novembre", "décembre"];

    if (mb_substr((string) $mois, 0, 1) == "0")
    {
        $mois = mb_substr((string) $mois, 1, 1);
    }

    return $Mois[$mois];
}

function date2nomJour($date)
{
    $Jour = ["dimanche", "lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi"];
    $temps = explode(" ", (string) $date);
    $tabDate = explode("-", $temps[0]);

    $t = mktime(0, 0, 0, $tabDate[1], $tabDate[2], $tabDate[0]);

    return $Jour[date("w", $t)];
}


/** @deprecated Use \Ladecadanse\Utils\DateHelper::isoToApp() instead */
function date_iso2app($date): string
{
    return \Ladecadanse\Utils\DateHelper::isoToApp($date !== null ? (string) $date : null);
}

/** @deprecated Use \Ladecadanse\Utils\DateHelper::appToIso() instead */
function date_app2iso($date): string
{
    return \Ladecadanse\Utils\DateHelper::appToIso((string) $date);
}

/** @deprecated Use \Ladecadanse\Utils\DateHelper::isoToNextDay() instead */
function dateIsoToNextDayDateIso(string $date): string
{
    return \Ladecadanse\Utils\DateHelper::isoToNextDay($date);
}

/**
 * FIXME: mv to Text class
 * @param ?string $chaine dirty
 * @return string clean
 */
function sanitizeForHtml(?string $chaine): string
{
    // ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401 is the default value since php 8.1
    return trim(htmlspecialchars((string) $chaine, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8'));
}
