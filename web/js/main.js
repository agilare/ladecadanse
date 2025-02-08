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


responsiveSetup();
AppGlobal.init();
