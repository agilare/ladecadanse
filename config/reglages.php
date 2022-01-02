<?php
header('X-Content-Type-Options "nosniff"');
header('X-Frame-Options: "ALLOW-FROM https://epic-magazine.ch/"');
# Added by PorCus comme un goret 
date_default_timezone_set('Europe/Berlin');

require_once('params.php');

require $rep_absolu . 'vendor/autoload.php';
 
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
$url_css = $url_site."web/css/";
$url_js = $url_site."web/js/";

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

$glo_regions = array("ge" => "Genève", "vd" => "Vaud", "rf" => "France", "hs" => "Autre"); //  "fr" => "Fribourg", 
$price_types = ['unknown' => 'inconnu', 'gratis' => 'entrée libre', 'asyouwish' => 'prix libre', 'chargeable' => 'payant'];

//icones disponibles
$iconeSupprimer = "<img src=\"".$IMGicones."delete.png\" alt=\"Supprimer\" title=\"Supprimer\" />";
$iconeEditer = "<img src=\"".$IMGicones."page_white_edit.png\" alt=\"Éditer\" width=\"16\" height=\"16\" />";
$iconeActive = "<img src=\"".$IMGicones."bullet_green.png\" alt=\"Publié\" width=\"16\" height=\"16\"/>";
$iconeDesactive = "<img src=\"".$IMGicones."bullet_red.png\" alt=\"Dépublié\" width=\"16\" height=\"16\"/>";
$iconeImage = "<img src=\"".$IMGicones."image.png\" alt=\"Image\" width=\"16\" height=\"16\"/>";
$iconeURL = "<img src=\"".$IMGicones."world.png\" alt=\"URL\" width=\"16\" height=\"16\" />";
$iconeGauche = "<img src=\"".$IMGicones."arrow_left.png\" alt=\"Précédent\" width=\"16\" height=\"16\" />";
$iconeDroite = "<img src=\"".$IMGicones."arrow_right.png\" alt=\"Suivant\" width=\"16\" height=\"16\" />";
$iconeEmail = "<img src=\"".$IMGicones."email.png\" alt=\"Email\" width=\"16\" height=\"16\" />";
$iconeOk = "<img src=\"".$IMGicones."tick.png\" alt=\"Accompli\" />";
$iconeErreur = "<img src=\"".$IMGicones."error.png\" alt=\"Erreur\" width=\"16\" height=\"16\" />";
$iconeSuite = "<img src=\"".$IMGicones."resultset_next.png\" alt=\"Lire la suite\"  width=\"16\" height=\"16\" />";
$iconeRemonter = "<img src=\"".$IMGicones."arrow_up.png\" alt=\"Remonter\" width=\"16\" height=\"16\" />";
$iconeCopier = "<img src=\"".$IMGicones."page_white_copy.png\" alt=\"Copier\" width=\"16\" height=\"16\" />";
$iconeVoirFiche = "<img src=\"".$IMGicones."page_white_magnify.png\" alt=\"Voir fiche\" width=\"16\" height=\"16\" />";
$iconeAjouterEv = "<img src=\"".$IMGicones."page_white_add.png\" alt=\"Ajouter\" />";
$iconeAjouterLieu = "<img src=\"".$IMGicones."building_add.png\" alt=\"Ajouter\" />";
$iconeRecherche = "<img src=\"".$IMGicones."zoom.png\" alt=\"Rechercher\" />";
$iconeRSS = "<img src=\"".$IMGicones."feed.png\" alt=\"RSS\" width=\"16\" height=\"16\" />";
$iconeHoraire = "<img src=\"".$IMGicones."time.png\" alt=\"horaire\" title=\"horaire\" width=\"16\" height=\"16\" />";
$iconeEntree = "<img src=\"".$IMGicones."money.png\" alt=\"Entrée\" title=\"Entrée\" width=\"16\" height=\"16\"/>";
$iconePrelocations = "<img src=\"".$IMGicones."ticket.gif\" alt=\"Prélocations, réservation\" title=\"Prélocations, réservations\" />";
$iconePrecedent = "<img src=\"".$IMGicones."resultset_previous.png\" alt=\"Précédent\" width=\"16\" height=\"16\" />";
$iconeSuivant = "<img src=\"".$IMGicones."resultset_next.png\" alt=\"Suivant\" width=\"16\" height=\"16\"/>";
$iconeImprimer = "<img src=\"".$IMGicones."printer.png\" alt=\"Imprimer\" width=\"16\" height=\"16\"/>";
$icone['connexion'] = "<img src=\"".$IMGicones."user_go.png\" alt=\"Connexion\" width=\"16\" height=\"16\" />";
$icone['information'] = "<img src=\"".$IMGicones."information.png\" alt=\"\" />";
$icone['monter'] = "<img src=\"".$IMGicones."bullet_arrow_top.png\" alt=\"\" />";
$icone['descendre'] = "<img src=\"".$IMGicones."bullet_arrow_bottom.png\" alt=\"\" />";
$icone['evenement'] = '<img src="'.$IMGicones.'calendar.png" alt="Événement" />';
$icone['breve'] = '<img src="'.$IMGicones.'newspaper.png" alt="" />';
$icone['lieu'] = '<img src="'.$IMGicones.'building.png" alt="Lieu" width="16" height="16" />';
$icone['description'] = '<img src="'.$IMGicones.'page_white.png" alt="Description" />';
$icone['personne'] = '<img src="'.$IMGicones.'user.png" alt="Personne" />';
$icone['commentaire'] = '<img src="'.$IMGicones.'comment.png" alt="" />';
$icone['ajouter_commentaire'] = '<img src="'.$IMGicones.'comment_add.png" alt="" />';
$icone['asc'] = '<img src="'.$IMGicones.'bullet_arrow_up.png" alt="" />';
$icone['desc'] = '<img src="'.$IMGicones.'bullet_arrow_down.png" alt="" />';
$icone['liste'] = '<img src="'.$IMGicones.'application_view_list.png" alt="Liste" />';
$icone['galerie'] = '<img src="'.$IMGicones.'application_view_tile.png" alt="Galerie" />';
$icone['mode_condense'] = '<img src="'.$IMGicones.'mode_condense.png" alt="Affichage condensé" width="16" height="16" />';
$icone['mode_etendu'] = '<img src="'.$IMGicones.'mode_etendu.png" alt="Affichage étendu" width="16" height="16" />';
$icone['recherche'] = '<img src="'.$IMGicones.'search.png" alt="Recherche" />';
$icone['voir_lieux'] = '<img src="'.$IMGicones.'building_go.png" alt="Voir lieux" width="16" height="16" />';
$icone['voir_semaine'] = '<img src="'.$IMGicones.'bullet_go.png" alt="Voir semaine" width="16" height="16" />';
$icone['voir_breves'] = '<img src="'.$IMGicones.'newspaper_go.png" alt="" />';
$icone['envoi_email'] = '<img src="'.$IMGicones.'email_go.png" alt="Envoi e-mail" width="16" height="16" />';
$icone['favori'] = '<img src="'.$IMGicones.'heart.png" alt="Favori" width="16" height="16" />';
$icone['ajouter_favori'] = '<img src="'.$IMGicones.'heart_add.png" alt="Ajouter favori" width="16" height="16" />';
$icone['supprimer_favori'] = '<img src="'.$IMGicones.'heart_delete.png" alt="Supprimer favori" width="16" height="16" />';
$icone['ajouts'] = '<img src="'.$IMGicones.'add.png" alt="" width="16" height="16" />';
$icone['accepter'] = '<img src="'.$IMGicones.'accept.png" alt="Accepter" width="16" height="16" />';
$icone['refuser'] = '<img src="'.$IMGicones.'decline.png" alt="Refuser" width="16" height="16" />';
$icone['supprimer_personne'] = '<img src="'.$IMGicones.'user_cross.png" alt="" />';
$icone['editer_personne'] = '<img src="'.$IMGicones.'user_edit.png" alt="" />';
$icone['asterisque'] = '<img src="'.$IMGicones.'asterisk_yellow.png" alt="" />';
$icone['date'] = '<img src="'.$IMGicones.'date.png" alt="" />';
$icone['ajouter_date'] = '<img src="'.$IMGicones.'date_add.png" alt="Exporter au format iCalendar" />';
$icone['supprimer_date'] = '<img src="'.$IMGicones.'date_delete.png" alt="" />';
$icone['plan'] = '<img src="'.$IMGicones.'map.png" alt="Plan" width="16" height="16" style="vertical-align: top;" />';
$icone['drapeau'] = '<img src="'.$IMGicones.'flag_yellow.png" alt="" width="16" height="16" />';
$icone['ajouter_texte'] = '<img src="'.$IMGicones.'pencil.png" alt="Ajouter texte" width="16" height="16" />';
$icone['organisateur'] = '<img src="'.$IMGicones.'group.png" alt="" />';
$icone['url_externe'] = '<img src="'.$IMGicones.'house_go.png" alt="Lien externe" />';
$icone['popup'] = '<img src="'.$IMGinterface.'lien_ext.gif" alt="Nouvel onglet" />';
$icone['depublier'] = '<img src="'.$IMGicones.'calendar_delete.png" alt="Dépublier" />';

$icone['jpg'] = "<img src=\"".$IMGicones."page_white_picture.png\" alt=\"\" />";
$icone['jpeg'] = "<img src=\"".$IMGicones."page_white_picture.png\" alt=\"\" />";
$icone['gif'] = "<img src=\"".$IMGicones."page_white_picture.png\" alt=\"\" />";
$icone['png'] = "<img src=\"".$IMGicones."page_white_picture.png\" alt=\"\" />";
$icone['pdf'] = "<img src=\"".$IMGicones."page_white_acrobat.png\" alt=\"\" />";
$icone['doc'] = "<img src=\"".$IMGicones."page_white_word.png\" alt=\"\" />";
$icone['rtf'] = "<img src=\"".$IMGicones."page_white_text.png\" alt=\"\" />";
$icone['xls'] = "<img src=\"".$IMGicones."page_white_excel.png\" alt=\"\" />";
$icone['svg'] = "<img src=\"".$IMGicones."page_white_picture.png\" alt=\"\" />";
$icone['zip'] = "<img src=\"".$IMGicones."page_white_zip.png\" alt=\"\" />";
$icone['tar'] = "<img src=\"".$IMGicones."page_white_zip.png\" alt=\"\" />";

$icones_fichiers = array("text" => "text.png");

/*
$tab_icones_statut = array("actif" => "<img src=\"".$IMGicones."bullet_green.png\" title=\"publié\" alt=\"\" />",
"inactif" => "<img src=\"".$IMGicones."bullet_yellow.png\" title=\"dépublié\" />",
"annule" => "<img src=\"".$IMGicones."bullet_white.png\" title=\"annulé\" />",
"complet" => "<img src=\"".$IMGicones."bullet_red.png\" title=\"complet\" />",
"ancien" => "<img src=\"".$IMGicones."bullet_white.png\" title=\"ancien\" />"
);

 */

$tab_icones_statut = array("actif" => "<div style='display:inline-block;background:green;width:12px;height:12px;border-radius:50%' title='Publié'>&nbsp;</div>",
"inactif" => "<div style='display:inline-block;background:red;width:12px;height:12px;border-radius:50%' title='Dépublié'>&nbsp;</div>",
"annule" => "<div style='display:inline-block;background:orange;width:12px;height:12px;border-radius:50%' title='Annulé'>&nbsp;</div>",
"complet" => "<div style='display:inline-block;background:darkorange;width:12px;height:12px;border-radius:50%' title='Complet'>&nbsp;</div>",
"ancien" => "<div style='display:inline-block;background:lightgray;width:12px;height:12px;border-radius:50%' title='Ancien'>&nbsp;</div>",
"propose" => "<div style='display:inline-block;background:lightblue;width:12px;height:12px;border-radius:50%' title='Proposé'>&nbsp;</div>"
);

//CSS
$indexCssScreen = array("decad");
$indexCssPrint = array("imprimer");
$lieuxCssScreen = array("decad", "lieux");
$lieuxCssPrint = array("imprimer_lieux");
$formsCssScreen = array("decad", "formulaires");
$formsCssPrint = array("imprimer_formulaires");

//affichage des lignes de resultats
$limiteRes = 10;
$limiteLiens = 3;
$limiteInf = 80;
$limiteRecherche = 10;
$min_pages = 10;
$tab_nblignes = array(100, 250, 500);


$glo_auj = date("Y-m-d");
$auj = date("Y-m-d");
$glo_auj_6h = date("Y-m-d", time() - 14400);

use Ladecadanse\Utils\DbConnector;
use Ladecadanse\Utils\Logger;
use Ladecadanse\Security\Authorization;

$authorization = new Authorization();

if (ENV == 'prod') {
    include_once "Mail.php";  
}

$connector = new DbConnector($param['dbhost'],$param['dbname'], $param['dbusername'], $param['dbpassword']);


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

/**
 * FIXME: mv to String class
 * @param string $chaine
 * @return string
 */
function sanitizeForHtml(string $chaine): string
{
    return trim(htmlspecialchars($chaine));
}