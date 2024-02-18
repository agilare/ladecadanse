// 2 façons d'utiliser les modules, juste pour voir : par fonctions (browser.js) et par objet literal (global.js)
import { responsiveSetup } from './browser.js';
import { AppGlobal } from './global.js';

responsiveSetup();
AppGlobal.init();
