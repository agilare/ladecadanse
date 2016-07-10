<?php
// configurer si néc. début de header.inc.php et comportements.inc.php
# Added by PorCus comme un goret 
date_default_timezone_set('Europe/Berlin');

require_once('params.php');
 
$CONF_maxfilesize = 2097152; // 2 Mo

$rep_images = $rep_absolu."images/";
$rep_images_breves = $rep_images."breves/";
$rep_images_even = $rep_images;
$rep_images_interface = $rep_images."interface/";
$rep_images_lieux = $rep_images."lieux/";
$rep_images_lieux_galeries = $rep_images_lieux."galeries/";
$rep_images_organisateurs = $rep_images."organisateurs/";

$rep_fichiers =  $rep_absolu."fichiers/";
$rep_fichiers_even = $rep_fichiers."evenements/";
$rep_fichiers_lieu = $rep_fichiers."lieux/";

$rep_includes = $rep_absolu."includes/";
$rep_librairies = $rep_absolu."librairies/";
$rep_csv = $rep_absolu."images/csv/"; //fichiers csv upload

$rep_cache = $rep_absolu."cache/";

// URL
$url_site = $url_domaine."/";
$url_images = $url_site."images/";
$url_fichiers = $url_site."fichiers/";
$url_fichiers_even = $url_fichiers."evenements/";
$url_fichiers_lieu = $url_fichiers."lieux/";
$url_images_lieu_galeries = $url_images."lieux/galeries/";
$url_images_organisateurs = $url_images."organisateurs/";
$url_admin = $url_site."admin/";
$url_css = $url_site."css/";
$url_js = $url_site."js/";

// IMAGES
global $IMGeven;
$IMGeven = $url_images;
global $IMGbreves;
$IMGbreves = $url_images."breves/";
global $IMGinterface;
$IMGinterface = $url_images."interface/";
global $IMGicones;
$IMGicones = $IMGinterface."icons/";
global $IMGlieux;
$IMGlieux = $url_images."lieux/";


$glo_mimes_documents_acceptes = array("image/jpeg", "image/pjpeg", "image/gif", "image/png", "image/x-png",
 "application/pdf",
"application/msword",
 "text/richtext", "application/rtf", "image/svg+xml","application/gzip",
"application/zip", "multipart/x-zip", "multipart/x-gzip", "application/x-tar");

$glo_mimes_images_acceptees = array("image/jpeg", "image/pjpeg", "image/gif", "image/png", "image/x-png");


$mime_extension = array("image/jpeg" => "jpg");

$glo_moisF = array("Janvier", "Février", "Mars", "Avril", "Mai", "Juin", "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre");
$glo_annee_max = 2020;

//mots clef pour le header html
$indexMotsClef = array("soirées","soirée","soir","nuit","fête","party","parties","genève","geneve","sortir","sorties",
"festival","festivals","musique","concerts","agenda","culture","culturel","alternatif","squats","bars","flyer",
"flyers","cinéma","ciné");

$glo_categories_lieux = array('bistrot' => 'bistrot', 'salle' => 'salle', 'restaurant' => 'restaurant', 'cinema' => 'cinéma',  'theatre' => 'théâtre', 'galerie' => 'galerie', 'boutique' => 'boutique', 'musee' => 'musée', 'autre' => 'autre');

$glo_tab_genre = array("fête" => "fêtes", "cinéma" => "ciné", "théâtre" => "théâtre", "expos" => "expos", "divers" => "divers");

$statuts_evenement = array('actif', 'complet', 'annule', 'inactif');
$statuts_lieu = array('actif',  'ancien', 'inactif');
$statuts_breve = array('actif', 'inactif');
$glo_statuts_personne = array('demande', 'actif', 'inactif');

$glo_tab_quartiers = array("geneve", "Acacias", "Champel", "Charmilles", "Centre", "Cornavin",  "Eaux-Vives", "Grottes","Jonction","Nations","Pâquis", "Petit-Saconnex","Plainpalais", "Saint-Gervais", "Saint-Jean", "Servette",
 "communes", "Aire-la-Ville", "Anières", "Avully", "Avusy", "Bardonnex", "Bellevue", "Bernex", "Carouge", "Cartigny", "Céligny",
 "Chancy", "Chêne-Bougeries", "Chêne-Bourg", "Choulex", "Collex-Bossy", "Collonge-Bellerive", "Cologny", "Confignon", "Corsier",
 "Dardagny", "Genthod", "Gy", "Hermance", "Jussy", "Laconnex", "Lancy", "Le Grand-Saconnex", "Meinier", "Meyrin", "Onex", "Perly-Certoux",
 "Plan-les-Ouates", "Pregny-Chambésy", "Presinge", "Puplinge", "Russin", "Satigny", "Soral", "Thônex", "Troinex", "Vandoeuvre",
 "Vernier","Versoix", "Veyrier", "ailleurs", "Nyon", "Vaud", "France", "autre");
 
$glo_tab_quartiers_hors_geneve = array("Nyon", "Vaud", "France", "autre");

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

require_once('visuel.php');

$glo_auj = date("Y-m-d");
$auj = date("Y-m-d");
$glo_auj_6h = date("Y-m-d", time() - 14400);

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

/*
require_once($rep_librairies.'gerer_erreur.php');
set_error_handler('gerer_erreur');
*/
