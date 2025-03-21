<?php
/*

	nG Firewall : Log Blocked Requests

	Version 2.0 2023/02/14 by Jeff Starr

	https://perishablepress.com/ng-firewall/
	https://perishablepress.com/ng-firewall-logging/

	-

	License: GPL v3 or later https://www.gnu.org/licenses/gpl.txt

	Overview: Logs HTTP requests blocked by nG Firewall. Recommended for testing/development only.

	Requires: Apache, mod_rewrite, PHP >= 5.4.0, nG Firewall (version 7G or better)

	Usage:

	1. Add nG Firewall to root .htaccess (or Apache config)

	2. Configure nG Firewall for logging (via tutorial)

	2. Add nG_log.php + nG_log.txt to root web directory

	3. Make nG_log.txt writable and protect via .htaccess

	4. Edit the five lines/options below if necessary

	Test well & leave feedback @ https://perishablepress.com/contact/

	Notes:

	In log entries, matching firewall patterns are indicated via brackets like [this]

*/

define('NGFIREWALL_LOGPATH', dirname(__FILE__) .'/../');

define('NGFIREWALL_STATUS', 403); // 403 = forbidden

define('NGFIREWALL_UALENGTH', 0); // 0 = no limit

define('NGFIREWALL_COOKIE', true); // log cookies

define('NGFIREWALL_LOGFILE', 'nG-firewall.log');

define('NGFIREWALL_EXIT', 'Goodbye');

date_default_timezone_set('UTC');



// Do not edit below this line --~

function perishablePress_nG_init() {

	if (perishablePress_nG_check()) {

		perishablePress_nG_log();

		perishablePress_nG_exit();

	}

}

function perishablePress_nG_vars() {

	$date     = date('Y/m/d H:i:s');

	$method   = perishablePress_nG_request_method();

	$protocol = perishablePress_nG_server_protocol();

	$uri      = perishablePress_nG_request_uri();

	$string   = perishablePress_nG_query_string();

	$address  = perishablePress_nG_ip_address();

	$host     = perishablePress_nG_remote_host();

	$referrer = perishablePress_nG_http_referrer();

	$agent    = perishablePress_nG_user_agent();

	$cookie   = perishablePress_nG_http_cookie();

	return array($date, $method, $protocol, $uri, $string, $address, $host, $referrer, $agent, $cookie);

}

function perishablePress_nG_check() {

	$check = isset($_SERVER['REDIRECT_QUERY_STRING']) ? $_SERVER['REDIRECT_QUERY_STRING'] : '';

	return ($check === 'log') ? true : false;

}

function perishablePress_nG_log() {

	list ($date, $method, $protocol, $uri, $string, $address, $host, $referrer, $agent, $cookie) = perishablePress_nG_vars();

	$sep = NGFIREWALL_COOKIE ? ' - ' : '';

	$log = $address .' - '. $date .' - '. $method .' - '. $protocol .' - '. $uri .' - '. $string .' - '. $host .' - '. $referrer .' - '. $agent . $sep . $cookie . "\n";

	$log = preg_replace('/(\ )+/', ' ', $log);

	$fp = fopen(NGFIREWALL_LOGPATH . NGFIREWALL_LOGFILE, 'a+') or exit("Error: can't open log file!");

	fwrite($fp, $log);

	fclose($fp);

}

function perishablePress_nG_exit() {

	http_response_code(NGFIREWALL_STATUS);

	exit(NGFIREWALL_EXIT);

}

function perishablePress_nG_server_protocol() {

	return isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : '';

}

function perishablePress_nG_request_method() {

	$string = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';

	$match = isset($_SERVER['REDIRECT_nG_REQUEST_METHOD']) ? $_SERVER['REDIRECT_nG_REQUEST_METHOD'] : '';

	return perishablePress_nG_get_patterns($string, $match);

}

function perishablePress_nG_query_string() {

	$request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

	$query = parse_url($request_uri);

	$string = isset($query['query']) ? $query['query'] : '';

	$match = isset($_SERVER['REDIRECT_nG_QUERY_STRING']) ? $_SERVER['REDIRECT_nG_QUERY_STRING'] : '';

	return perishablePress_nG_get_patterns($string, $match);

}

function perishablePress_nG_request_uri() {

	$request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

	$query = parse_url($request_uri);

	$string = isset($query['path']) ? $query['path'] : '';

	$match = isset($_SERVER['REDIRECT_nG_REQUEST_URI']) ? $_SERVER['REDIRECT_nG_REQUEST_URI'] : '';

	return perishablePress_nG_get_patterns($string, $match);

}

function perishablePress_nG_user_agent() {

	$string = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

	$string = (defined(NGFIREWALL_UALENGTH)) ? substr($string, 0, NGFIREWALL_UALENGTH) : $string;

	$match = isset($_SERVER['REDIRECT_nG_USER_AGENT']) ? $_SERVER['REDIRECT_nG_USER_AGENT'] : '';

	return perishablePress_nG_get_patterns($string, $match);

}

function perishablePress_nG_ip_address() {

	$string = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

	$match = isset($_SERVER['REDIRECT_nG_IP_ADDRESS']) ? $_SERVER['REDIRECT_nG_IP_ADDRESS'] : '';

	return perishablePress_nG_get_patterns($string, $match);

}

function perishablePress_nG_remote_host() {

	$string = ''; // todo: get host by address

	$match = isset($_SERVER['REDIRECT_nG_REMOTE_HOST']) ? $_SERVER['REDIRECT_nG_REMOTE_HOST'] : '';

	return perishablePress_nG_get_patterns($string, $match);

}

function perishablePress_nG_http_referrer() {

	$string = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

	$match = isset($_SERVER['REDIRECT_nG_HTTP_REFERRER']) ? $_SERVER['REDIRECT_nG_HTTP_REFERRER'] : '';

	return perishablePress_nG_get_patterns($string, $match);

}

function perishablePress_nG_http_cookie() {

	$string = isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : '';

	$match = isset($_SERVER['REDIRECT_nG_HTTP_COOKIE']) ? $_SERVER['REDIRECT_nG_HTTP_COOKIE'] : '';

	return NGFIREWALL_COOKIE ? perishablePress_nG_get_patterns($string, $match) : '';

}

function perishablePress_nG_get_patterns($string, $match) {

	$patterns = explode('___', $match);

	foreach ($patterns as $pattern) {

		$string .= (!empty($pattern)) ? ' ['. $pattern .'] ' : '';

	}

	$string = preg_replace('/\s+/', ' ', $string);

	return $string;

}

perishablePress_nG_init();
