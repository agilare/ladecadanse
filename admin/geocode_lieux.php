<?php
if (is_file("../config/reglages.php"))
{
	require_once("../config/reglages.php");
}
else
{
	echo "<p>Problème de chargement de la configuration du site, veuillez repasser plus tard</p>";
	exit;
}

use Ladecadanse\Security\Sentry;
use Ladecadanse\Utils\Validateur;

$videur = new Sentry();

if (!$videur->checkGroup(1))
{
	header("Location: ".$url_site."login.php"); die();
}

define("MAPS_HOST", "maps.google.ch");

$from = filter_input(INPUT_GET, 'from', FILTER_SANITIZE_NUMBER_INT);
$to = filter_input(INPUT_GET, 'to', FILTER_SANITIZE_NUMBER_INT);

if (empty($to) || empty($from))
    die("from and to required");

// Select all the rows in the markers table
$query = "SELECT nom, adresse, idLieu, localite FROM lieu "
        . "INNER JOIN localite ON lieu.localite_id= localite.id "
        . "WHERE statut='actif' and lat='' and lng ='' and idLieu BETWEEN $from AND $to";
$result = $connector->query($query);
if (!$result) {
  die("Invalid query: " . mysqli_error());
}

// Initialize delay in geocode speed
$delay = 0;
$base_url = "https://maps.googleapis.com/maps/api/geocode/json?key=" . GOOGLE_API_KEY;
?>
<h1>Geocoding</h1>
<?php
// Iterate through the rows, geocoding each address
while ($row = $connector->fetchArray($result)) {
    
  $geocode_pending = true;

  while ($geocode_pending) {
    $address = $row["adresse"]." ".$row["localite"];
	echo '<h2>'.$row['nom']."</h2><p>".$address."</p>";
    $id = $row["idLieu"];
    $request_url = $base_url . "&address=" . urlencode($address);
    $json = file_get_contents($request_url) or die("url not loading");

    $json2 = json_decode($json, true);
 
    if (strcmp($json2['status'], "OK") == 0)
    {
        $latlng = $json2['results'][0]['geometry']['location'];
        // successful geocode
        $geocode_pending = false;

        $query = sprintf("UPDATE lieu " .
               " SET lat = '%s', lng = '%s' " .
               " WHERE idLieu = %s LIMIT 1;",
               $connector->sanitize($latlng['lat']),
               $connector->sanitize($latlng['lng']),
               $connector->sanitize($id));
        echo $query."<br><br>";
        $update_result = $connector->query($query);

        if (!$update_result) {
          die("Invalid query: " . mysqli_error());
        }
    } 
    else
    {
      // failure to geocode
      $geocode_pending = false;
      echo "Address " . $address . " failed to geocode. ";
      echo "Received status " . $json2['status'] . "
\n";
    }
    usleep($delay);
  }
}
?>

<a href="index.php">Retour à l'Admin</a>
