const STORAGE_KEY = 'ladecadanse_favorites';
const DISMISS_KEY = 'ladecadanse_favorites_banner_dismissed';

const FavoritesStore =
{
    isLoggedIn: false,
    csrfToken: '',
    _cache: new Set(),

    init: async function initStore(isLoggedIn, inlineIds, csrfToken)
    {
        this.isLoggedIn = isLoggedIn;
        // avant le premier appel : _apiSync part dès cette méthode, pour un compte qui se
        // connecte avec des favoris posés en visiteur
        this.csrfToken = csrfToken || '';

        if (!this.isLoggedIn)
        {
            this._cache = new Set(this._localGet());
            return;
        }

        const guestFavs = this._localGet();
        if (guestFavs.length > 0)
        {
            await this._apiSync(guestFavs);
            localStorage.removeItem(STORAGE_KEY);
            this._cache = new Set(await this._apiList());
            return;
        }

        this._cache = new Set(Array.isArray(inlineIds) ? inlineIds : await this._apiList());
    },

    toggle: async function toggleFavorite(eventId)
    {
        eventId = parseInt(eventId, 10);

        if (this.isLoggedIn)
        {
            const result = await this._apiToggle(eventId);
            if (result.status === 'added')
            {
                this._cache.add(eventId);
            }
            else
            {
                this._cache.delete(eventId);
            }
        }
        else
        {
            if (this._cache.has(eventId))
            {
                this._cache.delete(eventId);
            }
            else
            {
                this._cache.add(eventId);
            }
            this._localSave();
        }

        return this._cache.has(eventId);
    },

    has: function hasFavorite(eventId)
    {
        return this._cache.has(parseInt(eventId, 10));
    },

    getAll: function getAllFavorites()
    {
        return Array.from(this._cache);
    },

    _localGet: function localGet()
    {
        try
        {
            const raw = localStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : [];
        }
        catch (e)
        {
            return [];
        }
    },

    _localSave: function localSave()
    {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(Array.from(this._cache)));
    },

    _apiToggle: async function apiToggle(eventId)
    {
        const response = await fetch('/event/favorites.php?action=toggle', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrfToken },
            body: JSON.stringify({ idE: eventId })
        });
        if (!response.ok)
        {
            throw new Error('Toggle failed');
        }
        return response.json();
    },

    _apiList: async function apiList()
    {
        const response = await fetch('/event/favorites.php?action=list');
        if (!response.ok)
        {
            throw new Error('List failed');
        }
        const data = await response.json();
        return data.ids || [];
    },

    _apiSync: async function apiSync(ids)
    {
        const response = await fetch('/event/favorites.php?action=sync', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrfToken },
            body: JSON.stringify({ ids: ids })
        });
        if (!response.ok)
        {
            throw new Error('Sync failed');
        }
    }
};

export const Favorites =
{
    init: async function initFavorites()
    {
        const $content = $('#contenu');
        if ($content.length === 0)
        {
            return;
        }

        const config = window.__LADECADANSE || {};

        this._bindEvents();
        await FavoritesStore.init(!!config.isLoggedIn, config.favoriteIds, config.csrfToken);

        this._hydrateButtons();
        this._syncMenuIcon();
        this._construireBarreMois();
        this._loadGuestFavorisPage();
        this._applyFavorisFilter();
    },

    /*
     * POC : la barre des mois affichée en tête de la page Favoris sur un téléphone, où la
     * colonne des mois est masquée. Elle se déduit des en-têtes de mois déjà rendus — par le
     * serveur pour un membre connecté, par l'API pour un visiteur —, donc sans donnée ni
     * requête supplémentaire. L'année est laissée de côté : la barre tient sur une ligne.
     */
    /** Clé du mois en cours, au format des ancres de la liste (2026-09) */
    _moisCourant: function moisCourant()
    {
        const maintenant = new Date();

        return maintenant.getFullYear() + '-' + String(maintenant.getMonth() + 1).padStart(2, '0');
    },

    _construireBarreMois: function construireBarreMois()
    {
        const $barre = $('#favoris_mois_mobile');
        if ($barre.length === 0)
        {
            return;
        }

        const moisCourant = this._moisCourant();
        const $liste = $barre.find('ul').empty();

        $('#contenu header.genre-titre[id^="favoris-mois-"]').each(function ()
        {
            const cle = this.id.slice('favoris-mois-'.length);
            const $item = $('<li>').append($('<a>').attr('href', '#' + this.id).text($(this).text().trim().replace(/\s+\d{4}$/, '')));

            // le mois en cours n'est pas une teinte de plus mais le marquage de départ : un clic
            // le déplace, et la barre ne montre jamais deux mois actifs
            if (cle === moisCourant)
            {
                $item.addClass('ici');
            }

            $liste.append($item);
        });

        $barre.prop('hidden', $liste.children().length === 0);
    },

    _setButtonState: function setButtonState($btn, isFavorite)
    {
        $btn.toggleClass('is-favorite', isFavorite);
        $btn.find('i.fa').toggleClass('fa-bookmark', isFavorite).toggleClass('fa-bookmark-o', !isFavorite);
    },

    // le marque-page du menu (visible en mobile) est vide tant qu'aucun favori n'est posé
    _syncMenuIcon: function syncMenuIcon()
    {
        const hasAny = FavoritesStore.getAll().length > 0;
        $('#bouton_favoris i.fa')
            .toggleClass('fa-bookmark', hasAny)
            .toggleClass('fa-bookmark-o', !hasAny);
    },

    _hydrateButtons: function hydrateButtons()
    {
        const self = this;
        $('.js-favorite-toggle').each(function ()
        {
            if (FavoritesStore.has($(this).data('event-id')))
            {
                self._setButtonState($(this), true);
            }
        });
    },

    _bindEvents: function bindEvents()
    {
        const self = this;
        const $content = $('#contenu');

        $content.on('click', '.js-favorite-toggle', async function (e)
        {
            e.preventDefault();
            const $btn = $(this);
            const eventId = $btn.data('event-id');
            const isNowFavorite = await FavoritesStore.toggle(eventId);
            self._setButtonState($btn, isNowFavorite);
            self._syncMenuIcon();
            self._renderFilter();
        });

        // un seul onglet, qui bascule : actif, un clic dessus (ou sur sa croix) retire le filtre,
        // comme la croix d'un onglet de genre
        $content.on('click', '.js-favoris-filter', function (e)
        {
            e.preventDefault();
            self._setFilter(self._displayFilter === 'favoris' ? 'tous' : 'favoris');
            self._applyFavorisFilter();
        });

        // barre des mois : l'ancre cliquée reste marquée, comme un onglet de genre actif
        $content.on('click', '#favoris_mois_mobile a', function ()
        {
            $('#favoris_mois_mobile li').removeClass('ici');
            $(this).closest('li').addClass('ici');
        });

        if (localStorage.getItem(DISMISS_KEY) === '1')
        {
            $('#favorites_guest_banner').hide();
        }

        $(document).on('click', '.js-favorites-banner-dismiss', function (e)
        {
            e.preventDefault();
            localStorage.setItem(DISMISS_KEY, '1');
            $('#favorites_guest_banner').fadeOut('fast');
        });
    },

    _getUrlParam: function getUrlParam(name)
    {
        const params = new URLSearchParams(window.location.search);
        return params.get(name);
    },

    _buildGuestSidebar: function buildGuestSidebar(months)
    {
        const $nav = $('.favoris-sidebar');
        if ($nav.length === 0 || !months || months.length === 0)
        {
            return;
        }

        let html = '<div class="favoris-sidebar-header"><i class="fa fa-calendar-o"></i> Mois</div><ul>';
        for (const month of months)
        {
            // le mois en cours se distingue ici comme dans la colonne rendue par le serveur
            const classe = month.key === this._moisCourant() ? ' class="favoris-mois-courant"' : '';
            html += '<li' + classe + '><a href="#favoris-mois-' + month.key + '">' + month.label + '</a></li>';
        }
        html += '</ul>';

        $nav.html(html).removeAttr('hidden');
    },

    _loadGuestFavorisPage: async function loadGuestFavorisPage()
    {
        const $guestList = $('#favorites-guest-list');
        if ($guestList.length === 0 || FavoritesStore.isLoggedIn)
        {
            return;
        }

        const ids = FavoritesStore.getAll();
        const $loading = $guestList.find('.js-favorites-loading');
        const $empty = $guestList.find('.js-favorites-empty');
        const $content = $guestList.find('.js-favorites-content');
        const $paginationTop = $guestList.find('.js-favorites-pagination-top');
        const $pagination = $guestList.find('.js-favorites-pagination');

        if (ids.length === 0)
        {
            $loading.hide();
            $empty.show();
            return;
        }

        const view = this._getUrlParam('view') || 'avenir';
        const page = parseInt(this._getUrlParam('page') || '1', 10);

        try
        {
            const response = await fetch('/event/favorites.php?action=events', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: ids, view: view, page: page })
            });
            if (!response.ok)
            {
                throw new Error('Events failed');
            }
            const data = await response.json();

            $loading.hide();

            if (data.count > 0)
            {
                $content.html(data.html);
                this._buildGuestSidebar(data.months || []);
                this._construireBarreMois();

                if (view === 'passes')
                {
                    $paginationTop.html(data.paginationHtml || '');
                    $pagination.html(data.paginationHtml || '');
                }
            }
            else
            {
                if (view === 'passes')
                {
                    $empty.text('Aucun événement passé dans vos favoris.').show();
                }
                else
                {
                    $empty.show();
                }
            }
        }
        catch (e)
        {
            $loading.text('Erreur lors du chargement des favoris.');
        }
    },

    _FILTER_KEY: 'ladecadanse_favorites_filter',

    _getFilter: function getFilter()
    {
        return localStorage.getItem(this._FILTER_KEY) === 'favoris' ? 'favoris' : 'tous';
    },

    _setFilter: function setFilter(value)
    {
        localStorage.setItem(this._FILTER_KEY, value === 'favoris' ? 'favoris' : 'tous');
    },

    _eventElements: function eventElements()
    {
        return $('article.evenement-short[data-event-id], tr.evenement[data-event-id]');
    },

    _displayFilter: 'tous',

    _markActiveTab: function markActiveTab(filter)
    {
        const actif = filter === 'favoris';

        $('.js-favoris-filter-item')
            .toggleClass('ici', actif)
            .attr('aria-current', actif ? 'true' : null);

        // même annonce que les onglets de genre une fois le filtre posé : le lien ne sert plus
        // qu'à le retirer
        $('.js-favoris-filter')
            .attr('title', actif ? 'Retirer le filtre' : null)
            .attr('aria-label', actif ? 'Retirer le filtre Favoris' : null);
    },

    _renderFilter: function renderFilter()
    {
        const $nav = $('#favoris_filter_navigation');
        if ($nav.length === 0)
        {
            return;
        }

        const store = FavoritesStore;
        const favorisMode = this._displayFilter === 'favoris';
        const $events = this._eventElements();
        let favInList = 0;

        $events.each(function ()
        {
            const isFav = store.has(parseInt($(this).data('event-id'), 10));
            if (isFav)
            {
                favInList++;
            }
            $(this).toggle(!favorisMode || isFav);
        });

        const previousCount = this._favCount;
        this._favCount = favInList;

        // un favori remis pendant que l'onglet se rétracte : il reste affiché
        clearTimeout(this._hideTimer);
        $('.js-favoris-filter-item').removeClass('plop-inverse');

        if (favInList === 0)
        {
            this._displayFilter = 'tous';
            $events.show();
            this._syncGroupHeaders(false);

            if (previousCount > 0 && this._animate())
            {
                $('.js-favoris-filter-item').addClass('plop-inverse');
                this._hideTimer = setTimeout(() =>
                {
                    $nav.attr('hidden', 'hidden');
                    $('.js-favoris-filter-item').removeClass('plop-inverse');
                }, 250);
                return;
            }

            $nav.attr('hidden', 'hidden');
            return;
        }

        $nav.removeAttr('hidden');
        this._renderCount(previousCount, favInList);
        this._markActiveTab(this._displayFilter);
        this._syncGroupHeaders(favorisMode);
    },

    // nombre de favoris de la liste affichée ; null tant que la page n'a pas été rendue une fois
    _favCount: null,

    _hideTimer: null,

    _animate: function animate()
    {
        return !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    },

    /*
     * Au chargement, le compteur s'affiche sans effet. Ensuite, l'onglet qui apparaît avec le
     * premier favori rebondit, et à chaque changement le nouveau nombre remplace l'ancien en
     * fondu. Les durées des minuteries sont celles des animations de global.css.
     */
    _renderCount: function renderCount(previous, count)
    {
        const $counter = $('.js-favoris-count');
        const animate = previous !== null && this._animate();

        if (!animate || previous === 0)
        {
            $counter.empty().append($('<span>').text(count));
            if (animate)
            {
                const $item = $('.js-favoris-filter-item').addClass('plop');
                setTimeout(() => $item.removeClass('plop'), 400);
            }
            return;
        }

        if (previous === count)
        {
            return;
        }

        // un clic rapide peut trouver un chiffre encore en train de sortir : il part tout de suite
        $counter.children('.sort').remove();
        // retrait à la durée de l'animation (global.css) plutôt qu'à animationend : un onglet
        // en arrière-plan ne l'émet pas, et l'ancien chiffre resterait sous le nouveau
        const $old = $counter.children().removeClass('entre').addClass('sort');
        setTimeout(() => $old.remove(), 300);
        $counter.append($('<span class="entre">').text(count));
    },

    _applyFavorisFilter: function applyFavorisFilter()
    {
        this._displayFilter = this._getFilter();
        this._renderFilter();
    },

    _syncGroupHeaders: function syncGroupHeaders(favorisMode)
    {
        const $genres = $('#prochains_evenements section.genre');
        const $monthRows = $('#prochains_evenements tr').has('td.mois');

        if (!favorisMode)
        {
            $genres.show();
            $monthRows.show();
            $genres.find('p.rappel_date').show();
            this._syncGenreJumps(false);
            return;
        }

        $genres.each(function ()
        {
            $(this).toggle($(this).find('article.evenement-short:visible').length > 0);
        });

        this._syncGenreJumps(true);

        // Les rappels de date (ou d'heure) d'une section se suivaient une fois les événements
        // masqués. Seul le dernier avant un événement visible reste — en ordre chronologique,
        // c'est celui qui porte la bonne heure —, et aucun avant le premier ni après le dernier.
        $genres.each(function ()
        {
            let seenVisibleEvent = false;
            let $pending = $();

            $(this).find('p.rappel_date, article.evenement-short').each(function ()
            {
                const $el = $(this);
                if ($el.is('p.rappel_date'))
                {
                    $el.hide();
                    $pending = $el;
                    return;
                }

                if ($el.is(':visible'))
                {
                    if (seenVisibleEvent)
                    {
                        $pending.show();
                    }
                    seenVisibleEvent = true;
                    $pending = $();
                }
            });
        });

        $monthRows.each(function ()
        {
            let $row = $(this).next();
            let hasVisible = false;
            while ($row.length && $row.find('td.mois').length === 0)
            {
                if ($row.is('.evenement') && $row.is(':visible'))
                {
                    hasVisible = true;
                    break;
                }
                $row = $row.next();
            }
            $(this).toggle(hasVisible);
        });
    },

    /*
     * Le lien « genre suivant » de l'agenda visait une section que le filtre a pu masquer : il
     * pointe sur la prochaine section visible, ou disparaît s'il n'y en a plus. La cible et le
     * libellé d'origine sont gardés pour être rendus quand le filtre est retiré.
     */
    _syncGenreJumps: function syncGenreJumps(favorisMode)
    {
        $('#prochains_evenements section.genre a.genre-jump').each(function ()
        {
            const $link = $(this);
            const labelNode = this.firstChild;

            if ($link.data('original-href') === undefined)
            {
                $link.data('original-href', $link.attr('href'));
                $link.data('original-label', labelNode.nodeValue);
            }

            if (!favorisMode)
            {
                $link.attr('href', $link.data('original-href')).show();
                labelNode.nodeValue = $link.data('original-label');
                return;
            }

            const $nextTitle = $link.closest('section.genre').nextAll('section.genre:visible').first().find('h2');
            if ($nextTitle.length === 0)
            {
                $link.hide();
                return;
            }

            // le titre porte le libellé avec majuscule initiale, le lien sans
            const title = $nextTitle.text();
            $link.attr('href', '#' + $nextTitle.attr('id')).show();
            labelNode.nodeValue = title.charAt(0).toLowerCase() + title.slice(1) + ' ';
        });
    }
};
