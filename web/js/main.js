// 2 façons d'utiliser les modules, juste pour voir : par fonctions (browser.js) et par objet literal (global.js)
import { responsiveSetup } from './browser.js';
import { AppGlobal } from './global.js';
import { PdfToImage } from './pdf-to-image.js';

// used everywhere : home, events, lieux, user...
$('.magnific-popup').magnificPopup({
    type: 'image',
    tClose: 'Fermer (Esc)',
    tLoading: 'Chargement...', // Text that is displayed during loading. Can contain %curr% and %total
    image: {
        tError: "L'image n'a pas pu être chargée"
    }
});

// used in lieu
$('.gallery-item').magnificPopup({
    type: 'image',
    tClose: 'Fermer (Esc)',
    tLoading: 'Chargement...', // Text that is displayed during loading. Can contain %curr% and %total
    gallery: {
        enabled: true,
        tPrev: 'Pr&eacute;c&eacute;dente (bouton gauche)',
        tNext: 'Suivante (bouton droit)',
        tCounter: '%curr% de %total%'
    }
});

$('.js-select2').select2(
{
    language: "fr",
    allowClear: false
});

// used for Localité, Category, lieu categorie...
$('.js-select2-options-with-style').select2(
{
    language: "fr",
    allowClear: true,
    templateResult: select2ApplyOptionInlineStyle,
    templateSelection: select2ApplyOptionInlineStyle
});

// used for Organisateurs
$('.js-select2-options-with-complement').select2(
{
    language: "fr",
    allowClear: true,
    templateResult: select2OptionWithComplement,
    templateSelection: select2OptionWithComplement
});

select2PreventReopenOnDeselect($('.js-select2-options-with-style, .js-select2-options-with-complement'));

/*
 * Select2 rouvre la liste déroulante quand on désélectionne par la croix, pour deux
 * raisons distinctes : la croix globale (allowClear) termine son traitement par un
 * toggle explicite, et la croix d'une étiquette (multiple) laisse le clic remonter
 * jusqu'au conteneur, qui l'interprète comme une demande d'ouverture.
 *
 * On marque donc la désélection en cours et on annule l'ouverture qui suit dans le
 * même tick — select2:opening est annulable. Le drapeau est relâché en fin de tick :
 * désélectionner depuis une liste déjà ouverte ne déclenche aucun select2:opening,
 * et un drapeau resté armé bloquerait l'ouverture suivante, elle légitime.
 */
function select2PreventReopenOnDeselect($selects)
{
    let deselecting = false;

    $selects.on('select2:clearing select2:unselecting', function ()
    {
        deselecting = true;
        setTimeout(function ()
        {
            deselecting = false;
        }, 0);
    });

    $selects.on('select2:opening', function (evt)
    {
        if (deselecting)
        {
            evt.preventDefault();
        }
    });
}

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
    let complementAffiche = complement ? String(complement).replace(/^https?:\/\//, '') : '';

    // .data() renvoie la valeur *décodée* de l'attribut data-* : l'interpoler dans une
    // chaîne HTML fait exécuter le balisage venant de la base (XSS stocké — un nom
    // d'organisateur contenant <script> se déclenchait à l'ouverture de la liste).
    // On assemble donc des noeuds DOM avec .text().
    const $result = $('<span>');
    if (nom)
    {
        $result.append($('<span>').text(nom));
    }
    if (complementAffiche)
    {
        $result.append(document.createTextNode(' '));
        $result.append($('<span>').css({'font-size': '0.9em', 'color': '#888'}).text(complementAffiche));
    }

    return $result;
};

responsiveSetup();
AppGlobal.init();
PdfToImage.init();
