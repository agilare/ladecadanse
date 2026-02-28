const STORAGE_KEY = 'ladecadanse_favorites';
const DISMISS_KEY = 'ladecadanse_favorites_banner_dismissed';

const FavoritesStore =
{
    isLoggedIn: false,
    _cache: new Set(),

    init: async function initStore(isLoggedIn)
    {
        this.isLoggedIn = isLoggedIn;

        if (this.isLoggedIn)
        {
            const guestFavs = this._localGet();
            if (guestFavs.length > 0)
            {
                await this._apiSync(guestFavs);
                localStorage.removeItem(STORAGE_KEY);
            }

            const serverIds = await this._apiList();
            this._cache = new Set(serverIds);
        }
        else
        {
            this._cache = new Set(this._localGet());
        }
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

    count: function countFavorites()
    {
        return this._cache.size;
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
        await FavoritesStore.init(!!config.isLoggedIn);

        this._hydrateButtons();
        this._bindEvents();
        this._loadGuestFavorisPage();
    },

    _hydrateButtons: function hydrateButtons()
    {
        const store = FavoritesStore;
        $('.js-favorite-toggle').each(function ()
        {
            const eventId = $(this).data('event-id');
            if (store.has(eventId))
            {
                $(this).addClass('is-favorite');
                $(this).find('i.fa').removeClass('fa-heart-o').addClass('fa-heart');
            }
        });
    },

    _bindEvents: function bindEvents()
    {
        const $content = $('#contenu');

        $content.on('click', '.js-favorite-toggle', async function (e)
        {
            e.preventDefault();
            const $btn = $(this);
            const eventId = $btn.data('event-id');
            const isNowFavorite = await FavoritesStore.toggle(eventId);

            const $icon = $btn.find('i.fa');
            if (isNowFavorite)
            {
                $btn.addClass('is-favorite');
                $icon.removeClass('fa-heart-o').addClass('fa-heart');
            }
            else
            {
                $btn.removeClass('is-favorite');
                $icon.removeClass('fa-heart').addClass('fa-heart-o');
            }
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

    _buildPaginationHtml: function buildPaginationHtml(page, totalPages)
    {
        if (totalPages <= 1)
        {
            return '';
        }

        let html = '<div class="pagination">';

        if (page > 1)
        {
            html += '<a id="prec" href="/favoris.php?view=passes&amp;page=' + (page - 1) + '" rel="prev">préc</a>';
        }

        for (let i = 1; i <= totalPages; i++)
        {
            if (i === page)
            {
                html += '<span class="current">' + i + '</span>';
            }
            else
            {
                html += '<a href="/favoris.php?view=passes&amp;page=' + i + '">' + i + '</a>';
            }
        }

        if (page < totalPages)
        {
            html += '<a id="suiv" href="/favoris.php?view=passes&amp;page=' + (page + 1) + '" rel="next">suiv</a>';
        }

        html += '</div>';
        return html;
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
                    const paginationHtml = this._buildPaginationHtml(data.page, data.totalPages);
                    $paginationTop.html(paginationHtml);
                    $pagination.html(paginationHtml);
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
    }
};
