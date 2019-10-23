<?php
// make a copy of this file named params.php 

define("ENV", "dev");

define("MODE_DEBUG", FALSE);
 
error_reporting(E_ALL);
ini_set('display_errors', '0');

// $glo_email_admin = "michel@ladecadanse.ch"; // prod
// $glo_email_info = "info@ladecadanse.ch"; // prod
// $glo_email_support = "info@ladecadanse.ch"; // prod
$glo_email_admin = "";
$glo_email_info = "";
$glo_email_support = "";

// auth SMTP
// $glo_email_host = "mail.darksite.ch"; // prod
// $glo_email_username = "info@ladecadanse.ch"; // prod
$glo_email_host = "";
$glo_email_username = "";
$glo_email_password = "";

define("MASTER_KEY", '');
define("GOOGLE_API_KEY", '');
define("GOOGLE_RECAPTCHA_API_KEY_CLIENT", '');
define("GOOGLE_RECAPTCHA_API_KEY_SERVER", '');

define("PREVIEW", true);

define("HOME_TMP_BANNER_ENABLED", false);
define("HOME_TMP_BANNER_TITLE", "");
define("HOME_TMP_BANNER_CONTENT", "");

// path
//$rep_absolu = "/home/www/darksite.ch/ladecadanse/"; // prod
$rep_absolu = "";

// URL
// $url_domaine = "http://ladecadanse.darksite.ch"; // prod
$url_domaine = "";
$url_site = $url_domaine."";

// base de données
$param['dbhost'] = '';
$param['dbusername'] = '';
$param['dbpassword'] = '';
$param['dbname'] = '';
