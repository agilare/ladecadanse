// users can add events for today, until 06h the day after, in line with the agenda
const nbHoursAfterMidnightForDay = 6;
let d = new Date();
d.setHours(d.getHours() - nbHoursAfterMidnightForDay);
const eventEditStartDate = d.getDate() + "." + (d.getMonth() + 1) + "." + d.getFullYear();

$('input.datepicker').Zebra_DatePicker({
    direction: [eventEditStartDate, false],
    format: 'd.m.Y',
    zero_pad: true,
    days: ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'],
    months: ['Janvier', 'F&eacute;vrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Ao&ucirc;t', 'Septembre', 'Octobre', 'Novembre', 'D&eacute;cembre'],
    show_clear_date: true,
    lang_clear_date: "Effacer",
    show_select_today: "Aujourd’hui"
});

$('input.datepicker_from').Zebra_DatePicker({
    direction: [eventEditStartDate, false],
    pair: $('input.datepicker_to'),
    format: 'd.m.Y',
    zero_pad: true,
    days: ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'],
    months: ['Janvier', 'F&eacute;vrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Ao&ucirc;t', 'Septembre', 'Octobre', 'Novembre', 'D&eacute;cembre'],
    show_clear_date: true,
    lang_clear_date: "Effacer",
    show_select_today: "Aujourd’hui",
    readonly_element: false
});

$('input.datepicker_to').Zebra_DatePicker({
    direction: 1,
    format: 'd.m.Y',
    zero_pad: true,
    days: ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'],
    months: ['Janvier', 'F&eacute;vrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Ao&ucirc;t', 'Septembre', 'Octobre', 'Novembre', 'D&eacute;cembre'],
    show_clear_date: true,
    lang_clear_date: "Effacer",
    show_select_today: "Aujourd’hui",
    readonly_element: false
});

$(".chosen-select").chosen({
    allow_single_deselect: true,
    no_results_text: "Aucun &eacute;l&eacute;ment correspondant n'a &eacute;t&eacute; trouv&eacute;",
    include_group_label_in_selected: true,
    search_contains: true
});

$('.file-upload-size-max').bind('change', function ()
{

    if (this.files[0].size > 2097152)
    {
        alert("La taille du fichier que vous avez sélectionné dépasse la limite autorisée (2 Mo), merci d'en choisir un plus léger");
    }
});


jQuery(function($) {
    $('.jquery-checkboxes').checkboxes('range', true);
});

tinymce.init({
    selector: 'textarea.tinymce',
    height: 500,
    menubar: false,
    plugins: [
        'autolink lists link charmap',
        'searchreplace visualblocks code',
        'paste code help wordcount'
    ],
    toolbar: 'bold italic link | h4 bullist numlist blockquote | undo redo | visualblocks removeformat code',
    content_css: [
        '//fonts.googleapis.com/css?family=Lato:300,300i,400,400i',
        '//www.tiny.cloud/css/codepen.min.css'
    ]
});
