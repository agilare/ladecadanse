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
            '<tr><td><a href="/user.php?idP=1">alice</a></td></tr>' +
            '</table>';

        Shortcuts.handleKeydown(keydown('j'));

        expect(document.activeElement).toBe(document.querySelector('a[href="/user.php?idP=1"]'));
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
