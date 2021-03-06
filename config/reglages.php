<?php
header('X-Content-Type-Options "nosniff"');
header('X-Frame-Options: "ALLOW-FROM https://epic-magazine.ch/"');
// configurer si néc. début de header.inc.php et comportements.inc.php
# Added by PorCus comme un goret 
date_default_timezone_set('Europe/Berlin');

require_once('params.php');
 
define("UPLOAD_MAX_FILESIZE", 3145728); // octets, = 3 Mo
define("POST_MAX_SIZE", 6291456);

ini_set('post_max_size', POST_MAX_SIZE);
ini_set('upload_max_filesize', UPLOAD_MAX_FILESIZE);
ini_set('max_file_uploads', 3);

$rep_images = $rep_absolu."images/";

$rep_images_breves = $rep_images."web/uploads/breves/";
$rep_images_even = $rep_images;
$rep_images_lieux = $rep_absolu."web/uploads/lieux/";
$rep_images_lieux_galeries = $rep_images_lieux."galeries/";
$rep_images_organisateurs = $rep_absolu."web/uploads/organisateurs/";
$rep_fichiers =  $rep_absolu."web/uploads/fichiers/";
$rep_fichiers_even = $rep_fichiers."evenements/";
$rep_fichiers_lieu = $rep_fichiers."lieux/";

$rep_images_interface = $rep_absolu."web/interface/";

$rep_includes = $rep_absolu."includes/";
$rep_librairies = $rep_absolu."librairies/";
$rep_csv = $rep_absolu."images/csv/"; //fichiers csv upload

$rep_cache = $rep_absolu."cache/";

// URL

$url_images = $url_site."images/";
$url_fichiers = $url_site."web/uploads/fichiers/";
$url_fichiers_even = $url_fichiers."evenements/";
$url_fichiers_lieu = $url_fichiers."lieux/";
$url_images_lieu_galeries = $url_site."web/uploads/lieux/galeries/";
$url_images_organisateurs = $url_site."web/uploads/organisateurs/";

$url_admin = $url_site."admin/";
$url_css = $url_site."css/";
$url_js = $url_site."js/";

// IMAGES
global $IMGeven;
$IMGeven = $url_images;
global $IMGbreves;
$IMGbreves = $url_images."breves/";
global $IMGlieux;
$IMGlieux = $url_site."web/uploads/lieux/";

global $IMGinterface;
$IMGinterface = $url_site."web/interface/";
global $IMGicones;
$IMGicones = $IMGinterface."icons/";



$glo_mimes_documents_acceptes = array("image/jpeg", "image/pjpeg", "image/gif", "image/png", "image/x-png",
 "application/pdf",
"application/msword",
 "text/richtext", "application/rtf", "image/svg+xml","application/gzip",
"application/zip", "multipart/x-zip", "multipart/x-gzip", "application/x-tar");

$glo_mimes_images_acceptees = array("image/jpeg", "image/pjpeg", "image/gif", "image/png", "image/x-png");


$mime_extension = array("image/jpeg" => "jpg");

$glo_moisF = array("Janvier", "Février", "Mars", "Avril", "Mai", "Juin", "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre");
$glo_annee_max = 2022;

//mots clef pour le header html
$indexMotsClef = array("soirées","soirée","soir","nuit","fête","party","parties","genève","geneve","sortir","sorties",
"festival","festivals","musique","concerts","agenda","culture","culturel","alternatif","squats","bars","flyer",
"flyers","cinéma","ciné");

$glo_categories_lieux = array('bistrot' => 'bistrot', 'salle' => 'salle', 'restaurant' => 'restaurant', 'cinema' => 'cinéma',  'theatre' => 'théâtre', 'galerie' => 'galerie', 'boutique' => 'boutique', 'musee' => 'musée', 'autre' => 'autre');

$glo_tab_genre = array("fête" => "fêtes", "cinéma" => "ciné", "théâtre" => "théâtre", "expos" => "expos", "divers" => "divers");

$statuts_evenement = array('propose' => 'Proposé', 'actif' => 'Proposé', 'complet' => 'Complet', 'annule' => 'Annulé', 'inactif' => 'Dépublié');
$statuts_lieu = array('actif',  'ancien', 'inactif');
$statuts_breve = array('actif', 'inactif');
$glo_statuts_personne = array('demande', 'actif', 'inactif');

$glo_tab_quartiers = array("geneve", "Champel", "Charmilles", "Centre", "Cornavin", "Grottes", "Jonction","Nations","Pâquis", "Plainpalais", "Saint-Gervais", "Saint-Jean", "Servette",
 "communes", "Aire-la-Ville", "Anières", "Avully", "Avusy", "Bardonnex", "Bellevue", "Bernex", "Carouge", "Cartigny", "Céligny",
 "Chancy", "Chêne-Bougeries", "Chêne-Bourg", "Choulex", "Collex-Bossy", "Collonge-Bellerive", "Cologny", "Confignon", "Corsier",
 "Dardagny", "Genthod", "Gy", "Hermance", "Jussy", "Laconnex", "Lancy", "Le Grand-Saconnex", "Meinier", "Meyrin", "Onex", "Perly-Certoux",
 "Plan-les-Ouates", "Pregny-Chambésy", "Presinge", "Puplinge", "Russin", "Satigny", "Soral", "Thônex", "Troinex", "Vandoeuvre",
 "Vernier","Versoix", "Veyrier", "ailleurs", "Nyon", "Vaud", "France", "autre");


$glo_tab_quartiers2 = 
        [
            "ge" =>
            ["Champel", "Charmilles", "Centre", "Cornavin", "Grottes","Jonction", "Nations", "Pâquis", "Petit-Saconnex", "Saint-Gervais", "Saint-Jean", "Servette"]
            
        ];
 
$glo_tab_quartiers_hors_geneve = array("Nyon", "Vaud", "France", "autre");
$glo_tab_ailleurs = ["rf" => "France", "hs" => "Autre"];


$actions = array("ajouter", "insert", "update", "editer");

$mimes_images_acceptes = array("image/jpeg", "image/pjpeg", "image/gif", "image/png", "image/x-png");

$mimes_documents_acceptes = array("image/jpeg", "image/pjpeg", "image/gif", "image/png", "image/x-png",
 "application/pdf",
"application/msword", "application/msexcel",
 "text/richtext", "application/rtf", "image/svg+xml","application/gzip",
"application/zip", "multipart/x-zip", "multipart/x-gzip", "application/x-tar");


$glo_menu_pratique = array("Contact" => "contacteznous.php", "À propos" => "apropos.php", "Faire un don" => "faireUnDon.php");

$g_mauvais_mdp = 
array('123456',
'password',
'soleil',
'genève',
'coucou',
'boubou',
'bonheur',
'vacances',
'doudou',
'papillon',
'bonjour',
'cheval',
'capitainne',
'Mathilde',
'caramel',
'garfield',
'friends',
'simba12',
'reslabol',
'shaka00',
'1254321',
'xydney',
'caline',
'licorne',
'mjdc10435410',
'280195',
'freesurf',
'musique',
'jfdodolaure',
'333333',
'rochet88',
'jennifer',
'motdepasse',
'maison',
'123soleil',
'chocolat',
'123123',
'nicolas',
'888888',
'othello1',
'carpediem',
'multipass',
'berocl69',
'166459',
'sofia10mag',
'chonchon',
'Camille',
'joelle',
'654321',
'12345678',
'qwertz',
'12345',
'football',
'ladecadanse',
'111111',
'abc123'
); 

$glo_regions = array("ge" => "Genève", "vd" => "Vaud", "fr" => "Fribourg", "rf" => "France", "hs" => "Autre");
$price_types = ['unknown' => 'inconnu', 'gratis' => 'entrée libre', 'asyouwish' => 'prix libre', 'chargeable' => 'payant'];


require_once('visuel.php');

$glo_auj = date("Y-m-d");
$auj = date("Y-m-d");
$glo_auj_6h = date("Y-m-d", time() - 14400);

//require $rep_absolu.'vendor/autoload.php';

if (is_file($rep_librairies.'DbConnector.php'))
{
	require_once($rep_librairies.'DbConnector.php');
	$connector = new DbConnector($param['dbhost'],$param['dbname'], $param['dbusername'], $param['dbpassword']);
}
else
{
	echo "<p>Classe de connexion à la base de données non trouvée à ".$rep_librairies."DbConnector.php</p>";
	exit;
}


require_once($rep_librairies.'usine.php');
require_once($rep_librairies.'dates.php');
require_once($rep_librairies.'presentation.php');
require_once($rep_librairies.'Logger.php');
require_once($rep_librairies.'SecurityToken.php');

session_start();

//use GeoIp2\Database\Reader;

$remote_addr = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_SANITIZE_STRING);

//$user_region_detected = null;
//if (strlen($remote_addr) > 5)
//{   
//    $reader = new Reader($rep_geolite2_db);
//
//    $record = $reader->city($remote_addr);
//    $user_region_detected = $record->mostSpecificSubdivision->isoCode;
//}


// par défaut
if (empty($_SESSION['region']))
    $_SESSION['region'] = 'ge';

// à l'aide du cookie ou de l'IP
if (!empty($_COOKIE['ladecadanse_region']))
{
    $_SESSION['region'] = $_COOKIE['ladecadanse_region'];
}
/*
elseif ($user_region_detected == 'VD')
{
    $_SESSION['region'] = strtolower($user_region_detected);
} 
 */   

$get['region'] = filter_input(INPUT_GET, "region", FILTER_SANITIZE_STRING);

if (array_key_exists($get['region'], $glo_regions))
{        
    $_SESSION['region'] = $get['region'];
    setcookie("ladecadanse_region", $get['region'], time() + 36000, '', true, true);  /* , 'ladecadanse.darksite.ch' */    
    
}
//echo "session : ".$_SESSION['region'];


 
$url_query_region = '';
$url_query_region_et = '';
$url_query_region_1er = '';
$get['region'] = '';
if ($_SESSION['region'] != 'ge')
{
    $url_query_region = 'region='.$_SESSION['region'];    
    $url_query_region_et = 'region='.$_SESSION['region']."&amp;";    
    $url_query_region_1er = '?region='.$_SESSION['region'];
    $get['region'] = $_SESSION['region'];
   
}

$logger = new Logger($rep_absolu."logs/");

$recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
