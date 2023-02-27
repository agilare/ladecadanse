<?php
// make a copy of this file with name 'env.php'

define("ENV", "dev"); // for the moment only used in page title
define("MODE_DEBUG", true); // display errors


// database connection
define("DB_HOST", '');
define("DB_NAME", '');
define("DB_USERNAME", '');
define("DB_PASSWORD", '');

// SMTP credentials to send emails to site admin and users
define("EMAIL_AUTH_HOST", '');
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

// external services
define("TINYMCE_API_KEY", '');

define("MATOMO_ENABLED", false); // analytics tool (enabled only in prod)

define("GOOGLE_API_KEY", ''); // use Google Maps library to display maps of venues https://developers.google.com/maps/documentation/javascript/get-api-key

define("GOOGLE_RECAPTCHA_API_KEY_CLIENT", ''); // for (public) "Proposer un événement form"
define("GOOGLE_RECAPTCHA_API_KEY_SERVER", '');

define("GOOGLE_ANALYTICS_ID", ''); // 1st analytics tool (enabled only in prod)
define("GOOGLE_ANALYTICS_ENABLED", false);

// small modules

// in homepage, banner (closable by user) for announcements by site admin
define("HOME_TMP_BANNER_ENABLED", false);
define("HOME_TMP_BANNER_TITLE", "Title of my announcement");
define("HOME_TMP_BANNER_CONTENT", "My announcement...");
