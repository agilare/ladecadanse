<?php
/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace Ladecadanse;

use Ladecadanse\Utils\Utils;

class HtmlShrink
{

    public static function adresseCompacteSelonContexte(?string $region, string $localite, string $quartier, string $adresse): string
    {
        $result = $adresse;

        // (Plainpalais) "autre" is unecessary
        if (!empty($quartier) && $quartier != 'autre')
        {
            $result .= " (" . $quartier . ")";
        }

        // avoid unecessary "Autre" and redundancy of quartier "Genève" and localite "Genève"
        if (!empty($localite) && $localite != 'Autre' && $quartier != $localite)
        {
            $result .= " - " . $localite;
        }

        if ($region == 'ge' && $localite != 'Genève')
        {
            $result .= " - Genève";
        }
        elseif ($region == 'vd')
        {
            $result .= " - Vaud";
        }
        elseif ($region == 'rf')
        {
            $result .= " - France";
        }

        return $result;
    }

    public static function getMenuRegions(array $glo_regions, $get, $event_nb = []): string
    {
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

                    $v = 'Fribourg';
                    $class_region = 'fr';
                }

                $ici = '';
                if ($n == $_SESSION['region'])
                    $ici = ' ici';

                $excludeFromQueryString = ['region'];

                if (!empty($get['page']) && $get['page'] == 1)
                {
                    $excludeFromQueryString[] = 'page';
                }

                if (!empty($get['nblignes']) && $get['nblignes'] == 50)
                {
                    $excludeFromQueryString[] = 'nblignes';
                }

                if (empty($get['idL']))
                {
                    $excludeFromQueryString[] = 'idL';
                }

                ?><li>
            <a href="?region=<?php echo $n; ?>&<?php echo Utils::urlQueryArrayToString($get, $excludeFromQueryString); ?>" class="<?php echo $class_region; ?><?php echo $ici; ?>"><?php echo $v; ?>&nbsp;<?php
                if (!empty($event_nb[$n]))
                {
                    ?><span class="events-nb"><?php echo $event_nb[$n]; ?></span><?php } ?></a></li><?php
                }
        }
        ?></ul>
        <?php
        return ob_get_contents();
    }

    public static function getPaginationString($totalitems, int $page = 1, int $limit = 15, int $adjacents = 1, $targetpage = "/", $pagestring = "?page="): string
    {
        //defaults
        if (!$adjacents)
            $adjacents = 1;
        if (!$limit)
            $limit = 15;
        if (!$page)
            $page = 1;

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


    public static function authorSignatureForHtml(int $idPersonne): string
    {

        global $connector;

        $signature_auteur = "";
        $sql_auteur = "SELECT pseudo, affiliation, signature, avec_affiliation
        FROM personne WHERE idPersonne=" . $idPersonne . "";

        $req_auteur = $connector->query($sql_auteur);
        $tab_auteur = $connector->fetchArray($req_auteur);

        if ($tab_auteur['signature'] == 'pseudo')
        {
            $signature_auteur = "<strong>" . sanitizeForHtml($tab_auteur['pseudo']) . "</strong>";
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
                $req_lieu_aff = $connector->query("SELECT nom FROM lieu WHERE idLieu=" . (int) $tab_aff['idAffiliation']);
                $tab_lieu_aff = $connector->fetchArray($req_lieu_aff);
                $nom_affiliation = $tab_lieu_aff['nom'];
            }

            $signature_auteur .= " (" . sanitizeForHtml($nom_affiliation) . ")";
        }

        return $signature_auteur;
    }

    public static function formLabel(array $tab_att, string $nom): string
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

    public static function formInput(array $tab_att): string
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
    public static function msgInfo(string $message): void
    {
        echo '<div class="msg_info">' . $message . '</div>';
    }

    /**
     * Affiche un texte dans une balise  de la classe "msg" et une icone OK
     *
     * @param string $message Texte ? afficher
     */
    public static function msgOk(string $message): void
    {
        echo '<div class="msg_ok">' . $message . '</div>';
    }


    /**
     * Affiche un texte dans une balise DIV de la classe "msg" et une icone d'erreur
     *
     * @param string $message Texte ? afficher
     */
    public static function msgErreur(string $message): void
    {
        echo '<div class="msg_erreur">' . $message . '</div>';
    }

    public static function showLinkRss(string $nom_page): void
    {
        if ($nom_page == "index")
        {
        ?>
            <link rel="alternate" type="application/rss+xml" title="Événements du jour" href="/rss.php?type=evenements_auj">
            <link rel="alternate" type="application/rss+xml" title="Derniers événements ajoutés" href="/rss.php?type=evenements_ajoutes">
        <?php
        }

        if ($nom_page == "lieu")
        {
        ?>
            <link rel="alternate" type="application/rss+xml" title="Prochains événements dans ce lieu" href="/rss.php?type=lieu_evenements&amp;id=<?php echo intval($_GET['idL']) ?>">
        <?php
        }
    }

    public static function getLinkAroundImg($aHref, $aClasses, $imgSrc, $imgWidth, $imgAlt)
    {
            ?>
        	<a href="<?php echo $aHref ?>" class="<?php echo $aClasses ?>" target="_blank">
        		<img src="<?php echo $imgSrc ?>" width="<?php echo $imgWidth ?>"  alt="<?php echo $imgAlt ?>">
        	</a>
        <?php
    }

    /**
     * legacy method with LIKE (September 2007 - July 2025)
     */
    public static function getSearchByPertinenceList(array $events_with_score, $no_score, $results_per_page, $idE_trouves, $get): string
    {
        global $connector;
        ob_start();

        foreach ($events_with_score as $no => $score)
        {
            if ($no_score >= (($get['page'] - 1)*$results_per_page) && $no_score <= (($get['page'] -1 )*$results_per_page + $results_per_page))
            {
                mysqli_data_seek($req_even, 0);

                while ($tab_even = $connector->fetchArray($req_even))
                {

                    if ($tab_even['idEvenement'] == $no)
                    {
                        $idE_trouves .= $no.";";

                        //Affichage du lieu selon son existence ou non dans la base
                        if ($tab_even['idLieu'] != 0)
                        {
                            $listeLieu = $connector->fetchArray(
                            $connector->query("SELECT nom, adresse, quartier, determinant, URL FROM lieu
                            WHERE idlieu='".(int) $tab_even['idLieu']."'"));

                            $salle = '';
                            if ($tab_even['idSalle'] != 0)
                            {
                            $tab_salle = $connector->fetchArray($connector->query("SELECT nom from salle where idSalle=".(int) $tab_even['idSalle']));
                            $salle = " - ".$tab_salle['nom'];
                            }
                            $infosLieu = $listeLieu['determinant'] . " <a href=\"/lieu.php?idL=" . (int) $tab_even['idLieu'] . "\" title=\"Voir la fiche du lieu : " . sanitizeForHtml($listeLieu['nom']) . "\" >" . sanitizeForHtml($listeLieu['nom']) . "</a>" . $salle;
                        }
                        else
                        {

                            $listeLieu['nom'] = sanitizeForHtml($tab_even['nomLieu']);
                        $infosLieu = sanitizeForHtml($tab_even['nomLieu']);
                        }
                    ?>
                        <tr

                        <?php

                            if ($tab_even['dateEvenement'] < $glo_auj_6h)
                            {
                                echo " class=\"ancien\"";
                        }
                            else if ($tab_even['dateEvenement'] == $glo_auj_6h)
                            {
                                echo " class=\"auj\"";
                        }
                            else if ($tab_even['dateEvenement'] > $glo_auj_6h)
                            {
                                echo " class=\"futur\"";
                        }

                        ?>>

                            <td class="desc_even">
                            <?php
                            $titre = $tab_even['titre'];
                            ?>
                                <h3><a href="/evenement.php?idE=<?php echo (int) $tab_even['idEvenement'] ?>" title="Voir la fiche de l'événement"><?php echo sanitizeForHtml($titre) ?></a></h3>
                            <?php
                            $maxChar = Text::trouveMaxChar($tab_even['description'], 50, 4);
                            if (mb_strlen((string) $tab_even['description']) > $maxChar)
                            {
                                $texte_court = Text::texteHtmlReduit(Text::wikiToText(sanitizeForHtml($tab_even['description'])), $maxChar, "");
                            }
                            else
                            {
                                $texte_court = Text::wikiToHtml(sanitizeForHtml($tab_even['description']));
                            }

                            echo "<p class=\"description\">".$texte_court."</p>";
                            echo "<p>".$infosLieu."</p>";
                        ?>
                        </td>
                        <td><a href="index.php?courant=<?php echo $tab_even['dateEvenement']; ?>"><?php echo date_iso2app($tab_even['dateEvenement']) ?></a></td>
                        <td><?php echo $glo_tab_genre[$tab_even['genre']] ?></td>

                        <?php if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 1) { ?>
                        <td><?php echo $score ?></td>
                        <?php } ?>
                        <td><!-- -->
                        <?php
                        if (
                        (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 6)
                        || (isset($_SESSION['SidPersonne']) && $_SESSION['SidPersonne'] == $tab_even['idPersonne'])
                        || (isset($_SESSION['Saffiliation_lieu']) && !empty($tab_even['idLieu']) && $tab_even['idLieu'] == $_SESSION['Saffiliation_lieu'])
                        ) {
                        ?>

                        <a href="/evenement-edit.php?action=editer&amp;idE=<?php echo (int) $tab_even['idEvenement'] ?>" title="Éditer cet événement"><?php echo $iconeEditer; ?></a>
                        <?php } ?>
                        </td>
                    </tr>
            <?php
                    }

                }
            }
            $no_score++;
        } //foreach ($tab_res as $id =>
        ob_clean();
        return ob_get_contents();
    }

}
