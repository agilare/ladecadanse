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

// readonly_element: false => le champ reste saisissable au clavier, le calendrier Zebra n'étant pas
// navigable au clavier (pas de tabindex ni de gestion des flèches dans la lib).
// Pas de `strict` : il viderait le champ à l'init pour un événement passé (date désactivée par
// `direction`) ; la validation de la date est faite côté serveur (evenement-edit.php)
const inputDatepickerConfig = {direction: [eventEditStartDate, false], readonly_element: false};
$('input.datepicker:not(.datepicker-always-visible)').Zebra_DatePicker({...ZebraDatepickerBasicConfig, ...inputDatepickerConfig});

// Calendrier "always visible" affiché en permanence sous le champ date, commandé côté serveur par le
// drapeau DATEPICKER_ALWAYS_VISIBLE_ENABLED (evenement-edit.php) : sans la classe ni #calendarDiv,
// rien ne s'initialise ici et le champ garde le calendrier surgissant du sélecteur ci-dessus.
// cf. https://stefangabos.github.io/Zebra_Datepicker/#always-visible
if ($('input.datepicker-always-visible').length && $('#calendarDiv').length) {
    const inputDatepickerAlwaysVisibleConfig = {direction: [eventEditStartDate, false], always_visible: $('#calendarDiv'), readonly_element: false};
    $('input.datepicker-always-visible').Zebra_DatePicker({...ZebraDatepickerBasicConfig, ...inputDatepickerAlwaysVisibleConfig});

    // le calendrier permanent ne se redessine pas quand on tape une date : set_date() n'appelle que
    // Ft(), qui se limite à propager la date au datepicker `pair` (inexistant ici). Seul show() relit
    // la valeur de l'input pour recalculer mois/année/jour et repeindre — c'est l'idiome interne de la
    // lib, qu'elle utilise elle-même à l'init du mode always_visible et pour rafraîchir un pair.
    $('input.datepicker-always-visible').on('change', function () {
        const datepicker = $(this).data('Zebra_DatePicker');
        if (datepicker && this.value.trim() !== '') {
            datepicker.show();
        }
    });

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

const confirmDuplicateCheckbox = document.querySelector('.duplicate-warning input[name="confirm_duplicate"]');
if (confirmDuplicateCheckbox) {
    const duplicateForm = confirmDuplicateCheckbox.closest('form');
    const duplicateSubmits = duplicateForm.querySelectorAll('input[type="submit"], button[type="submit"]');
    const syncDuplicateSubmits = () => {
        duplicateSubmits.forEach(button => { button.disabled = !confirmDuplicateCheckbox.checked; });
    };
    syncDuplicateSubmits();
    confirmDuplicateCheckbox.addEventListener('change', syncDuplicateSubmits);
}

/*
 * Bloc de saisie manuelle du lieu replié (evenement-edit.php), commandé côté serveur par le
 * drapeau LIEU_MANUAL_COLLAPSIBLE_ENABLED : sans lui le bloc reste un <div> déplié et rien
 * de ce qui suit ne s'attache.
 *
 * L'état d'ouverture au chargement est décidé par le serveur ; ici on ne fait que le mémoriser
 * dans un cookie et l'accorder avec la liste des lieux, les deux façons de désigner un lieu
 * étant exclusives (erreur « doublonLieux » à l'enregistrement).
 */
const lieuManuelDetails = document.querySelector('details#evenement-lieu-pastrouve');
if (lieuManuelDetails) {
    const LIEU_MANUEL_COOKIE = 'event_form_lieu_manual_open';
    // secure conditionnel : sur un poste de développement en http, un cookie 'secure' est rejeté
    // sans un mot, et le bloc paraîtrait ne jamais retenir son état
    const lieuManuelCookieOptions = '; path=/; samesite=lax' + (location.protocol === 'https:' ? '; secure' : '');
    // les champs du bloc ; #localite est un Select2, d'où le passage par jQuery pour tous
    const lieuManuelChamps = ['#nomLieu', '#adresse', '#localite', '#urlLieu'];
    const estRempli = ($champ) => {
        const valeur = $champ.val();

        return valeur !== null && valeur !== undefined && valeur !== '';
    };

    // Le cookie suit l'état du bloc, qu'on l'ait ouvert à la main ou que la liste des lieux l'ait
    // ouvert ou refermé : ce qu'il mémorise est « je saisis mes lieux à la main ».
    lieuManuelDetails.addEventListener('toggle', function memoriserOuvertureLieuManuel() {
        document.cookie = lieuManuelDetails.open
            ? LIEU_MANUEL_COOKIE + '=true; max-age=' + (365 * 24 * 3600) + lieuManuelCookieOptions
            : LIEU_MANUEL_COOKIE + '=; max-age=0' + lieuManuelCookieOptions;
    });

    // jQuery et non addEventListener : c'est Select2 qui émet ce change, par $().trigger(),
    // lequel n'atteint que les gestionnaires jQuery
    $('#idLieu').on('change', function accorderLieuManuelAvecListe() {
        if (this.value === '') {
            // plus de lieu dans la liste : il ne reste que la saisie à la main
            lieuManuelDetails.open = true;

            return;
        }

        // Le choix dans la liste, plus récent, l'emporte sur la saisie manuelle. Rien de rempli,
        // rien à effacer — et pas de bloc qui se referme sous les doigts.
        const champsRemplis = lieuManuelChamps.map(selecteur => $(selecteur)).filter(estRempli);
        if (champsRemplis.length === 0) {

            return;
        }

        champsRemplis.forEach($champ => $champ.val('').trigger('change'));
        lieuManuelDetails.open = false;
    });
}
