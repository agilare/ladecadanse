<?php

/**
 * Convertit une date au format MySQL au format franÃ§ais
 *
 * @param: string $d  Date au format mysql, par exemple 2005-05-24 (avec ou sans l'heure)
 * @param: string $format ('tout', 'heure', 'annee') Choix de la conversion
 * @see: categorie affichage
 * @return: string Date au format voulu
 * @todo: renvoi de la date sans l'heure, sans l'année
 */
function date_fr($d, $format = "", $affMois = "", $jour_sem = "", $html = true)
{

    $Jour = array("dimanche", "lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi");
    $Mois = array("", "janvier", "février", "mars", "avril", "mai", "juin", "juillet", "août", "septembre", "octobre", "novembre", "décembre");

    $temps = explode(" ", $d);

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

        $tab0 = explode(" ", $d);
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
        if ($affMois > -1)
            $nomMois = $Mois[date("n", $ts)];


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

function date2mois($date)
{
    $temps = explode(" ", $date);
    $tabDate = explode("-", $temps[0]);

    return $tabDate[1];
}

function date2annee($date)
{
    $temps = explode(" ", $date);
    $tabDate = explode("-", $temps[0]);

    return $tabDate[0];
}

function mois2fr($mois)
{
    $Mois = array("", "janvier", "février", "mars", "avril", "mai", "juin", "juillet", "août", "septembre", "octobre", "novembre", "décembre");

    if (mb_substr($mois, 0, 1) == "0")
    {
        $mois = mb_substr($mois, 1, 1);
    }

    return $Mois[$mois];
}

function date2jour($date)
{
    $temps = explode(" ", $date);
    $tabDate = explode("-", $temps[0]);
    $noJour = $tabDate[2];

    if ($noJour[0] == "0")
    {
        $noJour = mb_substr($noJour, 1, 1);
    }

    return $noJour;
}

function date2nomJour($date)
{
    $Jour = array("dimanche", "lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi");
    $temps = explode(" ", $date);
    $tabDate = explode("-", $temps[0]);

    $t = mktime(0, 0, 0, $tabDate[1], $tabDate[2], $tabDate[0]);

    return $Jour[date("w", $t)];
}

/**
 * Renvoie le no de semaine d'une date, 0: courante, 1: prochaine, 2: suivante
 *
 * @param string $date Date Ã  évaluer
 * @return int $sem No semaine de la date (0, 1, 2), -1 si la date n'est pas dans ces semaines
 */
function date2sem($date)
{

    //Jour de la semaine actuel
    $jourSem = date("w");

    //date Unix de $date
    $aaaammjj = explode("-", $date);
    $dateU = mktime(0, 0, 0, $aaaammjj[1], $aaaammjj[2], $aaaammjj[0]);

    //date Unix d'aujourd'hui
    $auj = mktime(1, 0, 0, date("m"), date("d"), date("Y"));

    //recherche du lundi de la semaine courante
    $lun0 = $auj;
    while ($jourSem != 1)
    {
        $lun0 -= 86400;
        $jourSem = date("w", $lun0);
    }

    //dimanche de la semaine courante
    $dim0 = $lun0 + (6 * 86400);

    // Parcours les semaines 0, 1 et 2, et compare chacun des jours de la semaine avec la date
    for ($sem = 0; $sem < 3; $sem++)
    {

        /* parcours les jours de la semaine courante et les compare avec la date Ã  évaluer
         * renvoie le numéro ce la semaine courante si une égalité est trouvée
         */
        for ($dateCour = $lun0; $dateCour <= $dim0; $dateCour += 86400)
        {

            //si la date Ã  vérifier est le mÃªme jour que la date courante
            if (date("d", $dateCour) == date("d", $dateU))
            {
                return $sem;
            }
        }

        //actualise le lundi et le dimanche vers la semaine prochaine
        $lun0 = $dim0 + 86400;
        $dim0 = $lun0 + (6 * 86400);
    }

    return -1;
}

function date_iso2app($date)
{
    if (mb_strlen($date) > 10)
    {
        $tab_date = explode(" ", $date);

        $tab_jour = explode("-", $tab_date[0]);
        //$tab_heure = explode(":", $tab_date[1]);

        return $tab_date[1] . " " . $tab_jour[2] . "." . $tab_jour[1] . "." . $tab_jour[0];
    }
    else
    {
        $tab_date = explode("-", $date);
        return $tab_date[2] . "." . $tab_date[1] . "." . $tab_date[0];
    }
}

function date_app2iso($date)
{
    if (mb_strlen($date) > 10)
    {
        return $date;
    }
    else
    {
        $tab_date = explode(".", $date);
        return $tab_date[2] . "-" . $tab_date[1] . "-" . $tab_date[0];
    }
}

function date_iso2time($date)
{
    $tab_datetime = explode(" ", $date);
    $tab_date = explode("-", $tab_datetime[0]);

    return mktime(0, 0, 0, $tab_date[1], $tab_date[2], $tab_date[0]);
}

function datetime_iso2time($date)
{
    $tab_date = explode(" ", $date);
    $tab_jour = explode("-", $tab_date[0]);
    $tab_heure = explode(":", $tab_date[1]);

    return mktime($tab_heure[0], $tab_heure[1], $tab_heure[2], $tab_jour[1], $tab_jour[2], $tab_jour[0]);
}

function date_iso2lundim($date)
{
    $tab_date = explode("-", $date);

    $i = 0;
    $ds = date("w", mktime(0, 0, 0, $tab_date[1], $tab_date[2], $tab_date[0]));

    while ($ds != 1)
    {
        $ds = date("w", mktime(0, 0, 0, $tab_date[1], $tab_date[2] - $i, $tab_date[0]));
        $i++;
        //echo "i:".$i." ";
    }

    if ($i > 0)
        $i--;

    $lundi = date("Y-m-d", mktime(0, 0, 0, $tab_date[1], $tab_date[2] - $i, $tab_date[0]));

    //echo "lundi:".$lundi."<br>";

    $j = 0;
    while ($ds != 0)
    {
        $ds = date("w", mktime(0, 0, 0, $tab_date[1], $tab_date[2] + $j, $tab_date[0]));
        $j++;
    }

    $dimanche = date("Y-m-d", mktime(0, 0, 0, $tab_date[1], $tab_date[2] + $j - 1, $tab_date[0]));
    //echo "dimanche:".$dimanche."<br>";

    return array($lundi, $dimanche);
}

function date_lendemain($date)
{
    $tab_date = explode("-", $date);
    return date('Y-m-d', mktime(0, 0, 0, $tab_date[1], $tab_date[2] + 1, $tab_date[0])); // $annee."-".$mois."-".$annee;
}

function horaire2heure($horaire_complet, $date_evenement)
{
    $horaire_heure = $horaire_complet;

    $fin_jour = date_lendemain($date_evenement) . " 06:00:00";
    //echo "fin jour :".$fin_jour."<br>";
    if ($horaire_heure > $fin_jour || $horaire_heure == "0000-00-00 00:00:00")
    {
        $horaire_heure = "";
    }

    //	echo "hor heure:".$horaire_heure;

    return mb_substr($horaire_heure, 11, -3);
}

function afficher_debut_fin($horaire_debut, $horaire_fin, $date_evenement)
{

    $afficher = "";

    $afficher = horaire2heure($horaire_debut, $date_evenement);
    if ($horaire_fin != date_lendemain($date_evenement) . " 06:00:01" && $horaire_fin != "0000-00-00 00:00:00" && $horaire_debut != date_lendemain($date_evenement) . " 06:00:01" && $horaire_debut != "0000-00-00 00:00:00")
    {
        $afficher .= " – ";
    }

    if ($horaire_fin != date_lendemain($date_evenement) . " 06:00:01" && $horaire_fin != "0000-00-00 00:00:00" && $horaire_debut == date_lendemain($date_evenement) . " 06:00:01")
    {
        $afficher .= "fin : ";
    }

    $afficher .= horaire2heure($horaire_fin, $date_evenement);

    return $afficher;
}


/**
 * FIXME: mv to Text class
 * @param string $chaine dirty
 * @return string clean
 */
function sanitizeForHtml(?string $chaine): string
{
    return trim(htmlspecialchars($chaine));
}
