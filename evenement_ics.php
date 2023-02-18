<?php
/**
 * Variables used in this script:
$summary - text title of the event
$datestart - the starting date (in seconds since unix epoch)
$dateend - the ending date (in seconds since unix epoch)
$address - the event's address
$uri - the URL of the event (add http://)
$description - text description of the event
$filename - the name of this file for saving (e.g. my-event-name.ics)

Notes:
- the UID should be unique to the event, so in this case I'm just using
uniqid to create a uid, but you could do whatever you'd like.

- iCal requires a date format of "yyyymmddThhiissZ". The "T" and "Z"
characters are not placeholders, just plain ol' characters. The "T"
character acts as a delimeter between the date (yyyymmdd) and the time
(hhiiss), and the "Z" states that the date is in UTC time. Note that if
you don't want to use UTC time, you must prepend your date-time values
with a TZID property. See RFC 5545 section 3.3.5

- The Content-Disposition: attachment; header tells the browser to save/open
the file. The filename param sets the name of the file, so you could set
it as "my-event-name.ics" or something similar.

- Read up on RFC 5545, the iCalendar specification. There is a lot of helpful
info in there, such as formatting rules. There are also many more options
to set, including alarms, invitees, busy status, etc.

https://www.ietf.org/rfc/rfc5545.txt
 */

require_once("app/bootstrap.php");


use Ladecadanse\Evenement;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Utils\Validateur;


if (isset($_GET['idE']))
{
    try {
        $get['idE'] = Validateur::validateUrlQueryValue($_GET['idE'], "int", 1);
    } catch (Exception $e) { header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request"); exit; }
}
else
{
    header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
    exit;
}

$even = new Evenement();
$even->setId($get['idE']);

$even->load();

// si idE ne correspond ࡡucune entrꥠdans la table
if (!$even->getValues() || in_array($even->getValue('statut'), ['propose', 'inactif']))
{

    header("HTTP/1.1 404 Not Found");
    echo file_get_contents("articles/404.php");
    exit;

}

if ($even->getValue('idLieu') != 0)
{
	$req_lieu = $connector->query("SELECT nom, adresse, quartier, localite, region, URL, lat, lng FROM lieu, localite
	WHERE lieu.localite_id=localite.id AND idlieu='".$even->getValue('idLieu')."'");
	$listeLieu = $connector->fetchArray($req_lieu);
	$lieu = sanitizeForHtml($listeLieu['nom']);

}
else
{
	$listeLieu['nom'] = sanitizeForHtml($even->getValue('nomLieu'));
	$lieu = sanitizeForHtml($even->getValue('nomLieu'));
	$listeLieu['adresse'] = sanitizeForHtml($even->getValue('adresse'));
	$listeLieu['quartier'] = sanitizeForHtml($even->getValue('quartier'));
        $req_localite = $connector->query("SELECT  localite FROM localite WHERE  id='".$even->getValue('localite_id')."'");
        $tab_localite = $connector->fetchArray($req_localite);

        $listeLieu['localite'] = sanitizeForHtml($tab_localite[0]);
	$listeLieu['URL'] = sanitizeForHtml($even->getValue('urlLieu'));

	$nom_lieu = $lieu;
}

$description = '';
if ($even->getValue('description') != '')
{
	$description = str_replace("\r\n", "\\n", $even->getValue('description'));
}



$filename = "evenement.ics";

$address = HtmlShrink::getAdressFitted(null, $listeLieu['localite'], $listeLieu['quartier'], $listeLieu['adresse']);

$dateend = '';
if ($even->getValue('horaire_fin') != "0000-00-00 00:00:00")
{
	$dateend = date("U", strtotime($even->getValue('horaire_fin')));
}

$datestart = date("U", strtotime($even->getValue('dateEvenement')));
if ($even->getValue('horaire_debut') != "0000-00-00 00:00:00")
{
	$datestart = date("U", strtotime($even->getValue('horaire_debut')));
}


$uniqueid = $get['idE'];

$uri = $site_full_url."/evenement.php?idE=".$get['idE'];

$summary = sanitizeForHtml($even->getValue('titre'));

if ($even->getValue('statut') == "annule" || $even->getValue('statut')  == "inactif" || $even->getValue('statut')  == "complet")
{
	$summary .= " - ".$even->getValue('statut') ;
}


// 1. Set the correct headers for this file
header('Content-type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

// 2. Define helper functions
// Converts a unix timestamp to an ics-friendly format
// NOTE: "Z" means that this timestamp is a UTC timestamp. If you need
// to set a locale, remove the "\Z" and modify DTEND, DTSTAMP and DTSTART
// with TZID properties (see RFC 5545 section 3.3.5 for info)
//
// Also note that we are using "H" instead of "g" because iCalendar's Time format
// requires 24-hour time (see RFC 5545 section 3.3.12 for info).
function dateToCal($timestamp) {
return date('Ymd\THis', $timestamp);
}
// Escapes a string of characters
function escapeString($string) {
return preg_replace('/([\,;])/','\\\$1', $string);
}

// 3. Echo out the ics file's contents
?>
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//hacksw/handcal//NONSGML v1.0//EN
CALSCALE:GREGORIAN
BEGIN:VEVENT
<?php if (!empty($dateend)) echo "DTEND:".dateToCal($dateend)."\n"; ?>
UID:<?php echo $uniqueid."\n"; ?>
DTSTAMP:<?php echo dateToCal(time())."Z\n"; ?>
LOCATION:<?php echo escapeString($address)."\n"; ?>
DESCRIPTION:<?php echo escapeString($description)."\n"; ?>
URL;VALUE=URI:<?php echo escapeString($uri)."\n"; ?>
SUMMARY:<?php echo escapeString($summary)."\n"; ?>
DTSTART:<?php echo dateToCal($datestart)."\n"; ?>
END:VEVENT
END:VCALENDAR