// 2 façons d'utiliser les modules, juste pour voir : par fonctions (browser.js) et par objet literal (global.js)
import { responsiveSetup } from './browser.js';
import { AppGlobal } from './global.js';

// used everywhere : home, events, lieux, user...
$('.magnific-popup').magnificPopup({
    type: 'image',
    tClose: 'Fermer (Esc)', // Alt text on close button
    tLoading: 'Chargement...', // Text that is displayed during loading. Can contain %curr% and %total
    image: {
        tError: "L'image n'a pas pu être chargée" // Error message when image could not be loaded
    }
});

// used in lieu
$('.gallery-item').magnificPopup({
    type: 'image',
    tClose: 'Fermer (Esc)', // Alt text on close button
    tLoading: 'Chargement...', // Text that is displayed during loading. Can contain %curr% and %total
    gallery: {
        enabled: true,
        tPrev: 'Pr&eacute;c&eacute;dente (bouton gauche)', // title for left button
        tNext: 'Suivante (bouton droit)', // title for right button
        tCounter: '%curr% de %total%' // markup of counter
    }
});

$('.js-select2-options-with-style').select2(
        {
            language: "fr",
            allowClear: true,
            templateResult: select2ApplyOptionInlineStyle,
            templateSelection: select2ApplyOptionInlineStyle
        });

$('.js-select2-options-with-complement').select2(
        {
            language: "fr",
            allowClear: true,
            templateResult: select2OptionWithComplement,
            templateSelection: select2OptionWithComplement
        });

function select2ApplyOptionInlineStyle(item)
{
    if (!item.id)

        return item.text;

    const $option = $(item.element);
    const style = $option.attr('style');

    const $result = $('<span>').text(item.text);
    if (style)

        $result.attr('style', style); // applique le style inline

    return $result;
};

function select2OptionWithComplement(item)
{
    if (!item.id)
    {
        return item.text; // Placeholder ou élément vide
    }

    let $option = $(item.element);
    let nom = $option.data('nom');
    let complement = $option.data('complement');

    // Si ni data-nom ni data-complement => afficher normalement
    if (!nom && !complement)
    {
        return item.text;
    }

    // Nettoyer complement si c'est une URL (on enlève http:// ou https://)
    let complementAffiche = complement ? complement.replace(/^https?:\/\//, '') : '';

    let result = '<span>';
    if (nom)
    {
        result += `<span>${nom}</span>`;
    }
    if (complementAffiche)
    {
        result += ` <span style="font-size: 0.9em; color: #888;">${complementAffiche}</span>`;
    }
    result += '</span>';

    return $(result);
};

const ReadSmore = window.readSmore;
const readMoreEls = document.querySelectorAll('.js-read-smore');
ReadSmore(readMoreEls, {
            moreText : "Lire la suite ",
            lessText : "Réduire",
            isInline : true
        }).init();

responsiveSetup();
AppGlobal.init();
