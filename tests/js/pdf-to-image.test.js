import { describe, it, expect } from 'vitest';
import { echelleDeRendu, extensionPourType, nomImageConvertie } from '../../web/js/pdf-to-image.js';

/*
 * jsdom n'implémente pas canvas : le rendu lui-même se vérifie à la main, dans
 * un vrai navigateur. Ne sont couvertes ici que les décisions prises autour de
 * ce rendu — celles où une erreur produit un fichier silencieusement invalide.
 */

describe('extensionPourType', () =>
{
    it('nomme d’après le format réellement produit, pas celui demandé', () =>
    {
        // canvas.toBlob() retombe sur PNG là où WebP n'est pas gravé, sans rien
        // signaler : un .webp contenant du PNG ne s'afficherait plus une fois servi
        expect(extensionPourType('image/webp')).toBe('.webp');
        expect(extensionPourType('image/png')).toBe('.png');
        expect(extensionPourType('image/jpeg')).toBe('.jpg');
    });

    it('retombe sur .png quand le type est inconnu ou absent', () =>
    {
        expect(extensionPourType('')).toBe('.png');
        expect(extensionPourType(undefined)).toBe('.png');
    });
});

describe('nomImageConvertie', () =>
{
    it('remplace l’extension .pdf par celle de l’image', () =>
    {
        expect(nomImageConvertie('affiche.pdf', 'image/webp')).toBe('affiche.webp');
        expect(nomImageConvertie('AFFICHE.PDF', 'image/webp')).toBe('AFFICHE.webp');
    });

    it('ôte « php » du nom, que le validateur refuse', () =>
    {
        expect(nomImageConvertie('shell.php.pdf', 'image/webp')).not.toContain('php');
        expect(nomImageConvertie('PHPinfo.pdf', 'image/webp')).not.toMatch(/php/i);
    });

    it('écarte les caractères susceptibles de poser problème en aval', () =>
    {
        expect(nomImageConvertie('../../etc/passwd.pdf', 'image/webp')).toBe('etc-passwd.webp');
        expect(nomImageConvertie('affiche été 2026.pdf', 'image/webp')).toBe('affiche-t-2026.webp');
    });

    it('garde un nom utilisable quand il ne reste rien', () =>
    {
        expect(nomImageConvertie('...pdf', 'image/webp')).toBe('document.webp');
        expect(nomImageConvertie('', 'image/webp')).toBe('document.webp');
    });

    it('borne la longueur', () =>
    {
        const nom = nomImageConvertie('a'.repeat(300) + '.pdf', 'image/webp');

        expect(nom.length).toBeLessThanOrEqual(85);
    });
});

describe('echelleDeRendu', () =>
{
    it('vise une largeur confortable pour le redimensionnement serveur', () =>
    {
        // une page A4 à 72 dpi fait 595 points de large
        expect(echelleDeRendu(595)).toBeCloseTo(1600 / 595, 5);
    });

    it('plafonne l’agrandissement d’une page minuscule', () =>
    {
        // sans plafond, une page de 10 points produirait un canvas de 160 000 px
        expect(echelleDeRendu(10)).toBe(3);
    });

    it('ne réduit pas une page déjà très large', () =>
    {
        expect(echelleDeRendu(3200)).toBeCloseTo(0.5, 5);
    });

    it('reste à 1 sur une largeur absente ou absurde', () =>
    {
        expect(echelleDeRendu(0)).toBe(1);
        expect(echelleDeRendu(-5)).toBe(1);
        expect(echelleDeRendu(undefined)).toBe(1);
    });
});
