<?php
// make a copy of this file with name 'env.php'

define("ENV", "dev"); // or "prod"
define("MODE_DEBUG", true); // display errors

// URL canonique du site, sans slash final. Décommenter en développement pour que les balises
// d'autodiscovery RSS et les liens vers les flux pointent sur cet environnement plutôt que sur
// la production. Laissée commentée, app/config.php retient https://www.ladecadanse.ch
//define("SITE_CANONICAL_URL", 'http://localhost:7777');

// database connection
define("DB_HOST", '');
define("DB_NAME", '');
define("DB_USERNAME", '');
define("DB_PASSWORD", '');

// SMTP config and credentials to send emails to site admin and users
define("EMAIL_AUTH_HOST", '');
define("EMAIL_SMTPAUTH", true);
define("EMAIL_AUTH_USERNAME", '');
define("EMAIL_AUTH_PASSWORD", '');
define("EMAIL_AUTH_SMTPSECURE", 'TLS');
define("EMAIL_AUTH_PORT", '587');
define("EMAIL_AUTH_SMTPDEBUG", '0'); // https://github.com/PHPMailer/PHPMailer/wiki/Troubleshooting#enabling-debug-output


// mail accounts
define("EMAIL_SITE", ''); // sender of automatic site emails (could be a noreply ?)
define("EMAIL_SITE_NAME", 'La décadanse');
define("EMAIL_ADMIN", ''); // recipient of site activity to watch, users requests (contact form, new event prop...) to process
define("EMAIL_ADMIN_NAME", 'La décadanse');

// envoie à EMAIL_ADMIN une copie de chaque mail adressé à un utilisateur, avec un sujet préfixé "[COPY]"
// (destiné à de courtes périodes de monitoring : suivre la circulation des messages et leur rendu)
define("EMAIL_COPY_TO_ADMIN", false);

// external services
define("TINYMCE_API_KEY", ''); // rich text editor for presentations of lieux and organisateurs

define("MATOMO_ENABLED", false); // statistics tool (enabled only in prod)
define("MATOMO_URL", '');
define("MATOMO_SITE_ID", '');

// front-end errors logger
define("GLITCHTIP_ENABLED", false);
define("GLITCHTIP_DSN", "");

// service to monitor and moderate bots (AI, crawlers, etc.)
define("DARKVISITORS_ENABLED", false);
define("DARKVISITORS_PROJECT_KEY", '');
define("DARKVISITORS_ACCESS_TOKEN", '');

// suivi interne des bots et IP suspectes (table bot_monitor + admin/bots.php)
// créer la table (resources/database/v3-11-0_bot_monitor-create-table.sql) avant d'activer
define("BOT_MONITORING_ENABLED", false);
define("BOT_MONITORING_SUSPECT_THRESHOLD", 150); // seuil de hits pour "humains suspects" dans le dashboard

// accepter les PDF dans les champs flyer et image d'un événement, dont seule la
// 1re page est gardée, convertie en WebP (evenement-edit.php, admin/events.php)
//
// Deux voies, dont une seule demande quelque chose au serveur :
//   - champ fichier : le navigateur convertit (web/js/pdf-to-image.js), rien à
//     installer, mais ~3,4 Mo de pdf.js à télécharger au premier PDF déposé
//   - « ou coller une URL » : le serveur convertit (Utils\PdfToImage), ce qui
//     demande l'extension imagick ET Ghostscript. Sans eux cette voie refuse les
//     PDF et renvoie vers le champ fichier, le reste continuant de fonctionner.
//     Voir « Convertir les PDF collés en URL » dans le README.
//
// Trois états, comme tout drapeau passant par Ladecadanse\FeatureFlag :
//   false       les champs n'annoncent pas le PDF, ne l'acceptent pas, et
//               pdf.js n'est jamais chargé
//   'preview'   réservé aux administrateurs, pour éprouver la fonctionnalité en
//               ligne sans l'ouvrir au public ; le texte d'aide le signale, une
//               préversion qui ne se voit pas se croit livrée
//   true        ouvert à tous
//
// La chaîne littérale, et non FeatureFlag::PREVIEW : ce fichier est chargé par
// app/bootstrap.php avant l'autoloader, aucune classe n'y est encore connue.
define("PDF_CONVERSION_ENABLED", false);

// afficher le calendrier du champ date d'un événement (evenement-edit.php) déployé
// en permanence sous le champ, au lieu du calendrier surgissant au clic
// (Zebra_DatePicker, mode always_visible).
//
// Trois états, comme tout drapeau passant par Ladecadanse\FeatureFlag :
//   false       le champ date se comporte comme partout ailleurs sur le site :
//               le calendrier ne s'ouvre qu'au clic
//   'preview'   réservé aux administrateurs, le temps d'éprouver la mise en page
//               que le calendrier impose au reste du formulaire ; une mention
//               sous le calendrier rappelle qu'il n'est pas public
//   true        ouvert à tous
define("DATEPICKER_ALWAYS_VISIBLE_ENABLED", false);

// situer chaque événement de l'agenda (index.php) par rapport à l'heure de chargement
// de la page : à venir, en cours, terminé. La journée du jour seulement, seule où la
// comparaison apprend quelque chose.
//
// Trois états, comme tout drapeau passant par Ladecadanse\FeatureFlag :
//   false       l'agenda est celui d'avant : aucun repère de temporalité
//   'preview'   réservé aux administrateurs, le temps d'éprouver ce que ces repères
//               apportent une fois posés sur de vrais horaires ; une mention en tête
//               de liste rappelle qu'ils ne sont pas publics
//   true        ouvert à tous
define("EVENT_TIME_STATUS_ENABLED", false);

define("PAYPAL_HOSTED_BUTTON_ID", "");

// to allow access to events API (api.php)
define("LADECADANSE_API_ENABLED", false);
define("LADECADANSE_API_USER", '');
define("LADECADANSE_API_KEY", '');

// small modules

// in homepage, closable warn alert for announcements by site admin
define("HOME_TMP_BANNER_ENABLED", false);
define("HOME_TMP_BANNER_TITLE", "Title of my announcement");
define("HOME_TMP_BANNER_CONTENT", "My announcement...");

// in homepage, closable info alert for announcements by site admin to actors
define("HOME_TMP_BACK_BANNER_ENABLED", false);
define("HOME_TMP_BACK_BANNER_TITLE", "Title of my announcement");
define("HOME_TMP_BACK_BANNER_CONTENT", "My announcement...");

// after crash of 22.10.2024 and then existence of 2 database versions, allow restart of application with limited edition on current db version to avoid conflict qui backed up db
// removed its usage in code the 15.02.2025
define("PARTIAL_EDIT_MODE", false);
define("PARTIAL_EDIT_FROM_DATETIME", "2024-10-22 23:21:00");
define("PARTIAL_EDIT_MODE_MSG", "Le site est actuellement partiellement fonctionel, ainsi certains éléments comme celui-ci ne peuvent être modifiés");
