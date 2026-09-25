/**
 * Copie dans web/libs/ les fichiers que le navigateur charge depuis les
 * bibliothèques front-end déclarées dans package.json.
 *
 * npm télécharge, ce script ne fait que choisir : node_modules n'est pas servi,
 * et la plupart des paquets livrent sources, cartes et variantes dont le site
 * n'a que faire. web/libs/ n'est pas versionné ; il se reconstruit à chaque
 * `npm install` / `npm ci` (hook postinstall), et `composer deploy` l'envoie
 * par git-ftp quand package-lock.json ou ce fichier ont changé — voir
 * .git-ftp-include.
 *
 * Le répertoire est vidé d'abord : une bibliothèque retirée ne laisse rien
 * derrière elle. Une source manquante arrête tout, plutôt que de publier un
 * web/libs/ incomplet.
 *
 * Usage :
 *   npm run libs:sync
 */

import { copyFileSync, existsSync, mkdirSync, rmSync } from 'node:fs';
import { basename, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const RACINE = dirname(dirname(fileURLToPath(import.meta.url)));
const SOURCES = join(RACINE, 'node_modules');
const DESTINATION = join(RACINE, 'web', 'libs');

/**
 * Source dans node_modules → répertoire de destination dans web/libs.
 *
 * Quatre contraintes de chemins relatifs à respecter :
 * - la CSS de Font Awesome cherche ses polices en ../fonts/ ;
 * - celle de Zebra_Datepicker cherche icons.png dans son propre répertoire ;
 * - pdf.js veut pdf.mjs et pdf.worker.mjs côte à côte (web/js/pdf-to-image.js) ;
 * - le fr.js de select2 a sa propre balise <script>, son chemin n'a qu'à la suivre.
 */
const FICHIERS = [
    ['font-awesome/css/font-awesome.min.css', 'font-awesome/css'],
    ['font-awesome/fonts/FontAwesome.otf', 'font-awesome/fonts'],
    ['font-awesome/fonts/fontawesome-webfont.eot', 'font-awesome/fonts'],
    ['font-awesome/fonts/fontawesome-webfont.svg', 'font-awesome/fonts'],
    ['font-awesome/fonts/fontawesome-webfont.ttf', 'font-awesome/fonts'],
    ['font-awesome/fonts/fontawesome-webfont.woff', 'font-awesome/fonts'],
    ['font-awesome/fonts/fontawesome-webfont.woff2', 'font-awesome/fonts'],
    ['magnific-popup/dist/magnific-popup.css', 'magnific-popup'],
    ['magnific-popup/dist/jquery.magnific-popup.js', 'magnific-popup'],
    ['select2/dist/css/select2.min.css', 'select2/css'],
    ['select2/dist/js/select2.min.js', 'select2/js'],
    ['select2/dist/js/i18n/fr.js', 'select2/js/i18n'],
    ['zebra_datepicker/dist/zebra_datepicker.min.js', 'zebra-datepicker'],
    ['zebra_datepicker/dist/css/default/zebra_datepicker.min.css', 'zebra-datepicker/css/default'],
    ['zebra_datepicker/dist/css/default/icons.png', 'zebra-datepicker/css/default'],
    ['checkboxes.js/dist/jquery.checkboxes-1.2.2.min.js', 'checkboxes'],
    ['normalize.css/normalize.css', 'normalize'],
    // build « legacy » : le formulaire qui le charge est public, la compatibilité
    // prime sur les quelques kilo-octets du build moderne
    ['pdfjs-dist/legacy/build/pdf.mjs', 'pdfjs'],
    ['pdfjs-dist/legacy/build/pdf.worker.mjs', 'pdfjs'],
];

const manquants = FICHIERS
    .map(([source]) => source)
    .filter((source) => !existsSync(join(SOURCES, source)));

if (manquants.length > 0) {
    console.error(`libs:sync — introuvable dans node_modules :\n  ${manquants.join('\n  ')}\n`
        + 'Lancer `npm ci`, ou corriger la liste de bin/libs-sync.mjs après une mise à jour.');
    process.exit(1);
}

rmSync(DESTINATION, { recursive: true, force: true });

for (const [source, repertoire] of FICHIERS) {
    const cible = join(DESTINATION, repertoire);
    mkdirSync(cible, { recursive: true });
    copyFileSync(join(SOURCES, source), join(cible, basename(source)));
}

console.log(`libs:sync — ${FICHIERS.length} fichiers copiés dans web/libs/`);
