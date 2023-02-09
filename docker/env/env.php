<?php
// make a copy of this file with name 'env.php'
define("ENV", "dev");
define("MODE_DEBUG", true);

// database
define("DB_HOST", 'ladecadanse_db');
define("DB_NAME", 'ladecadanse');
define("DB_USERNAME", 'dev');
define("DB_PASSWORD", 'dev');

// SMTP credentials
define("EMAIL_AUTH_HOST", ''); // prod : mail.darksite.ch
define("EMAIL_AUTH_USERNAME", '');
define("EMAIL_AUTH_PASSWORD", '');
define("EMAIL_AUTH_SMTPSECURE", 'TLS');
define("EMAIL_AUTH_PORT", '587');
define("EMAIL_AUTH_SMTPDEBUG", '0');

// mailers
define("EMAIL_SITE", ''); // prod : info@ladecadanse.ch
define("EMAIL_SITE_NAME", 'La décadanse');
define("EMAIL_ADMIN", ''); // prod : info@ladecadanse.ch
define("EMAIL_ADMIN_NAME", 'La décadanse');

// external services
define("MATOMO_ENABLED", false); // analytics tool (enabled only in prod)

define("GOOGLE_API_KEY", ''); // use Google Maps library to display maps of venues

define("GOOGLE_RECAPTCHA_API_KEY_CLIENT", ''); // for (public) "Proposer un événement form"
define("GOOGLE_RECAPTCHA_API_KEY_SERVER", '');

define("GOOGLE_ANALYTICS_ID", ''); // 1st analytics tool (enabled only in prod)
define("GOOGLE_ANALYTICS_ENABLED", false);

// small modules
define("PREVIEW", true);

// closable banner in homepage for announcements
define("HOME_TMP_BANNER_ENABLED", false);
define("HOME_TMP_BANNER_TITLE", "");
define("HOME_TMP_BANNER_CONTENT", "");
