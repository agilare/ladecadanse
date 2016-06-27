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

require_once($rep_librairies."Sentry.php");
$videur = new Sentry();

if (!$videur->checkGroup(1))
{
	header("Location: ".$url_site."login.php"); die();
}

require_once($rep_librairies.'Validateur.php');

define("MAPS_HOST", "maps.google.ch");
define("KEY", "ABQIAAAAG-ck6Fuoq0SY-KLet7TuJBSmMl_j_m3i5qUsS9HDusFzfphmARSfXhQU23wz2ESm5vkdEFug_jntfw");



// Select all the rows in the markers table
mysqli_query("SET character_set_results = 'utf8'");
$query = "SELECT * FROM lieu WHERE statut='actif' and lat='' and lng =''";
$result = $connector->query($query);
if (!$result) {
  die("Invalid query: " . mysqli_error());
}

// Initialize delay in geocode speed
$delay = 0;
$base_url = "http://" . MAPS_HOST . "/maps/geo?output=csv&key=" . KEY;

// Iterate through the rows, geocoding each address
while ($row = @mysqli_fetch_assoc($result)) {
  $geocode_pending = true;

  while ($geocode_pending) {
    $address = $row["adresse"]." GenÃ¨ve";
	echo '<p><strong>'.$row['nom']."</strong> : ".$address."</p>";
    $id = $row["idLieu"];
    $request_url = $base_url . "&q=" . urlencode($address);
    $csv = file_get_contents($request_url) or die("url not loading");

    $csvSplit = mb_split(",", $csv);
    $status = $csvSplit[0];
    $lat = $csvSplit[2];
    $lng = $csvSplit[3];
    if (strcmp($status, "200") == 0) {
      // successful geocode
      $geocode_pending = false;
      $lat = $csvSplit[2];
      $lng = $csvSplit[3];

      $query = sprintf("UPDATE lieu " .
             " SET lat = '%s', lng = '%s' " .
             " WHERE idLieu = %s LIMIT 1;",
             $connector->sanitize($lat),
             $connector->sanitize($lng),
             $connector->sanitize($id));

     $update_result = $connector->query($query);
	 //$update_result = 1;
	  echo $query."<br><br>";
      if (!$update_result) {
        die("Invalid query: " . mysqli_error());
      }
    } else if (strcmp($status, "620") == 0) {
      // sent geocodes too fast
      $delay += 100000;
    } else {
      // failure to geocode
      $geocode_pending = false;
      echo "Address " . $address . " failed to geocoded. ";
      echo "Received status " . $status . "
\n";
    }
    usleep($delay);
  }
}

echo '<a href="'.$url_admin.'maintenance.php">Retour à Maintenance</a>';
?>
