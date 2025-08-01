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

require_once("../app/bootstrap.php");

use Ladecadanse\Evenement;

if (empty($_GET['idE']) || !is_numeric($_GET['idE']))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
    exit;
}

$get['idE'] = (int) $_GET['idE'];


// EVENT AND APPENDIXES
$sql_event = "SELECT

  e.genre AS e_genre,
  e.idEvenement AS e_idEvenement,
  e.titre AS e_titre,
  e.statut AS e_statut,
  e.idPersonne AS e_idPersonne,
  e.dateEvenement AS e_dateEvenement,
  e.ref AS e_ref,
  e.flyer AS e_flyer,
  e.image AS e_image,
  e.description AS e_description,
  e.ref AS e_ref,
  e.horaire_debut AS e_horaire_debut,
  e.horaire_fin AS e_horaire_fin,
  e.horaire_complement AS e_horaire_complement,
  e.prix AS e_prix,
  e.prelocations AS e_prelocations,
  e.idLieu AS e_idLieu,
  e.idSalle AS e_idSalle,
  e.nomLieu AS e_nomLieu,
  e.adresse AS e_adresse,
  e.quartier AS e_quartier,
  loc.localite AS e_localite,
  e.region AS e_region,
  e.urlLieu AS e_urlLieu,
  e.dateAjout AS e_dateAjout,

  l.nom AS l_nom,
  l.determinant AS l_determinant,
  l.adresse AS l_adresse,
  l.quartier AS l_quartier,
  l.lat AS l_lat,
  l.lng AS l_lng,
  l.URL AS l_URL,
  lloc.localite AS lloc_localite,
  l.region AS l_region,

  s.nom AS s_nom

FROM evenement e
JOIN localite loc ON e.localite_id = loc.id
LEFT JOIN lieu l ON e.idLieu = l.idLieu
LEFT JOIN localite lloc ON l.localite_id = lloc.id
LEFT JOIN salle s ON e.idSalle = s.idSalle
WHERE e.idEvenement = :idE";

$stmt = $connectorPdo->prepare($sql_event);
$stmt->execute([':idE' => $get['idE']]);
$tab_even = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$authorization->isPersonneAllowedToEditEvenement($_SESSION, $tab_even) && in_array($tab_even['e_statut'], ['propose', 'inactif']))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
    exit;
}

$even_ics = Evenement::getIcsValues($tab_even, $site_full_url);

header('Content-type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename=' . "evenement-" . $get['idE'] . "-" . $tab_even['e_dateEvenement'] .  ".ics");
header('X-Robots-Tag: noindex');
?>
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//hacksw/handcal//NONSGML v1.0//EN
CALSCALE:GREGORIAN
BEGIN:VEVENT
<?php if (!empty($even_ics['DTEND']))
{
    echo "DTEND:" . $even_ics['DTEND'] . "\n";
}
?>
UID:<?= $even_ics['UID']."\n"; ?>
DTSTAMP:<?= $even_ics['DTSTAMP']."Z\n"; ?>
LOCATION:<?= $even_ics['LOCATION']."\n"; ?>
DESCRIPTION:<?= $even_ics['DESCRIPTION']."\n"; ?>
URL;VALUE=URI:<?= $even_ics['URI']."\n"; ?>
SUMMARY:<?= $even_ics['SUMMARY']."\n"; ?>
DTSTART:<?= $even_ics['DTSTART']."\n"; ?>
END:VEVENT
END:VCALENDAR