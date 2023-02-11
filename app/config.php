<?php

define("UPLOAD_MAX_FILESIZE", 3145728); // 3 Mo
define("POST_MAX_SIZE", 6291456); // 6 Mo

ini_set('post_max_size', POST_MAX_SIZE);
ini_set('upload_max_filesize', UPLOAD_MAX_FILESIZE);
ini_set('max_file_uploads', 3);


// DIR
define('__ROOT__', dirname(dirname(__FILE__))); // full path to dir, for ex. /users/michel/hosts/ladecadanse

$rep_images_even = __ROOT__ . "/images/"; // legacy, should be web/uploads/evenements
$rep_uploads_lieux = __ROOT__ . "/web/uploads/lieux/";
$rep_uploads_lieux_galeries = __ROOT__ . "/web/uploads/lieux/galeries/";
$rep_uploads_organisateurs = __ROOT__ . "/web/uploads/organisateurs/";
$rep_fichiers_even = __ROOT__ . "/web/uploads/fichiers/evenements/";
$rep_fichiers_lieu = __ROOT__ . "/web/uploads/fichiers/lieux/";


// PATHS
$url_uploads_events = "/images/";
$url_uploads_lieux = "/web/uploads/lieux/";
$url_uploads_lieux_galeries = "/web/uploads/lieux/galeries/";
$url_uploads_organisateurs = "/web/uploads/organisateurs/";

$url_images_interface_icons = "/web/interface/icons/";


$glo_mimes_documents_acceptes = array("image/jpeg", "image/pjpeg", "image/gif", "image/png", "image/x-png",
    "application/pdf",
    "application/msword",
    "text/richtext", "application/rtf", "image/svg+xml", "application/gzip",
    "application/zip", "multipart/x-zip", "multipart/x-gzip", "application/x-tar");
$glo_mimes_images_acceptees = array("image/jpeg", "image/pjpeg", "image/gif", "image/png", "image/x-png");
$mimes_images_acceptes = array("image/jpeg", "image/pjpeg", "image/gif", "image/png", "image/x-png");
$mimes_documents_acceptes = array("image/jpeg", "image/pjpeg", "image/gif", "image/png", "image/x-png",
    "application/pdf",
    "application/msword", "application/msexcel",
    "text/richtext", "application/rtf", "image/svg+xml", "application/gzip",
    "application/zip", "multipart/x-zip", "multipart/x-gzip", "application/x-tar");


$glo_auj = date("Y-m-d");
$auj = date("Y-m-d");
$glo_auj_6h = date("Y-m-d", time() - 14400); // 4h

$glo_regions = array("ge" => "Genève", "vd" => "Vaud", "rf" => "France", "hs" => "Autre"); //  "fr" => "Fribourg",
$glo_moisF = ["Janvier", "Février", "Mars", "Avril", "Mai", "Juin", "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre"];

// USERS
$glo_statuts_personne = array('demande', 'actif', 'inactif');
$g_mauvais_mdp = ['123456', 'password', 'soleil', 'genève', 'coucou', 'boubou', 'bonheur', 'vacances', 'doudou', 'papillon', 'bonjour', 'cheval', 'capitainne', 'Mathilde', 'caramel', 'garfield', 'friends', 'simba12', 'reslabol', 'shaka00', '1254321', 'xydney', 'caline', 'licorne', 'mjdc10435410', '280195', 'freesurf', 'musique', 'jfdodolaure', '333333', 'rochet88', 'jennifer', 'motdepasse', 'maison', '123soleil', 'chocolat', '123123', 'nicolas', '888888', 'othello1', 'carpediem', 'multipass', 'berocl69', '166459', 'sofia10mag', 'chonchon', 'Camille', 'joelle', '654321', '12345678', 'qwertz', '12345', 'football', 'ladecadanse', '111111', 'abc123' ];

// EVENTS
$glo_tab_genre = array("fête" => "fêtes", "cinéma" => "ciné", "théâtre" => "théâtre", "expos" => "expos", "divers" => "divers");
$statuts_evenement = array('propose' => 'Proposé', 'actif' => 'Proposé', 'complet' => 'Complet', 'annule' => 'Annulé', 'inactif' => 'Dépublié');
$price_types = ['unknown' => 'inconnu', 'gratis' => 'entrée libre', 'asyouwish' => 'prix libre', 'chargeable' => 'payant'];

// PLACES
$statuts_lieu = array('actif', 'ancien', 'inactif');
$glo_categories_lieux = array('bistrot' => 'bistrot', 'salle' => 'salle', 'restaurant' => 'restaurant', 'cinema' => 'cinéma', 'theatre' => 'théâtre', 'galerie' => 'galerie', 'boutique' => 'boutique', 'musee' => 'musée', 'autre' => 'autre');

$glo_tab_quartiers = ["geneve", "Champel", "Charmilles", "Centre", "Cornavin", "Grottes", "Jonction", "Nations", "Pâquis", "Plainpalais", "Saint-Gervais", "Saint-Jean", "Servette",
    "communes", "Aire-la-Ville", "Anières", "Avully", "Avusy", "Bardonnex", "Bellevue", "Bernex", "Carouge", "Cartigny", "Céligny",
    "Chancy", "Chêne-Bougeries", "Chêne-Bourg", "Choulex", "Collex-Bossy", "Collonge-Bellerive", "Cologny", "Confignon", "Corsier",
    "Dardagny", "Genthod", "Gy", "Hermance", "Jussy", "Laconnex", "Lancy", "Le Grand-Saconnex", "Meinier", "Meyrin", "Onex", "Perly-Certoux",
    "Plan-les-Ouates", "Pregny-Chambésy", "Presinge", "Puplinge", "Russin", "Satigny", "Soral", "Thônex", "Troinex", "Vandoeuvre",
    "Vernier", "Versoix", "Veyrier", "ailleurs", "Nyon", "Vaud", "France", "autre"];
$glo_tab_quartiers2 = [
            "ge" =>
            ["Champel", "Charmilles", "Centre", "Cornavin", "Grottes", "Jonction", "Nations", "Pâquis", "Petit-Saconnex", "Saint-Gervais", "Saint-Jean", "Servette"]
];
$glo_tab_quartiers_hors_geneve = array("Nyon", "Vaud", "France", "autre");
$glo_tab_ailleurs = ["rf" => "France", "hs" => "Autre"];

// INTERFACE
$glo_menu_pratique = array("Contact" => "contacteznous.php", "À propos" => "articles/apropos.php", "Faire un don" => "articles/faireUnDon.php");
$tab_nblignes = array(100, 250, 500); // nb lignes de resultats de listes
$actions = array("ajouter", "insert", "update", "editer");

$iconeSupprimer = "<img src=\"" . $url_images_interface_icons . "delete.png\" alt=\"Supprimer\" title=\"Supprimer\" />";
$iconeEditer = "<img src=\"" . $url_images_interface_icons . "page_white_edit.png\" alt=\"Éditer\" width=\"16\" height=\"16\" />";
$iconeActive = "<img src=\"" . $url_images_interface_icons . "bullet_green.png\" alt=\"Publié\" width=\"16\" height=\"16\"/>";
$iconeDesactive = "<img src=\"" . $url_images_interface_icons . "bullet_red.png\" alt=\"Dépublié\" width=\"16\" height=\"16\"/>";
$iconeImage = "<img src=\"" . $url_images_interface_icons . "image.png\" alt=\"Image\" width=\"16\" height=\"16\"/>";
$iconeURL = "<img src=\"" . $url_images_interface_icons . "world.png\" alt=\"URL\" width=\"16\" height=\"16\" />";
$iconeGauche = "<img src=\"" . $url_images_interface_icons . "arrow_left.png\" alt=\"Précédent\" width=\"16\" height=\"16\" />";
$iconeDroite = "<img src=\"" . $url_images_interface_icons . "arrow_right.png\" alt=\"Suivant\" width=\"16\" height=\"16\" />";
$iconeEmail = "<img src=\"" . $url_images_interface_icons . "email.png\" alt=\"Email\" width=\"16\" height=\"16\" />";
$iconeOk = "<img src=\"" . $url_images_interface_icons . "tick.png\" alt=\"Accompli\" />";
$iconeErreur = "<img src=\"" . $url_images_interface_icons . "error.png\" alt=\"Erreur\" width=\"16\" height=\"16\" />";
$iconeSuite = "<img src=\"" . $url_images_interface_icons . "resultset_next.png\" alt=\"Lire la suite\"  width=\"16\" height=\"16\" />";
$iconeRemonter = "<img src=\"" . $url_images_interface_icons . "arrow_up.png\" alt=\"Remonter\" width=\"16\" height=\"16\" />";
$iconeCopier = "<img src=\"" . $url_images_interface_icons . "page_white_copy.png\" alt=\"Copier\" width=\"16\" height=\"16\" />";
$iconeVoirFiche = "<img src=\"" . $url_images_interface_icons . "page_white_magnify.png\" alt=\"Voir fiche\" width=\"16\" height=\"16\" />";
$iconeAjouterEv = "<img src=\"" . $url_images_interface_icons . "page_white_add.png\" alt=\"Ajouter\" />";
$iconeAjouterLieu = "<img src=\"" . $url_images_interface_icons . "building_add.png\" alt=\"Ajouter\" />";
$iconeRecherche = "<img src=\"" . $url_images_interface_icons . "zoom.png\" alt=\"Rechercher\" />";
$iconeRSS = "<img src=\"" . $url_images_interface_icons . "feed.png\" alt=\"RSS\" width=\"16\" height=\"16\" />";
$iconeHoraire = "<img src=\"" . $url_images_interface_icons . "time.png\" alt=\"horaire\" title=\"horaire\" width=\"16\" height=\"16\" />";
$iconeEntree = "<img src=\"" . $url_images_interface_icons . "money.png\" alt=\"Entrée\" title=\"Entrée\" width=\"16\" height=\"16\"/>";
$iconePrelocations = "<img src=\"" . $url_images_interface_icons . "ticket.gif\" alt=\"Prélocations, réservation\" title=\"Prélocations, réservations\" />";
$iconePrecedent = "<img src=\"" . $url_images_interface_icons . "resultset_previous.png\" alt=\"Précédent\" width=\"16\" height=\"16\" />";
$iconeSuivant = "<img src=\"" . $url_images_interface_icons . "resultset_next.png\" alt=\"Suivant\" width=\"16\" height=\"16\"/>";
$iconeImprimer = "<img src=\"" . $url_images_interface_icons . "printer.png\" alt=\"Imprimer\" width=\"16\" height=\"16\"/>";
$icone['connexion'] = "<img src=\"" . $url_images_interface_icons . "user_go.png\" alt=\"Connexion\" width=\"16\" height=\"16\" />";
$icone['information'] = "<img src=\"" . $url_images_interface_icons . "information.png\" alt=\"\" />";
$icone['monter'] = "<img src=\"" . $url_images_interface_icons . "bullet_arrow_top.png\" alt=\"\" />";
$icone['descendre'] = "<img src=\"" . $url_images_interface_icons . "bullet_arrow_bottom.png\" alt=\"\" />";
$icone['evenement'] = '<img src="' . $url_images_interface_icons . 'calendar.png" alt="Événement" />';
$icone['lieu'] = '<img src="' . $url_images_interface_icons . 'building.png" alt="Lieu" width="16" height="16" />';
$icone['description'] = '<img src="' . $url_images_interface_icons . 'page_white.png" alt="Description" />';
$icone['personne'] = '<img src="' . $url_images_interface_icons . 'user.png" alt="Personne" />';
$icone['commentaire'] = '<img src="' . $url_images_interface_icons . 'comment.png" alt="" />';
$icone['ajouter_commentaire'] = '<img src="' . $url_images_interface_icons . 'comment_add.png" alt="" />';
$icone['asc'] = '<img src="' . $url_images_interface_icons . 'bullet_arrow_up.png" alt="" />';
$icone['desc'] = '<img src="' . $url_images_interface_icons . 'bullet_arrow_down.png" alt="" />';
$icone['liste'] = '<img src="' . $url_images_interface_icons . 'application_view_list.png" alt="Liste" />';
$icone['galerie'] = '<img src="' . $url_images_interface_icons . 'application_view_tile.png" alt="Galerie" />';
$icone['mode_condense'] = '<img src="' . $url_images_interface_icons . 'mode_condense.png" alt="Affichage condensé" width="16" height="16" />';
$icone['mode_etendu'] = '<img src="' . $url_images_interface_icons . 'mode_etendu.png" alt="Affichage étendu" width="16" height="16" />';
$icone['recherche'] = '<img src="' . $url_images_interface_icons . 'search.png" alt="Recherche" />';
$icone['voir_lieux'] = '<img src="' . $url_images_interface_icons . 'building_go.png" alt="Voir lieux" width="16" height="16" />';
$icone['voir_semaine'] = '<img src="' . $url_images_interface_icons . 'bullet_go.png" alt="Voir semaine" width="16" height="16" />';
$icone['envoi_email'] = '<img src="' . $url_images_interface_icons . 'email_go.png" alt="Envoi e-mail" width="16" height="16" />';
$icone['ajouts'] = '<img src="' . $url_images_interface_icons . 'add.png" alt="" width="16" height="16" />';
$icone['accepter'] = '<img src="' . $url_images_interface_icons . 'accept.png" alt="Accepter" width="16" height="16" />';
$icone['refuser'] = '<img src="' . $url_images_interface_icons . 'decline.png" alt="Refuser" width="16" height="16" />';
$icone['supprimer_personne'] = '<img src="' . $url_images_interface_icons . 'user_cross.png" alt="" />';
$icone['editer_personne'] = '<img src="' . $url_images_interface_icons . 'user_edit.png" alt="" />';
$icone['asterisque'] = '<img src="' . $url_images_interface_icons . 'asterisk_yellow.png" alt="" />';
$icone['date'] = '<img src="' . $url_images_interface_icons . 'date.png" alt="" />';
$icone['ajouter_date'] = '<img src="' . $url_images_interface_icons . 'date_add.png" alt="Exporter au format iCalendar" />';
$icone['supprimer_date'] = '<img src="' . $url_images_interface_icons . 'date_delete.png" alt="" />';
$icone['plan'] = '<img src="' . $url_images_interface_icons . 'map.png" alt="Plan" width="16" height="16" style="vertical-align: top;" />';
$icone['drapeau'] = '<img src="' . $url_images_interface_icons . 'flag_yellow.png" alt="" width="16" height="16" />';
$icone['ajouter_texte'] = '<img src="' . $url_images_interface_icons . 'pencil.png" alt="Ajouter texte" width="16" height="16" />';
$icone['organisateur'] = '<img src="' . $url_images_interface_icons . 'group.png" alt="" />';
$icone['url_externe'] = '<img src="' . $url_images_interface_icons . 'house_go.png" alt="Lien externe" />';
$icone['popup'] = '<img src="/web/interface/lien_ext.gif" alt="Nouvel onglet" />';
$icone['depublier'] = '<img src="' . $url_images_interface_icons . 'calendar_delete.png" alt="Dépublier" />';
$icone['jpg'] = "<img src=\"" . $url_images_interface_icons . "page_white_picture.png\" alt=\"\" />";
$icone['jpeg'] = "<img src=\"" . $url_images_interface_icons . "page_white_picture.png\" alt=\"\" />";
$icone['gif'] = "<img src=\"" . $url_images_interface_icons . "page_white_picture.png\" alt=\"\" />";
$icone['png'] = "<img src=\"" . $url_images_interface_icons . "page_white_picture.png\" alt=\"\" />";
$icone['pdf'] = "<img src=\"" . $url_images_interface_icons . "page_white_acrobat.png\" alt=\"\" />";
$icone['doc'] = "<img src=\"" . $url_images_interface_icons . "page_white_word.png\" alt=\"\" />";
$icone['rtf'] = "<img src=\"" . $url_images_interface_icons . "page_white_text.png\" alt=\"\" />";
$icone['xls'] = "<img src=\"" . $url_images_interface_icons . "page_white_excel.png\" alt=\"\" />";
$icone['svg'] = "<img src=\"" . $url_images_interface_icons . "page_white_picture.png\" alt=\"\" />";
$icone['zip'] = "<img src=\"" . $url_images_interface_icons . "page_white_zip.png\" alt=\"\" />";
$icone['tar'] = "<img src=\"" . $url_images_interface_icons . "page_white_zip.png\" alt=\"\" />";
$icones_fichiers = array("text" => "text.png");

$tab_icones_statut = array("actif" => "<div style='display:inline-block;background:green;width:12px;height:12px;border-radius:50%' title='Publié'>&nbsp;</div>",
    "inactif" => "<div style='display:inline-block;background:red;width:12px;height:12px;border-radius:50%' title='Dépublié'>&nbsp;</div>",
    "annule" => "<div style='display:inline-block;background:orange;width:12px;height:12px;border-radius:50%' title='Annulé'>&nbsp;</div>",
    "complet" => "<div style='display:inline-block;background:darkorange;width:12px;height:12px;border-radius:50%' title='Complet'>&nbsp;</div>",
    "ancien" => "<div style='display:inline-block;background:lightgray;width:12px;height:12px;border-radius:50%' title='Ancien'>&nbsp;</div>",
    "propose" => "<div style='display:inline-block;background:lightblue;width:12px;height:12px;border-radius:50%' title='Proposé'>&nbsp;</div>"
);


$recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
