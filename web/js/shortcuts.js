/**
 * Global keyboard shortcuts (issue #112). Keys are matched on event.key (never
 * event.code) so they keep working whatever the visitor's keyboard layout is
 * (AZERTY, QWERTZ, QWERTY...) - what changes between layouts is which physical
 * key/modifier combo produces a given character, not the character itself.
 * shiftKey is deliberately never used to filter out a shortcut, since some
 * layouts require Shift to produce a character we bind to (e.g. "/" via
 * Shift+7 on a Swiss keyboard).
 *
 * Le registre ci-dessous est la source unique des raccourcis : `handleKeydown` y
 * cherche la touche pressée, et le mode « mouseless » (mouseless.js) s'en sert pour
 * savoir quels éléments neutraliser et quelle touche afficher. Les dupliquer les
 * ferait diverger au premier raccourci ajouté.
 *
 * Champs d'une entrée :
 * - `key`       touche telle que rendue par event.key, comparée sans tenir compte de la casse
 * - `label`     ce qu'affiche le badge du mode mouseless
 * - `pages`     restreint le raccourci à ces valeurs de body[data-page] ; absent = partout
 * - `selectors` cibles du raccourci ; `activate`/`focus` n'utilisent que la première,
 *               le mode mouseless neutralise toutes celles qui existent dans la page
 * - `action`    'activate' (clic), 'focus' (focus) ou 'focusSearch' (cas particulier)
 *
 * Ordre : les raccourcis globaux viennent avant ceux propres à une page, car
 * `handleKeydown` retient la première entrée qui trouve effectivement sa cible.
 */
export const SHORTCUTS = [
    { key: 'h', label: 'H', action: 'activate', selectors: ['#titre_site a'] },
    {
        key: 's',
        label: 'S',
        action: 'focusSearch',
        // focusSearch() choisit dynamiquement parmi ces trois cibles selon celle qui est visible
        selectors: ['form.recherche_mobile input.mots', '#btn_search', 'form.recherche input.mots']
    },
    { key: 'a', label: 'A', action: 'activate', selectors: ['a[href^="/evenement-edit.php?action=ajouter"]'] },
    { key: 'd', label: 'D', action: 'activate', selectors: ['a[href="/admin/index.php"]'] },
    { key: 'l', label: 'L', action: 'activate', selectors: ['a[href^="/lieu/lieux.php"]'] },
    { key: 'o', label: 'O', action: 'activate', selectors: ['a[href^="/organisateur/organisateurs.php"]'] },

    {
        key: 'ArrowLeft',
        label: '←',
        pages: ['index'],
        action: 'activate',
        selectors: ['ul.entete_contenu_navigation a[rel~="prev"]']
    },
    {
        key: 'ArrowRight',
        label: '→',
        pages: ['index'],
        action: 'activate',
        selectors: ['ul.entete_contenu_navigation a[rel~="next"]']
    },
    {
        key: 'f',
        label: 'F',
        pages: ['event/evenement'],
        action: 'activate',
        selectors: ['#illustrations a.magnific-popup']
    },
    {
        key: 'e',
        label: 'E',
        pages: ['event/evenement'],
        action: 'activate',
        selectors: ['a[href*="evenement-edit.php?action=editer"]']
    },
    {
        key: 'c',
        label: 'C',
        pages: ['event/evenement'],
        action: 'activate',
        selectors: ['a[href^="/event/copy.php"]']
    },
    {
        key: 'e',
        label: 'E',
        pages: ['lieu/lieu', 'organisateur/organisateur'],
        action: 'activate',
        selectors: ['.action_editer a']
    },
    {
        key: '/',
        label: '/',
        pages: ['lieu/lieux'],
        action: 'focus',
        selectors: ['.table-filters input[name="nom"]']
    }
];


export const Shortcuts =
{
    init : function bindKeyboardShortcuts()
    {
        document.addEventListener('keydown', Shortcuts.handleKeydown);
    },

    /**
     * Entrées du registre applicables à une page, dans l'ordre de priorité.
     *
     * @param {string|undefined} page valeur de body[data-page]
     * @returns {Array<object>}
     */
    entriesForPage : function entriesForPage(page)
    {
        return SHORTCUTS.filter(function isApplicable(entry)
        {
            return !entry.pages || entry.pages.includes(page);
        });
    },

    handleKeydown : function handleKeydown(e)
    {
        if (e.ctrlKey || e.altKey || e.metaKey || e.isComposing)
        {
            return;
        }

        const target = e.target;
        if (target && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)))
        {
            return;
        }

        const pressed = e.key.toLowerCase();
        const candidates = Shortcuts.entriesForPage(document.body.dataset.page)
            .filter(function matchesKey(entry)
            {
                return entry.key.toLowerCase() === pressed;
            });

        // Une entrée dont la cible est absente de la page laisse sa chance à la suivante,
        // et si aucune n'aboutit la touche garde son comportement natif.
        const handled = candidates.some(Shortcuts.run);

        if (handled)
        {
            e.preventDefault();
        }
    },

    run : function runShortcut(entry)
    {
        switch (entry.action)
        {
        case 'activate':
            return Shortcuts.activate(entry.selectors[0]);
        case 'focus':
            return Shortcuts.focusElement(entry.selectors[0]);
        case 'focusSearch':
            return Shortcuts.focusSearch();
        }

        return false;
    },

    activate : function activate(selector)
    {
        const el = document.querySelector(selector);
        if (!el)
        {
            return false;
        }
        el.click();
        return true;
    },

    focusElement : function focusElement(selector)
    {
        const el = document.querySelector(selector);
        if (!el)
        {
            return false;
        }
        el.focus();
        return true;
    },

    focusSearch : function focusSearch()
    {
        // champ mobile déjà ouvert (page de résultats) : le focuser plutôt que de le refermer
        const mobileField = document.querySelector('form.recherche_mobile input.mots');
        if (mobileField && mobileField.offsetParent !== null)
        {
            mobileField.focus();
            return true;
        }

        const btnSearch = document.getElementById('btn_search');
        if (btnSearch && btnSearch.offsetParent !== null)
        {
            btnSearch.click();
            return true;
        }
        return Shortcuts.focusElement('form.recherche input.mots');
    }
};
