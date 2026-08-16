import { Shortcuts } from './shortcuts.js';

/**
 * Mode « mouseless » : entraînement aux raccourcis clavier (issue #112).
 *
 * Une fois actif, les éléments que les raccourcis savent atteindre ne répondent plus
 * au clic de souris et affichent la touche à utiliser. Le but est pédagogique : rendre
 * la souris inopérante là où un raccourci existe est le seul moyen fiable de le faire
 * entrer dans les doigts.
 *
 * L'activation se fait par `?mouseless=1` et n'est rendue possible qu'aux niveaux
 * ADMIN et SUPERADMIN : c'est _header.inc.php qui décide d'émettre ou non le script
 * d'amorçage posant la classe `mouseless` sur <html>. Ce module ne fait rien sans
 * elle, ce qui suffit à refermer l'accès côté client.
 *
 * La liste des cibles vient du registre de shortcuts.js : le mode et les raccourcis
 * ne peuvent donc pas diverger.
 */

const STORAGE_KEY = 'ladecadanse.mouseless';
const MODE_CLASS = 'mouseless';
const FLASH_CLASS = 'mouseless-flash';
const FLASH_DURATION_IN_MS = 400;
const BANNER_ID = 'mouseless-banner';
// délai entre les deux Échap de la sortie : assez large pour une frappe volontaire,
// trop court pour réunir par hasard deux Échap tapés pour deux raisons différentes
const DOUBLE_ESCAPE_DELAY_IN_MS = 700;

export const Mouseless =
{
    /** @type {Map<Element, Element>} cible neutralisée -> son badge <kbd> (absent pour les champs) */
    badges : new Map(),

    /** @type {Array<HTMLInputElement>} champs dont le placeholder a été préfixé */
    fields : [],

    /** @type {string} sélecteur combiné de toutes les cibles de la page */
    targetsSelector : '',

    /** @type {number} date de la dernière frappe d'Échap, pour reconnaître la double frappe */
    lastEscapeAt : 0,

    /** @type {?Element} le « Échap » du bandeau, qu'on fait clignoter à la première frappe */
    escapeKey : null,

    init : function initMouselessMode()
    {
        if (!document.documentElement.classList.contains(MODE_CLASS))
        {
            return;
        }

        // Sans clavier physique le mode ne rend le site qu'inutilisable : on le laisse
        // mémorisé (l'utilisateur le retrouvera sur son poste) mais inactif ici.
        if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches)
        {
            document.documentElement.classList.remove(MODE_CLASS);
            return;
        }

        Mouseless.stripUrlParam();
        Mouseless.enable();
    },

    enable : function enableMouselessMode()
    {
        const entries = Shortcuts.entriesForPage(document.body.dataset.page);

        Mouseless.targetsSelector = entries
            .reduce(function collectSelectors(all, entry)
            {
                return all.concat(entry.selectors);
            }, [])
            .join(', ');

        entries.forEach(function decorateEntry(entry)
        {
            entry.selectors.forEach(function decorateSelector(selector)
            {
                document.querySelectorAll(selector).forEach(function decorateTarget(target)
                {
                    Mouseless.decorate(target, entry);
                });
            });
        });

        document.addEventListener('click', Mouseless.onClickCapture, true);
        document.addEventListener('keydown', Mouseless.onKeydown);
        Mouseless.showBanner();
    },

    disable : function disableMouselessMode()
    {
        document.removeEventListener('click', Mouseless.onClickCapture, true);
        document.removeEventListener('keydown', Mouseless.onKeydown);

        Mouseless.badges.forEach(function removeBadge(badge)
        {
            if (badge)
            {
                badge.remove();
            }
        });
        Mouseless.badges.clear();

        Mouseless.fields.forEach(function restoreField(field)
        {
            field.removeEventListener('mousedown', Mouseless.onFieldPointer, true);
            field.removeEventListener('touchstart', Mouseless.onFieldPointer, { capture: true });

            if (field.dataset.mouselessPlaceholder === undefined)
            {
                return;
            }
            // un champ sans placeholder d'origine ne doit pas en garder un vide
            if (field.dataset.mouselessPlaceholder === '')
            {
                field.removeAttribute('placeholder');
            }
            else
            {
                field.placeholder = field.dataset.mouselessPlaceholder;
            }
            delete field.dataset.mouselessPlaceholder;
        });
        Mouseless.fields = [];

        const banner = document.getElementById(BANNER_ID);
        if (banner)
        {
            banner.remove();
        }
        Mouseless.escapeKey = null;
        Mouseless.lastEscapeAt = 0;

        Mouseless.targetsSelector = '';
        document.documentElement.classList.remove(MODE_CLASS);
        Mouseless.remember(false);
    },

    /**
     * Signale la touche sur une cible : badge <kbd> après un lien, préfixe du
     * placeholder pour un champ (on ne peut rien insérer dans un <input>).
     */
    decorate : function decorateTarget(target, entry)
    {
        if (Mouseless.badges.has(target))
        {
            return;
        }

        // aria-keyshortcuts a du sens en permanence, pas seulement dans ce mode
        target.setAttribute('aria-keyshortcuts', entry.label);

        if (target.tagName === 'INPUT')
        {
            target.dataset.mouselessPlaceholder = target.getAttribute('placeholder') || '';
            target.placeholder = `« ${entry.label} » ${target.dataset.mouselessPlaceholder}`;
            target.addEventListener('mousedown', Mouseless.onFieldPointer, true);
            target.addEventListener('touchstart', Mouseless.onFieldPointer, { capture: true, passive: false });
            Mouseless.fields.push(target);
            Mouseless.badges.set(target, null);
            return;
        }

        // offsetParent vaut null pour un élément masqué : inutile de baliser
        // le champ de recherche mobile quand on est sur l'affichage desktop.
        if (target.offsetParent === null)
        {
            Mouseless.badges.set(target, null);
            return;
        }

        const badge = document.createElement('kbd');
        badge.className = 'mouseless-key';
        badge.setAttribute('aria-hidden', 'true');
        badge.textContent = entry.label;
        target.insertAdjacentElement('afterend', badge);
        Mouseless.badges.set(target, badge);
    },

    /**
     * Neutralise le clic souris sur les cibles du registre.
     *
     * Deux tests, tous deux nécessaires :
     * - `isTrusted` est faux pour le `el.click()` déclenché par un raccourci, qui doit
     *   donc continuer de passer ;
     * - `detail` vaut 0 pour le clic synthétisé par Entrée sur un élément focalisé au
     *   clavier : le bloquer casserait la navigation Tab + Entrée, ce qui serait absurde
     *   pour un mode censé promouvoir le clavier.
     */
    onClickCapture : function onClickCapture(e)
    {
        if (!e.isTrusted || e.detail === 0 || !Mouseless.targetsSelector)
        {
            return;
        }

        const target = e.target.closest ? e.target.closest(Mouseless.targetsSelector) : null;
        if (!target)
        {
            return;
        }

        e.preventDefault();
        e.stopPropagation();
        Mouseless.flash(target);
    },

    /**
     * Annuler le mousedown empêche la prise de focus à la souris sans toucher au champ :
     * `disabled` ou `readonly` casseraient le focus() du raccourci.
     *
     * Pas de garde `isTrusted` ici, contrairement au clic : le raccourci appelle focus(),
     * qui ne produit aucun mousedown. Tout mousedown sur ces champs vient donc du pointeur.
     */
    onFieldPointer : function onFieldPointer(e)
    {
        e.preventDefault();
    },

    /**
     * Sortie à la double frappe d'Échap. Un seul appui ne suffit pas : Échap sert partout
     * ailleurs — fermer la fenêtre du flyer, annuler une saisie, interrompre un chargement —
     * et faisait quitter le mode par accident, alors qu'on le garde des jours durant.
     *
     * La première frappe fait clignoter le rappel du bandeau : de quoi comprendre qu'il en
     * faut une seconde sans avoir à l'avoir lu avant.
     */
    onKeydown : function onKeydown(e)
    {
        if (e.key !== 'Escape')
        {
            return;
        }

        const now = Date.now();
        const isSecondPress = (now - Mouseless.lastEscapeAt) <= DOUBLE_ESCAPE_DELAY_IN_MS;
        // une frappe isolée réamorce le compte, une double frappe le remet à zéro pour
        // qu'une troisième ne se raccroche pas à la deuxième
        Mouseless.lastEscapeAt = isSecondPress ? 0 : now;

        if (isSecondPress)
        {
            Mouseless.disable();
            return;
        }

        if (Mouseless.escapeKey)
        {
            Mouseless.flash(Mouseless.escapeKey);
        }
    },

    /** Rappelle la touche attendue : le badge (ou la cible elle-même) clignote. */
    flash : function flashTarget(target)
    {
        const flashed = Mouseless.badges.get(target) || target;

        flashed.classList.remove(FLASH_CLASS);
        // forcer un reflow pour que l'animation reparte à chaque clic
        void flashed.offsetWidth;
        flashed.classList.add(FLASH_CLASS);

        window.setTimeout(function endFlash()
        {
            flashed.classList.remove(FLASH_CLASS);
        }, FLASH_DURATION_IN_MS);
    },

    showBanner : function showBanner()
    {
        const banner = document.createElement('div');
        banner.id = BANNER_ID;
        banner.setAttribute('role', 'status');

        const label = document.createElement('span');
        label.textContent = 'mode mouseless actif · ';
        banner.append(label);

        // j/k ne décorent aucune cible (leur `selectors` est vide) : le bandeau est le seul
        // endroit où les annoncer, et seulement là où la page a effectivement une liste.
        if (Shortcuts.listForPage(document.body.dataset.page))
        {
            const keyNext = document.createElement('kbd');
            keyNext.textContent = 'j';

            const keysSeparator = document.createElement('span');
            keysSeparator.textContent = ' ';

            const keyPrev = document.createElement('kbd');
            keyPrev.textContent = 'k';

            const listSuffix = document.createElement('span');
            listSuffix.textContent = ' pour parcourir la liste · ';

            banner.append(keyNext, keysSeparator, keyPrev, listSuffix);
        }

        const key = document.createElement('kbd');
        key.textContent = 'Échap';
        Mouseless.escapeKey = key;

        const suffix = document.createElement('span');
        suffix.textContent = ' ×2 pour quitter · ';

        // ce lien n'appartient pas au registre, il reste donc cliquable : c'est la
        // garantie de ne jamais rester coincé dans le mode
        const link = document.createElement('a');
        link.href = Mouseless.urlWithParam('0');
        link.textContent = 'désactiver';

        banner.append(key, suffix, link);
        document.body.appendChild(banner);
    },

    /** Retire `mouseless` de l'URL affichée pour ne pas le propager aux liens partagés. */
    stripUrlParam : function stripUrlParam()
    {
        if (!window.history || !window.history.replaceState)
        {
            return;
        }

        const url = new URL(window.location.href);
        if (!url.searchParams.has('mouseless'))
        {
            return;
        }

        url.searchParams.delete('mouseless');
        const query = url.searchParams.toString();
        window.history.replaceState(null, '', `${url.pathname}${query ? `?${query}` : ''}${url.hash}`);
    },

    /** URL courante avec `mouseless` forcé à une valeur, les autres paramètres conservés. */
    urlWithParam : function urlWithParam(value)
    {
        const url = new URL(window.location.href);
        url.searchParams.set('mouseless', value);
        return `${url.pathname}?${url.searchParams.toString()}${url.hash}`;
    },

    remember : function rememberMouselessState(active)
    {
        try
        {
            window.localStorage.setItem(STORAGE_KEY, active ? '1' : '0');
        }
        catch (e)
        {
            // navigation privée : le mode vaut pour la page courante, sans mémoire
        }
    }
};
