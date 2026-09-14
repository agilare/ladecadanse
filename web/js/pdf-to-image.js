/*
 * @package ladecadanse
 * @copyright  Copyright (c) 2007 - 2025 Michel Gaudry <michel@ladecadanse.ch>
 * @license    AGPL License; see LICENSE file for details.
 */

/*
 * Conversion en image de la première page des PDF déposés dans un champ fichier.
 *
 * Le PDF ne quitte jamais le poste : pdf.js le rend sur un canvas, et c'est
 * l'image qui part dans le formulaire. Le serveur n'en reçoit donc aucun, et
 * n'a aucun décodeur PDF à exposer — la production en a pourtant un
 * (Imagick + Ghostscript, vérifié), mais evenement-edit.php est ouvert aux
 * visiteurs non connectés, et Ghostscript est la principale source historique
 * d'exécution de code à distance de cet écosystème.
 *
 * Sans JavaScript, le PDF part tel quel et le serveur le refuse avec un message
 * qui explique quoi faire (Validateur::validerFichierImage).
 */

'use strict';

const LARGEUR_CIBLE_EN_PIXELS = 1600;
const ECHELLE_MAXIMALE = 3;
const QUALITE_WEBP = 0.85;
// pdf.js 6.2.108, build « legacy » : le formulaire est public, mieux vaut la
// compatibilité que les quelques kilo-octets du build moderne. Les releases ne
// fournissent pas de version minifiée ; deflate, actif dans le .htaccess, s'en
// charge. Le chargement reste paresseux — rien n'est téléchargé tant que
// personne ne dépose un PDF.
const CHEMIN_PDFJS = '/web/js/libs/pdfjs/pdf.mjs';
const CHEMIN_WORKER_PDFJS = '/web/js/libs/pdfjs/pdf.worker.mjs';

/**
 * Échelle de rendu de la page, d'après la largeur de son viewport à l'échelle 1.
 *
 * On vise nettement plus large que les 600 px auxquels le serveur réduira
 * l'image : le rééchantillonnage a besoin de matière. Le plafond évite qu'un
 * PDF déclarant une page minuscule ne fasse fabriquer un canvas démesuré.
 */
export function echelleDeRendu(largeurViewport)
{
    if (!(largeurViewport > 0))
    {
        return 1;
    }

    return Math.min(ECHELLE_MAXIMALE, LARGEUR_CIBLE_EN_PIXELS / largeurViewport);
}

/**
 * Extension correspondant au type réellement produit par canvas.toBlob().
 *
 * Là où WebP n'est pas gravé (Safari avant 16.4), toBlob() retombe
 * silencieusement sur PNG. Nommer le fichier d'après le format *demandé*
 * produirait un .webp contenant du PNG : le serveur, qui reconnaît le format au
 * contenu, écrirait une image que le navigateur refuserait ensuite d'afficher.
 */
export function extensionPourType(typeMime)
{
    switch (typeMime)
    {
    case 'image/webp':
        return '.webp';
    case 'image/jpeg':
        return '.jpg';
    default:
        return '.png';
    }
}

/**
 * Nom du fichier image issu d'un PDF, assaini.
 *
 * Le validateur côté serveur refuse « php » dans un nom de fichier, et le nom
 * traverse plusieurs couches avant d'être écrit : on ne garde que des
 * caractères sans surprise.
 */
export function nomImageConvertie(nomPdf, typeMime)
{
    const base = String(nomPdf || '').replace(/\.pdf$/i, '');
    const assaini = base
        // décompose puis retire les diacritiques : « été » devient « ete » et non
        // « -t- », ce que donnerait le filtrage seul
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .replace(/php/gi, '')
        .replace(/[^A-Za-z0-9._-]+/g, '-')
        .replace(/^[-._]+/, '')
        .slice(0, 80);

    return (assaini || 'document') + extensionPourType(typeMime);
}

/**
 * Limite de poids annoncée par le formulaire, en octets.
 *
 * Même champ que celui lu par global.js : la valeur vient de PHP, on ne la
 * recopie pas.
 */
function tailleMaxAutorisee(champ)
{
    const champMax = champ.form && champ.form.querySelector('input[name="MAX_FILE_SIZE"]');
    const valeur = champMax ? parseInt(champMax.value, 10) : NaN;

    return Number.isFinite(valeur) && valeur > 0 ? valeur : 5242880;
}

let conversionsEnCours = 0;

function messageDuChamp(champ)
{
    let message = champ.parentNode.querySelector('.js-pdf-message');

    if (!message)
    {
        message = document.createElement('div');
        message.className = 'js-pdf-message';
        champ.parentNode.insertBefore(message, champ.nextSibling);
    }

    return message;
}

/**
 * Affiche un message sous le champ, dans le ton de ce qu'il annonce.
 *
 * `msg` est le style d'erreur du formulaire — fond jaune : une conversion
 * réussie ne doit pas s'y afficher, sous peine de ressembler à un incident.
 *
 * @param {'info'|'succes'|'erreur'} ton
 */
function afficherMessage(champ, texte, ton)
{
    const classes = { info: 'alert-info', succes: 'alert-success', erreur: 'msg' };
    const message = messageDuChamp(champ);

    message.className = 'js-pdf-message ' + (classes[ton] || classes.info);
    message.textContent = texte;
}

function effacerMessage(champ)
{
    const message = champ.parentNode.querySelector('.js-pdf-message');

    if (message)
    {
        message.remove();
    }
}

async function chargerPdfJs()
{
    const pdfjs = await import(CHEMIN_PDFJS);

    pdfjs.GlobalWorkerOptions.workerSrc = CHEMIN_WORKER_PDFJS;

    return pdfjs;
}

/**
 * Rend la première page du PDF et renvoie un File image prêt à être envoyé.
 */
async function convertirPremierePage(fichier)
{
    const pdfjs = await chargerPdfJs();
    const document_ = await pdfjs.getDocument({ data: new Uint8Array(await fichier.arrayBuffer()) }).promise;

    if (document_.numPages < 1)
    {
        throw new Error('Ce PDF ne contient aucune page');
    }

    const page = await document_.getPage(1);
    const viewport = page.getViewport({ scale: echelleDeRendu(page.getViewport({ scale: 1 }).width) });

    const canvas = document.createElement('canvas');
    canvas.width = Math.round(viewport.width);
    canvas.height = Math.round(viewport.height);

    // Une page de PDF n'a pas de fond : sans ce blanc, tout ce que l'affiche
    // laisse vide ressortirait transparent, puis noir dans un format qui ignore
    // la transparence.
    const contexte = canvas.getContext('2d');
    contexte.fillStyle = '#ffffff';
    contexte.fillRect(0, 0, canvas.width, canvas.height);

    await page.render({ canvasContext: contexte, viewport: viewport }).promise;

    const blob = await new Promise(function produireImage(resolve)
    {
        canvas.toBlob(resolve, 'image/webp', QUALITE_WEBP);
    });

    if (!blob)
    {
        throw new Error('La première page n’a pas pu être convertie en image');
    }

    return new File([blob], nomImageConvertie(fichier.name, blob.type), { type: blob.type });
}

/**
 * Remplace le contenu du champ par le fichier converti.
 *
 * input.files n'accepte qu'une FileList, que rien ne permet de construire
 * directement : DataTransfer est le seul chemin.
 */
function remplacerFichier(champ, fichier)
{
    const transfert = new DataTransfer();

    transfert.items.add(fichier);
    champ.files = transfert.files;
}

async function traiterChangement(champ)
{
    if (champ.files.length === 0)
    {
        effacerMessage(champ);
        return;
    }

    const fichier = champ.files[0];

    if (fichier.type !== 'application/pdf' && !/\.pdf$/i.test(fichier.name))
    {
        effacerMessage(champ);
        return;
    }

    conversionsEnCours += 1;
    afficherMessage(champ, 'Conversion de la première page du PDF en cours…', 'info');

    try
    {
        const image = await convertirPremierePage(fichier);

        if (image.size > tailleMaxAutorisee(champ))
        {
            throw new Error('La page convertie reste trop lourde : essayez un PDF plus simple, ou envoyez une image');
        }

        remplacerFichier(champ, image);
        afficherMessage(champ, 'Première page du PDF convertie en image (' + Math.round(image.size / 1024) + ' Ko).', 'succes');
    }
    catch (erreur)
    {
        // On ne laisse surtout pas le PDF dans le champ : il partirait au
        // serveur, qui le refuserait avec un message bien moins parlant.
        champ.value = '';

        const chiffre = erreur && erreur.name === 'PasswordException'
            ? 'Ce PDF est protégé par un mot de passe et ne peut pas être converti.'
            : null;

        afficherMessage(champ, chiffre || 'Ce PDF n’a pas pu être converti. '
            + 'Convertissez sa première page en JPEG ou PNG, puis envoyez-la.', 'erreur');
    }
    finally
    {
        conversionsEnCours -= 1;
    }
}

export const PdfToImage = {
    init: function bindPdfToImageConversion()
    {
        const champs = document.querySelectorAll('input[type="file"].js-pdf-to-image');

        if (champs.length === 0)
        {
            return;
        }

        champs.forEach(function brancherChamp(champ)
        {
            champ.addEventListener('change', function convertirSiPdf()
            {
                traiterChangement(champ);
            });
        });

        // Le gel du bouton d'envoi vit dans global.js ; ici on empêche seulement
        // qu'un envoi parte pendant qu'une conversion tourne encore. Le cas est
        // improbable — la conversion démarre au choix du fichier — mais il
        // enverrait le PDF au lieu de l'image.
        document.addEventListener('submit', function retenirPendantConversion(evenement)
        {
            if (conversionsEnCours > 0)
            {
                evenement.preventDefault();
                evenement.stopPropagation();
                window.alert('La conversion du PDF est encore en cours, merci de patienter quelques instants.');
            }
        }, true);
    }
};
