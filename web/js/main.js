import { responsiveSetup } from './browser.js';
import * as AppGlobal from './global.js';

// ALL
responsiveSetup();
AppGlobal.bindEventsOfMainNavigation();
AppGlobal.bindEventsOfVariousInteractions();

// SOME PAGES
AppGlobal.bindEventsOfForms();
AppGlobal.bindEventsEvents();

// SPECIFIC PAGES
AppGlobal.bindHomeEvents();
AppGlobal.bindLieuxEvents();
