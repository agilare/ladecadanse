<?php
// make a copy of this file with name 'env.php'
define("ENV", "dev");
define("MODE_DEBUG", true);
 

// full path of your ladecadanse application directory, for ex. "/home/michel/hosts/ladecadanse/"
$rep_absolu = "";

// URL
// main domain, for ex "http://localhost" or "http://ladecadanse.local"; 
$url_domaine = "";
// complete if your site is in a subdirectory, for ex with "ladecadanse", $url_site will be http://localhost/ladecadanse
$url_site = $url_domaine."/";

// database
$param['dbhost'] = '';
$param['dbname'] = '';
$param['dbusername'] = '';
$param['dbpassword'] = '';

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

define("GOOGLE_API_KEY", ''); // use Google Maps library to display maps of venues

define("GOOGLE_RECAPTCHA_API_KEY_CLIENT", ''); // for (public) "Proposer un événement form"
define("GOOGLE_RECAPTCHA_API_KEY_SERVER", '');

define("GOOGLE_ANALYTICS_ID", ''); // 1st analytics tool (enabled only in prod)
define("GOOGLE_ANALYTICS_ENABLED", false);

define("MATOMO_ENABLED", false); // 2nd analytics tool (enabled only in prod)

define("PREVIEW", true); 

// closable banner in homepage for announcements
define("HOME_TMP_BANNER_ENABLED", false);
define("HOME_TMP_BANNER_TITLE", "");
define("HOME_TMP_BANNER_CONTENT", "");
