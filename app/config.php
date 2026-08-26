<?php

// INFRA
//// FILE MANAGEMENT

define("UPLOAD_MAX_FILESIZE", 3145728); // 3 Mo
define("POST_MAX_SIZE", 6291456); // 6 Mo

// Plafond sur les dimensions, et non sur le poids : GD décompresse à ~4 octets
// par pixel, et un fichier léger peut couvrir énormément de pixels. 40 Mpx
// représentent ~160 Mo en mémoire, sur les 640 Mo de l'hébergement, et couvrent
// tout appareil photo actuel.
define("UPLOAD_MAX_MEGAPIXELS", 40);

ini_set('post_max_size', POST_MAX_SIZE);
ini_set('upload_max_filesize', UPLOAD_MAX_FILESIZE);
ini_set('max_file_uploads', 3);


// DIR
define('__ROOT__', dirname(__FILE__, 2)); // full path to dir, for ex. /users/michel/hosts/ladecadanse
define('ASSETS_DIR', '/web'); // racine URL et suffixe système pour tous les assets (css, js, uploads…)

// URL canonique du site, sans slash final. Contrairement à $site_full_url, qui se déduit de
// $_SERVER['SERVER_NAME'] et vaut donc « http://ladecadanse.ch » pour un visiteur arrivé en http,
// cette constante ne varie pas selon la requête. Indispensable partout où une URL est enregistrée
// durablement chez un tiers : un href relatif dans les balises d'autodiscovery RSS faisait
// enregistrer les abonnements en http://, payés ensuite par une redirection 301 à chaque relève.
// app/env.php, chargé avant ce fichier, peut la surcharger pour un environnement de développement.
if (!defined('SITE_CANONICAL_URL'))
{
    define('SITE_CANONICAL_URL', 'https://www.ladecadanse.ch');
}


$rep_images_even = __ROOT__ . ASSETS_DIR . "/uploads/evenements/";
$rep_uploads_lieux = __ROOT__ . ASSETS_DIR . "/uploads/lieux/";
$rep_uploads_lieux_galeries = __ROOT__ . ASSETS_DIR . "/uploads/lieux/galeries/";
$rep_uploads_organisateurs = __ROOT__ . ASSETS_DIR . "/uploads/organisateurs/";
$rep_fichiers_lieu = __ROOT__ . ASSETS_DIR . "/uploads/fichiers/lieux/";

// PATHS — chemins relatifs à ASSETS_DIR, utilisables directement avec AssetManager::get()
$url_uploads_events = "/uploads/evenements/";
$url_uploads_lieux = "/uploads/lieux/";
$url_uploads_lieux_galeries = "/uploads/lieux/galeries/";
$url_uploads_organisateurs = "/uploads/organisateurs/";

// Icônes historiques conservées : leur dessin famfamfam n'a pas d'équivalent
// Font Awesome jugé satisfaisant. Le reste du jeu Silk a été supprimé (#151).
$url_images_interface_icons = "/web/interface/icons/";

// default Open Graph tags values, overrided in some pages
$page_image = 'web/interface/ladecadanse-695x672.jpeg';
$page_url = ltrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

$glo_mimes_documents_acceptes = [
    "image/jpeg", "image/pjpeg", "image/gif", "image/png", "image/x-png", "image/webp",
    "application/pdf",
    "application/msword",
    "text/richtext", "application/rtf", "image/svg+xml", "application/gzip",
    "application/zip", "multipart/x-zip", "multipart/x-gzip", "application/x-tar"
];
$glo_mimes_images_acceptees = ["image/jpeg", "image/pjpeg", "image/gif", "image/png", "image/x-png", "image/webp"];
$mimes_images_acceptes = ["image/jpeg", "image/pjpeg", "image/gif", "image/png", "image/x-png", "image/webp"];
$mimes_documents_acceptes = [
    "image/jpeg", "image/pjpeg", "image/gif", "image/png", "image/x-png", "image/webp",
    "application/pdf",
    "application/msword", "application/msexcel",
    "text/richtext", "application/rtf", "image/svg+xml", "application/gzip",
    "application/zip", "multipart/x-zip", "multipart/x-gzip", "application/x-tar"];

//// FILE MANAGEMENT END


//// TIME & REGIONS

define("DATE_DEFAULT_TIMEZONE", 'Europe/Zurich');

$glo_auj = date("Y-m-d");
$auj = date("Y-m-d");
$glo_auj_6h = date("Y-m-d", time() - 21_600); // -6h

// TODO: rn to $glo_regions_fr
$glo_regions = ["ge" => "Genève", "vd" => "Vaud", "rf" => "France", "hs" => "Autre"]; //  "fr" => "Fribourg",
$glo_moisF = ["Janvier", "Février", "Mars", "Avril", "Mai", "Juin", "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre"];

// region selected by user is extended to arounding regions
$glo_regions_coverage = ['ge' => ['ge', 'rf', 'hs'], 'vd' => ['vd', 'hs']];

//// TIME & REGIONS END

// INFRA END


// DOMAIN

//// EVENTS
$glo_tab_genre = ["fête" => "fêtes", "cinéma" => "ciné", "théâtre" => "théâtre", "expos" => "expos", "divers" => "divers"];
$statuts_evenement = ['propose' => 'Proposé', 'actif' => 'Proposé', 'complet' => 'Complet', 'annule' => 'Annulé', 'inactif' => 'Dépublié'];
$price_types = ['unknown' => 'inconnu', 'gratis' => 'entrée libre', 'asyouwish' => 'prix libre', 'chargeable' => 'payant'];
$tab_tri_agenda = ["dateAjout", "horaire_debut"];
// lieu/lieu.php et organisateur/organisateur.php, onglet « Passés » : sens du tri par date.
// Les libellés ne sont pas affichés (les boutons sont des icônes) mais servent de title et d'aria-label.
$tab_ordre_evenements_passes = [
    "asc"  => ["icone" => "fa-sort-numeric-asc",  "titre" => "Du plus ancien au plus récent"],
    "desc" => ["icone" => "fa-sort-numeric-desc", "titre" => "Du plus récent au plus ancien"],
];
$glo_motifs_notification_auteur = [
    'depublie_charte'     => "l'événement a été dépublié car il enfreint la charte éditoriale",
    'depublie_doublon'    => "l'événement a été dépublié car il existe déjà dans l'agenda",
    'categorie_deplacee'  => "l'événement a été déplacé dans une catégorie plus appropriée",
    'erreurs_corrigees'   => "une ou plusieurs erreurs ont été corrigées",
    'lieu_remplace'       => "le lieu écrit manuellement a été remplacé par le lieu équivalent enregistré sur La décadanse",
    'organisateur_ajoute' => "un organisateur manquant a été ajouté",
    'image_ajoutee'       => "une image de l'événement a été ajoutée ou changée",
];

//// PLACES
$statuts_lieu = ['actif', 'ancien', 'inactif'];
$glo_categories_lieux = ['bistrot' => 'bistrot', 'salle' => 'salle', 'restaurant' => 'restaurant', 'cinema' => 'cinéma', 'theatre' => 'théâtre', 'galerie' => 'galerie', 'boutique' => 'boutique', 'musee' => 'musée', 'autre' => 'autre'];

$glo_tab_quartiers = [
    "geneve",
        "Champel", "Charmilles", "Centre", "Cornavin", "Grottes", "Jonction", "Nations", "Pâquis", "Plainpalais", "Saint-Gervais", "Saint-Jean", "Servette",
    "communes",
        "Aire-la-Ville", "Anières", "Avully", "Avusy", "Bardonnex", "Bellevue", "Bernex", "Carouge", "Cartigny", "Céligny",
        "Chancy", "Chêne-Bougeries", "Chêne-Bourg", "Choulex", "Collex-Bossy", "Collonge-Bellerive", "Cologny", "Confignon", "Corsier",
        "Dardagny", "Genthod", "Gy", "Hermance", "Jussy", "Laconnex", "Lancy", "Le Grand-Saconnex", "Meinier", "Meyrin", "Onex", "Perly-Certoux",
        "Plan-les-Ouates", "Pregny-Chambésy", "Presinge", "Puplinge", "Russin", "Satigny", "Soral", "Thônex", "Troinex", "Vandoeuvre",
        "Vernier", "Versoix", "Veyrier",
    "ailleurs",
        "Nyon", "Vaud", "France", "autre"];

$glo_tab_quartiers2 = [
    "ge" => ["Champel", "Charmilles", "Centre", "Cornavin", "Grottes", "Jonction", "Nations", "Pâquis", "Petit-Saconnex", "Saint-Gervais", "Saint-Jean", "Servette"]
];

$glo_tab_quartiers_hors_geneve = ["Nyon", "Vaud", "France", "autre"];
// $glo_tab_ailleurs (France, Autre) a disparu : ces deux entrées, jusque-là greffées en dur
// sur les <select> de localités, sont devenues des localités de la table `localite`
// (cantons 'rf' et 'hs') — voir Ladecadanse\Localite::CANTONS.

// DOMAIN END

// VIEW "Mises à jour" => "/articles/mises-a-jour.php",
$glo_menu_pratique = [ "Mises à jour" => "/articles/mises-a-jour.php", "Faire un don" => "/articles/faireUnDon.php", "Contact" => "/misc/contacteznous.php"];
$tab_nblignes = [50, 100, 250, 500]; // nb lignes de resultats de listes
$actions = ["ajouter", "insert", "update", "editer"];

// Icônes réutilisées dans plusieurs pages, en glyphes Font Awesome 4.7 ou en
// images héritées quand leur dessin famfamfam a été jugé préférable (#151).
// Attention : ce sont des fragments HTML, ils sont échoés tels quels.
// L'icône seule ne porte pas de nom accessible ; le lien qui la contient doit
// fournir un title ou un aria-label lorsqu'aucun texte ne l'accompagne.
$iconeSupprimer = '<i class="fa fa-trash-o" title="Supprimer" aria-label="Supprimer"></i>';
$iconeEditer = '<img src="' . $url_images_interface_icons . 'page_white_edit.png" alt="Éditer" width="16" height="16" />';
$iconeCopier = '<img src="' . $url_images_interface_icons . 'page_white_copy.png" alt="Copier" width="16" height="16" />';
$iconePrecedent = '<i class="fa fa-arrow-left" aria-hidden="true"></i>';
$iconeSuivant = '<i class="fa fa-arrow-right" aria-hidden="true"></i>';

$icone['asc'] = '<i class="fa fa-sort-asc fa-lg" aria-hidden="true"></i>';
$icone['desc'] = '<i class="fa fa-sort-desc fa-lg" aria-hidden="true"></i>';
$icone['envoi_email'] = '<i class="fa fa-paper-plane-o fa-lg" aria-hidden="true"></i>';
$icone['ajouts'] = '<i class="fa fa-plus" aria-hidden="true"></i>';
$icone['ajouter_texte'] = '<i class="fa fa-pencil" aria-hidden="true"></i>';
$icone['personne'] = '<i class="fa fa-user fa-lg" aria-hidden="true"></i>';
$icone['plan'] = '<img src="' . $url_images_interface_icons . 'map.png" alt="Plan" width="16" height="16" style="vertical-align: top;" />';
$icone['depublier'] = '<img src="' . $url_images_interface_icons . 'calendar_delete.png" alt="Dépublier" />';