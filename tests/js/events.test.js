import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { Events } from '../../web/js/global.js';

// event/actions.php n'accepte que POST, avec le jeton CSRF de la session que les liens
// « Supprimer » et « Dépublier » portent dans data-token. fetch est remplacé par un double :
// on vérifie ce que requestAction() envoie réellement, et ce qu'elle fait de la réponse.
// Les gestionnaires de clic, eux, passent par jQuery, absent de jsdom : ils ne sont pas testés
// ici, et se bornent à lire data-id et data-token avant d'appeler requestAction().
let fetchMock;

beforeEach(function stubFetch()
{
    fetchMock = vi.fn(() => Promise.resolve({ ok: true, status: 200 }));
    vi.stubGlobal('fetch', fetchMock);
});

afterEach(function restoreFetch()
{
    vi.unstubAllGlobals();
});

function sentRequest()
{
    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, options] = fetchMock.mock.calls[0];

    return { url: url, options: options, fields: Object.fromEntries(options.body) };
}

function answerWith(status)
{
    fetchMock.mockResolvedValueOnce({ ok: status >= 200 && status < 300, status: status });
}

describe('Events.requestAction', function ()
{
    it('poste l’action, l’identifiant et le jeton dans le corps, rien dans l’url', async function ()
    {
        await Events.requestAction('unpublish', 42, 'a1b2c3');

        const request = sentRequest();
        expect(request.url).toBe('/event/actions.php');
        expect(request.options.method).toBe('POST');
        expect(request.fields).toEqual({ action: 'unpublish', id: '42', token: 'a1b2c3' });
    });

    it('encode le corps comme un formulaire, seul format que PHP range dans $_POST', async function ()
    {
        await Events.requestAction('delete', 7, 'a1b2c3');

        expect(sentRequest().options.body).toBeInstanceOf(URLSearchParams);
    });

    it('envoie la suppression par le même chemin', async function ()
    {
        await Events.requestAction('delete', 7, 'a1b2c3');

        expect(sentRequest().fields).toEqual({ action: 'delete', id: '7', token: 'a1b2c3' });
    });

    it('envoie un jeton vide, et non « undefined », depuis un lien qui n’en porte pas', async function ()
    {
        await Events.requestAction('delete', 7, undefined);

        expect(sentRequest().fields.token).toBe('');
    });

    it('se résout sur la réponse quand le serveur accepte', async function ()
    {
        answerWith(200);

        await expect(Events.requestAction('delete', 7, 'a1b2c3')).resolves.toMatchObject({ status: 200 });
    });

    it('invite à recharger la page quand le jeton est refusé (400)', async function ()
    {
        answerWith(400);

        await expect(Events.requestAction('unpublish', 7, 'perime')).rejects.toThrow('Veuillez recharger la page');
    });

    it('nomme l’action refusée et le statut dans les autres cas', async function ()
    {
        answerWith(403);
        await expect(Events.requestAction('delete', 7, 'a1b2c3')).rejects.toThrow('suppression refusée (403)');

        answerWith(405);
        await expect(Events.requestAction('unpublish', 7, 'a1b2c3')).rejects.toThrow('dépublication refusée (405)');
    });
});
