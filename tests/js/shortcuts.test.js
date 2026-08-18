import { describe, it, expect, beforeEach, vi } from 'vitest';
import { Shortcuts } from '../../web/js/shortcuts.js';

// `handleKeydown` ne lit que quelques propriétés de l'événement : un objet littéral suffit,
// et permet de forger des combinaisons qu'un vrai KeyboardEvent jsdom rendrait laborieuses.
function keydown(key, extra)
{
    return {
        key: key,
        ctrlKey: false,
        altKey: false,
        metaKey: false,
        isComposing: false,
        target: document.body,
        preventDefault: vi.fn(),
        ...extra
    };
}

// jsdom n'implémente pas la navigation : on annule le clic pour éviter les erreurs
// « Not implemented: navigation » sur les liens porteurs d'un href.
function spyOnClick(selector)
{
    const spy = vi.fn();
    document.querySelector(selector).addEventListener('click', function (e)
    {
        e.preventDefault();
        spy();
    });

    return spy;
}

// jsdom ne calcule aucun layout : `offsetParent` y vaut toujours null. focusSearch() s'en sert
// comme test de visibilité, on le force donc explicitement.
function makeVisible(el)
{
    Object.defineProperty(el, 'offsetParent', { configurable: true, value: document.body });
}

beforeEach(function resetDom()
{
    document.body.innerHTML = '';
    document.body.dataset.page = '';
});

// Ces tests verrouillent l'invariant documenté dans global.js : les raccourcis sont résolus sur
// event.key (jamais event.code) et shiftKey n'exclut jamais un raccourci, pour que le site reste
// utilisable quel que soit le layout clavier du visiteur (AZERTY, QWERTZ, QWERTY...).
describe('Shortcuts — indépendance du layout clavier', function ()
{
    it('résout le raccourci sur event.key, en ignorant event.code', function ()
    {
        document.body.innerHTML = '<a href="/evenement-edit.php?action=ajouter">Ajouter</a>';
        const clicked = spyOnClick('a[href^="/evenement-edit.php?action=ajouter"]');

        // sur AZERTY, la touche qui produit « a » porte le code « KeyQ »
        Shortcuts.handleKeydown(keydown('a', { code: 'KeyQ' }));

        expect(clicked).toHaveBeenCalled();
    });

    it('déclenche « / » même avec Shift enfoncé (Shift+7 sur clavier suisse)', function ()
    {
        document.body.dataset.page = 'lieu/lieux';
        document.body.innerHTML = '<div class="table-filters"><input name="nom"></div>';
        const filtre = document.querySelector('.table-filters input[name="nom"]');

        const e = keydown('/', { shiftKey: true });
        Shortcuts.handleKeydown(e);

        expect(document.activeElement).toBe(filtre);
        expect(e.preventDefault).toHaveBeenCalled();
    });

    it('focalise aussi le filtre des organisateurs, qui partage le balisage des lieux', function ()
    {
        document.body.dataset.page = 'organisateur/organisateurs';
        document.body.innerHTML = '<div class="table-filters"><input name="nom"></div>';
        const filtre = document.querySelector('.table-filters input[name="nom"]');

        const e = keydown('/');
        Shortcuts.handleKeydown(e);

        expect(document.activeElement).toBe(filtre);
        expect(e.preventDefault).toHaveBeenCalled();
    });

    it('ne filtre pas les lettres sur shiftKey', function ()
    {
        document.body.innerHTML = '<div id="titre_site"><a>La décadanse</a></div>';
        const clicked = spyOnClick('#titre_site a');

        Shortcuts.handleKeydown(keydown('h', { shiftKey: true }));

        expect(clicked).toHaveBeenCalled();
    });

    it('est insensible à la casse', function ()
    {
        document.body.innerHTML = '<div id="titre_site"><a>La décadanse</a></div>';
        const clicked = spyOnClick('#titre_site a');

        Shortcuts.handleKeydown(keydown('H'));

        expect(clicked).toHaveBeenCalled();
    });
});

describe('Shortcuts — gardes', function ()
{
    it.each(['ctrlKey', 'altKey', 'metaKey', 'isComposing'])(
        'laisse passer la touche quand %s est actif',
        function (flag)
        {
            document.body.innerHTML = '<div id="titre_site"><a>La décadanse</a></div>';
            const clicked = spyOnClick('#titre_site a');

            const e = keydown('h', { [flag]: true });
            Shortcuts.handleKeydown(e);

            expect(clicked).not.toHaveBeenCalled();
            expect(e.preventDefault).not.toHaveBeenCalled();
        }
    );

    // Sans cette garde, taper « a » dans un champ ouvrirait le formulaire d'ajout d'événement.
    it.each(['input', 'textarea', 'select'])(
        'laisse passer la touche quand le focus est dans <%s>',
        function (tag)
        {
            document.body.innerHTML = '<div id="titre_site"><a>La décadanse</a></div>' +
                '<' + tag + '></' + tag + '>';
            const clicked = spyOnClick('#titre_site a');

            const e = keydown('h', { target: document.querySelector(tag) });
            Shortcuts.handleKeydown(e);

            expect(clicked).not.toHaveBeenCalled();
            expect(e.preventDefault).not.toHaveBeenCalled();
        }
    );

    it('laisse passer la touche dans un élément contenteditable', function ()
    {
        document.body.innerHTML = '<div id="titre_site"><a>La décadanse</a></div>';
        const clicked = spyOnClick('#titre_site a');

        // jsdom n'implémente pas contentEditable : on forge la cible que handleKeydown inspecte
        const e = keydown('h', { target: { tagName: 'DIV', isContentEditable: true } });
        Shortcuts.handleKeydown(e);

        expect(clicked).not.toHaveBeenCalled();
        expect(e.preventDefault).not.toHaveBeenCalled();
    });

    it('ne bloque pas la touche quand la cible du raccourci est absente de la page', function ()
    {
        const e = keydown('h');
        Shortcuts.handleKeydown(e);

        expect(e.preventDefault).not.toHaveBeenCalled();
    });
});

describe('Shortcuts — raccourcis propres à une page', function ()
{
    it('ne navigue avec les flèches que sur la page index', function ()
    {
        document.body.innerHTML =
            '<ul class="entete_contenu_navigation"><li><a rel="prev">Précédent</a></li></ul>';
        const clicked = spyOnClick('ul.entete_contenu_navigation a[rel~="prev"]');

        document.body.dataset.page = 'lieu/lieu';
        Shortcuts.handleKeydown(keydown('ArrowLeft'));
        expect(clicked).not.toHaveBeenCalled();

        document.body.dataset.page = 'index';
        Shortcuts.handleKeydown(keydown('ArrowLeft'));
        expect(clicked).toHaveBeenCalled();
    });

    // Sur une fiche événement, j et k quittent la page pour l'événement voisin du même jour :
    // le parcours de l'agenda poursuivi, là où ailleurs ils ne déplacent que le focus.
    describe('j et k sur une fiche événement', function ()
    {
        const PRECEDENT = '<div class="entete_contenu_navigation">' +
            '<a href="/event/evenement.php?idE=1" rel="prev nofollow">Événement précédent</a></div>';
        const SUIVANT = '<div id="footer_navigation"><div class="entete_contenu_navigation">' +
            '<a href="/event/evenement.php?idE=3" rel="next nofollow">Événement suivant</a></div></div>';
        // le calendrier de la colonne gauche, qui porte les mêmes rel pour changer de mois
        const CALENDRIER = '<nav id="navigation_calendrier">' +
            '<a href="/index.php?courant=2026-07-31" rel="prev">Mois précédent</a>' +
            '<a href="/index.php?courant=2026-09-01" rel="next">Mois suivant</a></nav>';

        it('mène à l’événement suivant avec j et au précédent avec k', function ()
        {
            document.body.dataset.page = 'event/evenement';
            document.body.innerHTML = PRECEDENT + SUIVANT + CALENDRIER;
            const suivant = spyOnClick('#footer_navigation a[rel~="next"]');
            const precedent = spyOnClick('.entete_contenu_navigation a[rel~="prev"]');

            Shortcuts.handleKeydown(keydown('j'));
            expect(suivant).toHaveBeenCalled();

            Shortcuts.handleKeydown(keydown('k'));
            expect(precedent).toHaveBeenCalled();
        });

        // Sans quoi j sauterait d'un mois sur l'agenda depuis une fiche.
        it('ne suit jamais la navigation par mois du calendrier', function ()
        {
            document.body.dataset.page = 'event/evenement';
            document.body.innerHTML = CALENDRIER;
            const moisSuivant = spyOnClick('#navigation_calendrier a[rel~="next"]');

            const e = keydown('j');
            Shortcuts.handleKeydown(e);

            expect(moisSuivant).not.toHaveBeenCalled();
            expect(e.preventDefault).not.toHaveBeenCalled();
        });

        // Premier événement du jour : la fiche ne rend pas le lien « précédent ».
        it('rend la touche au navigateur quand l’événement voisin n’existe pas', function ()
        {
            document.body.dataset.page = 'event/evenement';
            document.body.innerHTML = SUIVANT;

            const e = keydown('k');
            Shortcuts.handleKeydown(e);

            expect(e.preventDefault).not.toHaveBeenCalled();
        });
    });

    it('n’ouvre l’édition avec « e » que sur une fiche', function ()
    {
        document.body.innerHTML =
            '<a href="/evenement-edit.php?action=editer&id=1">Éditer</a>';
        const clicked = spyOnClick('a[href*="evenement-edit.php?action=editer"]');

        document.body.dataset.page = 'index';
        Shortcuts.handleKeydown(keydown('e'));
        expect(clicked).not.toHaveBeenCalled();

        document.body.dataset.page = 'event/evenement';
        Shortcuts.handleKeydown(keydown('e'));
        expect(clicked).toHaveBeenCalled();
    });
});

describe('Shortcuts — pagination aux flèches', function ()
{
    // Le bloc de HtmlShrink::getPaginationString(), réduit à ce que le sélecteur regarde :
    // un lien aux extrémités atteignables, un <span class="disabled"> aux bornes.
    function pagination(hasPrev, hasNext)
    {
        const prec = hasPrev
            ? '<a id="prec" href="?page=1" rel="prev">préc</a>'
            : '<span class="disabled">préc</span>';
        const suiv = hasNext
            ? '<a id="suiv" href="?page=3" rel="next">suiv</a>'
            : '<span class="disabled">suiv</span>';

        return `<div class="pagination">${prec}<span class="current">2</span>${suiv}</div>`;
    }

    const PAGES_PAGINEES = [
        'event/search',
        'lieu/lieux',
        'organisateur/organisateurs',
        'user/dashboard',
        'admin/gererEvenements',
        'admin/users'
    ];

    it.each(PAGES_PAGINEES)('recule et avance d’une page sur %s', function (page)
    {
        document.body.dataset.page = page;
        document.body.innerHTML = pagination(true, true);
        const prec = spyOnClick('.pagination a[rel~="prev"]');
        const suiv = spyOnClick('.pagination a[rel~="next"]');

        Shortcuts.handleKeydown(keydown('ArrowLeft'));
        expect(prec).toHaveBeenCalled();

        Shortcuts.handleKeydown(keydown('ArrowRight'));
        expect(suiv).toHaveBeenCalled();
    });

    // Sans quoi les flèches seraient avalées sur la dernière page, où elles doivent
    // continuer de faire défiler la page.
    it('rend la flèche au navigateur à la borne, où le lien devient un span', function ()
    {
        document.body.dataset.page = 'admin/users';
        document.body.innerHTML = pagination(false, true);

        const e = keydown('ArrowLeft');
        Shortcuts.handleKeydown(e);

        expect(e.preventDefault).not.toHaveBeenCalled();
    });

    it('ne branche les flèches que sur les pages à résultats', function ()
    {
        document.body.dataset.page = 'event/evenement';
        document.body.innerHTML = pagination(true, true);
        const suiv = spyOnClick('.pagination a[rel~="next"]');

        const e = keydown('ArrowRight');
        Shortcuts.handleKeydown(e);

        expect(suiv).not.toHaveBeenCalled();
        expect(e.preventDefault).not.toHaveBeenCalled();
    });

    // Ces pages affichent le même bloc avant et après le tableau : les deux liens mènent à la
    // même page, mais un seul clic doit partir.
    it('ne suit qu’un seul lien quand la page répète le bloc de pagination', function ()
    {
        document.body.dataset.page = 'lieu/lieux';
        document.body.innerHTML = pagination(true, true) + '<table></table>' + pagination(true, true);
        const liens = Array.from(document.querySelectorAll('.pagination a[rel~="next"]'));
        const clics = liens.map(function spy(lien)
        {
            const clicked = vi.fn();
            lien.addEventListener('click', function (e)
            {
                e.preventDefault();
                clicked();
            });
            return clicked;
        });

        Shortcuts.handleKeydown(keydown('ArrowRight'));

        expect(clics[0]).toHaveBeenCalled();
        expect(clics[1]).not.toHaveBeenCalled();
    });
});

describe('Shortcuts — back-office', function ()
{
    const MENU_ADMIN =
        '<a href="/admin/index.php">Tableau de bord</a>' +
        '<a href="/admin/gererEvenements.php">Événements</a>' +
        '<a href="/admin/users.php">Comptes</a>';

    it('ouvre la gestion des événements avec « b » et les comptes avec « u »', function ()
    {
        document.body.dataset.page = 'index';
        document.body.innerHTML = MENU_ADMIN;
        const evenements = spyOnClick('a[href="/admin/gererEvenements.php"]');
        const comptes = spyOnClick('a[href="/admin/users.php"]');

        Shortcuts.handleKeydown(keydown('b'));
        expect(evenements).toHaveBeenCalled();

        Shortcuts.handleKeydown(keydown('u'));
        expect(comptes).toHaveBeenCalled();
    });

    // _header.inc.php ne rend ces liens qu'aux administrateurs : pour tous les autres visiteurs,
    // les touches doivent rester celles du navigateur.
    it('laisse « b » et « u » au navigateur sans menu d’administration', function ()
    {
        document.body.dataset.page = 'index';
        document.body.innerHTML = '<div id="titre_site"><a>La décadanse</a></div>';

        ['b', 'u'].forEach(function (touche)
        {
            const e = keydown(touche);
            Shortcuts.handleKeydown(e);
            expect(e.preventDefault).not.toHaveBeenCalled();
        });
    });

    it.each(['admin/gererEvenements', 'admin/users'])(
        'focalise le filtre de %s avec « / »',
        function (page)
        {
            document.body.dataset.page = page;
            document.body.innerHTML =
                '<span class="search-field"><input type="search" name="terme"></span>';
            const filtre = document.querySelector('.search-field input[name="terme"]');

            const e = keydown('/');
            Shortcuts.handleKeydown(e);

            expect(document.activeElement).toBe(filtre);
            expect(e.preventDefault).toHaveBeenCalled();
        }
    );
});

describe('Shortcuts.focusSearch', function ()
{
    const RECHERCHE_MOBILE = '<form class="recherche_mobile"><input class="mots"></form>';
    const BTN_SEARCH = '<button id="btn_search">Rechercher</button>';

    it('focus le champ mobile lorsqu’il est déjà ouvert, sans le refermer', function ()
    {
        document.body.innerHTML = RECHERCHE_MOBILE + BTN_SEARCH;
        const champMobile = document.querySelector('form.recherche_mobile input.mots');
        makeVisible(champMobile);
        const btnClicked = spyOnClick('#btn_search');

        Shortcuts.handleKeydown(keydown('s'));

        expect(document.activeElement).toBe(champMobile);
        expect(btnClicked).not.toHaveBeenCalled();
    });

    it('ouvre le panneau mobile via #btn_search quand le champ est masqué', function ()
    {
        document.body.innerHTML = RECHERCHE_MOBILE + BTN_SEARCH;
        makeVisible(document.getElementById('btn_search'));
        const btnClicked = spyOnClick('#btn_search');

        Shortcuts.handleKeydown(keydown('s'));

        expect(btnClicked).toHaveBeenCalled();
    });

    it('se rabat sur le champ de recherche desktop', function ()
    {
        document.body.innerHTML = '<form class="recherche"><input class="mots"></form>';
        const champDesktop = document.querySelector('form.recherche input.mots');

        const e = keydown('s');
        Shortcuts.handleKeydown(e);

        expect(document.activeElement).toBe(champDesktop);
        expect(e.preventDefault).toHaveBeenCalled();
    });
});

describe('Shortcuts — parcours des listes avec j/k', function ()
{
    // jsdom ne calcule aucun layout et n'implémente pas scrollIntoView : sans ce bouchon,
    // moveInList lèverait une TypeError avant même de déplacer le focus.
    beforeEach(function stubScroll()
    {
        Element.prototype.scrollIntoView = vi.fn();
    });

    // La table des lieux, réduite à ce que les sélecteurs de LISTS regardent : un en-tête
    // hors <tbody>, puis des lignes portant chacune deux liens vers la même fiche.
    function listeLieux(noms)
    {
        const lignes = noms.map(function toRow(nom, i)
        {
            return '<tr>' +
                '<td><a href="lieu.php?idL=' + (i + 1) + '">' + nom + '</a></td>' +
                '<td><a href="lieu.php?idL=' + (i + 1) + '#prochains_evenements">3</a></td>' +
                '</tr>';
        }).join('');

        document.body.dataset.page = 'lieu/lieux';
        document.body.innerHTML =
            '<table id="derniers_lieux">' +
            '<thead><tr><th>Nom</th><th>Événements</th></tr></thead>' +
            '<tbody>' + lignes + '</tbody>' +
            '</table>';
    }

    function liensPrincipaux()
    {
        return Array.from(
            document.querySelectorAll('#derniers_lieux tbody tr td:first-child a')
        );
    }

    it('entre dans la liste par le premier élément avec j, par le dernier avec k', function ()
    {
        listeLieux(['Cave12', 'L’Usine', 'Le Zoo']);
        const liens = liensPrincipaux();

        Shortcuts.handleKeydown(keydown('j'));
        expect(document.activeElement).toBe(liens[0]);

        // blur() et non body.focus() : jsdom ne rend pas <body> focalisable, le focus
        // resterait sur le lien et k se contenterait de reculer d'un cran
        document.activeElement.blur();
        Shortcuts.handleKeydown(keydown('k'));
        expect(document.activeElement).toBe(liens[2]);
    });

    it('avance et recule d’un élément à la fois', function ()
    {
        listeLieux(['Cave12', 'L’Usine', 'Le Zoo']);
        const liens = liensPrincipaux();

        Shortcuts.handleKeydown(keydown('j'));
        Shortcuts.handleKeydown(keydown('j'));
        expect(document.activeElement).toBe(liens[1]);

        Shortcuts.handleKeydown(keydown('k'));
        expect(document.activeElement).toBe(liens[0]);
    });

    // Choix assumé : pas de bouclage, comme dans vi.
    it('ne boucle pas aux extrémités', function ()
    {
        listeLieux(['Cave12', 'L’Usine']);
        const liens = liensPrincipaux();

        // sur le premier élément, k ne repart pas du dernier
        liens[0].focus();
        Shortcuts.handleKeydown(keydown('k'));
        expect(document.activeElement).toBe(liens[0]);

        // sur le dernier, j ne revient pas au premier
        liens[1].focus();
        Shortcuts.handleKeydown(keydown('j'));
        expect(document.activeElement).toBe(liens[1]);
    });

    it('focalise le nom et non le second lien de la ligne', function ()
    {
        listeLieux(['Cave12']);

        Shortcuts.handleKeydown(keydown('j'));

        expect(document.activeElement.getAttribute('href')).toBe('lieu.php?idL=1');
    });

    // La ligne d'en-tête des tableaux du back-office est posée hors <tbody> : c'est son
    // absence de lien principal, et non un sélecteur dédié, qui l'exclut du parcours.
    it('saute les lignes dépourvues de lien principal', function ()
    {
        document.body.dataset.page = 'admin/users';
        document.body.innerHTML =
            '<table id="ajouts">' +
            '<tr><th>Pseudo</th></tr>' +
            '<tr><td><a href="/user/dashboard.php?idP=1">alice</a></td></tr>' +
            '</table>';

        Shortcuts.handleKeydown(keydown('j'));

        expect(document.activeElement).toBe(document.querySelector('a[href="/user/dashboard.php?idP=1"]'));
    });

    // Le focus traîne souvent sur un lien secondaire (compteur, icône d'édition) : j doit
    // repartir de cette ligne-là, pas du début de la liste.
    it('repart de la ligne courante même depuis un lien secondaire', function ()
    {
        listeLieux(['Cave12', 'L’Usine', 'Le Zoo']);
        const compteurPremiereLigne =
            document.querySelector('#derniers_lieux tbody tr td:nth-child(2) a');
        compteurPremiereLigne.focus();

        Shortcuts.handleKeydown(keydown('j'));

        expect(document.activeElement).toBe(liensPrincipaux()[1]);
    });

    it('rend la touche au navigateur sur une page sans liste', function ()
    {
        document.body.dataset.page = 'event/evenement';
        document.body.innerHTML = '<a href="/lieu/lieu.php?idL=1">Un lieu</a>';

        const e = keydown('j');
        Shortcuts.handleKeydown(e);

        expect(e.preventDefault).not.toHaveBeenCalled();
        expect(document.activeElement).toBe(document.body);
    });

    it('rend la touche au navigateur quand la liste est vide', function ()
    {
        document.body.dataset.page = 'lieu/lieux';
        document.body.innerHTML = '<table id="derniers_lieux"><tbody></tbody></table>';

        const e = keydown('j');
        Shortcuts.handleKeydown(e);

        expect(e.preventDefault).not.toHaveBeenCalled();
    });

    it('parcourt les résultats de recherche par leur titre', function ()
    {
        document.body.dataset.page = 'event/search';
        document.body.innerHTML =
            '<div id="res_recherche"><table><tbody>' +
            '<tr><td class="desc_even"><h3><a href="evenement.php?idE=1">Concert</a></h3></td>' +
            '<td class="date"><a href="/index.php?courant=2026-08-16">16 août</a></td></tr>' +
            '<tr><td class="desc_even"><h3><a href="evenement.php?idE=2">Expo</a></h3></td>' +
            '<td class="date"><a href="/index.php?courant=2026-08-17">17 août</a></td></tr>' +
            '</tbody></table></div>';

        Shortcuts.handleKeydown(keydown('j'));
        expect(document.activeElement.getAttribute('href')).toBe('evenement.php?idE=1');

        Shortcuts.handleKeydown(keydown('j'));
        expect(document.activeElement.getAttribute('href')).toBe('evenement.php?idE=2');
    });

    // Le tableau d'événements d'une fiche : la première colonne mène à la journée d'agenda,
    // c'est a.url qu'il faut focaliser. Les lignes de titre de mois n'ont aucun lien.
    it.each(['lieu/lieu', 'organisateur/organisateur'])(
        'parcourt le tableau d’événements de %s en sautant les titres de mois',
        function (page)
        {
            document.body.dataset.page = page;
            document.body.innerHTML =
                '<section id="prochains_evenements"><table>' +
                '<tr><td colspan="5" class="mois">Août</td></tr>' +
                '<tr class="vevent evenement">' +
                '<td class="dtstart"><a href="/index.php?courant=2026-08-16">sam 16</a></td>' +
                '<td><a class="url" href="/event/evenement.php?idE=1"><strong>Concert</strong></a></td>' +
                '</tr>' +
                '<tr class="vevent evenement">' +
                '<td class="dtstart"><a href="/index.php?courant=2026-09-02">mer 2</a></td>' +
                '<td><a class="url" href="/event/evenement.php?idE=2"><strong>Expo</strong></a></td>' +
                '</tr>' +
                '</table></section>';

            Shortcuts.handleKeydown(keydown('j'));
            expect(document.activeElement.getAttribute('href')).toBe('/event/evenement.php?idE=1');

            Shortcuts.handleKeydown(keydown('j'));
            expect(document.activeElement.getAttribute('href')).toBe('/event/evenement.php?idE=2');
        }
    );

    // Le tableau de bord empile trois tableaux dans #tableaux : le parcours les enchaîne, en
    // écartant les en-têtes et les lignes de séparation de date, dépourvues de lien.
    it('enchaîne les trois tableaux du tableau de bord', function ()
    {
        document.body.dataset.page = 'admin/index';
        document.body.innerHTML =
            '<div id="tableaux">' +
            '<table><thead><tr><th>Heure</th><th>Compte</th></tr></thead><tbody>' +
            '<tr><td colspan="6">jeudi 13 août</td></tr>' +
            '<tr><td>18:02</td><td><a href="/user/dashboard.php?idP=7">alice</a></td></tr>' +
            '</tbody></table>' +
            '<table id="derniers_evenements_ajoutes"><tbody>' +
            '<tr><td>09:14</td><td><a class="titre" href="/event/evenement.php?idE=1">Concert</a></td></tr>' +
            '</tbody></table>' +
            '<table><tr><th>Type</th><th>Lieu</th></tr>' +
            '<tr><td>presentation</td><td><a href="/lieu/lieu.php?idL=3">Cave12</a></td></tr>' +
            '</table>' +
            '</div>';

        const attendus = [
            '/user/dashboard.php?idP=7',
            '/event/evenement.php?idE=1',
            '/lieu/lieu.php?idL=3'
        ];

        attendus.forEach(function (href)
        {
            Shortcuts.handleKeydown(keydown('j'));
            expect(document.activeElement.getAttribute('href')).toBe(href);
        });

        // et le parcours revient en arrière d'un tableau à l'autre
        Shortcuts.handleKeydown(keydown('k'));
        expect(document.activeElement.getAttribute('href')).toBe('/event/evenement.php?idE=1');
    });

    // Sans cette garde, filtrer les lieux par « jura » sauterait de ligne en ligne.
    it('laisse taper j et k dans le filtre de la liste', function ()
    {
        listeLieux(['Cave12', 'L’Usine']);
        const filtre = document.createElement('input');
        document.body.appendChild(filtre);

        const e = keydown('j', { target: filtre });
        Shortcuts.handleKeydown(e);

        expect(e.preventDefault).not.toHaveBeenCalled();
        expect(document.activeElement).toBe(document.body);
    });
});
