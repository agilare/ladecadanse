<?php
// reset to default

if (empty($get['courant']))
{
    $get['courant'] = $glo_auj_6h;
}

if (empty($get['genre']))
{
    $get['genre'] = '';
}

if (empty($get['tri_agenda']))
{
    $get['tri_agenda'] = "dateAjout";
}

$get['sem'] = (int) ($get['sem'] ?? 0);

$dateCourant = new DateTime($get['courant']);

// Trouver le premier jour du mois
$firstDayOfMonth = (clone $dateCourant)->modify('first day of this month');
// Trouver le dernier jour du mois
$lastDayOfMonth = (clone $dateCourant)->modify('last day of this month');
// Aller au dimanche de la semaine ISO contenant ce jour
$sundayOfLastWeek = (clone $lastDayOfMonth)->modify('sunday this week');

$dateToday = new DateTime();

// nb of ALL events after this month, published and in current region
$sql_eventsNextmonthsCount = "SELECT COUNT(idEvenement) AS nb "
    . " FROM evenement e "
    . "JOIN localite l ON e.localite_id = l.id WHERE dateEvenement > '".$lastDayOfMonth->format('Y-m-d')."' AND statut NOT IN ('inactif', 'propose') AND (region IN ('" . $connector->sanitize($_SESSION['region']) . "', " . ($_SESSION['region'] == 'ge' ? "'rf'," : "") . " 'hs') OR FIND_IN_SET ('" . $connector->sanitize($_SESSION['region']) . "', l.regions_covered))  ";
$req_eventsNextmonthsCount = $connector->query($sql_eventsNextmonthsCount);
$res_eventsNextmonths = $connector->fetchArray($req_eventsNextmonthsCount);
$eventsNextmonthsCount = (int) $res_eventsNextmonths['nb'];

$urlForwardedParameters = ($get['genre'] !== '' ? "&amp;genre=" . $get['genre'] : "") . (!empty($get['sem']) ? "&amp;sem=" . $get['sem'] : "") . ($get['tri_agenda'] !== 'dateAjout' ? "&amp;tri_agenda=" . $get['tri_agenda'] : "");
?>

<div id="navigation_calendrier" >
    <table id="calendrier">
        <thead>
            <tr id="mois">
                <th>
                    <?php
                    // agenda started in sept. 2005
                    if ($dateCourant > new DateTime("2005-09-01"))
                    {
                        $dateMoisPrecDernierJour = (clone $dateCourant)->modify('last day of -1 month')->format('Y-m-d');
                        ?>
                        <a href="/evenement-agenda.php?<?php echo $url_query_region_et . "courant=" . $dateMoisPrecDernierJour . $urlForwardedParameters ?>" title="Mois précédent" ><i class="fa fa-backward"></i></a>
                        <?php
                    }
                    ?>
                </th>
                <th id="mois_courant" colspan="6"><?php echo ucfirst((string) mois2fr($dateCourant->format('n'))) . " " . $dateCourant->format('Y') ?></th>
                <th>
                    <?php
                    if ($eventsNextmonthsCount > 0)
                    {
                        $dateMoisSuivPremierJour = (clone $dateCourant)->modify('first day of +1 month')->format('Y-m-d');
                        ?>
                        <a href="/evenement-agenda.php?<?php echo $url_query_region_et . "courant=" . $dateMoisSuivPremierJour . $urlForwardedParameters ?>" title="Mois suivant"><i class="fa fa-forward"></i></a>
                    <?php } ?>
                </th>
            </tr>

            <tr id="jours">
                <th></th><th>lun</th><th>mar</th><th>mer</th><th>jeu</th><th>ven</th><th>sam</th><th>dim</th>
            </tr>
        </thead>
        <tbody>
        <?php
        // Créer la période de dates jour par jour
        $period = new DatePeriod((clone $firstDayOfMonth)->modify('monday this week'), new DateInterval('P1D'), (clone $lastDayOfMonth)->modify('sunday this week')->modify('+1 day'));
        foreach ($period as $day)
        {
            // lundi : prefixé d'un <td></td> contenant lien pour voir la semaine
            if ($day->format('N') == 1)
            {
                ?>
                <tr class="semaine <?php if ($get['sem'] == 1 && $dateCourant->format('oW') === $day->format('oW')) { echo " semaine_ici"; } ?>">
                    <td><a href="/evenement-agenda.php?<?php echo $url_query_region_et ?>courant=<?php echo $day->format('Y-m-d'). "&amp;sem=1" . ($get['tri_agenda'] !== 'dateAjout' ? "&amp;tri_agenda=" . $get['tri_agenda'] : " ") ?>" title="Toute la semaine"><i class="fa fa-caret-right"></i></a>
                    </td>
            <?php
            }

            // mark past, today, current, week end, other month dates
            $jour_classes = [];
            $jour_ici = "";
            if ($day->format("Y-m-d") < $dateToday->format("Y-m-d"))
            {
                $jour_classes[] = 'past';
            }

            if ($day->format("Y-m-d") == $dateToday->format("Y-m-d"))
            {
                $jour_classes[] = 'auj';
            }

            if (in_array($day->format('w'), [6, 0]))
            {
                $jour_classes[] = 'sam';
            }

            if ($day < $firstDayOfMonth || $day > $lastDayOfMonth)
            {
                $jour_classes[] = 'autre_mois';
            }

            if ($day == $dateCourant && $get['sem'] != 1)
            {
                $jour_ici = ' id="cal_ici"';
            }
            ?>
            <td <?= $jour_ici ?> class="<?= implode(" ", $jour_classes) ?>">
                <?php
                // à partir du mois suivants il n'y a plus du tout d'événements : lien inutile
                if ($day > $lastDayOfMonth && $eventsNextmonthsCount == 0)
                {
                    ?>
                    <span class="day-without-events"><?= $day->format('j') ?></span>
                <?php }
                else
                { ?>
                    <a href="/evenement-agenda.php?<?php echo $url_query_region_et . "courant=" . $day->format('Y-m-d') . ($get['tri_agenda'] !== 'dateAjout' ? "&amp;tri_agenda=" . $get['tri_agenda'] : " ") . ($get['genre'] !== '' ? "&amp;genre=" . $get['genre'] : "") ?>"><?= $day->format('j') ?></a>
                <?php } ?>
            </td>
            <?php
            if ($day->format('N') == 7)
            {
            ?>
                </tr>
            <?php
            }
        }
        ?>
        <tbody>
    </table>

    <ul id="menu_calendrier">
        <li id="demain">
            <a href="/evenement-agenda.php?<?php echo $url_query_region_et . "courant=" . (clone $dateToday)->modify('+1 day')->format('Y-m-d'). $urlForwardedParameters ?>">Demain</a>
        </li>
        <li id="cette_semaine">
            <a href="/evenement-agenda.php?<?php echo $url_query_region_et
            . "courant=" . $dateToday->format('Y-m-d')
            . ($get['genre'] !== '' ? "&amp;genre=" . $get['genre'] : "")
            . "&amp;sem=1" . ($get['tri_agenda'] !== 'dateAjout' ? "&amp;tri_agenda=". $get['tri_agenda'] : " ")
            ?>">Cette semaine</a>
        </li>
        <li>
            <form action="/evenement-agenda.php" method="get">
                <?php if ($get['tri_agenda'] !== 'dateAjout')
                {
                    ?>
                    <input type="hidden" name="tri_agenda" value="<?= $get['tri_agenda'] ?>" />
                <?php } ?>
                <input type="date" name="courant" size="12" /><input type="submit" class="submit" name="formulaire" value="OK" />
            </form>
        </li>
    </ul>

    <div class="spacer"></div>

</div>
<!-- Fin navigation_calendrier -->
