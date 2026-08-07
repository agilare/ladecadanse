import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { SetCookie } from '../../web/js/browser.js';

// Le getter `document.cookie` ne restitue que les paires nom=valeur : expires, path et les
// flags sont invisibles depuis la lecture. On intercepte donc l'écriture pour pouvoir
// assertir sur la chaîne brute réellement produite.
let written;

beforeEach(function interceptCookieWrites()
{
    written = null;
    Object.defineProperty(document, 'cookie', {
        configurable: true,
        get: function () { return ''; },
        set: function (value) { written = value; }
    });
});

afterEach(function restoreCookieAccessor()
{
    delete document.cookie;
    vi.useRealTimers();
});

function expiresOf(cookieString)
{
    const match = cookieString.match(/expires=([^;]+)/);

    return match ? new Date(match[1]) : null;
}

describe('SetCookie', function ()
{
    it('pose le cookie sur tout le domaine avec les flags de sécurité par défaut', function ()
    {
        SetCookie('banniere_home', 1);

        expect(written).toBe('banniere_home=1; path=/; secure; samesite=lax');
    });

    it('encode la valeur', function ()
    {
        SetCookie('recherche', 'concert & fête');

        expect(written).toContain('recherche=concert%20%26%20f%C3%AAte');
    });

    it('respecte un path explicite', function ()
    {
        SetCookie('admin_pref', 'compact', 0, '/admin');

        expect(written).toBe('admin_pref=compact; path=/admin; secure; samesite=lax');
    });

    it('ne pose pas d’expiration sans durée : cookie de session', function ()
    {
        SetCookie('tmp', 'x');

        expect(written).not.toContain('expires');
    });

    it('ne pose pas d’expiration pour une durée de 0 jour', function ()
    {
        SetCookie('tmp', 'x', 0);

        expect(written).not.toContain('expires');
    });

    it('convertit une durée en jours en date d’expiration', function ()
    {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-01-01T12:00:00Z'));

        SetCookie('banniere_home', 1, 180);

        const attendu = new Date('2026-01-01T12:00:00Z').getTime() + 180 * 86400 * 1000;
        expect(expiresOf(written).getTime()).toBe(attendu);
    });

    // Une durée négative sert à supprimer un cookie : sans le clamp, `setTime` produirait une
    // date antérieure à l'epoch, que les navigateurs ne sérialisent pas de façon fiable.
    it('ramène une durée négative à l’epoch', function ()
    {
        SetCookie('banniere_home', 1, -1);

        expect(expiresOf(written).getTime()).toBe(0);
    });
});
