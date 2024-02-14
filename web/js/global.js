import { SetCookie } from './browser.js';

export const FADE_SPEED_MEDIUM_IN_MS = 400;
const FADE_SPEED_SHORT_IN_MS = 100;

export const AppGlobal = 
{
    init : function init()
    {
        this.commonInteractions();
        this.mainNavigation();
        Forms.init();
        Events.init();
        Lieux.init(); 
        HomePage.init();
    },
    commonInteractions : function bindEventsOfVariousInteractions()
    {
        $('.dropdown').click(function dropdownTarget()
        {
            $('#' + $(this).data('target')).toggle();
            return false;
        });

        $('.btn_toggle').on('click', function toggleTarget()
        {
            $('.element_toggle').toggle();
            //return false;
        });

        $('#show-description-btn').on('click', function showDescription()
        {
            showhide('description', 'presentation');
            return false;
        });

        $('#show-presentation-btn').on('click', function showPresentation()
        {
            showhide('presentation', 'description');
            return false;
        });
    },
    /**
     * only used in mobile view
     * @returns {undefined}
     */
    mainNavigation : function bindEventsOfMainNavigation()
    {
        $('#btn_menu_pratique').on('click', function toggleMenuPratique(e)
        {
            e.preventDefault();

            if (!$('#menu_pratique').is(':visible'))
            {
                $('#menu_pratique').fadeIn(FADE_SPEED_MEDIUM_IN_MS);
                //$('#main_menu').toggle(vitesse_fondu);
            }
            else
            {
                $('#menu_pratique').fadeOut(FADE_SPEED_MEDIUM_IN_MS);
                //$('#main_menu').toggle(vitesse_fondu);
            }
        });

        $('#btn_calendrier').click(function toggleCalendrier()
        {
            $('#navigation_calendrier').toggle();
            return false;
        });

        $('#btn_search').on('click', function toggleSearchField()
        {
            $('.recherche_mobile').toggle(FADE_SPEED_MEDIUM_IN_MS);
            //return false;
        });
    }    
    
};

const Forms = {
    init : function bindEventsOfForms()
    {
        const MAX_UPLOAD_SIZE_IN_BYTES = 2097152;
        $('.js-file-upload-size-max').on('change', function alertOnFilesizeUpload()
        {
            if (this.files[0].size > MAX_UPLOAD_SIZE_IN_BYTES)
            {
                alert('La taille du fichier que vous avez sélectionné dépasse la limite autorisée (2 Mo), merci d’en choisir un plus léger');
            }
        });

        //$('#prix-precisions').hide();
        $('.precisions').on('change', function togglePrixPrecision()
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

        $('form.js-submit-freeze-wait').submit(function disableSubmit()
        {
            $('input[type="submit"]', this).val('Envoi...').attr('disabled', 'disabled');
            return true;
        });
    }
};


// pages specific 

/**
* used in pages lieu, lieux, organisateur, organisateurs
*
* @returns {undefined}
*/  
const Lieux = {
    init : function bindLieuxEvents()
    {
        $('#btn_listelieux').on('click', function toggleMenuLieux(e)
        {
            e.preventDefault();

            if (!$('#menu_lieux').is(':visible'))
            {
                $('#menu_lieux').fadeIn(FADE_SPEED_MEDIUM_IN_MS);
                //$('#main_menu').toggle(vitesse_fondu);
            }
            else
            {
                $('#menu_lieux').fadeOut(FADE_SPEED_MEDIUM_IN_MS);
                //$('#main_menu').toggle(vitesse_fondu);
            }
        });
    }
};



/**
 * used in pages evenement-agenda, index, lieu, organisateur
 *
 * @returns {undefined}
 */
const Events = {
    init : function bindEventsEvents ()
    {
        const $content = $('#contenu');

        if ($content.length === 0)
        {
            return;
        }

        $content.on('click', '.btn_event_del', function requestEventDel(e)
        {
            e.preventDefault();
            const event_id = $(this).data('id');
            $.get(`/evenement-actions.php?action=delete&id=${event_id}`, function hideEventRow()
            {
                $(`#btn_event_del_${event_id}`).closest('tr').fadeOut('fast');
            });

        });

        $content.on('click', '.btn_event_unpublish', function requestUnpublishEvent(e)
        {
            e.preventDefault();
            const event_id = $(this).data('id');
            $.get(`/evenement-actions.php?action=unpublish&id=${event_id}` , function hideEvent()
            {
                $(`#btn_event_unpublish_${event_id}`).closest('.evenement').fadeOut();
            });

        });

        $content.on('click', '#js-event-delete-btn', function confirmEventDel()
        {
            return confirm('Voulez-vous vraiment supprimer cet événement ?');
        });
    }
};


// page specific

const HomePage =
{
    init : function bindHomeEvents()
    {
        if ($('#js-home-tmp-banner-close-btn').length === 0)
        {
            return;
        }

        // browser.js
        $('#js-home-tmp-banner-close-btn').on('click', function hideTmpBannerAndSetCookie()
        {
            const HOME_TMP_BANNER_COOKIE_DURATION_IN_DAYS = 180;
            SetCookie('msg_orga_benevole', 1, HOME_TMP_BANNER_COOKIE_DURATION_IN_DAYS);
            this.parentNode.style.display = 'none';
            return false;
        });
    }
};



function showhide(show, hide)
{
    $('.type-' + show).fadeIn(FADE_SPEED_SHORT_IN_MS);
    $('.btn-' + show).addClass('ici');
    $('.type-' + hide).fadeOut(FADE_SPEED_SHORT_IN_MS);
    $('.btn-' + hide).removeClass('ici');
}
