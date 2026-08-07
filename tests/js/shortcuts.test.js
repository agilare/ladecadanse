import { describe, it, expect, beforeEach, vi } from 'vitest';
import { Shortcuts } from '../../web/js/global.js';

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
