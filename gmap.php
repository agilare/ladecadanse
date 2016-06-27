<?php
if (is_file("config/reglages.php"))
{
	require_once("config/reglages.php");
}
require_once($rep_librairies."Sentry.php");
$videur = new Sentry();

$nom_page = "lieu";



/* if (!isset($_GET['idL']) || !is_numeric($_GET['idL']))
{
	echo "Un ID lieu doit ètre désigné par un entier";
	exit;
}
else
{
	$get['idL'] = trim($_GET['idL']);
} */
/* if (isset($_GET['genre_even']))
{

	$get['genre_even'] = trim($_GET['genre_even']);
} */

if (isset($_GET['idL']))
{
	$get['idL'] = verif_get($_GET['idL'], "int", 1);
}
else
{
	
	msgErreur("id obligatoire");
	exit;
}


//récolte des détails sur le lieu
$req_lieu = $connector->query("SELECT lat, lng, nom, adresse, quartier, logo, photo1 FROM lieu WHERE idLieu=".$get['idL']);
$tab_lieu = $connector->fetchArray($req_lieu);

$page_titre = $tab_lieu['nom']." (".$tab_lieu['quartier'].")";


$illustration = "";

if (!empty($tab_lieu['logo']))
{
	$illustration = "<img src=".$IMGlieux."s_".$tab_lieu['logo']." style=float:left;margin-right:0.2em />";
}
else if (!empty($tab_lieu['photo1']))
{
	$illustration = "<img src=".$IMGlieux."s_".$tab_lieu['photo1']." height=80 style=float:left;margin-right:0.2em />";
}

$info_lieu = "<div style='width:200px'>".$illustration."<div class=details><p class=adresse><strong>".securise_string($tab_lieu['nom'])."</strong></p><p class=adresse>".securise_string($tab_lieu['adresse'])."</p><p class=adresse>".$tab_lieu['quartier']."</p></div></div>";

?>

<!DOCTYPE html>

<html>
  <head>
    <meta http-equiv="content-type" content="text/html; charset=latin1"/>
    <title><?php echo $page_titre ?></title>
    <!--<script src="http://maps.google.com/maps?file=api&amp;v=2&amp;key=ABQIAAAAG-ck6Fuoq0SY-KLet7TuJBSmMl_j_m3i5qUsS9HDusFzfphmARSfXhQU23wz2ESm5vkdEFug_jntfw"
      type="text/javascript"></script>-->
	  
   <script async defer
      src="https://maps.googleapis.com/maps/api/js?key=AIzaSyB7tYPq8JwEOK_xXEzswUjD9CyW7VklWwM&callback=initMap">
    </script>
    <script type="text/javascript">
/*     function load() {
      if (GBrowserIsCompatible()) {
        var map = new GMap2(document.getElementById("map"));
		var point = new GLatLng(<?php echo $tab_lieu['lat'] ?>, <?php echo $tab_lieu['lng'] ?>)
        map.setCenter(point, 13);
		var marqueur = new GMarker(point);
		
        map.addControl(new GSmallMapControl());
        map.addControl(new GMapTypeControl());	

        GEvent.addListener(marqueur, "click", function() {
            marqueur.openInfoWindowHtml("<?php echo $info_lieu; ?>");
          });

		map.addOverlay(marqueur);		  
      }
    } */

var map;
function initMap() {
	
	var myLatLng = {lat: <?php echo $tab_lieu['lat'] ?>, lng: <?php echo $tab_lieu['lng'] ?>};

	map = new google.maps.Map(document.getElementById('map'), {
		center: myLatLng,
		zoom: 14
	});

	var marker = new google.maps.Marker({
		position: myLatLng,
		map: map
	});

	var infowindow = new google.maps.InfoWindow({
		content: "<?php echo $info_lieu; ?>"
	});

	marker.addListener('click', function() {
		infowindow.open(map, marker);
	});
  
}

    </script>
	
	<link rel="stylesheet" href="<?php echo $url_site ?>css/reset.css" type="text/css" media="screen" />
	<link rel="stylesheet" href="<?php echo $url_site ?>css/global.css" type="text/css" media="screen" />
	<link rel="stylesheet" href="<?php echo $url_site ?>css/gmaps.css" type="text/css" media="screen" />


  </head>
  <body>
    <div id="map" style="width: 500px; height: 300px; margin:10% auto;"></div>
</body>
</html>

