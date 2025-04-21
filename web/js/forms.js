/*
 * @package ladecadanse
 * @copyright  Copyright (c) 2007 - 2025 Michel Gaudry <michel@ladecadanse.ch>
 * @license    AGPL License; see LICENSE file for details.
 */
'use strict';

$('.js-select2').select2(
        {
            language: "fr",
            allowClear: true,
            templateResult: function (data) {
                if (!data.id) return data.text;

                const $option = $(data.element);
                const style = $option.attr('style');

                const $result = $('<span>').text(data.text);
                if (style) $result.attr('style', style); // applique le style inline

                return $result;
            },
            templateSelection: function (data) {
                if (!data.id) return data.text;

                const $option = $(data.element);
                const style = $option.attr('style');

                const $selection = $('<span>').text(data.text);
                if (style) $selection.attr('style', style); // applique le style inline

                return $selection;
            }
        });

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
$('input.datepicker').Zebra_DatePicker({...ZebraDatepickerBasicConfig, ...inputDatepickerConfig});

const inputDatepickerFromConfig = {direction: [eventEditStartDate, false], pair: $('input.datepicker_to'), readonly_element: false};
$('input.datepicker_from').Zebra_DatePicker({...ZebraDatepickerBasicConfig, ...inputDatepickerFromConfig});

const inputDatepickerToConfig = {direction: 1, readonly_element: false};
$('input.datepicker_to').Zebra_DatePicker({...ZebraDatepickerBasicConfig, ...inputDatepickerToConfig});

