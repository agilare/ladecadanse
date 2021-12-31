<?php
/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace Ladecadanse;

use Ladecadanse\Utils;

/**
 * Description of HtmlShrink
 *
 * @author michel
 */
class HtmlShrink
{

    public static function getAdressFitted($region, $localite, $quartier, $adr)
    {
        $adresse = '';

        if (!empty($adr))
            $adresse .= $adr;

        if (!empty($quartier) && $quartier != 'autre')
            $adresse .= " (" . $quartier . ") ";

        if (!empty($localite) && $localite != 'Autre' && $localite != $quartier)
            $adresse .= " - " . $localite;


        if ($localite != 'Genève' && $region == 'ge')
            $adresse .= " - Genève";

        if ($region == 'vd')
            $adresse .= " - Vaud";


        if ($region == 'rf')
            $adresse .= " - France";

        return $adresse;
    }

    public static function getMenuRegions($glo_regions, $get, $event_nb = [])
    {


        $html = '';
        ob_start();
        //
        ?>
        <ul class="menu_region">
        <?php
        $class_region = 'ge';
        foreach ($glo_regions as $n => $v)
        {
            if ($n == 'ge' || $n == 'vd') //|| $n == 'fr'
            {
                if ($n == 'vd')
                {
                    $v = 'Lausanne';
                    $class_region = 'vd';
                }

                if ($n == 'fr')
                {
//                    if (!(isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 4))
//                        continue;

                    $v = 'Fribourg';
                    $class_region = 'fr';
                }

                $ici = '';
                if ($n == $_SESSION['region'])
                    $ici = ' ici';

                $nb = '';
                if (!empty($event_nb[$n]))
                {
                    $nb = $event_nb[$n]['nb'];
                }
                ?><li><a href="?region=<?php echo $n; ?>&<?php echo Utils::urlQueryArrayToString($get, 'region'); ?>" class="<?php echo $class_region; ?><?php echo $ici; ?>"><?php echo $v; ?>&nbsp;<?php if (PREVIEW && $nb !== '')
                {
                    ?><span class="events-nb"><?php echo $nb; ?></span><?php } ?></a></li><?php
                        }
                    }
                    ?></ul>
                    <?php
        return ob_get_contents();
    }
    

    public static function getPaginationString($page = 1, $totalitems, $limit = 15, $adjacents = 1, $targetpage = "/", $pagestring = "?page=")
    {
        //defaults
        if (!$adjacents)
            $adjacents = 1;
        if (!$limit)
            $limit = 15;
        if (!$page)
            $page = 1;
        if (!$targetpage)
            $targetpage = "/";

        //other vars
        $prev = $page - 1;         //previous page is page - 1
        $next = $page + 1;         //next page is page + 1
        $lastpage = ceil($totalitems / $limit);    //lastpage is = total items / items per page, rounded up.
        $lpm1 = $lastpage - 1;

        $margin = 2;
        $padding = 2;
        //last page minus 1

        /*
          Now we apply our rules and draw the pagination object.
          We're actually saving the code to a variable in case we want to draw it more than once.
         */
        $pagination = "";
        if ($lastpage > 1)
        {
            $pagination .= "<div class=\"pagination\"";
            if ($margin || $padding)
            {
                $pagination .= " style=\"";
                if ($margin)
                    $pagination .= "margin: $margin;";
                if ($padding)
                    $pagination .= "padding: $padding;";
                $pagination .= "\"";
            }

            $pagination .= ">";

            //previous button
            if ($page > 1)
                $pagination .= "<a id=\"prec\" href=\"$targetpage$pagestring$prev\">préc</a>";
            else
                $pagination .= "<span class=\"disabled\">préc</span>";

            //pages
            if ($lastpage < 7 + ($adjacents * 2)) //not enough pages to bother breaking it up
            {
                for ($counter = 1; $counter <= $lastpage; $counter++)
                {
                    if ($counter == $page)
                        $pagination .= "<span class=\"current\">$counter</span>";
                    else
                        $pagination .= "<a href=\"$targetpage$pagestring$counter\">$counter</a>";
                }
            }
            elseif ($lastpage >= 7 + ($adjacents * 2)) //enough pages to hide some
            {
                //close to beginning; only hide later pages
                if ($page < 1 + ($adjacents * 3))
                {
                    for ($counter = 1; $counter < 4 + ($adjacents * 2); $counter++)
                    {
                        if ($counter == $page)
                            $pagination .= "<span class=\"current\">$counter</span>";
                        else
                            $pagination .= "<a href=\"$targetpage$pagestring$counter\">$counter</a>";
                    }
                    $pagination .= "...";
                    $pagination .= "<a href=\"$targetpage$pagestring$lpm1\">$lpm1</a>";
                    $pagination .= "<a href=\"$targetpage$pagestring$lastpage\">$lastpage</a>";
                }
                //in middle; hide some front and some back
                elseif ($lastpage - ($adjacents * 2) > $page && $page > ($adjacents * 2))
                {
                    $pagination .= "<a href=\"" . $targetpage . $pagestring . "1\">1</a>";
                    $pagination .= "<a href=\"" . $targetpage . $pagestring . "2\">2</a>";
                    $pagination .= "...";
                    for ($counter = $page - $adjacents; $counter <= $page + $adjacents; $counter++)
                    {
                        if ($counter == $page)
                            $pagination .= "<span class=\"current\">$counter</span>";
                        else
                            $pagination .= "<a href=\"$targetpage$pagestring$counter\">$counter</a>";
                    }
                    $pagination .= "...";
                    $pagination .= "<a href=\"$targetpage$pagestring$lpm1\">$lpm1</a>";
                    $pagination .= "<a href=\"$targetpage$pagestring$lastpage\">$lastpage</a>";
                }
                //close to end; only hide early pages
                else
                {
                    $pagination .= "<a href=\"" . $targetpage . $pagestring . "1\">1</a>";
                    $pagination .= "<a href=\"" . $targetpage . $pagestring . "2\">2</a>";
                    $pagination .= "...";
                    for ($counter = $lastpage - (1 + ($adjacents * 3)); $counter <= $lastpage; $counter++)
                    {
                        if ($counter == $page)
                            $pagination .= "<span class=\"current\">$counter</span>";
                        else
                            $pagination .= "<a href=\"$targetpage$pagestring$counter\">$counter</a>";
                    }
                }
            }

            //next button
            if ($page < $counter - 1)
                $pagination .= "<a id=\"suiv\"  href=\"$targetpage$pagestring$next\">suiv</a>";
            else
                $pagination .= "<span class=\"disabled\">suiv</span>";
            $pagination .= "</div>\n";
        }

        return $pagination;
    }    


    public static function authorSignature($idPersonne)
    {

        global $connector;

        $signature_auteur = "";
        $sql_auteur = "SELECT pseudo, nom, prenom, affiliation, signature, avec_affiliation
        FROM personne WHERE idPersonne=" . $idPersonne . "";

        $req_auteur = $connector->query($sql_auteur);
        $tab_auteur = $connector->fetchArray($req_auteur);

        if ($tab_auteur['signature'] == 'pseudo')
        {
            $signature_auteur = "<strong>" . $tab_auteur['pseudo'] . "</strong>";
        }
        else if ($tab_auteur['signature'] == 'prenom')
        {
            $signature_auteur = "<strong>" . $tab_auteur['prenom'] . "</strong>";
        }
        else if ($tab_auteur['signature'] == 'nomcomplet')
        {
            $signature_auteur = "<strong>" . $tab_auteur['prenom'] . " " . $tab_auteur['nom'] . "</strong>";
        }

        if ($tab_auteur['avec_affiliation'] == 'oui')
        {
            $nom_affiliation = "";
            $req_aff = $connector->query("
            SELECT idAffiliation FROM affiliation
            WHERE idPersonne=" . $idPersonne . " AND genre='lieu'");

            if (!empty($tab_auteur['affiliation']))
            {
                $nom_affiliation = $tab_auteur['affiliation'];
            }
            else if ($tab_aff = $connector->fetchArray($req_aff))
            {
                $req_lieu_aff = $connector->query("SELECT nom FROM lieu WHERE idLieu=" . $tab_aff['idAffiliation']);
                $tab_lieu_aff = $connector->fetchArray($req_lieu_aff);
                $nom_affiliation = $tab_lieu_aff['nom'];
            }

            $signature_auteur .= " (" . $nom_affiliation . ")";
        }

        return $signature_auteur;
    }


    public static function popupLink($uri, $nom, $largeur, $hauteur, $lien)
    {
        return "<a href=\"#\" onclick=\"window.open('" . $uri . "','" . $nom . "','height=" . $hauteur . "px,width=" . $largeur . "px,toolbar=no,menuBar=yes,location=no,directories=0,status=no,scrollbars=yes,resizable=yes,left=10,top=10');return(false)\" title=\"" . $nom . "\">" . $lien . "</a>";
    }
 

    public static function formLabel($tab_att, $nom)
    {
        $aff = "<label ";

        foreach ($tab_att as $att => $val)
        {
            if (!empty($val))
            {
                $aff .= $att . "=\"" . $val . "\" ";
            }
        }

        $aff .= ">" . $nom . "</label>";

        return $aff;
    }    

    public static function formInput($tab_att)
    {
        $aff = "<input ";

        foreach ($tab_att as $att => $val)
        {
            if (!empty($val))
            {
                $aff .= $att . "=\"" . $val . "\" ";
            }
        }

        $aff .= "/>";

        return $aff;
    }

    /**
     * Affiche un texte dans une balise div de la classe "msg" et une icone
     *
     * @param string $message Texte ? afficher
     */
    public static function msgInfo($message)
    {
        echo '<div class="msg_info">' . $message . '</div>';
    }
    
    /**
     * Affiche un texte dans une balise  de la classe "msg" et une icone OK
     *
     * @param string $message Texte ? afficher
     */
    public static function msgOk($message)
    {
        echo '<div class="msg_ok">' . $message . '</div>';
    }    


    /**
     * Affiche un texte dans une balise DIV de la classe "msg" et une icone d'erreur
     *
     * @param string $message Texte ? afficher
     */
    public static function msgErreur($message)
    {
        echo '<div class="msg_erreur">' . $message . '</div>';
    }
    
}
