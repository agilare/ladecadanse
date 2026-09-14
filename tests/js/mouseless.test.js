import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { Mouseless } from '../../web/js/mouseless.js';
import { Shortcuts } from '../../web/js/shortcuts.js';

const LIEN_LIEUX = '<a href="/lieu/lieux.php">Lieux</a>';
const RECHERCHE = '<form class="recherche"><input class="mots" placeholder="Rechercher un événement"></form>';

// jsdom ne calcule aucun layout : `offsetParent` y vaut toujours null, or le mode s'en sert
// pour ne pas baliser un élément masqué (le champ de recherche mobile en affichage desktop).
function makeVisible(el)
{
    Object.defineProperty(el, 'offsetParent', { configurable: true, value: document.body });

    return el;
}

/**
 * `isTrusted` est en lecture seule et non redéfinissable, dans jsdom comme dans un navigateur :
 * impossible de forger un vrai clic de souris. On appelle donc `onClickCapture` directement avec
 * un objet littéral — même convention que shortcuts.test.js pour `handleKeydown`, qui ne lit lui
 * aussi que quelques propriétés de l'événement.
 *
 * `detail` distingue le clic pointeur (>= 1) du clic synthétisé par Entrée au clavier (0).
 */
function clickOn(selector, extra)
{
    const e = {
        target: document.querySelector(selector),
        isTrusted: true,
        detail: 1,
        preventDefault: vi.fn(),
        stopPropagation: vi.fn(),
        ...extra
    };
    Mouseless.onClickCapture(e);

    return e;
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

function activateMode()
{
    document.documentElement.classList.add('mouseless');
    Mouseless.init();
}

beforeEach(function resetDom()
{
    document.body.innerHTML = '';
    document.body.dataset.page = '';
});

afterEach(function leaveMode()
{
    // les écouteurs sont posés sur `document`, qui survit au reset du body
    Mouseless.disable();
    vi.useRealTimers();
});

function pressEscape()
{
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
}

describe('Mouseless — portail d’accès', function ()
{
    // _header.inc.php n'émet le script d'amorçage (donc la classe) que pour ADMIN et
    // SUPERADMIN : sans elle, le module doit rester totalement inerte.
    it('ne pose ni écouteur ni badge sans la classe mouseless sur <html>', function ()
    {
        document.body.innerHTML = LIEN_LIEUX;
        makeVisible(document.querySelector('a'));
        const listen = vi.spyOn(document, 'addEventListener');

        Mouseless.init();

        expect(listen).not.toHaveBeenCalledWith('click', expect.anything(), true);
        expect(document.querySelector('kbd.mouseless-key')).toBeNull();
        expect(document.getElementById('mouseless-banner')).toBeNull();
        listen.mockRestore();
    });

    it('reste inactif sur un pointeur grossier, faute de clavier physique', function ()
    {
        // jsdom n'implémente pas matchMedia : on l'installe le temps du test
        window.matchMedia = function () { return { matches: true }; };
        document.body.innerHTML = LIEN_LIEUX;
        makeVisible(document.querySelector('a'));
        const listen = vi.spyOn(document, 'addEventListener');

        activateMode();

        expect(listen).not.toHaveBeenCalledWith('click', expect.anything(), true);
        expect(document.documentElement.classList.contains('mouseless')).toBe(false);
        listen.mockRestore();
        delete window.matchMedia;
    });
});

describe('Mouseless — neutralisation du clic', function ()
{
    it('bloque le clic souris sur une cible du registre', function ()
    {
        document.body.innerHTML = LIEN_LIEUX;
        makeVisible(document.querySelector('a'));

        activateMode();
        const e = clickOn('a');

        expect(e.preventDefault).toHaveBeenCalled();
        expect(e.stopPropagation).toHaveBeenCalled();
    });

    // Bloquer ce clic-là casserait la navigation Tab + Entrée, ce qui serait absurde
    // pour un mode censé promouvoir le clavier.
    it('laisse passer le clic synthétisé par Entrée au clavier (detail 0)', function ()
    {
        document.body.innerHTML = LIEN_LIEUX;
        makeVisible(document.querySelector('a'));

        activateMode();
        const e = clickOn('a', { detail: 0 });

        expect(e.preventDefault).not.toHaveBeenCalled();
    });

    it('laisse passer un clic non « trusted », celui que produit el.click()', function ()
    {
        document.body.innerHTML = LIEN_LIEUX;
        makeVisible(document.querySelector('a'));

        activateMode();
        const e = clickOn('a', { isTrusted: false });

        expect(e.preventDefault).not.toHaveBeenCalled();
    });

    // Vérification de bout en bout du câblage : l'écouteur est bien posé en capture sur
    // document, et le clic synthétique du raccourci le traverse jusqu'à sa cible.
    it('laisse le raccourci atteindre sa cible, mode actif', function ()
    {
        document.body.innerHTML = LIEN_LIEUX;
        makeVisible(document.querySelector('a'));
        const clicked = spyOnClick('a[href^="/lieu/lieux.php"]');

        activateMode();
        Shortcuts.activate('a[href^="/lieu/lieux.php"]');

        expect(clicked).toHaveBeenCalled();
    });

    it('ne bloque pas un lien étranger au registre', function ()
    {
        document.body.innerHTML = '<a href="/articles/apropos.php">À propos</a>';

        activateMode();
        const e = clickOn('a');

        expect(e.preventDefault).not.toHaveBeenCalled();
    });

    it('ne neutralise un raccourci de page que sur la page concernée', function ()
    {
        document.body.innerHTML = '<div class="action_editer"><a href="/lieu/edit.php">Éditer</a></div>';
        makeVisible(document.querySelector('.action_editer a'));

        document.body.dataset.page = 'index';
        activateMode();

        expect(clickOn('.action_editer a').preventDefault).not.toHaveBeenCalled();

        Mouseless.disable();
        document.body.dataset.page = 'lieu/lieu';
        activateMode();

        expect(clickOn('.action_editer a').preventDefault).toHaveBeenCalled();
    });

    it('bloque le clic sur le flyer de la fiche événement', function ()
    {
        document.body.innerHTML = '<figure id="illustrations"><a class="magnific-popup" href="/f.jpg"><img alt=""></a></figure>';
        makeVisible(document.querySelector('#illustrations a'));
        document.body.dataset.page = 'event/evenement';

        activateMode();
        // le clic part de l'image : le blocage doit remonter jusqu'au lien
        const e = clickOn('#illustrations img');

        expect(e.preventDefault).toHaveBeenCalled();
    });
});

describe('Mouseless — champs de saisie', function ()
{
    it('annule le mousedown pour empêcher la prise de focus à la souris', function ()
    {
        document.body.innerHTML = RECHERCHE;
        activateMode();

        const e = new MouseEvent('mousedown', { bubbles: true, cancelable: true });
        document.querySelector('input.mots').dispatchEvent(e);

        expect(e.defaultPrevented).toBe(true);
    });

    // `disabled` ou `readonly` empêcheraient aussi le clic, mais casseraient le raccourci.
    it('laisse le raccourci focaliser le champ', function ()
    {
        document.body.innerHTML = RECHERCHE;
        activateMode();

        Shortcuts.focusSearch();

        expect(document.activeElement).toBe(document.querySelector('input.mots'));
    });

    it('préfixe le placeholder par la touche, puis le restaure à la sortie', function ()
    {
        document.body.innerHTML = RECHERCHE;
        const champ = document.querySelector('input.mots');

        activateMode();
        expect(champ.placeholder).toBe('« S » Rechercher un événement');

        Mouseless.disable();
        expect(champ.placeholder).toBe('Rechercher un événement');
    });

    it('ne laisse pas de placeholder vide sur un champ qui n’en avait pas', function ()
    {
        document.body.innerHTML = '<form class="recherche"><input class="mots"></form>';
        const champ = document.querySelector('input.mots');

        activateMode();
        Mouseless.disable();

        expect(champ.hasAttribute('placeholder')).toBe(false);
    });

    it('rend le mousedown au champ à la sortie du mode', function ()
    {
        document.body.innerHTML = RECHERCHE;
        activateMode();
        Mouseless.disable();

        const e = new MouseEvent('mousedown', { bubbles: true, cancelable: true });
        document.querySelector('input.mots').dispatchEvent(e);

        expect(e.defaultPrevented).toBe(false);
    });
});

describe('Mouseless — affichage de la touche', function ()
{
    it('insère le badge de la touche après la cible', function ()
    {
        document.body.innerHTML = LIEN_LIEUX;
        makeVisible(document.querySelector('a'));

        activateMode();

        const badge = document.querySelector('kbd.mouseless-key');
        expect(badge).not.toBeNull();
        expect(badge.textContent).toBe('L');
        expect(badge.previousElementSibling).toBe(document.querySelector('a'));
        expect(document.querySelector('a').getAttribute('aria-keyshortcuts')).toBe('L');
    });

    // Certaines cibles sont dans le HTML sans être affichées — le menu lieux/organisateurs
    // est dupliqué entre en-tête et pied selon la largeur : un badge y serait invisible
    // mais compterait dans la mise en page une fois le bloc réaffiché.
    it('ne balise pas un élément masqué, mais neutralise quand même son clic', function ()
    {
        document.body.innerHTML = LIEN_LIEUX + LIEN_LIEUX;
        const [visible, masque] = document.querySelectorAll('a[href^="/lieu/lieux.php"]');
        makeVisible(visible);

        activateMode();

        expect(document.querySelectorAll('kbd.mouseless-key').length).toBe(1);
        expect(visible.nextElementSibling.tagName).toBe('KBD');
        expect(masque.nextElementSibling.tagName).not.toBe('KBD');
        expect(clickOn('a[href^="/lieu/lieux.php"]', { target: masque }).preventDefault).toHaveBeenCalled();
    });

    it('fait clignoter le badge quand un clic est bloqué', function ()
    {
        document.body.innerHTML = LIEN_LIEUX;
        makeVisible(document.querySelector('a'));

        activateMode();
        clickOn('a');

        expect(document.querySelector('kbd.mouseless-key').classList.contains('mouseless-flash')).toBe(true);
    });

    it('retire badges et bandeau à la sortie du mode', function ()
    {
        document.body.innerHTML = LIEN_LIEUX;
        makeVisible(document.querySelector('a'));

        activateMode();
        expect(document.getElementById('mouseless-banner')).not.toBeNull();

        Mouseless.disable();

        expect(document.querySelector('kbd.mouseless-key')).toBeNull();
        expect(document.getElementById('mouseless-banner')).toBeNull();
        expect(document.documentElement.classList.contains('mouseless')).toBe(false);
    });
});

describe('Mouseless — sortie du mode', function ()
{
    it('quitte le mode sur une double frappe d’Échap et rend le clic aux liens', function ()
    {
        document.body.innerHTML = LIEN_LIEUX;
        makeVisible(document.querySelector('a'));

        activateMode();
        pressEscape();
        pressEscape();

        expect(document.documentElement.classList.contains('mouseless')).toBe(false);
        expect(clickOn('a').preventDefault).not.toHaveBeenCalled();
        expect(window.localStorage.getItem('ladecadanse.mouseless')).toBe('0');
    });

    // Échap sert partout ailleurs (fermer la fenêtre du flyer, annuler une saisie) : un
    // appui isolé faisait quitter le mode par accident.
    it('reste dans le mode sur un Échap isolé, et le signale', function ()
    {
        document.body.innerHTML = LIEN_LIEUX;
        makeVisible(document.querySelector('a'));

        activateMode();
        pressEscape();

        expect(document.documentElement.classList.contains('mouseless')).toBe(true);
        expect(clickOn('a').preventDefault).toHaveBeenCalled();
        // hors page à liste, le bandeau n'a que le rappel « Échap » : il clignote pour
        // faire comprendre qu'une seconde frappe est attendue
        expect(document.querySelector('#mouseless-banner kbd').classList.contains('mouseless-flash')).toBe(true);
    });

    it('reste dans le mode sur deux Échap trop espacés', function ()
    {
        vi.useFakeTimers();
        document.body.innerHTML = LIEN_LIEUX;
        makeVisible(document.querySelector('a'));

        activateMode();
        pressEscape();
        vi.advanceTimersByTime(2000);
        pressEscape();

        expect(document.documentElement.classList.contains('mouseless')).toBe(true);
    });

    it('propose un lien de sortie qui conserve les autres paramètres d’URL', function ()
    {
        window.history.replaceState(null, '', '/index.php?courant=2026-08-10');
        document.body.innerHTML = LIEN_LIEUX;

        activateMode();

        const lien = document.querySelector('#mouseless-banner a');
        expect(lien.getAttribute('href')).toContain('courant=2026-08-10');
        expect(lien.getAttribute('href')).toContain('mouseless=0');
    });

    it('retire le paramètre mouseless de l’URL affichée', function ()
    {
        window.history.replaceState(null, '', '/index.php?courant=2026-08-10&mouseless=1');

        activateMode();

        expect(window.location.search).toBe('?courant=2026-08-10');
    });
});
