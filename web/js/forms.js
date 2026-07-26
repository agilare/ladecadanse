/*
 * @package ladecadanse
 * @copyright  Copyright (c) 2007 - 2025 Michel Gaudry <michel@ladecadanse.ch>
 * @license    AGPL License; see LICENSE file for details.
 */
'use strict';

// users can add events for today, until 06h the day after, in line with the agenda
const nbHoursAfterMidnightForDay = 6;
let d = new Date();
d.setHours(d.getHours() - nbHoursAfterMidnightForDay);
const eventEditStartDate = d.getDate() + '.' + (d.getMonth() + 1) + '.' + d.getFullYear();

let ZebraDatepickerBasicConfig = {
    format: 'd.m.Y',
    zero_pad: true,
    days: ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'],
    months: ['Janvier', 'F&eacute;vrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Ao&ucirc;t', 'Septembre', 'Octobre', 'Novembre', 'D&eacute;cembre'],
    show_clear_date: true,
    lang_clear_date: 'Effacer',
    show_select_today: 'Aujourd’hui'
};

const inputDatepickerConfig = {direction: [eventEditStartDate, false]};
$('input.datepicker:not(.datepicker-always-visible)').Zebra_DatePicker({...ZebraDatepickerBasicConfig, ...inputDatepickerConfig});

// POC (réservé aux SUPERADMIN) : calendrier "always visible" affiché en permanence sous le champ date
// cf. https://stefangabos.github.io/Zebra_Datepicker/#always-visible
if ($('input.datepicker-always-visible').length && $('#calendarDiv').length) {
    const inputDatepickerAlwaysVisibleConfig = {direction: [eventEditStartDate, false], always_visible: $('#calendarDiv')};
    $('input.datepicker-always-visible').Zebra_DatePicker({...ZebraDatepickerBasicConfig, ...inputDatepickerAlwaysVisibleConfig});

    // le calendrier de la librairie est en position:absolute ; on ajuste dynamiquement la hauteur
    // du conteneur (position:relative) sur celle réellement rendue, pour éviter tout espace vide
    // résiduel (le nombre de semaines affichées varie selon le mois, et la largeur/le retour à la
    // ligne du pied de page varient entre desktop et mobile)
    const calendarDiv = document.getElementById('calendarDiv');
    const syncCalendarHeight = () => {
        const calendar = calendarDiv.querySelector('.Zebra_DatePicker');
        calendarDiv.style.height = calendar ? calendar.offsetHeight + 'px' : '';
    };
    new MutationObserver(syncCalendarHeight).observe(calendarDiv, {childList: true, subtree: true});
    window.addEventListener('resize', syncCalendarHeight);
    syncCalendarHeight();
}

const inputDatepickerFromConfig = {direction: [eventEditStartDate, false], pair: $('input.datepicker_to'), readonly_element: false};
$('input.datepicker_from').Zebra_DatePicker({...ZebraDatepickerBasicConfig, ...inputDatepickerFromConfig});

const inputDatepickerToConfig = {direction: 1, readonly_element: false};
$('input.datepicker_to').Zebra_DatePicker({...ZebraDatepickerBasicConfig, ...inputDatepickerToConfig});

