import { SetCookie } from './browser.js';

export const vitesse_fondu = 400;

export function bindEventsOfVariousInteractions()
{
    $('.dropdown').click(function ()
    {
        $('#' + $(this).data('target')).toggle();
        return false;
    });

    $('.btn_toggle').on('click', function ()
    {
        $('.element_toggle').toggle();
        //return false;
    });

    $('#show-description-btn').on('click', function ()
    {
        showhide('description', 'presentation');
        return false;
    });

    $('#show-presentation-btn').on('click', function ()
    {
        showhide('presentation', 'description');
        return false;
    });
}

/**
 * only used in mobile view
 * @returns {undefined}
 */
export function bindEventsOfMainNavigation ()
{
    $('#btn_menu_pratique').on('click', function (e)
    {
        e.preventDefault();

        if (!$('#menu_pratique').is(':visible'))
        {
            $('#menu_pratique').fadeIn(vitesse_fondu);
            //$('#main_menu').toggle(vitesse_fondu);
        }
        else
        {
            $('#menu_pratique').fadeOut(vitesse_fondu);
            //$('#main_menu').toggle(vitesse_fondu);
        }
    });

    $('#btn_calendrier').click(function ()
    {
        $('#navigation_calendrier').toggle();
        return false;
    });

    $('#btn_search').on('click', function ()
    {
        $('.recherche_mobile').toggle(400);
        //return false;
    });
}


export function bindEventsOfForms()
{
    const MAX_UPLOAD_SIZE_IN_BYTES = 2097152;
    $('.file-upload-size-max').on('change', function ()
    {
        if (this.files[0].size > MAX_UPLOAD_SIZE_IN_BYTES)
        {
            alert('La taille du fichier que vous avez sélectionné dépasse la limite autorisée (2 Mo), merci d’en choisir un plus léger');
        }
    });

    //$('#prix-precisions').hide();
    $('.precisions').on('change', function ()
    {
        if (this.checked && (this.value == 'asyouwish' || this.value == 'chargeable'))
        {
            $('#prix-precisions').show();
            $('#prix-precisions #prix').focus();
        }
        else
        {
            $('#prix-precisions').hide();
            $('#prix-precisions #prix, #prix-precisions #prelocations').val('');
            this.focus();
        }
    });

    $('form.submit-freeze-wait').submit(function ()
    {
        $('input[type="submit"]', this).val('Envoi...').attr('disabled', 'disabled');
        return true;
    });
}

// pages specific

export function bindHomeEvents()
{
    // browser.js
    $('#home-tmp-banner-close-btn').on('click', function ()
    {
        const HOME_TMP_BANNER_COOKIE_DURATION_IN_DAYS = 180;
        SetCookie('msg_orga_benevole', 1, HOME_TMP_BANNER_COOKIE_DURATION_IN_DAYS);
        this.parentNode.style.display = 'none';
        return false;
    });
}

/**
 * used in pages lieu, lieux, organisateur, organisateurs
 *
 * @returns {undefined}
 */
export function bindLieuxEvents()
{
    $('#btn_listelieux').on('click', function (e)
    {
        e.preventDefault();

        if (!$('#menu_lieux').is(':visible'))
        {
            $('#menu_lieux').fadeIn(vitesse_fondu);
            //$('#main_menu').toggle(vitesse_fondu);
        }
        else
        {
            $('#menu_lieux').fadeOut(vitesse_fondu);
            //$('#main_menu').toggle(vitesse_fondu);
        }
    });
}


/**
 * used in pages evenement-agenda, index, lieu, organisateur
 *
 * @returns {undefined}
 */
export function bindEventsEvents ()
{
    $('.btn_event_del').on('click', function (e)
    {
        e.preventDefault();
        const event_id = $(this).data('id');
        $.get('/evenement-actions.php?action=delete&id=' + event_id, function ()
        {
            $('#btn_event_del_' + event_id).closest('tr').fadeOut('fast');
        });

    });

    $('.btn_event_unpublish').on('click', function (e)
    {
        e.preventDefault();
        var event_id = $(this).data('id');
        $.get('/evenement-actions.php?action=unpublish&id=' + event_id, function ()
        {
            $('#btn_event_unpublish_' + event_id).closest('.evenement').fadeOut();
        });

    });

    $('#event-delete-btn').on('click', function ()
    {
        return confirm('Voulez-vous vraiment supprimer cet événement ?');
    });
}


function showhide(show, hide)
{
    $('.type-' + show).fadeIn(100);
    $('.btn-' + show).addClass('ici');
    $('.type-' + hide).fadeOut(100);
    $('.btn-' + hide).removeClass('ici');
}
