import { SetCookie } from './browser.js';
import { Shortcuts } from './shortcuts.js';
import { Mouseless } from './mouseless.js';

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
        Calendar.init();
        Shortcuts.init();
        Mouseless.init();
    },
    commonInteractions : function bindEventsOfVariousInteractions()
    {
        $('.dropdown').click(function dropdownTarget()
        {
            $('#' + $(this).data('target')).toggle();
            return false;
        });

        if ('showPopover' in HTMLElement.prototype) {
            // Browsers with Popover API support (Safari 17+, Chrome 114+, Firefox 125+).
            // Position each popover below its trigger; re-run on scroll so it tracks
            // the button, and clean up the scroll listener when the popover closes.
            document.querySelectorAll('.calendar-export-menu').forEach(function(menu)
            {
                const trigger = document.querySelector('[popovertarget="' + menu.id + '"]');
                if (!trigger) return;

                function setPos()
                {
                    const rect = trigger.getBoundingClientRect();
                    menu.style.top = (rect.bottom + 4) + 'px';
                    menu.style.left = rect.left + 'px';
                }

                menu.addEventListener('toggle', function(e)
                {
                    // e.newState may be absent in early Safari; fall back to :popover-open check.
                    const isOpen = e.newState === 'open' || (e.newState === undefined && menu.matches(':popover-open'));
                    if (isOpen) {
                        setPos();
                        // Store the reference so we can pass the exact same function to removeEventListener.
                        menu._scrollHandler = setPos;
                        window.addEventListener('scroll', setPos, { passive: true });
                    } else if (menu._scrollHandler) {
                        window.removeEventListener('scroll', menu._scrollHandler);
                        delete menu._scrollHandler;
                    }
                });
            });
        } else {
            // Fallback for browsers without the Popover API (Safari < 17, etc.).
            // The <ul> renders as a normal visible element without support; we hide it
            // with --fallback and toggle --open on click. Position is handled by CSS
            // (position: absolute within the position: relative wrapper) — no JS coords needed.
            document.querySelectorAll('.calendar-export-menu').forEach(function(menu)
            {
                menu.classList.add('calendar-export-menu--fallback');
                const trigger = document.querySelector('[popovertarget="' + menu.id + '"]');
                if (!trigger) return;

                trigger.addEventListener('click', function(e)
                {
                    e.stopPropagation();
                    const wasOpen = menu.classList.contains('calendar-export-menu--open');
                    // Close any other open menu first.
                    document.querySelectorAll('.calendar-export-menu--open').forEach(function(other)
                    {
                        other.classList.remove('calendar-export-menu--open');
                    });
                    if (!wasOpen) {
                        menu.classList.add('calendar-export-menu--open');
                    }
                });
            });

            // Close the open menu when clicking anywhere outside it.
            document.addEventListener('click', function()
            {
                document.querySelectorAll('.calendar-export-menu--open').forEach(function(menu)
                {
                    menu.classList.remove('calendar-export-menu--open');
                });
            });
        }

        $('.btn_toggle').on('click', function toggleTarget()
        {
            $('.element_toggle').toggle();
            //return false;
        });

        $('#show-description-btn').on('click', function showDescription()
        {
            $('#show-presentation-btn a').removeClass('actif');
            $(this).addClass('actif');
            showhide('description', 'presentation');
            return false;
        });

        $('#show-presentation-btn').on('click', function showPresentation()
        {
            $('#show-description-btn a').removeClass('actif');
            $(this).addClass('actif');
            showhide('presentation', 'description');
            return false;
        });

        $('.js-auto-submiter').on('change', function autosubmit()
        {
            this.form.submit();
        });

        const backToTopBtn = document.getElementById('back-to-top');
        if (backToTopBtn) {
            function updateBackToTop()
            {
                backToTopBtn.classList.toggle('is-visible', window.scrollY > 300);
            }
            window.addEventListener('scroll', updateBackToTop, { passive: true });
            updateBackToTop();

            backToTopBtn.addEventListener('click', function (e)
            {
                e.preventDefault();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }

        this.readMoreOnClippedDescriptions();
    },
    /**
     * Le CSS plafonne les descriptions des cartes d'événement à 6 lignes
     * (line-clamp). PHP pose déjà le lien « Lire la suite » quand il a tronqué
     * en caractères, mais il ne peut pas savoir si le navigateur a rogné le
     * texte : cela dépend du retour à la ligne automatique, donc de la largeur
     * de la colonne et de la taille de police. On révèle donc le lien sur les
     * blocs réellement coupés, et on resynchronise au redimensionnement, où le
     * nombre de lignes rendues change.
     *
     * Sans JS, seul le lien posé par PHP reste visible : c'est le comportement
     * d'avant le line-clamp, pas une régression.
     */
    readMoreOnClippedDescriptions : function revealReadMoreLinkOnClippedDescriptions()
    {
        const descriptions = document.querySelectorAll('.evenement-short .description .js-description-clamp');
        if (descriptions.length === 0)
        {
            return;
        }

        // mémorise la décision du serveur : elle ne doit jamais être défaite
        descriptions.forEach(function memorizeServerDecision(paragraph)
        {
            const link = paragraph.parentNode.querySelector('.js-lire-la-suite');
            if (link)
            {
                link.dataset.shownByServer = link.hidden ? '0' : '1';
            }
        });

        function syncReadMoreLinks()
        {
            descriptions.forEach(function toggleOneLink(paragraph)
            {
                const link = paragraph.parentNode.querySelector('.js-lire-la-suite');
                if (!link)
                {
                    return;
                }

                // 1px de marge : les hauteurs sous-pixel font mentir la comparaison stricte
                const isClipped = paragraph.scrollHeight > paragraph.clientHeight + 1;

                link.hidden = (link.dataset.shownByServer === '0' && !isClipped);
            });
        }

        syncReadMoreLinks();

        let resyncTimer = null;
        window.addEventListener('resize', function scheduleResync()
        {
            window.clearTimeout(resyncTimer);
            resyncTimer = window.setTimeout(syncReadMoreLinks, FADE_SPEED_SHORT_IN_MS);
        }, { passive: true });
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
            $('form.recherche_mobile').find('input[type="search"]').focus();
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
            if (this.files.length > 0 && this.files[0].size > MAX_UPLOAD_SIZE_IN_BYTES)
            {
                alert('La taille du fichier que vous avez sélectionné dépasse la limite autorisée (2 Mo), merci d’en choisir un plus léger');
            }
        });

        const dirtyForms = new Set();

        $('form.js-submit-freeze-wait').submit(function disableSubmit()
        {
            dirtyForms.delete(this);
            $('input[type="submit"]', this).val('Envoi...').attr('disabled', 'disabled');
            return true;
        });

        // 1re itération : limité au formulaire d'édition d'événement (evenement-edit.php)
        if (document.body.dataset.page === 'evenement-edit')
        {
            $('form.js-submit-freeze-wait').on('input change', function markFormDirty()
            {
                dirtyForms.add(this);
            });

            window.addEventListener('beforeunload', function warnOnUnsavedFormChanges(e)
            {
                if (dirtyForms.size === 0)
                {
                    return;
                }
                e.preventDefault();
                e.returnValue = '';
            });
        }

        $('.js-clear-search-field').on('click', function clearAndSubmitSearchField()
        {
            const form = $(this).closest('form')[0];
            $(form).find('input[type="search"]').val('');

            // Un champ nommé « submit » masque form.submit() : le formulaire expose ses champs
            // comme propriétés, et jQuery, ne trouvant plus de fonction, n'envoyait rien. Passer
            // par le prototype contourne cet écrasement.
            HTMLFormElement.prototype.requestSubmit.call(form);
        });

//        $('form#ajouter_editer #titre').on('paste', function(e)
//        {
//            // Récupère le texte collé
//            const pastedText = (e.originalEvent.clipboardData || window.clipboardData).getData('text');
//
//            const maxLength = parseInt($(this).prop('maxLength'));
//
//            if (!maxLength) {
//                return;
//            }
//
//            if (pastedText.length > maxLength)
//            {
//                alert(`Le texte collé dans ce champ dépasse la longueur maximale de ${maxLength} caractères, il a donc été tronqué`);
//            }
//        });
    // better than above
        $('form#ajouter_editer #titre').on("input", function () {
            const maxLength = this.maxLength; // Récupère la valeur de maxlength
            const currentLength = this.value.length;

            if (currentLength >= maxLength) {
                alert(`Le texte dans ce champ ne peut dépasser la longueur maximale de ${maxLength} caractères`);
            }
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
            fetch(`/event/actions.php?action=delete&id=${event_id}`)
                .then(response => $(`#btn_event_del_${event_id}`).closest('tr').fadeOut('fast'))
                .catch(error => alert('Erreur : ' + error));
        });

        // data-on-success sur le lien décide de ce qu'on fait de l'événement dépublié :
        // 'hide' (défaut) sur les listes d'agenda, 'status' sur les tableaux de gestion
        // qui affichent aussi les événements dépubliés, 'reload' sur la fiche événement.
        $content.on('click', '.btn_event_unpublish', function requestUnpublishEvent(e)
        {
            e.preventDefault();
            const $btn = $(this);
            const event_id = $btn.data('id');
            fetch(`/event/actions.php?action=unpublish&id=${event_id}`)
                .then(function unpublishDone(response)
                {
                    if (!response.ok)
                    {
                        throw new Error(`dépublication refusée (${response.status})`);
                    }

                    const onSuccess = $btn.attr('data-on-success');

                    if (onSuccess === 'reload')
                    {
                        window.location.reload();
                    }
                    else if (onSuccess === 'status')
                    {
                        $btn.closest('tr')
                            .find('.even-icon-status-round')
                            .attr('class', 'even-icon-status-round statut-inactif')
                            .attr('title', 'Dépublié');
                        $btn.fadeOut(FADE_SPEED_SHORT_IN_MS);
                    }
                    else
                    {
                        $btn.closest('tr, article.evenement-short').fadeOut('fast');
                    }
                })
                .catch(error => alert('Erreur : ' + error));
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
        if ($('.js-alert-close-btn').length === 0)
        {
            return;
        }

        // browser.js
        $('.js-alert-close-btn').on('click', function hideTmpBannerAndSetCookie()
        {
            const HOME_TMP_BANNER_COOKIE_DURATION_IN_DAYS = 180;
            SetCookie(this.parentNode.id, 1, HOME_TMP_BANNER_COOKIE_DURATION_IN_DAYS);
            this.parentNode.style.display = 'none';
            return false;
        });
    }
};



const Calendar =
{
    pageCourant : '',

    init : function bindCalendarNavigation()
    {
        const $container = $('#navigation_calendrier');
        if ($container.length === 0)
        {
            return;
        }

        Calendar.pageCourant = $container.data('page-courant') || '';

        $container.on('click', '.js-calendar-nav', function handleCalendarNav(e)
        {
            e.preventDefault();
            const courant = $(this).data('courant');
            Calendar.loadMonth(courant);
        });
    },

    loadMonth : function fetchAndReplaceCalendar(courant)
    {
        const pageCourant = Calendar.pageCourant || '';
        fetch(`/event/calendrier-ajax.php?courant=${encodeURIComponent(courant)}&page_courant=${encodeURIComponent(pageCourant)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function handleResponse(response)
            {
                if (!response.ok)
                {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(function replaceCalendarHtml(html)
            {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;
                const newNav = tempDiv.querySelector('#navigation_calendrier');
                if (newNav)
                {
                    $('#navigation_calendrier').html(newNav.innerHTML);
                }
            })
            .catch(function fallbackToFullNavigation()
            {
                window.location.href = `/index.php?courant=${encodeURIComponent(courant)}`;
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
