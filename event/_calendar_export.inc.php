<?php
/**
 * Calendar export dropdown menu.
 *
 * Required variable: $calLinks (from EvenementCalendarRenderer::getLinks())
 * Optional variable: $calExportCompact (bool) - icon-only trigger with tooltip (default: false, shows full label)
 * Optional variable: $calExportId (string) - unique suffix for menu id (required when multiple on same page)
 */

$menuId = 'calendar-export-menu' . (!empty($calExportId) ? '-' . $calExportId : '');
?>
<li class="calendar-export-wrapper">
    <?php if (!empty($calExportCompact)) : ?>
        <a href="#" class="ical dropdown" data-target="<?= $menuId ?>" title="Ajouter à un agenda"><i class="fa fa-calendar-plus-o fa-lg"></i></a>
    <?php else : ?>
        <a href="#" class="dropdown" data-target="<?= $menuId ?>"><i class="fa fa-calendar-plus-o fa-lg"></i>&nbsp;Ajouter à un agenda&nbsp;<i class="fa fa-caret-down" aria-hidden="true"></i></a>
    <?php endif; ?>
    <ul id="<?= $menuId ?>" class="calendar-export-menu" style="display:none;">
        <li><a href="<?= $calLinks['google'] ?>" target="_blank" rel="noopener"><i class="fa fa-google fa-fw"></i>&nbsp;Google Calendar</a></li>
        <li><a href="<?= $calLinks['outlook'] ?>" target="_blank" rel="noopener"><i class="fa fa-windows fa-fw"></i>&nbsp;Outlook.com</a></li>
        <li><a href="<?= $calLinks['office365'] ?>" target="_blank" rel="noopener"><i class="fa fa-building-o fa-fw"></i>&nbsp;Microsoft 365</a></li>
        <li><a href="<?= $calLinks['yahoo'] ?>" target="_blank" rel="noopener"><i class="fa fa-yahoo fa-fw"></i>&nbsp;Yahoo</a></li>
        <li><a href="<?= $calLinks['ical'] ?>"><i class="fa fa-apple fa-fw"></i>&nbsp;iCal / Apple</a></li>
    </ul>
</li>
