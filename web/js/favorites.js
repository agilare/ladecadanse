const STORAGE_KEY = 'ladecadanse_favorites';
const DISMISS_KEY = 'ladecadanse_favorites_banner_dismissed';

const FavoritesStore =
{
    isLoggedIn: false,
    _cache: new Set(),

    init: async function initStore(isLoggedIn, inlineIds)
    {
        this.isLoggedIn = isLoggedIn;

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
            headers: { 'Content-Type': 'application/json' },
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
            headers: { 'Content-Type': 'application/json' },
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
        if (!config.favoritesEnabled)
        {
            return;
        }

        this._bindEvents();
        await FavoritesStore.init(!!config.isLoggedIn, config.favoriteIds);

        this._hydrateButtons();
        this._loadGuestFavorisPage();
        this._applyFavorisFilter();
    },

    _setButtonState: function setButtonState($btn, isFavorite)
    {
        $btn.toggleClass('is-favorite', isFavorite);
        $btn.find('i.fa').toggleClass('fa-heart', isFavorite).toggleClass('fa-heart-o', !isFavorite);
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
            self._renderFilter();
        });

        $content.on('click', '.js-favoris-filter', function (e)
        {
            e.preventDefault();
            self._setFilter($(this).data('filter'));
            self._applyFavorisFilter();
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
            html += '<li><a href="#favoris-mois-' + month.key + '">' + month.label + '</a></li>';
        }
        html += '</ul>';

        $nav.html(html);
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
        $('#favoris_filter_navigation .js-favoris-filter').each(function ()
        {
            $(this).closest('li').toggleClass('ici', $(this).data('filter') === filter);
        });
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

        if (favInList === 0)
        {
            $nav.attr('hidden', 'hidden');
            this._displayFilter = 'tous';
            $events.show();
            this._syncGroupHeaders(false);
            return;
        }

        $nav.removeAttr('hidden');
        $nav.find('.js-favoris-count').text('(' + favInList + ')');
        this._markActiveTab(this._displayFilter);
        this._syncGroupHeaders(favorisMode);
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
            return;
        }

        $genres.each(function ()
        {
            $(this).toggle($(this).find('article.evenement-short:visible').length > 0);
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
    }
};
