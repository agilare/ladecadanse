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
 * - `action`    'activate' (clic), 'focus' (focus), 'focusSearch' (cas particulier)
 *               ou 'listNext'/'listPrev' (parcours de liste, voir LISTS)
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

    // Parcours d'une liste, à la vi. Le `selectors` vide n'est pas un oubli : les cibles sont
    // les lignes entières, décrites par page dans LISTS. Il dit exactement ce qu'on veut du
    // mode mouseless, qui itère sur ce tableau : ne rien décorer ni neutraliser ici. Baliser
    // les cinquante lignes d'une liste de lieux d'un badge « J » et bloquer le clic sur chacune
    // la rendrait illisible, alors qu'une mention dans le bandeau suffit à annoncer la touche.
    { key: 'j', label: 'J', action: 'listNext', selectors: [] },
    { key: 'k', label: 'K', action: 'listPrev', selectors: [] },

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


/**
 * Listes parcourables aux touches j/k, une entrée par page.
 *
 * - `pages` valeurs de body[data-page] concernées
 * - `items` sélecteur des éléments de la liste (ligne de tableau, article...)
 * - `link`  sélecteur, relatif à l'item, du lien principal — celui qu'on focalise, et
 *           donc celui qu'Entrée ouvrira
 *
 * Un item dépourvu de ce lien n'est pas une étape du parcours et disparaît de lui-même :
 * c'est ce qui écarte la ligne d'en-tête des tableaux du back-office, posée hors <tbody>,
 * sans avoir à la décrire ni à toucher au balisage.
 *
 * Deux pièges du balisage existant, à garder en tête avant de modifier ces sélecteurs :
 * - organisateurs.php réutilise l'id #derniers_lieux de lieux.php ; c'est `pages` qui
 *   distingue les deux, d'où deux entrées d'apparence redondante ;
 * - sur ces deux pages, chaque ligne pointe deux fois la même fiche (le nom, puis le
 *   compteur d'événements ancré sur #prochains_evenements). querySelector retenant le
 *   premier trouvé, on focalise bien le nom.
 */
export const LISTS = [
    {
        pages: ['index'],
        // .evenement-short sert aussi ailleurs (fiches lieu, organisateur) : on reste dans
        // la colonne centrale pour ne pas embarquer les derniers événements de la marge
        items: '#contenu article.evenement-short',
        link: 'header.titre h3 a'
    },
    {
        pages: ['lieu/lieux'],
        items: '#derniers_lieux tbody tr',
        link: 'a[href^="lieu.php?idL="]'
    },
    {
        pages: ['organisateur/organisateurs'],
        items: '#derniers_lieux tbody tr',
        link: 'a[href^="/organisateur/organisateur.php?idO="]'
    },
    {
        pages: ['admin/gererEvenements'],
        items: '#ajouts tr',
        link: 'a.titre'
    },
    {
        pages: ['admin/users'],
        items: '#ajouts tr',
        link: 'a[href^="/user.php?idP="]'
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
        case 'listNext':
            return Shortcuts.moveInList(1);
        case 'listPrev':
            return Shortcuts.moveInList(-1);
        }

        return false;
    },

    /**
     * Liste parcourable d'une page, s'il y en a une.
     *
     * @param {string|undefined} page valeur de body[data-page]
     * @returns {object|undefined}
     */
    listForPage : function listForPage(page)
    {
        return LISTS.find(function isApplicable(list)
        {
            return list.pages.includes(page);
        });
    },

    /**
     * Étapes du parcours : les items de la liste porteurs d'un lien principal, dans
     * l'ordre du document.
     *
     * @param {object} list entrée de LISTS
     * @returns {Array<{item: Element, link: Element}>}
     */
    listSteps : function listSteps(list)
    {
        return Array.from(document.querySelectorAll(list.items))
            .map(function toStep(item)
            {
                return { item: item, link: item.querySelector(list.link) };
            })
            .filter(function hasLink(step)
            {
                return step.link !== null;
            });
    },

    /**
     * Déplace le focus d'un cran dans la liste de la page.
     *
     * On déplace le focus plutôt que d'entretenir un état « ligne courante » : Entrée
     * ouvre alors nativement, les lecteurs d'écran annoncent l'entité, et un Tab repart
     * de la bonne ligne. Le repère visuel est laissé au CSS, via :focus-within.
     *
     * @param {number} step 1 pour l'élément suivant, -1 pour le précédent
     * @returns {boolean} false si la page n'a pas de liste, pour rendre la touche au navigateur
     */
    moveInList : function moveInList(step)
    {
        const list = Shortcuts.listForPage(document.body.dataset.page);
        if (!list)
        {
            return false;
        }

        const steps = Shortcuts.listSteps(list);
        if (steps.length === 0)
        {
            return false;
        }

        // closest() plutôt qu'une comparaison au lien principal : le focus peut être posé
        // sur n'importe quel lien secondaire de la ligne (compteur, icône d'édition), d'où
        // l'on doit repartir de cette ligne-là et non du début.
        const active = document.activeElement;
        const current = active && active.closest ? active.closest(list.items) : null;
        const index = steps.findIndex(function isCurrent(candidate)
        {
            return candidate.item === current;
        });

        // hors de la liste, j entre par le premier élément et k par le dernier
        const target = index === -1
            ? (step === 1 ? 0 : steps.length - 1)
            : Math.min(Math.max(index + step, 0), steps.length - 1);

        // faire défiler l'item et non le lien : sur l'agenda, un article haut n'entrerait
        // dans le viewport que par son titre
        steps[target].item.scrollIntoView({ block: 'nearest' });
        steps[target].link.focus({ preventScroll: true });

        return true;
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
