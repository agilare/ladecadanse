<?php
/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace Ladecadanse;

use Ladecadanse\Stats\MonthlyAddedEvents;
use Ladecadanse\Utils\DateHelper;

class HtmlShrink
{
    /**
     * Reconstruit une query string à partir de $_GET, en excluant un ou
     * plusieurs paramètres. Utilisée pour les liens de pagination et de tri,
     * qui doivent conserver les autres critères de la page courante.
     *
     * Le résultat est échappé pour le HTML : il est destiné à un attribut href.
     *
     * Asymétrie connue : la branche "string" ignore les valeurs de type array,
     * la branche "array" non (elle émettrait "param=Array" + un warning). Aucun
     * appelant ne passe aujourd'hui de valeur array dans $get.
     *
     * @param array<string, mixed> $get
     * @param array<string>|string $sauf Nom(s) de paramètre(s) à exclure
     */
    public static function urlQueryArrayToString(array $get, array|string $sauf = ""): string
    {
        $afficher = "";

        if (!is_array($sauf))
        {
            foreach ($get as $nom => $valeur)
            {
                if ($nom != $sauf && !is_array($valeur))
                {
                    $afficher .= $nom . "=" . $valeur . "&";
                }
            }
        }
        else
        {
            foreach ($get as $nom => $valeur)
            {
                if (!in_array($nom, $sauf))
                {
                    $afficher .= $nom . "=" . $valeur . "&";
                }
            }
        }

        // -5 et non -1 : sanitizeForHtml() a transformé le "&" final en "&amp;"
        return mb_substr(sanitizeForHtml($afficher), 0, -5);
    }

    /**
     * TODO: mv to a LieuRenderer
     */
    public static function adresseCompacteSelonContexte(?string $region, string $localite, string $quartier, string $adresse): string
    {
        $result = $adresse;

        // (Plainpalais) "autre" is unecessary
        if (!empty($quartier) && $quartier != 'autre')
        {
            $result .= " (" . $quartier . ")";
        }

        // Une localité fourre-tout (« Ailleurs en France », « Hors Genève, Vaud et France »)
        // n'a de sens que dans le <select> qui la propose : elle n'apprendrait rien de plus que
        // la région ajoutée juste après. Le quartier « Genève » et la localité « Genève » ne se
        // répètent pas non plus.
        if (!empty($localite) && !Localite::estFourreTout($localite) && $quartier != $localite)
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
                    $v = 'Vaud';
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
            <a href="?region=<?php echo $n; ?>&<?php echo self::urlQueryArrayToString($get, $excludeFromQueryString); ?>" class="<?php echo $class_region; ?><?php echo $ici; ?>"><?php echo $v; ?>&nbsp;<?php
                if (!empty($event_nb[$n]))
                {
                    ?><span class="events-nb"><?php echo $event_nb[$n]; ?></span><?php } ?></a></li><?php
                }
        }
        ?></ul>
        <?php
        return ob_get_contents();
    }

    /**
     * Filtre « Localité » de la liste des lieux.
     *
     * Les groupes viennent tous de la table `localite` : France et « Autre », greffées en dur
     * ici tant qu'elles n'étaient que des régions, y sont désormais des localités à part
     * entière (cantons 'rf' et 'hs').
     *
     * $localitesWithLieux ne retient que les localités où la région courante a effectivement un
     * lieu : un canton qui n'en garde aucun n'ouvre pas de groupe vide.
     */
    public static function getLocalitesSelect(array $regions_localites, array $glo_regions, array $localitesWithLieux = []): string
    {
        ob_start();
        ?>
        <select  name="localite" class="js-select2-options-with-style" data-placeholder="Localité" style="width:100px">
            <option value=""></option>
            <?php foreach ($regions_localites as $region => $localites) : ?>
                <?php
                if (!empty($localitesWithLieux))
                {
                    $localites = array_filter($localites, static fn (array $loc): bool => in_array($loc['id'], $localitesWithLieux));
                }
                ?>
                <?php if (empty($localites)) { continue; } ?>
                <optgroup label="<?= $glo_regions[$region] ?>">
                    <?php foreach ($localites as $loc) : ?>
                        <option value="<?= $loc['id'] ?>" <?php if ($_SESSION['user_prefs_lieux_localite'] == $loc['id']) : ?>selected="selected"<?php endif; ?>><?= $loc['localite'] ?></option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
        <?php
        $result = ob_get_contents();
        ob_clean();
        return $result;
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
                $pagination .= "<a id=\"prec\" href=\"$targetpage$pagestring$prev\" rel=\"prev\">préc</a>";
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
                $pagination .= "<a id=\"suiv\"  href=\"$targetpage$pagestring$next\" rel=\"next\">suiv</a>";
            else
                $pagination .= "<span class=\"disabled\">suiv</span>";
            $pagination .= "</div>\n";
        }

        return $pagination;
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
     * En-têtes des colonnes mensuelles des listes d'administration : « jan », « fév »…
     *
     * L'année n'apparaît qu'en infobulle : la fenêtre de douze mois chevauche deux années, et
     * l'écrire dans chaque colonne mangerait la place que ces colonnes étroites n'ont pas.
     *
     * @param list<string> $monthKeys clés 'Y-m', voir Stats\MonthlyAddedEvents::monthKeys()
     */
    public static function getMonthlyCountsHeaderCells(array $monthKeys): string
    {
        $cells = "";

        foreach ($monthKeys as $monthKey)
        {
            $month = (int) substr($monthKey, 5, 2);
            $title = DateHelper::monthName($month) . " " . substr($monthKey, 0, 4);

            $cells .= '<th class="mois" title="' . sanitizeForHtml($title) . '">'
                . sanitizeForHtml(DateHelper::monthNameShort($month)) . '</th>';
        }

        return $cells;
    }

    /**
     * Cellules mensuelles d'une ligne : le nombre d'événements ajoutés ce mois-là, et en dessous
     * celui du même mois un an plus tôt.
     *
     * Un mois sans ajout laisse sa place vide plutôt que d'afficher « 0 » : dans une bande de
     * douze colonnes, ce qu'on cherche est la silhouette de l'activité, que douze zéros brouillent.
     * Le saut de ligne, lui, est écrit dans tous les cas : sans lui, les cellules sans rappel
     * seraient plus courtes que les autres et la bande perdrait sa ligne de base.
     *
     * @param array<string, int> $countsByMonth clés 'Y-m' couvrant les mois affichés *et* les
     *                                          douze précédents, mois sans ajout absents
     * @param list<string>       $monthKeys     mois affichés, du plus ancien au plus récent
     */
    public static function getMonthlyCountsCells(array $countsByMonth, array $monthKeys): string
    {
        $cells = "";
        $currentMonthKey = end($monthKeys);

        foreach ($monthKeys as $monthKey)
        {
            // les mois révolus sont atténués, comme les événements passés du tableau de bord :
            // le mois en cours est le seul encore susceptible de bouger
            $class = $monthKey === $currentMonthKey ? "mois" : "mois mois-passe";

            $nb = $countsByMonth[$monthKey] ?? 0;
            $nbPreviousYear = $countsByMonth[MonthlyAddedEvents::previousYearKey($monthKey)] ?? 0;

            $rappel = $nbPreviousYear > 0
                ? '<span class="mois-an-passe">' . (int) $nbPreviousYear . '</span>'
                : '&nbsp;';

            $cells .= '<td class="' . $class . '">' . ($nb > 0 ? (int) $nb : '') . '<br>' . $rappel . '</td>';
        }

        return $cells;
    }

    public static function msgInfo(string $message): void
    {
        echo '<div class="msg_info">' . $message . '</div>';
    }

    public static function msgOk(string $message): void
    {
        echo '<div class="msg_ok">' . $message . '</div>';
    }

    public static function msgErreur(string $message): void
    {
        echo '<div class="msg_erreur">' . $message . '</div>';
    }

    public static function showLinkRss(string $nom_page): void
    {
        if ($nom_page == "index")
        {
        ?>
            <link rel="alternate" type="application/rss+xml" title="Événements du jour" href="<?= SITE_CANONICAL_URL ?>/event/rss.php?type=evenements_auj">
            <link rel="alternate" type="application/rss+xml" title="Derniers événements ajoutés" href="<?= SITE_CANONICAL_URL ?>/event/rss.php?type=evenements_ajoutes">
        <?php
        }

        // $nom_page vaut « dossier/fichier » (voir bootstrap.php) : la comparaison avec « lieu »
        // n'a jamais été vraie, la balise du flux de lieu n'était donc pas émise
        if ($nom_page == "lieu/lieu" && isset($_GET['idL']))
        {
        ?>
            <link rel="alternate" type="application/rss+xml" title="Prochains événements dans ce lieu" href="<?= SITE_CANONICAL_URL ?>/event/rss.php?type=lieu_evenements&amp;id=<?php echo intval($_GET['idL']) ?>">
        <?php
        }
    }
}
