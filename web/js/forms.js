/*
 * @package ladecadanse
 * @copyright  Copyright (c) 2007 - 2025 Michel Gaudry <michel@ladecadanse.ch>
 * @license    AGPL License; see LICENSE file for details.
 */
'use strict';

$('.js-select2-options-with-style').select2(
        {
            language: "fr",
            allowClear: true,
            templateResult: select2ApplyOptionInlineStyle,
            templateSelection: select2ApplyOptionInlineStyle
        });

$('.js-select2-options-with-complement').select2(
        {
            language: "fr",
            allowClear: true,
            templateResult: select2OptionWithComplement,
            templateSelection: select2OptionWithComplement
        });


function select2ApplyOptionInlineStyle(item)
{
    if (!item.id)

        return item.text;

    const $option = $(item.element);
    const style = $option.attr('style');

    const $result = $('<span>').text(item.text);
    if (style)

        $result.attr('style', style); // applique le style inline

    return $result;
};

function select2OptionWithComplement(item)
{
    if (!item.id)
    {
        return item.text; // Placeholder ou élément vide
    }

    let $option = $(item.element);
    let nom = $option.data('nom');
    let complement = $option.data('complement');

    // Si ni data-nom ni data-complement => afficher normalement
    if (!nom && !complement)
    {
        return item.text;
    }

    // Nettoyer complement si c'est une URL (on enlève http:// ou https://)
    let complementAffiche = complement ? complement.replace(/^https?:\/\//, '') : '';

    let result = '<span>';
    if (nom)
    {
        result += `<span>${nom}</span>`;
    }
    if (complementAffiche)
    {
        result += ` <span style="font-size: 0.9em; color: #888;">${complementAffiche}</span>`;
    }
    result += '</span>';

    return $(result);
};

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

