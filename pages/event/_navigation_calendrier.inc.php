<?php
// reset to default

if (empty($get['courant']))
{
    $get['courant'] = $glo_auj_6h;
}

$date_courante = new DateTime($get['courant']);

// Trouver le premier jour du mois
$first_day_of_month = (clone $date_courante)->modify('first day of this month');
// Trouver le dernier jour du mois
$last_day_of_month = (clone $date_courante)->modify('last day of this month');
$date_today = new DateTime();

// nb of ALL events after this month, published and in current region
$sql_eventsNextmonthsCount = "SELECT COUNT(idEvenement) AS nb "
    . " FROM evenement e "
    . "JOIN localite l ON e.localite_id = l.id WHERE dateEvenement > '".$last_day_of_month->format('Y-m-d')."' AND statut NOT IN ('inactif', 'propose')"; // USELESS REGION FILTERING DISABLED: AND (region IN ('" . $connector->sanitize($_SESSION['region']) . "', " . ($_SESSION['region'] == 'ge' ? "'rf'," : "") . " 'hs') OR FIND_IN_SET ('" . $connector->sanitize($_SESSION['region']) . "', l.regions_covered))  ";
$req_eventsNextmonthsCount = $connector->query($sql_eventsNextmonthsCount);
$res_eventsNextmonths = $connector->fetchArray($req_eventsNextmonthsCount);
$events_next_months_count = (int) $res_eventsNextmonths['nb'];

?>

<nav id="navigation_calendrier" data-page-courant="<?= htmlspecialchars($get['courant']) ?>">
    <table id="calendrier">
        <thead>
            <tr id="mois">
                <th>
                    <?php
                    // agenda started in sept. 2005
                    if ($date_courante > new DateTime("2005-09-01"))
                    {
                        $date_prev_month_last_day = (clone $date_courante)->modify('last day of -1 month')->format('Y-m-d');
                        ?>
                        <a href="/index.php?<?php echo $url_query_region_et . "courant=" . $date_prev_month_last_day  ?>" rel="prev" title="Mois précédent" aria-label="Mois précédent" class="js-calendar-nav" data-courant="<?= $date_prev_month_last_day ?>"><i class="fa fa-backward"></i></a>
                        <?php
                    }
                    ?>
                </th>
                <th id="mois_courant" colspan="5"><?php echo ucfirst((string) \Ladecadanse\Utils\DateHelper::monthName((int)$date_courante->format('n'))) . " " . $date_courante->format('Y') ?></th>
                <th>
                    <?php
                    if ($events_next_months_count > 0)
                    {
                        $date_next_month_first_day = (clone $date_courante)->modify('first day of +1 month')->format('Y-m-d');
                        ?>
                        <a href="/index.php?<?php echo $url_query_region_et . "courant=" . $date_next_month_first_day ?>" rel="next" title="Mois suivant" aria-label="Mois suivant" class="js-calendar-nav" data-courant="<?= $date_next_month_first_day ?>"><i class="fa fa-forward"></i></a>
                    <?php } ?>
                </th>
            </tr>

            <tr id="jours">
                <th>lun</th><th>mar</th><th>mer</th><th>jeu</th><th>ven</th><th>sam</th><th>dim</th>
            </tr>
        </thead>
        <tbody>
        <?php
        // Créer la période de dates jour par jour (always 6 rows = 42 days to prevent layout jumps)
        $period_start = (clone $first_day_of_month)->modify('monday this week');
        $period_end = (clone $period_start)->modify('+42 days');
        $period = new DatePeriod($period_start, new DateInterval('P1D'), $period_end);
        $selection_date = !empty($calendar_page_courant) ? new DateTime($calendar_page_courant) : $date_courante;
        foreach ($period as $day)
        {
            // lundi : prefixé d'un <td></td> contenant lien pour voir la semaine
            if ($day->format('N') == 1)
            {
                ?>
                <tr class="semaine">
            <?php
            }

            // mark past, today, current, week end, other month dates
            $jour_classes = [];
            $jour_ici = "";
            if ($day->format("Y-m-d") < $date_today->format("Y-m-d"))
            {
                $jour_classes[] = 'past';
            }

            if ($day->format("Y-m-d") == $date_today->format("Y-m-d"))
            {
                $jour_classes[] = 'auj';
            }

            if (in_array($day->format('w'), [6, 0]))
            {
                $jour_classes[] = 'sam';
            }

            if ($day < $first_day_of_month || $day > $last_day_of_month)
            {
                $jour_classes[] = 'autre_mois';
            }

            if (empty($calendar_no_selection) && $day->format('Y-m-d') == $selection_date->format('Y-m-d'))
            {
                $jour_ici = ' id="cal_ici"';
            }
            ?>
            <td <?= $jour_ici ?> class="<?= implode(" ", $jour_classes) ?>">
                <?php
                // à partir du mois suivants il n'y a plus du tout d'événements : lien inutile
                if ($day > $last_day_of_month && $events_next_months_count == 0)
                {
                    ?>
                    <span class="day-without-events"><?= $day->format('j') ?></span>
                <?php }
                else
                { ?>
                    <a href="/index.php?<?php echo $url_query_region_et . "courant=" . $day->format('Y-m-d') ?>"><?= $day->format('j') ?></a>
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
        </tbody>
    </table>

    <ul id="menu_calendrier">
        <?php if ($date_courante->format('Y-m') !== $selection_date->format('Y-m')) {
            $selection_is_before = $selection_date < $date_courante;
            $selection_date_formatted = $selection_date->format('Y-m-d');
            $selection_date_label = $selection_date->format('j') . ' ' . \Ladecadanse\Utils\DateHelper::monthName((int)$selection_date->format('n')) . ' ' . $selection_date->format('Y');
            $arrow_icon = $selection_is_before ? '<i class="fa fa-chevron-left"></i> ' : '';
            $arrow_icon_after = $selection_is_before ? '' : ' <i class="fa fa-chevron-right"></i>';
        ?>
            <li>
                <a href="/index.php?<?= $url_query_region_et ?>courant=<?= $selection_date_formatted ?>"
                   class="js-calendar-nav calendar-back-today"
                   data-courant="<?= $selection_date_formatted ?>"><?= $arrow_icon . $selection_date_label . $arrow_icon_after ?></a>
            </li>
        <?php } ?>
        <li>
            <form action="/index.php" method="get">
                <input type="date" name="courant" size="12" aria-label="Date"><input type="submit" class="submit" name="formulaire" value="OK" aria-label="Aller à cette date du calendrier">
            </form>
        </li>
    </ul>

    <div class="spacer"></div>

</nav>
<!-- Fin navigation_calendrier -->
