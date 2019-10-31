<?php
if (is_file("config/reglages.php"))
{
	require_once("config/reglages.php");
}
require_once($rep_librairies."Sentry.php");
$videur = new Sentry();

require_once($rep_librairies.'CollectionDescription.class.php');

if ($_SESSION['region'] == 'vd')
 {
     $page_titre_region = " à Lausanne";   
 }
 elseif ($_SESSION['region'] == 'fr')
 {
     $page_titre_region = " à Fribourg";   
 }
 else
 {
     $page_titre_region = " à Genève";
 }

$page_titre = "Lieux de sorties ".$page_titre_region." : bistrots, salles, bars, restaurants, cinémas,
 théâtres, galeries, boutiques, musées, ...";
$page_description = "Dernières descriptions de lieux à Genève";
$extra_css = array("menu_lieux");
include("includes/header.inc.php");

$get['idL'] = "";
if (isset($_GET['idL']))
{
	$get['idL'] = verif_get($_GET['idL'], "int", 1);
}

$fiches = new CollectionDescription();
$fiches->loadFiches('description', $_SESSION['region']);
$pair = 0;

$map_style = '';
if ((isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 4))
{
    

$rf = '';
if ($_SESSION['region'] == 'ge')
    $rf = "'rf',";
    
//  AND e.statut = 'actif' AND e.dateEvenement >= CURDATE()
$sql_lieux = "
SELECT l.idLieu AS idLieu, nom, l.adresse AS adresse, l.quartier AS quartier, categorie, lat, lng, (select count(idEvenement) from evenement e where e.statut = 'actif' AND e.dateEvenement >= CURDATE() and e.idLieu = l.idLieu) AS nb_event
FROM lieu l
WHERE l.statut='actif' AND l.region IN ('".$connector->sanitize($_SESSION['region'])."', $rf 'hs')  
    AND lat != '0.000000' AND lng != '0.000000'
ORDER BY TRIM(LEADING 'l\'' FROM (TRIM(LEADING 'les ' FROM (TRIM(LEADING 'la ' FROM (TRIM(LEADING 'le ' FROM lower(nom)))))))) COLLATE utf8mb4_unicode_ci";

//echo $sql_lieux;

$req_lieux = $connector->query($sql_lieux);

$nb_lieux = $connector->getNumRows($req_lieux);

$pair = 0;
$prec = "";
$url_prec = "";
$url_suiv = "";
$nomDuLieu = "";
$id_passe = 0;

$tab_markers = [];
while ($lieu = mysqli_fetch_assoc($req_lieux))
{
    $tab_markers[] = $lieu;
}
?>

<script type="text/javascript">

var tab_markers = <?php echo json_encode($tab_markers); ?>;

console.log(tab_markers);

function initMap() {
	
	var mapDiv = document.getElementById('map');
	var map = new google.maps.Map(mapDiv, {
	  center: {lat: 46.519653, lng: 6.632273}, // Suisse Romande
	  zoom: 9
	});
		
	markers = [];
	infowindows = [];
	
	var bounds = new google.maps.LatLngBounds();
	
	jQuery.each(tab_markers, function() {
				
		// création position avec lat et lng, puis marker dans map, puis extension contours avec ce nouv. marker
		var pos = new google.maps.LatLng(parseFloat(this['lat']), parseFloat(this['lng'])); 
		var marker = new google.maps.Marker({
			position: pos,
           label: this['nb_event'],

			map: map
           // icon: {                               url: "http://maps.google.com/mapfiles/ms/icons/blue-dot.png"                           }
		});
		
		bounds.extend(marker.getPosition());
		
		var contentString = '<div class="vd-marker-infowindow"><a href="lieu.php?idL=' + this['idLieu'] + '"><b>' + this['nom'] + '</b></a><br>' + this['categorie'] + '<br>' + this['adresse'] + '<br>' + this['quartier'];
		
		var infowindow = new google.maps.InfoWindow({
			content: contentString
		});
		
		infowindows.push(infowindow);

		marker.addListener('click', function() {
			
		   for (var i = 0; i < infowindows.length; i++) {
			  infowindows[i].close();
			}
			
			infowindow.open(map, marker);
		});
		
		markers.push(marker);
		map.fitBounds(bounds);
	});
	
	//var mc = new MarkerClusterer(map, markers, {styles: [{  textColor: "#e72557", textSize:13, height:46, width:46, url: '/templates/ee/images/cluster-j.png' }], gridSize: 40, maxZoom: 15}); // anchor:[14,16],

  }

  
  
</script>
<?php 
$map_style = 'style="width:94%;margin:0 auto;height:500px;border:1px solid #555;border-radius:4px"';

} ?>
<div id="contenu" class="colonne">
    
    <div id="entete_contenu">
        <h2 style="font-size:1.6em; width: 15%;">Lieux</h2> <?php if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 6) { ?><a href="ajouterLieu.php?action=ajouter" style="float: left;padding: 5px 1px;"><img src="web/interface/icons/building_add.png" alt=""  /> Ajouter un lieu</a><?php } ?><?php getMenuRegions($glo_regions, $get); ?>
        <div class="spacer"></div>
        <p class="mobile" id="btn_listelieux">
            <button href="#"><i class="fa fa-list fa-lg"></i>&nbsp;Liste des lieux</button>
        </p>
    </div>

	<div class="spacer"></div>
    
    
    <div id="map" <?php echo $map_style ?>></div>
 <div style="clear:both"></div>
	
    <div id="derniers_lieux" style="width:94%;margin:0 auto;">

	<h2>Derniers lieux ajoutés</h2>

	<?php
	$req_lieux_recents = $connector->query("
	SELECT idLieu, nom, adresse, quartier, localite, dateAjout
	FROM lieu, localite WHERE lieu.localite_id=localite.id AND region='".$connector->sanitize($_SESSION['region'])."' ORDER BY dateAjout DESC LIMIT 10");

	// Création de la section si il y a moins un lieu
	if ($connector->getNumRows($req_lieux_recents) > 0)
	{

		while ($tab_lieux_recents = $connector->fetchArray($req_lieux_recents))
		{
		//printr($tab_lieux_recents);
		?>
		<h3><a href="<?php echo $url_site; ?>lieu.php?idL=<?php echo $tab_lieux_recents['idLieu']; ?>" title="Voir la fiche du lieu" ><?php echo $tab_lieux_recents['nom']; ?></a></h3>
		
		<p><?php 
                
                echo htmlspecialchars(get_adresse( '', $tab_lieux_recents['localite'], $tab_lieux_recents['quartier'], $tab_lieux_recents['adresse'])); ?></p>
		<?php
		}
	}
	?>

	</div>
   

<div class="clear_mobile"></div>
</div>
<!-- fin Contenu -->

<div id="colonne_gauche" class="colonne">

<?php
include("includes/navigation_calendrier.inc.php");
?>
</div>
<!-- Fin Colonnegauche -->

<div id="colonne_droite" class="colonne">
    <?php include("includes/menulieux.inc.php");echo $aff_menulieux; ?>
</div>
<!-- Fin colonne_droite -->

<div class="spacer"><!-- --></div>
<?php
include("includes/footer.inc.php");
?>
