/*
 * @package ladecadanse
 * @copyright  Copyright (c) 2007 - 2026 Michel Gaudry <michel@ladecadanse.ch>
 * @license    AGPL License; see LICENSE file for details.
 */

'use strict';

/**
 * Retire du contenu collé ce que le serveur écartera de toute façon : le
 * sanitizer HTML (voir OrganisateurEdition) ne garde ni style, ni class, ni id,
 * et déballe les éléments qui ne servent qu'à la mise en forme. Sans ce
 * nettoyage, l'auteur voit dans l'éditeur une mise en page — polices, couleurs,
 * fonds du site d'origine — qui disparaît silencieusement à l'enregistrement.
 *
 * @param {Element} racine Fragment collé, tel que TinyMCE l'a déjà analysé
 */
function nettoyerCollage(racine) {
    racine.querySelectorAll('[style], [class], [id]').forEach(function (element) {
        element.removeAttribute('style');
        element.removeAttribute('class');
        element.removeAttribute('id');
    });

    // Une fois leurs styles retirés, span et font ne portent plus rien : on garde
    // leur contenu et on jette l'enveloppe. Les imbrications se résolvent d'elles-mêmes,
    // l'enfant restant dans l'arbre après le déballage de son parent.
    racine.querySelectorAll('span, font').forEach(function (element) {
        element.replaceWith(...element.childNodes);
    });
}

tinymce.init({
    selector: 'textarea.tinymce',
    // le pack de langue vient du même CDN que TinyMCE, déjà autorisé par la CSP
    language: 'fr_FR',
    height: 500,
    menubar: false,
    plugins: ['autolink', 'lists', 'link', 'charmap', 'searchreplace', 'visualblocks', 'code', 'help', 'wordcount'],
    toolbar: 'bold italic link | h3 bullist numlist blockquote | undo redo | visualblocks removeformat code',
    content_css: [
        '//fonts.googleapis.com/css?family=Lato:300,300i,400,400i',
        '//www.tiny.cloud/css/codepen.min.css'
    ],
    relative_urls: false,
    remove_script_host: true,
    // une image collée deviendrait un data: URI dans le champ, que le serveur
    // retire ensuite : autant ne pas l'accepter
    paste_data_images: false,
    paste_postprocess: function (editor, args) {
        nettoyerCollage(args.node);
    }
});
