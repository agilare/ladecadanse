<?php

require_once("app/bootstrap.php");

use Ladecadanse\Utils\Validateur; // forms
use Ladecadanse\Utils\QueryParamValidator; // query string
use Ladecadanse\Utils\ImageDriver2; // files
use Ladecadanse\Utils\ImageUrlFetcher; // url import
use Ladecadanse\Security\SecurityToken;
use Ladecadanse\Evenement; // domain
use Ladecadanse\Localite; // domain
use Ladecadanse\EvenementRenderer; // presentation
use Ladecadanse\Utils\Mailing;
use Ladecadanse\Utils\AuteurNotifier;
use Ladecadanse\HtmlShrink; // template
use Ladecadanse\UserLevel; // domain
use Ladecadanse\Utils\DateHelper;
use Ladecadanse\Utils\RefList;
use Ladecadanse\Personne;
use Ladecadanse\UserSettings;

// Un visiteur non connecté utilise le formulaire public « Proposer un événement » :
// pas de fieldset Statut, email obligatoire, événement créé en « proposé ».
// Aucun code de cette page ne touche à $_SESSION['Sgroupe'], la valeur reste vraie jusqu'au pied de page.
$est_connecte = isset($_SESSION['Sgroupe']);

// ...template
// Request query : action, idE, idL, idO
$get['action'] = "ajouter";
if (isset($_GET['action']))
 {
    try
    {
        $get['action'] = QueryParamValidator::validateUrlQueryValue($_GET['action'], "enum", "ajouter", $actions);
    }
    catch (Exception)
    {
        header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
        exit;
    }
}

if (isset($_GET['idE']))
 {
    try
    {
        $get['idE'] = QueryParamValidator::validateUrlQueryValue($_GET['idE'], "int", 1);
    }
    catch (Exception)
    {
        header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
        exit;
    }
}

if (isset($_GET['idL']))
 {
    try
    {
        $get['idL'] = QueryParamValidator::validateUrlQueryValue($_GET['idL'], "int", 1);
    }
    catch (Exception)
    {
        header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
        exit;
    }
}

if (isset($_GET['idO']))
 {
    try
    {
        $get['idO'] = QueryParamValidator::validateUrlQueryValue($_GET['idO'], "int", 1);
    }
    catch (Exception)
    {
        header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
        exit;
    }
}

// autorization to edit : author, 'admin' level, member of lieu, member of organisateur, member of lieu's organisateur
if ($get['action'] != "ajouter" && $get['action'] != "insert")
{
    // "editer" and "update" both work on an existing event : without its id
    // there is nothing to authorize, and every link of the site carries it
    if (!isset($get['idE']))
    {
        header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
        exit;
    }

    $res_even_lieu = $connector->query("SELECT idLieu, statut, idPersonne, user_email, dateAjout, dateEvenement, horaire_fin FROM evenement WHERE idEvenement=" . (int) $get['idE']);
    $tab_even_lieu = $connector->fetchArray($res_even_lieu);

    if (
            !(
            (isset($_SESSION['SidPersonne']) && $authorization->isAuthor("evenement", $_SESSION['SidPersonne'], $get['idE'])) || ($est_connecte && $_SESSION['Sgroupe'] <= 6) || (isset($_SESSION['Saffiliation_lieu']) && isset($tab_even_lieu['idLieu']) && $tab_even_lieu['idLieu'] == $_SESSION['Saffiliation_lieu']) || (isset($_SESSION['SidPersonne']) && $authorization->isPersonneInEvenementByOrganisateur($_SESSION['SidPersonne'], $get['idE'])) || (isset($_SESSION['SidPersonne']) && isset($tab_even_lieu['idLieu']) && $authorization->isPersonneInLieuByOrganisateur($_SESSION['SidPersonne'], $tab_even_lieu['idLieu']))
            )
    )
    {
        header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
        HtmlShrink::msgErreur("Vous ne pouvez pas modifier cet événement car vous n'avez pas les droits suffisants ou vous n'êtes pas (ou plus) connecté");
        exit;
    }

    // Un événement passé est une archive : le rouvrir pour en changer la date écraserait
    // l'événement d'origine, et l'agenda historique y perdrait. Les éditeurs gardent la main,
    // les autres peuvent le copier vers de nouvelles dates ou le dépublier.
    // Ce bloc couvre "editer" comme "update" : l'affichage du formulaire et sa soumission.
    if (!$authorization->isPersonneEditor($_SESSION)
        && DateHelper::isEvenementPast($tab_even_lieu['dateEvenement'], $tab_even_lieu['horaire_fin']))
    {
        header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
        HtmlShrink::msgErreur("Cet événement est passé : il est archivé et ne peut plus être modifié. "
            . "Pour le reprogrammer, <a href=\"/event/copy.php?idE=" . (int) $get['idE'] . "\">copiez-le vers d'autres dates</a>.");
        exit;
    }
}

// Résolution de l'auteur original de l'événement, pour le fieldset "E-mail à l'auteur" (issue #149)
// (ne pas utiliser $champs['idPersonne'] plus bas : il est écrasé par l'idPersonne de la session courante)
$original_author_groupe     = null; // null = soumission anonyme, traitée comme non-admin
$original_author_email      = null;
$original_author_name       = '';
$original_author_idPersonne = null;
if (!empty($tab_even_lieu['idPersonne']))
{
    $auteur = Personne::getPersonneById((int) $tab_even_lieu['idPersonne']);
    if ($auteur)
    {
        $original_author_groupe     = (int) $auteur['groupe'];
        $original_author_email      = $auteur['email'];
        $original_author_name       = $auteur['pseudo'];
        $original_author_idPersonne = (int) $auteur['idPersonne'];
    }
}
elseif (!empty($tab_even_lieu['user_email']))
{
    $original_author_email = $tab_even_lieu['user_email'];
}

$can_notify_auteur =
    $est_connecte && $_SESSION['Sgroupe'] <= UserLevel::ADMIN
    && ($get['action'] === "editer" || $get['action'] === "update") && isset($get['idE'])
    && !empty($original_author_email)
    && ($original_author_groupe === null || $original_author_groupe >= UserLevel::AUTHOR);

// Import d'image par URL : réservé aux utilisateurs connectés (tous niveaux),
// donc indisponible sur le formulaire public "Proposer un événement"
$can_import_image_url = $est_connecte;

// form values received
$champs = ["statut" => "", "genre" => "", "titre" => "", "dateEvenement" => "", "idLieu" => 0, "idSalle" => 0,
    "nomLieu" => "", "adresse" => "", "quartier" => "",  "localite_id" => "", "region" => "", "urlLieu" => "",
    'organisateurs' => '', "description" => "", "ref" => "", "horaire_debut" => "", "horaire_fin" => "",
    "horaire_complement" => "", "price_type" => "", "prix" => "", "prelocations" => "", "user_email" => "", "remarque" => ""];
$fichiers = ['flyer' => '', 'image' => ''];
$supprimer = [];
$url_flyer = '';
$url_image = '';
$fetched_flyer = null;
$fetched_image = null;
$notif_motifs = [];
$notif_message = '';

/*
* Préremplissage depuis les valeurs par défaut du profil (user-edit.php, fieldset « Événements »).
*
* Uniquement à l'ouverture du formulaire d'ajout : le formulaire poste vers ?action=insert, si bien
* que le ré-affichage après une erreur de validation ne repasse jamais par « ajouter » — une saisie
* en cours ne peut donc pas être écrasée. La condition sur la session écarte le formulaire public
* « Proposer un événement », qui n'a pas d'utilisateur connecté.
*/
$ev_user_defaults = UserSettings::eventNewDefaults(null);
$prefilled_from_user_defaults = false;

if ($get['action'] === 'ajouter' && !empty($_SESSION['SidPersonne']))
{
    $ev_user_defaults = UserSettings::eventNewDefaults(
        Personne::getSettingsJson((int) $_SESSION['SidPersonne'])
    );

    foreach (['genre', 'horaire_debut', 'horaire_fin', 'prix'] as $champ_defaut)
    {
        if ($ev_user_defaults[$champ_defaut] !== '')
        {
            $champs[$champ_defaut] = $ev_user_defaults[$champ_defaut];
            $prefilled_from_user_defaults = true;
        }
    }

    // ?idL= garde la priorité : le select des lieux teste les deux sources, et remplir les deux
    // marquerait deux <option selected> dans un select simple.
    if ($ev_user_defaults['idLieu'] > 0 && empty($get['idL']))
    {
        $champs['idLieu'] = $ev_user_defaults['idLieu'];
        $prefilled_from_user_defaults = true;
    }

    // Les organisateurs sont présélectionnés plus bas, via $tab_organisateurs_even.
    if ($ev_user_defaults['idOrganisateurs'] !== [] && empty($get['idO']))
    {
        $prefilled_from_user_defaults = true;
    }
}

$show_form = true;
$formTokenName = 'form_token_evenement_edit';
$verif = new Validateur();
$fichiers = ['flyer' => ['name' => '', 'size' => 0], 'image' => ['name' => '', 'size' => 0]];

if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok')
{
    // fill default empty fields with received values
    foreach ($champs as $c => $v)
    {
        if (isset($_POST[$c]) && !in_array($c, ['flyer', 'image']))
        {
            $champs[$c] = $_POST[$c];
        }
    }

    // le champ Références est saisi à raison d'une URL par ligne, mais stocké (et exposé par l'API)
    // sous forme de liste séparée par des points-virgules : on repasse tout de suite au format
    // canonique, pour que la validation, l'INSERT/UPDATE et le ré-affichage après erreur travaillent
    // tous sur la même valeur — une chaîne, que les boucles de génération SQL puissent sanitizer
    $champs['ref'] = RefList::toStorage($champs['ref']);

    // public form : check
    if (!$est_connecte)
    {
        // check token received == token initially set in form registered in session
        if (!isset($_SESSION[$formTokenName]) || $_POST[$formTokenName] !== $_SESSION[$formTokenName])
        {
            HtmlShrink::msgErreur("Désolé, le formulaire est expiré, veuillez le saisir à nouveau");
            exit;
        }

        unset($_SESSION[$formTokenName]);
    }
    else // ?
    {
        if (!SecurityToken::check($_POST['token'], $_SESSION['token']))
        {
            $verif->setErreur("genres", "Le système de sécurité du site n'a pu authentifier votre action. Veuillez réafficher ce formulaire et réessayer");
        }
    }

    // redundant ?
    if (isset($_POST['organisateurs']))
		$champs['organisateurs'] = $_POST['organisateurs'];

    // un navigateur envoie toujours les champs fichier, même vides ; un client qui
    // ne le fait pas (tests fonctionnels, POST scripté) ne doit pas déclencher
    // d'erreur : on retombe alors sur le "fichier vide" défini plus haut
	$fichiers['flyer'] = $_FILES['flyer'] ?? $fichiers['flyer'];
    $fichiers['image'] = $_FILES['image'] ?? $fichiers['image'];

    if ($can_import_image_url) {
        $url_flyer = trim($_POST['flyer_url'] ?? '');
        $url_image = trim($_POST['image_url'] ?? '');
    }

    // ?
    // not set, null, '' or 0 => 0
    if (empty($champs['idLieu']))
        $champs['idLieu'] = 0;


	// récup des suppressions qui seront à effectuer
	if (isset($_POST['sup_flyer']))
	{
		$supprimer['flyer'] = $_POST['sup_flyer'];
	}

	if (isset($_POST['sup_image']))
	{
		$supprimer['image'] = $_POST['sup_image'];
	}

	if (!empty($_POST['name_as']))
	{
		$verif->setErreur("name_as", "Veuillez laisser ce champ vide");
	}



	/*
	 * VERIFICATION DES CHAMPS ENVOYES par POST
	 */

	$verif->valider($champs['genre'], "genre", "texte", 1, 200, 1);
	if (!empty($champs['genre']) && !array_key_exists($champs['genre'], $glo_tab_genre))
	{
		$verif->setErreur("genres", "Cette catégorie n'est pas valable");
	}

	$verif->valider($champs['titre'], "titre", "texte", 1, 80, 1);

	$verif->valider($champs['nomLieu'], "nomLieu", "texte", 1, 80, 0);
	if (empty($champs['idLieu']) && empty($champs['nomLieu']))
	{
		$verif->setErreur("idLieu", "Vous devez désigner un lieu");
    }

	if (!empty($champs['idLieu']) && preg_match("/^[0-9]+_[0-9]+$/", (string) $champs['idLieu']))
	{
		//echo "match";
		$tab_idLieu = explode("_", (string) $champs['idLieu']);
		$champs['idLieu'] = $tab_idLieu[0];
		$champs['idSalle'] = $tab_idLieu[1];
	}

    if (!empty($champs['idLieu']) && (!preg_match("/^[0-9]+$/", (string) $champs['idLieu']) && !preg_match("/^[0-9]+_[0-9]+$/", (string) $champs['idLieu'])))
	{
        $verif->setErreur("idLieu", "La valeur du lieu n'est pas au bon format");
	}

	$verif->valider($champs['adresse'], "adresse", "texte", 2, 100, 0);
	if (empty($champs['lien']) && !empty($champs['nomLieu']) && empty($champs['adresse']))
	{
		$verif->setErreur("adresse", "L'adresse est obligatoire");
	}

	if (empty($champs['lien']) && !empty($champs['nomLieu']) && empty($champs['localite_id']))
	{
		$verif->setErreur("localite_id", "La localité est obligatoire");
	}

    if ($champs['idLieu'] != 0 && ($champs['nomLieu'] != "" || $champs['adresse'] != "") )
	{
		$verif->setErreur('doublonLieux', 'Vous ne pouvez pas indiquer 2 lieux; si vous choisissez le lieu dans la liste ci-dessus, vous pouvez le confirmer en  enregistrant ce formulaire');
    }

	if (empty($champs['dateEvenement']))
	{
		$verif->setErreur('dateEvenement', "Il faut indiquer la date de l'événement");
	}
	else
	{
		// La date doit être exactement au format jj.mm.aaaa : checkdate() seul, sur des morceaux
		// castés en (int), laisse passer tout ce qui traîne derrière l'année ("12.05.2026<payload>"),
		// et appToIso() renvoie telle quelle toute chaîne de plus de 10 caractères — la valeur polluée
		// se retrouverait alors dans le message flash, rendu sans échappement
		$tab_date = explode('.', (string) $champs['dateEvenement']);
		if (!preg_match('/^[0-9]{1,2}\.[0-9]{1,2}\.[0-9]{4}$/', (string) $champs['dateEvenement'])
			|| !checkdate((int) $tab_date[1], (int) $tab_date[0], (int) $tab_date[2])) {
			$verif->setErreur('dateEvenement', "La date n'est pas correcte");
		}

		$date_iso = DateHelper::appToIso($champs['dateEvenement']);
	}

	$verif->valider($champs['description'], "description", "texte", 4, 10000, 0);
	$verif->valider($champs['ref'], "ref", "texte", 1, 1000, 0);

	// Chaque référence doit ressembler à une URL : au moins « www. » ou un protocole http(s):// en
	// tête. Une saisie « exemple.org » seule n'est pas transformée en lien à l'affichage
	// (EvenementRenderer::getRefListHtml ne préfixe que « www » et exige http(s)://) : on la refuse ici.
	foreach (RefList::split($champs['ref']) as $ref_url)
	{
		if (!preg_match('#^(https?://|www\.)#i', $ref_url))
		{
			$verif->setErreur('ref', "Chaque adresse doit commencer par « http:// », « https:// » ou au moins « www. » (par ex. « www.exemple.org » et non « exemple.org »)");
			break;
		}
	}

    if ($_SERVER["CONTENT_LENGTH"] > POST_MAX_SIZE)
    {
        $verif->setErreur('image', "Le poids des fichiers envoyés dépasse la limite autorisée");
    }
    else
    {
        $verif->validerFichier($fichiers['flyer'], "flyer", $glo_mimes_images_acceptees, 0);
        $verif->validerFichier($fichiers['image'], "image", $glo_mimes_images_acceptees, 0);
    }

    /**
     * Import d'une image par URL (utilisateurs connectés) : exclusion mutuelle avec le champ
     * fichier, puis format et récupération. Renvoie l'image récupérée, ou null s'il n'y a rien
     * à importer ou si une erreur a été signalée au validateur.
     *
     * @param string $champ 'flyer' ou 'image' ; donne aussi la clé d'erreur « <champ>_url »
     */
    $importerImageParUrl = function (string $champ, string $url) use ($verif, $fichiers, $glo_mimes_images_acceptees): ?array
    {
        if (empty($url))
        {
            return null;
        }

        if (!empty($fichiers[$champ]['name']))
        {
            $verif->setErreur($champ . '_url', "Veuillez saisir un fichier OU une URL, pas les deux.");

            return null;
        }

        if (!$verif->validerURL($champ . '_url', $url))
        {
            return null;
        }

        $fetched = ImageUrlFetcher::fetch($url, $glo_mimes_images_acceptees);

        if ($fetched['error'] !== null)
        {
            $verif->setErreur($champ . '_url', $fetched['error']);

            return null;
        }

        return $fetched;
    };

    $fetched_flyer = $importerImageParUrl('flyer', $url_flyer);
    $fetched_image = $importerImageParUrl('image', $url_image);

    // at least debut or complement
    if (empty($champs['horaire_debut']) && empty($champs['horaire_complement']))
	{
		$verif->setErreur("horaire", "Veuillez indiquer l'horaire");
	}

	$verif->valider($champs['horaire_debut'], "horaire_debut", "texte", 1, 5, 0);

	if (!empty($champs['horaire_debut']) && !preg_match("/^[0-9]{1,2}:[0-9]{2}$/", (string) $champs['horaire_debut']))
	{
		$verif->setErreur('horaire_debut', "Le format de l'heure n'est pas correct, veuillez écrire en hh:mm");
	}

	$verif->valider($champs['horaire_fin'], "horaire_fin", "texte", 1, 5, 0);
	if (!empty($champs['horaire_fin']) && !preg_match("/^[0-9]{1,2}:[0-9]{2}$/", (string) $champs['horaire_fin']))
	{
		$verif->setErreur('horaire_debut', "Le format de l'heure n'est pas correct, veuillez écrire en hh:mm");
	}

	$verif->valider($champs['horaire_complement'], "horaire_complement", "texte", 1, 200, 0);
    $verif->valider($champs['prix'], "prix", "texte", 1, 100, 0);
	$verif->valider($champs['prelocations'], "prelocations", "texte", 1, 200, 0);

    // "E-mail à l'auteur" (issue #149) : ne pas mettre dans $champs, ce ne sont pas des colonnes de evenement
    $notif_motifs = [];
    if ($can_notify_auteur && isset($_POST['notif_motifs']) && is_array($_POST['notif_motifs']))
    {
        $notif_motifs = array_values(array_intersect($_POST['notif_motifs'], array_keys($glo_motifs_notification_auteur)));
    }
    $notif_message = $can_notify_auteur ? trim((string) ($_POST['notif_message'] ?? '')) : '';
    if ($can_notify_auteur)
    {
        $verif->valider($notif_message, "notif_message", "texte", 0, 2000, 0);
    }

    // public : email (required) and remark
    if (!$est_connecte)
    {
        $verif->valider($champs['user_email'], "email", "email", 4, 250, 1);
        $verif->valider($champs['description'], "description", "texte", 4, 10000, 0);
    }

    // valide : insert or update
    if ($verif->nbErreurs() === 0)
	{
		//creation/nettoyage des valeurs à insérer dans la table

		$champs['idPersonne'] = $_SESSION['SidPersonne'] ?? 0;
		$champs['dateEvenement'] = DateHelper::appToIso($champs['dateEvenement']);

		if ($champs['prix'] == "0")
		{
			$champs['prix'] = "entrée libre";
		}

		// TODO : transposer également le protocole
		if ($champs['urlLieu'] != "" && !preg_match("/^https?:\/\//", (string) $champs['urlLieu']))
		{
			$champs['urlLieu'] = "http://".$champs['urlLieu'];
		}

        // 23:59, 00:00, 06:00
		// conversion de l'heure indiquée en datetime
        $date_next_day = DateHelper::isoToNextDay($date_iso);
        // les événements sans heure de début sont relegués au lendemain à 06:00:01 afin qu'ils soient affichés (artificiellement) en dernier dans l'agenda
		if (!empty($champs['horaire_debut']))
		{
			$tab_horaire_debut = explode(":", (string) $champs['horaire_debut']);
			$sec_horaire_debut = (int) $tab_horaire_debut[0] * 3600 + (int) $tab_horaire_debut[1] * 60;

            // si nb de secondes après minuit < 6h augmenter la date au lendemain, sinon rester au jour indiqué
            // entre 0h et 6h la date sera le lendemain, entre 6h et 23:59 ça reste à la date de dateEvenement
			if ($sec_horaire_debut >= 0 && $sec_horaire_debut <= 21_600) // 6h
			{
				$champs['horaire_debut'] = $date_next_day." ".$champs['horaire_debut'].":00";
			}
			else
			{
				$champs['horaire_debut'] = $champs['dateEvenement']." ".$champs['horaire_debut'].":00";
			}
		}
		else
		{
			$champs['horaire_debut'] = $date_next_day." 06:00:01";
		}

		if (!empty($champs['horaire_fin']))
		{
			$tab_horaire_fin = explode(":", (string) $champs['horaire_fin']);
			$sec_horaire_fin = (int) $tab_horaire_fin[0] * 3600 + (int) $tab_horaire_fin[1] * 60;

			if ($sec_horaire_fin >= 0 && $sec_horaire_fin <= 21_600) // TODO: || (!empty($champs['horaire_debut']) && date($champs['horaire_debut']) == $date_next_day)
			{
				$champs['horaire_fin'] = $date_next_day." ".$champs['horaire_fin'].":00";
			}
			else
			{
				$champs['horaire_fin'] = $champs['dateEvenement']." ".$champs['horaire_fin'].":00";
			}
		}
		else
		{
			$champs['horaire_fin'] = $date_next_day." 06:00:01";
		}

		//dedoublonne la liste des orgas et nettoye avec string -> int
		if (isset($_POST['organisateurs']) && is_array($_POST['organisateurs']) && count($champs['organisateurs']) > 0)
		{
			$champs['organisateurs'] = array_map('intval', array_unique($champs['organisateurs']));
		}


		// pour remplir les champs nomLieu, adresse, etc. de la table evenement
		if (!empty($champs['idLieu']))
		{
			// cast et non sanitize() : hors quotes, l'échappement ne bloque pas « 1 OR … »
			$sql_lieu = "SELECT nom, adresse, quartier, localite_id, region, URL FROM lieu WHERE idLieu=".(int) $champs['idLieu'];
			$req_lieu = $connector->query($sql_lieu);
			$tab_lieu = $connector->fetchArray($req_lieu);
			$champs['nomLieu'] = $tab_lieu['nom'];
			$champs['adresse'] = $tab_lieu['adresse'];
			$champs['quartier'] = $tab_lieu['quartier'];
			$champs['localite_id'] = $tab_lieu['localite_id'];
			$champs['region'] = $tab_lieu['region'];
			$champs['urlLieu'] = $tab_lieu['URL'];
		}
        elseif (!empty($champs['localite_id']))
        {
            $loc_qua = explode("_", (string) $champs['localite_id']);
            if (count($loc_qua) > 1)
            {
                $champs['localite_id'] =  $loc_qua[0];
                $champs['quartier'] = $loc_qua[1];
                $champs['region'] = 'ge';
            }
            else
            {
                $champs['quartier'] = '';

                // Nyon est vaudoise, mais rattachée à l'agenda genevois
                if ($champs['localite_id'] == 529 )
                {
                    $champs['region'] = 'ge';
                }
                else
                {
                    // La région suit le canton de la localité choisie — la France ('rf') et
                    // « Autre » ('hs') y comprises, depuis qu'elles sont des localités.
                    // cast et non sanitize() : hors quotes, l'échappement ne bloque pas « 1 OR … »
                    $sql_lieu = "SELECT canton FROM localite WHERE id=".(int) $champs['localite_id'];
                    $req_lieu = $connector->query($sql_lieu);
                    $tab_lieu = $connector->fetchArray($req_lieu);
                    $champs['region'] = $tab_lieu['canton'];
                }
            }
        }

        if (!$est_connecte)
        {
            $champs['statut'] = 'propose';
        }

		/*
		 * Préparation du nom du flyer et de l'image, par ex 3047_2006-02-20.jpg
		 * en cas d'ajout, obtention de l'ID du nouvel événement
		 */
		if (!empty($fichiers['flyer']['name']) || !empty($fichiers['image']['name']) || $fetched_flyer !== null || $fetched_image !== null)
		{

			$nouv_idE = 0;

			if (isset($get['idE']))
			{
				$nouv_idE = $get['idE'];
			}
			else
			{
				$req_maxId = $connector->query("SELECT MAX(idEvenement) AS max_idE FROM evenement");
				$maxId = $connector->fetchArray($req_maxId);
				$nouv_idE = $maxId['max_idE'] + 1;
			}

			// Extension d'une image récupérée par URL, d'après son type MIME : elle n'a pas
			// de nom de fichier d'origine dont on pourrait la déduire.
			$extensionPourMime = fn (string $mime): string => match($mime) {
				'image/png', 'image/x-png' => '.png',
				'image/gif' => '.gif',
				'image/webp' => '.webp',
				default => '.jpg',
			};

			// Même schéma de nom pour les deux, l'image intercalant « _img » avant l'extension
			foreach (['flyer' => ['', $fetched_flyer], 'image' => ['_img', $fetched_image]] as $champ_img => [$suffixe, $fetched])
			{
				$prefixe = $nouv_idE."_".$champs['dateEvenement'].$suffixe;

				if (!empty($fichiers[$champ_img]['name']))
				{
					$champs[$champ_img] = $prefixe.mb_strrchr((string) $fichiers[$champ_img]['name'], '.');
				}
				elseif ($fetched !== null)
				{
					$champs[$champ_img] = $prefixe.$extensionPourMime($fetched['mime']);
				}
			}
		}

		if ($get['action'] == 'insert')
		{

			$sql_insert_attributs = "";
			$sql_insert_valeurs = "";

			foreach ($champs as $c => $v)
			{
				if ($c != 'organisateurs') {
					$sql_insert_attributs .= $c.", ";
					$sql_insert_valeurs .= "'".$connector->sanitize($v)."', ";
				}
			}

			$sql_insert_attributs .= "dateAjout, date_derniere_modif";
			$sql_insert_valeurs .= "'".date("Y-m-d H:i:s")."', '".date("Y-m-d H:i:s")."'";

			$sql_insert =  "INSERT INTO evenement (".$sql_insert_attributs.") VALUES (".$sql_insert_valeurs.")";

			if ($connector->query($sql_insert))
			{
				$req_id = $connector->getInsertId();

				$_SESSION['evenement-edit_flash_msg'] = "L'événement a été créé. <a href='/index.php?courant=".urlencode((string) $champs['dateEvenement'])."#event-".(int)$req_id."'>Voir dans l'agenda</a>";

                if (!$est_connecte)
                {
                    $_SESSION['evenement-edit_flash_msg'] = "Merci pour votre proposition. Nous allons l'examiner et vous aurez une réponse dès qu'elle sera traitée (cela peut prendre quelques jours)";
                    $subject = "Nouvelle proposition d'événement : \"".$champs['titre']."\" le ".DateHelper::isoToFr($champs['dateEvenement'], 'annee', html: false)." à ".$champs['nomLieu'];
                    $contenu_message = "Merci de vérifier cet événement et l'accepter (statut : publié) ou le refuser (status : dépublié) : ";
                    $contenu_message .= $site_full_url."event/evenement.php?idE=".(int)$req_id;
                    $contenu_message .= "\n\n";
                    $contenu_message .= "Par : ".$champs['user_email'];
                    $contenu_message .= "\n\nRemarque :\n".$champs['remarque'];

                    $mailer = new Mailing();
                    $mailer->toAdmin($subject, $contenu_message, $champs['user_email']);
                }

				$show_form = false;
            } else {
				HtmlShrink::msgErreur("La requête INSERT dans 'evenement' a échoué");
			}
		}
		elseif ($get['action'] == 'update')
		{

			/**
			 * Efface du disque le fichier que l'UPDATE s'apprête à remplacer ou à vider.
			 * À appeler tant que la base porte encore son nom, sinon il devient introuvable.
			 *
			 * @param string $colonne Nom de colonne littéral ('flyer' ou 'image'), jamais une saisie
			 */
			$supprimerAncienFichier = function (string $colonne) use ($connector, $get): void
			{
				$req = $connector->query("SELECT $colonne FROM evenement WHERE idEvenement=" . (int) $get['idE']);

				if (!$req)
				{
					return;
				}

				$ancien = $connector->fetchArray($req);

				//si un ancien fichier a été effectivement trouvé, suppression des fichiers
				if (!empty($ancien[$colonne]))
				{
					Evenement::rmImageAndItsMiniature($ancien[$colonne]);
				}
			};

			$sql_flyer = ""; // champ SQL pour le flyer

			//si un nouveau flyer a été uploadé, suppression de l'ancien fichier
			if (!empty($champs['flyer']))
			{
				$sql_flyer = ", flyer='".$champs['flyer']."'";
				$supprimerAncienFichier('flyer');
			}

			//si le champ "supprimer le flyer" est coché sans qu'un nouveau flyer soit remplacant
			if (!empty($supprimer['flyer']))
			{
				$sql_flyer = ", flyer=''";
				$supprimerAncienFichier('flyer');
			} //if supprimer flyer

			$sql_image = ""; // champ SQL pour l'image

			//si une nouvelle image a été uploadée, suppression de l'ancien fichier
			if (!empty($champs['image']))
			{
				$sql_image = ", image='".$champs['image']."'";
				$supprimerAncienFichier('image');
			}

			//si le champ "supprimer l'image" est coché sans qu'une nouvelle image soit remplaçante
			if (!empty($supprimer['image']))
			{
				$sql_image = ", image=''";
				$supprimerAncienFichier('image');
			} //if supprimer image

			$sql_update = "UPDATE evenement SET ";

			foreach ($champs as $c => $v)
			{
				if ($c != "idPersonne" && $c != 'organisateurs') {
					$sql_update .= $c."='".$connector->sanitize($v)."', ";
				}
			}


			$sql_update .= "date_derniere_modif='".date("Y-m-d H:i:s")."'";
			$sql_update .= $sql_flyer.$sql_image." WHERE idEvenement=".(int)$get['idE'];

			$req_update = $connector->query($sql_update);

			/*
			* MAJ réussie, message OK, et RAZ de l'action
			*/
			if ($req_update)
			{

				$sql = "DELETE FROM evenement_organisateur WHERE idEvenement=" . (int) $get['idE'];
                $req = $connector->query($sql);
				$req_id = $get['idE'];

                $confirmation_flash_msg = '';

                // acceptation d'un even
                if ($tab_even_lieu['statut'] == 'propose')
                {
                    if ($champs['statut'] == 'actif')
                    {
                        $subject = "Votre événement \"".$champs['titre']."\" sur La décadanse a été publié";
                        $contenu_message = "Bonjour,\n\n";
                        $contenu_message .= "Merci de nous avoir proposé un événement, nous venons de le publier : ";
                        $contenu_message .= $site_full_url."event/evenement.php?idE=". (int)$req_id;
                        $contenu_message .= "\n\n";
                        $contenu_message .= "La décadanse";

                        // le message flash est rendu sans échappement (il contient du HTML voulu) :
                        // l'e-mail, qui n'est validé que pour le formulaire public, est échappé ici
                        $confirmation_flash_msg = " Un email de confirmation a été envoyé à " . sanitizeForHtml($champs['user_email']);

                        $mailer = new Mailing();
                        $mailer->toUser($champs['user_email'], $subject, $contenu_message);
                    }
                }

                // E-mail à l'auteur (issue #149)
                if ($can_notify_auteur && (!empty($notif_motifs) || $notif_message !== ''))
                {
                    $notifier = new AuteurNotifier($tplEngine, new Mailing());
                    $sent = $notifier->notify(
                        $original_author_email,
                        $original_author_name,
                        $champs['titre'],
                        $champs['dateEvenement'],
                        $tab_even_lieu['dateAjout'] ?? '',
                        $site_full_url . "event/evenement.php?idE=" . (int) $req_id,
                        $_SESSION['Semail'] ?? EMAIL_ADMIN,
                        $_SESSION['user'] ?? '',
                        $notif_motifs,
                        $notif_message,
                        $glo_motifs_notification_auteur
                    );
                    if ($sent)
                    {
                        $confirmation_flash_msg .= " Un message a été envoyé à l'auteur de l'événement.";
                    }
                }

                $_SESSION['evenement-edit_flash_msg'] = "L'événement a été modifié.$confirmation_flash_msg<br><a href='/index.php?courant=".urlencode((string) $champs['dateEvenement'])."#event-".(int) $req_id."'>Voir dans l'agenda</a>";

				$get['action'] = 'editer';

				$show_form = false;
            }
			else
			{
				HtmlShrink::msgErreur("La requête UPDATE de la table evenement a échoué");
			}

		} //if get_action = 'insert' ou 'update'

		/*
		* TRAITEMENT DE L'IMAGE UPLOADEE
		*/

		// TODO : processImage() renvoie ses erreurs, mais personne ne les lit — l'événement
		// est enregistré avec un nom de fichier dont l'image a pu ne pas être écrite.
		// (Le garde-fou qui vidait le champ testait $msg2, une variable qui n'a jamais existé.)
		foreach (['flyer' => $fetched_flyer, 'image' => $fetched_image] as $champ_img => $fetched)
		{
			// fichier envoyé par le formulaire
			if (!empty($fichiers[$champ_img]['name']))
			{
				$imD2 = new ImageDriver2("evenement");
				$imD2->processImage($_FILES[$champ_img], $champs[$champ_img], 600, 600);
				$imD2->processImage($_FILES[$champ_img], "s_" . $champs[$champ_img], 120, 190, '', 0);
			}

			// image récupérée par URL : ImageDriver2 travaille sur un chemin, pas sur des octets
			if ($fetched !== null && !empty($champs[$champ_img]))
			{
				$tmp = tempnam(sys_get_temp_dir(), 'ldd_img_');
				try {
					file_put_contents($tmp, $fetched['data']);
					$imD2 = new ImageDriver2("evenement");
					$imD2->processImageFromPath($tmp, $champs[$champ_img], 600, 600);
					$imD2->processImageFromPath($tmp, "s_" . $champs[$champ_img], 120, 190, '', 0);
				} finally {
					@unlink($tmp);
				}
			}
		}

		if (isset($_POST['organisateurs']) && is_array($champs['organisateurs']))
		{
			foreach ($champs['organisateurs'] as $no => $idOrg)
			{
				if (!empty($idOrg))
                {
                    $sql = "INSERT INTO evenement_organisateur (idEvenement, idOrganisateur) VALUES (" . (int) $req_id . ", " . (int) $idOrg . ")";
                    $connector->query($sql);
				}
			}
		}

		// $req_id porte l'id de l'événement traité dans les deux cas : celui que l'INSERT
		// vient de créer, ou celui que l'UPDATE tenait de l'URL. C'est déjà lui qui sert
		// à construire la redirection ci-dessous.
        $logger->info('[evenement-edit]', ['action' => $get['action'], 'titre' => $champs['titre'], 'date' => $champs['dateEvenement'], 'genre' => $champs['genre'], 'idLieu' => (int) $champs['idLieu'], 'lieu' => $champs['nomLieu'], 'idE' => (int) $req_id, 'idP' => (int) ($_SESSION['SidPersonne'] ?? 0)]);

        if ($est_connecte)
        {
            header("Location: /event/evenement.php?idE=" . (int) $req_id);
            die();
        }
        else
        {
            header("Location: index.php"); die();
        }

	/*
	 * En cas d'erreur, réinitialisation des images pour qu'elles se réaffichent dans le formulaire
	 */
	}
	elseif ($get['action'] == 'update')
	{

		if ($affIm = $connector->fetchArray($connector->query("SELECT flyer, image FROM evenement WHERE idEvenement =" . (int) $get['idE'])))
        {
			$champs['flyer'] = $affIm['flyer'];
			$champs['image'] = $affIm['image'];
		}

	} //if erreur == 0
} // if POST != ""


/*
* Les deux modes du formulaire, figés pour tout l'affichage.
*
* À calculer ici et pas plus haut : le traitement du POST repasse $get['action'] de
* 'update' à 'editer' après un enregistrement réussi. Une fois le POST traité, la valeur
* ne bouge plus.
*/
$est_edition = in_array($get['action'], ['editer', 'update'], true);
$est_ajout   = in_array($get['action'], ['ajouter', 'insert'], true);

// Les lieux et localités fribourgeois ('fr' ; la France, elle, est codée 'rf' — cf. $glo_regions)
// ne sont plus proposés à l'ajout, mais restent affichables en édition pour ne pas vider
// le select d'un événement déjà rattaché à l'un d'eux. Le select des localités applique le même
// filtre, en passant $est_ajout à Localite::getOptionsHtml().
// Fragment littéral, sans donnée saisie : il est concaténé dans la requête du formulaire.
$sql_lieu_excl_fr = $est_ajout ? " AND region != 'fr' " : '';

$page_titre = "Proposer un événement";
$page_description = "Proposer un événement pour l'agenda";

if ($est_connecte)
{
    $page_titre = "ajouter/modifier un événement";
    $page_description = "Formulaire d'ajout/modification d'un événement dans l'agenda";
}

$extra_css = ["formulaires"];

include("_header.inc.php");
?>

<main id="contenu" class="colonne evenement-edit">

<?php
// first display or redisplay (failed validation)
if ($show_form)
{
    $jour = "";
    $mois = "";
    $annee = "";

    $aff_titre = '';
    $aff_actions = '';

    /*
    * POUR EDITER UN EVENEMENT, ALLER CHERCHER SES VALEURS DANS LA BASE
    * Récupération des valeurs de la table et remplissage des champs pour le formulaire
    * Affichage d'un menu d'actions pour l'admin
    */
    if ($get['action'] == 'editer' && isset($get['idE']))
    {
        if ($_SESSION['Sgroupe'] <= UserLevel::ACTOR) {
            $req_even = $connector->query("SELECT idLieu, idSalle, idPersonne, statut, titre, genre,
            dateEvenement, nomLieu, adresse, urlLieu, quartier, localite_id, region, description, flyer, image, prix, price_type, horaire_debut, horaire_fin, horaire_complement, ref, prelocations, remarque, user_email, dateAjout FROM evenement WHERE idEvenement =" . (int) $get['idE']);

            if ($affEven = $connector->fetchArray($req_even))
            {
                foreach($affEven as $c => $v)
                {
                    $champs[$c] = $v;
                }

                $champs['dateEvenement'] = DateHelper::isoToApp($champs['dateEvenement']);
                $champs['horaire_debut'] = EvenementRenderer::datetimeToHhMm($affEven['horaire_debut'], $affEven['dateEvenement']);
                $champs['horaire_fin'] = EvenementRenderer::datetimeToHhMm($affEven['horaire_fin'], $affEven['dateEvenement']);
            }
            else
            {
                HtmlShrink::msgErreur("La requête select a échoué");
                exit;
            }
        }
        else
        {
            HtmlShrink::msgErreur("Vous n'avez pas les droits pour éditer un événement");
            exit;
        } // if GET action

    } // action editer

    $aff_titre .= !$est_connecte ? 'Proposer un événement':'Ajouter un événement';
    $act = 'insert';

    if ($est_edition)
    {
        $aff_titre = 'Modifier <a style="font-size:0.7em" href="/event/evenement.php?idE=' . (int) $get['idE'] . '">' . sanitizeForHtml($champs['titre']) . '</a>';
        $act = "update&amp;idE=".(int)$get['idE'];
    }
    ?>
    <header id="entete_contenu">
        <h1><?php echo $aff_titre.$aff_actions; ?></h1>
        <div class="spacer"></div>
    </header>

    <?php
    if ($verif->nbErreurs() > 0)
    {
        $msg_err = "<strong>Il y a ".$verif->nbErreurs()." erreur(s)</strong>";
        if (!empty($fichiers['flyer']['name']) || !empty($fichiers['image']['name'])) {
            $msg_err .= " <br>Pensez à resélectionner votre flyer ou image ci-dessous (ils ont été retirés du formulaire par sécurité)";
        }
        HtmlShrink::msgErreur($msg_err);
        if (!empty($verif->getHtmlErreur("global")))
        {
            echo $verif->getHtmlErreur("global");
        }
    }
    $_SESSION[$formTokenName] = bin2hex(random_bytes(32));
    ?>

    <form method="post" id="ajouter_editer" class="js-submit-freeze-wait" enctype="multipart/form-data" action="<?php echo basename(__FILE__) . "?action=" . $act ?>">

        <input type="text" name="name_as" value="" class="name_as" /><?php echo $verif->getHtmlErreur('name_as'); ?>
        <input type="hidden" name="<?php echo $formTokenName; ?>" value="<?php echo sanitizeForHtml($_SESSION[$formTokenName]); ?>">

        <?php if ($prefilled_from_user_defaults) { ?>
        <div class="guideForm">Certains champs sont préremplis depuis vos valeurs par défaut &middot; <a href="/user-edit.php?action=editer&amp;idP=<?php echo (int) $_SESSION['SidPersonne']; ?>">Modifier mes valeurs</a></div>
        <?php } ?>

        <div class="alert-warn">

            <?php if (!$est_edition) { ?>
            <h2>Avant de commencer :</h2>
            <?php } ?>

            <?php if (!$est_connecte) { ?>
                <p style="line-height: 1.6em;">Utilisez ce formulaire <strong>si vous n'avez pas déjà un compte sur La décadanse</strong>. L'événement sera publié (ou pas) après une validation de notre part dans les prochains jours.<br>Sinon, veuillez <a href="/user/login.php">vous connecter</a> pour ajouter votre événement.</p>
            <?php } ?>

            <p>Veillez svp à ce que votre événement n’est pas déjà présent dans l’<a href="index.php" target="_blank">agenda</a> et respecte notre <a href="/articles/charte-editoriale.php" target="_blank">charte&nbsp;éditoriale</a> (pas tous les types d'événements sont publiés)</p>

        </div>

        <?php if (!$est_edition) { ?>
            <?php // width:auto : les <p> du formulaire font 100% (formulaires.css), ce qui s'ajoutait ici aux marges et débordait du viewport ?>
            <p style="margin:1em;width:auto">
                Les événements annoncés sur La décadanse sont également visibles sur <a href="https://epic-magazine.ch/" target="_blank">EPIC-Magazine</a></p>
            <details style="margin-left:15px;margin-top:-11px">
            <summary>Détails</summary>
                <ul><li><b>EPIC-Magazine</b> - webmagazine qui met en avant la culture locale et émergente à Genève et dans ses environs&nbsp;: intégration de l'agenda dans la <a href="https://epic-magazine.ch/lieux/" target="_blank">page Cartographie</a>
                    </li>
                </ul>
            </details>
            <br>
        <?php } ?>

        <?php if (!$est_connecte || !empty($champs['user_email'])) { ?>
        <fieldset>
            <p>
                <label for="user_email">Votre email*</label>
                <input type="email" id="user_email" name="user_email" value="<?php echo sanitizeForHtml($champs['user_email']) ?>"
                       required size="30" <?php echo ($est_connecte && !empty($champs['user_email'])) ? 'readonly class="readonly" ': ''; ?> maxlength="120">
            </p>
            <?php if (!$est_connecte) { ?>
            <p>Déjà un compte ? <a href="/user/login.php">Connectez-vous</a>, ajoutez votre événement et il sera immédiatement publié</p>
            <?php } ?>
        </fieldset>
    <?php } ?>

    <h2 style="margin:20px 0 5px 0;">L’événement</h2>

    <p style="margin:5px 0;">* indique un champ obligatoire</p>

    <?php if ($est_edition) {?>
    <p class="piedForm">
        <input type="hidden" name="formulaire" value="ok" />
        <input type="submit" value="Enregistrer" class="submit submit-big" />
    </p>
    <?php } ?>

    <?php
    if (($est_edition) && isset($get['idE']))
    {
    ?>

    <fieldset style="padding-top:0.1em">

        <legend>Statut de l’événement</legend>
        <ul class="radio mobile-vertical" style="display:inline-block;margin-left:0;margin-top:0.7em;margin-bottom:0.7em">
        <?php
        $statuts = ['propose' => '<strong>proposé</strong> (non visible)', 'actif' => '<strong>publié</strong>', 'complet' => '<strong>publié</strong> marqué <span class="even-statut-label statut-complet">COMPLET</span>', 'annule' => '<strong>publié</strong> marqué <span class="even-statut-label statut-annule">ANNULÉ</span>', 'inactif' => '<strong>dépublié</strong> (non visible)'];
        foreach ($statuts as $s => $n)
        {
            if ($s === 'propose' && ($_SESSION['Sgroupe'] > 6 || (!empty($champs['user_email']) && $champs['statut'] != 'propose')))
                continue;

            $coche = '';
            if (strcmp($s, (string) $champs['statut']) == 0)
            {
                $coche = 'checked="checked"';
            }
            $ligne = $s === 'propose' ? ' style="display:block"' : '';
            echo '<li class="listehoriz"'.$ligne.'>
            <input type="radio" name="statut" value="'.$s.'" '.$coche.' id="statut_'.$s.'" title="statut de l\'événement" class="radio_horiz"
        ';
        echo '/>
            <label class="continu" for="statut_'.$s.'">'.$n.'</label></li>';
        }
        ?>
        </ul>
        <?php
        echo $verif->getHtmlErreur("statut");
        ?>
    </fieldset>

    <?php
    }
    else
    {
    ?>
        <input type="hidden" name="statut" value="actif" id="statut_actif" />
    <?php
    }
    ?>

    <fieldset>

        <legend>Catégorie*</legend>

        <ul class="radio mobile-vertical" style="font-size: 1.15em;">
        <?php
        foreach ($glo_tab_genre as $k => $v)
        {
            $coche = '';
            if (strcmp((string) $k, (string) $champs['genre']) == 0)
            {
                $coche = 'checked="checked"';
            }

            $required = '';
            if ($k === 'fête')
            {
                $required = ' required';
                $v = '<span class="tooltip">fêtes <i class="fa fa-info-circle" aria-hidden="true"></i>
<span class="tooltiptext">Inclut les soirées, les concerts, etc.</span></span>';
            }
            echo '<li class="listehoriz"><input type="radio" name="genre" value="'.$k.'" '.$coche.' id="genre_'.$k.'"  class="radio_horiz" '.$required.' /><label class="continu" for="genre_'.$k.'">'.$v.'</label></li>';

        }
        ?>
        </ul>
        <div class="guideChamp" style="margin-top:1em">Merci de choisir la catégorie pertinente; si vous n'êtes pas sûr, vous pouvez <a href="/misc/contacteznous.php" target="_blank">nous demander</a>
        </div>
        <?php echo $verif->getHtmlErreur("genre"); ?>
    </fieldset>

    <fieldset>
        <legend>Date & horaire</legend>

            <?php
            // POC : calendrier "always visible" (Zebra_DatePicker) sous le champ date, réservé au niveau SUPERADMIN
            $poc_calendrier_toujours_visible = $est_connecte && (int) $_SESSION['Sgroupe'] === UserLevel::SUPERADMIN;
            ?>
            <div style="display: flex; align-items: flex-start; gap: 6px; flex-wrap: wrap;">
                <label for="dateEvenement" style="white-space: nowrap;">Date*</label>
                <div style="display: flex; flex-direction: column; align-items: flex-start;">
                    <input type="text" name="dateEvenement" id="dateEvenement" size="9" value="<?php echo sanitizeForHtml($champs['dateEvenement']); ?>" class="datepicker<?php echo $poc_calendrier_toujours_visible ? ' datepicker-always-visible' : ''; ?>" placeholder="jj.mm.aaaa" required />
                    <?php
                    echo $verif->getHtmlErreur('dateEvenement');
                    if ($poc_calendrier_toujours_visible) {
                    // le calendrier est positionné en absolu par la librairie ; on réserve sa place dans le flux
                    // (au lieu de recouvrir les champs suivants) via position:relative, la hauteur exacte étant
                    // ajustée dynamiquement en JS (forms.js) selon le nombre de semaines du mois et la largeur d'écran
                    ?><div id="calendarDiv" style="position: relative; margin-top: 6px;"></div>
                    <?php } ?>
                </div>
            <?php if ($est_ajout) { ?>

            <div class="guideChamp" style="margin-top:-0.2em">
                <?php if ($est_connecte) { ?>Si l’événement se répète sur plusieurs dates, vous pouvez l’ajouter à d'autres dates avec le bouton <b>Copier</b>, à la page suivante
                <?php } else { ?>
                Si l’événement se répète sur plusieurs dates, merci de nous indiquer précisément les jours et horaires dans le <a href="#remarque">champ Remarque</a> ci-dessous.
        <?php } ?>
            </div>
            <div class="spacer"></div>
            <?php } ?>
            </div>

            <p style="margin:5px 0">
                <label for="horaire_debut" style="display:inline-block"><span class="tooltip">Début <i class="fa fa-info-circle" aria-hidden="true"></i>
    <span class="tooltiptext">Jusqu’à 06:00, le début sera considéré faisant partie du jour de l’événement</span></span> </label>
                <input type="time" name="horaire_debut" id="horaire_debut" size="5" value="<?php echo sanitizeForHtml($champs['horaire_debut']) ?>" />

                <label for="horaire_fin" class="continu">Fin</label>
                <input type="time" name="horaire_fin" id="horaire_fin" size="5" value="<?php echo sanitizeForHtml($champs['horaire_fin']) ?>" />

                <?php
                echo $verif->getHtmlErreur('horaire_debut');
                echo $verif->getHtmlErreur('horaire_fin');
                ?>
            </p>

            <div class="guideChamp" style="margin-top:0">Mettez si possible l'heure de fin, pour un meilleur fonctionnement de l'agenda</div>



            <div style="margin-top:1.3em">
                <label for="horaire_complement">Complément d'horaire</label>
                <input type="text" name="horaire_complement" id="horaire_complement" size="60" maxlength="200" value="<?php echo sanitizeForHtml($champs['horaire_complement']) ?>" />
            <?php echo $verif->getHtmlErreur('horaire_complement'); ?>
            </div>

            <?php echo $verif->getHtmlErreur('horaire'); ?>
        </fieldset>

        <fieldset>

            <legend>Lieu*</legend>
            <p>
                <label for="idLieu"><strong>Nom du lieu :</strong></label>
                <select name="idLieu" id="idLieu" class="js-select2-options-with-style" title="Un lieu dans base de données de La décadanse" style="max-width:350px"  data-placeholder="">
                <?php
                //Menu des lieux actifs de la base
                echo "<option value=\"\"></option>";
                $req_lieux = $connector->query("
                SELECT lieu.idLieu, lieu.nom, COALESCE(localite.canton, '') AS canton
                FROM lieu
                LEFT JOIN localite ON lieu.localite_id = localite.id
                WHERE lieu.statut='actif' " . $sql_lieu_excl_fr . "
                ORDER BY
                  " . Localite::sqlOrdreCantons("COALESCE(localite.canton, '')") . ",
                  TRIM(LEADING 'L\'' FROM (TRIM(LEADING 'Les ' FROM (TRIM(LEADING 'La ' FROM (TRIM(LEADING 'Le ' FROM lieu.nom))))))) COLLATE utf8mb4_unicode_ci");

                // Toutes les salles en une requête, indexées par lieu : la boucle ci-dessous
                // en lançait une par lieu affiché.
                $salles_par_lieu = [];
                $req_salles = $connector->query("SELECT idSalle, idLieu, nom FROM salle WHERE status='actif' ORDER BY idLieu, idSalle");
                while ($salle_trouvee = $connector->fetchArray($req_salles))
                {
                    $salles_par_lieu[(int) $salle_trouvee['idLieu']][] = $salle_trouvee;
                }

                $current_canton = null;
                while ($lieuTrouve = $connector->fetchArray($req_lieux))
                {
                    $canton = $lieuTrouve['canton'];
                    if ($canton !== $current_canton) {
                        if ($current_canton !== null) echo '</optgroup>';
                        echo '<optgroup label="' . (Localite::CANTONS[$canton] ?? sanitizeForHtml($canton)) . '">';
                        $current_canton = $canton;
                    }

                    $nom_lieu = $lieuTrouve['nom'];

                    echo '<option  ';

                    if ($lieuTrouve['idLieu'] == $champs['idLieu'] || (!empty($get['idL']) && $lieuTrouve['idLieu'] == $get['idL']))
                    {
                        echo "selected=\"selected\" ";
                    }

                    echo "value=\"" . (int)$lieuTrouve['idLieu'] . "\">" . sanitizeForHtml($nom_lieu) . "</option>";

                    foreach ($salles_par_lieu[(int) $lieuTrouve['idLieu']] ?? [] as $tab_salle)
                    {
                        echo "<option ";
                        if ($champs['idSalle'] != 0 && $tab_salle['idSalle'] == $champs['idSalle'])
                        {
                            echo "selected=\"selected\" ";
                        }
                        echo " style=\"font-style:italic;color:#444;\" value=" . (int)$lieuTrouve['idLieu'] . "_" . (int)$tab_salle['idSalle'] . ">" . sanitizeForHtml($nom_lieu) . "&nbsp;– " . sanitizeForHtml($tab_salle['nom']) . "</option>";
                    }
                }
                if ($current_canton !== null) echo '</optgroup>';
                ?>
                </select>
                <!--<div class="guideChamp" style="font-size:0.9em"><span style="background:yellow">Nouveau :</span> tapez le nom du lieu dans le champ libre et accédez y plus rapidement</div>-->
                <?php
                echo $verif->getHtmlErreur("idLieu");
                echo $verif->getHtmlErreur("dejaPresent");
                ?>
            </p>

            <div id="evenement-lieu-pastrouve">

                <p style="width:auto;font-size: 1em;text-align: left;margin: 5px">Si et seulement si vous n'avez pas trouvé le lieu dans la liste ci-dessus, renseignez-le ici&nbsp;:</p>
                <div class="spacer"></div>
                <p>
                <?php
                $tab_nomLieu_label = ["for" => "nomLieu"];
                echo HtmlShrink::formLabel($tab_nomLieu_label, "Nom du lieu");
                echo $verif->getHtmlErreur("nomLieuIdentique");

                $tab_nomLieu = ["type" => "text", "name" => "nomLieu", "id" => "nomLieu", "size" => "35", "maxlength" => "60", "value" => ""];
            if (empty($champs['idLieu']))
                {
                    $tab_nomLieu['value'] = sanitizeForHtml($champs['nomLieu']);
                }
                echo HtmlShrink::formInput($tab_nomLieu);
                echo $verif->getHtmlErreur("nomLieu");
                ?>
                </p>

                <p>
                    <label for="adresse">Adresse</label>
                    <?php
                    echo $verif->getHtmlErreur("adresseIdentique");
                    ?>

                    <input type="text" name="adresse" id="adresse" size="40" maxlength="100" title="rue, no" value="<?php if (empty($champs['idLieu'])) { echo sanitizeForHtml($champs['adresse']); } ?>"  />
                <?php
                echo $verif->getHtmlErreur("adresse");
                echo $verif->getHtmlErreur("doublonLieux");

                //echo "localite_id : ".$champs['localite_id'].", quartier : ".$champs['quartier'];
                ?>
            </p>
            <p>
                <label for="localite">Localité/quartier</label>&nbsp;<select name="localite_id" id="localite" class="js-select2-options-with-style" data-placeholder="" style="max-width:300px;">
                    <?php
                    // Le lieu choisi dans la liste porte déjà sa localité : comme le nom et
                    // l'adresse saisis à la main, le select reste alors vide. Après une erreur
                    // de saisie en revanche, on réaffiche le choix posté.
                    $localite_selectionnee = (isset($_POST['localite_id']) || empty($champs['idLieu'])) ? $champs['localite_id'] : '';
                    echo Localite::getOptionsHtml($localite_selectionnee, $champs['quartier'], $est_ajout);
                    ?>
                </select>
        <?php
        echo $verif->getHtmlErreur("localite_id");
        echo Localite::getAideChoixHtml();
        ?>
        </p>

        <p>
            <label for="urlLieu">Site web du lieu</label>
            <input type="text" name="urlLieu" id="urlLieu" size="40" maxlength="80" title="URL du lieu" value="<?php if (empty($champs['idLieu'])) { echo sanitizeForHtml($champs['urlLieu']); } ?>" />
        <?php
        echo $verif->getHtmlErreur("urlLieu");
        ?>
        </p>
        </div>
    </fieldset>

    <fieldset>

        <legend>L’événement</legend>

        <p>
            <label for="titre">Titre*</label>
            <input type="text" name="titre" id="titre" maxlength="80" value="<?php echo sanitizeForHtml($champs['titre']) ?>" required />
            <?php echo $verif->getHtmlErreur("titre"); ?>
        </p>

        <p>
            <label for="description">Description</label>
            <textarea name="description" id="description" rows="20"><?php echo sanitizeForHtml($champs['description']) ?></textarea>
            <?php echo $verif->getHtmlErreur('description'); ?>
        </p>
    </fieldset>

    <fieldset id="references">

        <legend>Références</legend>
        <p>
            <label for="ref">Sites web</label>
            <?php // pas de saut de ligne entre la balise ouvrante et la valeur : un textarea rend son contenu littéralement ?>
            <textarea name="ref" id="ref" rows="3" maxlength="1000" placeholder="https://exemple.ch/concert&#10;https://facebook.com/events/123"><?php echo sanitizeForHtml(RefList::toTextarea($champs['ref'])); ?></textarea>
        </p>
        <div class="guideChamp">Site de l’événement, de l’organisateur (s’il n’est pas présent ci-dessous), de la page Facebook... Une URL par ligne.</div>
            <?php
            echo $verif->getHtmlErreur('ref');
            ?>
            <div class="spacer"></div>

        <?php
        $tab_organisateurs_even = [];

        // À la création, les organisateurs par défaut du profil alimentent la même variable que les
        // organisateurs d'un événement existant : la boucle d'options plus bas les sélectionne sans
        // rien changer. ?idO= reste prioritaire.
        if ($get['action'] == "ajouter" && empty($get['idO']))
        {
            $tab_organisateurs_even = $ev_user_defaults['idOrganisateurs'];
        }

        if ($est_edition)
        {

            $sql = "SELECT organisateur.idOrganisateur, nom
        FROM organisateur, evenement_organisateur
        WHERE evenement_organisateur.idEvenement=".(int)$get['idE']." AND
         organisateur.idOrganisateur=evenement_organisateur.idOrganisateur
         ORDER BY date_ajout DESC";

         $req = $connector->query($sql);

            if ($connector->getNumRows($req))
            {
                while ($tab = $connector->fetchArray($req))
                {
                    $tab_organisateurs_even[] = $tab['idOrganisateur'];
                }
            }

        }
        ?><?php
        echo $verif->getHtmlErreur("doublon_organisateur");
        ?>
        <p>
            <label for="organisateurs">Organisateur(s) de l’événement</label>
                <select name="organisateurs[]" id="organisateurs" data-placeholder="Tapez les noms des organisateurs" class="js-select2-options-with-complement" multiple >
                    <?php
                    //echo "<option value=\"\">&nbsp;</option>";
                        $req = $connector->query("
        SELECT idOrganisateur, nom, URL FROM organisateur WHERE statut='actif' ORDER BY TRIM(LEADING 'L\'' FROM (TRIM(LEADING 'Les ' FROM (TRIM(LEADING 'La ' FROM (TRIM(LEADING 'Le ' FROM nom))))))) COLLATE utf8mb4_unicode_ci"
    );

        while ($tab = $connector->fetchArray($req))
        {
        ?><option data-nom="<?php echo sanitizeForHtml($tab['nom']); ?>" data-complement="<?php echo sanitizeForHtml($tab['URL']); ?>"
        <?php if ((isset($_POST['organisateurs']) && in_array($tab['idOrganisateur'], $_POST['organisateurs'])) || in_array($tab['idOrganisateur'], $tab_organisateurs_even) || (!empty($get['idO']) && $get['idO'] == $tab['idOrganisateur']))
            {
                echo 'selected="selected" ';
            }
            ?>
            value="<?= (int) $tab['idOrganisateur'] ?>"><?= sanitizeForHtml($tab['nom']) ?></option>
        <?php
        }
        ?>
        </select>
        <div class="guideChamp">L’événement figurera dans la page de ces <a href="/organisateur/organisateurs.php" target="_blank" rel="external">organisateurs</a>. <!--Si vous souhaitez que votre organisation soit listée, <a href="/misc/contacteznous.php?pre=req-orga" target='_blank' rel="external">demandez-nous</a> (avec des infos : texte, liens...)</div>-->
        </p>
    </fieldset>

    <fieldset>
            <legend>Entrée</legend>

            <div id="prix-precisions" style="display:block">
                <p>
                <label for="prix">Prix</label>
                    <input type="text" name="prix" id="prix" size="45" maxlength="100" value="<?php echo sanitizeForHtml($champs['prix']) ?>" />
                    <?php
                echo $verif->getHtmlErreur('prix');
                ?>
            </p>

            <div class="guideChamp">Vous pouvez mettre juste un <code>0</code> si l'entrée est libre.</div>

                <p>
                <label for="prelocations">Prélocations</label>
                    <input type="text" name="prelocations" id="prelocations" size="70" maxlength="200" value="<?php echo sanitizeForHtml($champs['prelocations']) ?>" />
                    <?php
                echo $verif->getHtmlErreur('prelocations');
                ?>
            </p>
        </div>
    </fieldset>

    <fieldset>
        <legend>Images</legend>

        <div style="margin-left: 0.8em;margin-bottom:1.2em;">Formats JPEG, PNG, GIF ou WebP; max. 2 Mo</div>

        <p style="margin-left: 0.8em;margin-bottom:1.2em;font-weight: bold">Affiche/flyer</p>

        <p>
            <label for="flyer">Envoyer</label>
            <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo UPLOAD_MAX_FILESIZE ?>" /> <!-- 2 Mo -->
            <input type="file" name="flyer" id="flyer" class="js-file-upload-size-max" size="25" accept="image/jpeg,image/pjpeg,image/png,image/x-png,image/gif,image/webp" class="fichier" />
            <?php if ($verif->nbErreurs() > 0 && !empty($fichiers['flyer']['name'])): ?>
                <div class="msg">Le fichier sélectionné a été retiré du formulaire par le navigateur (sécurité). Veuillez le sélectionner à nouveau.</div>
            <?php endif; ?>

            <?php if ($can_import_image_url): ?>
                <div class="clear"></div>
                <label for="flyer_url" class="ou-separateur">ou coller une URL</label>
                <input type="url" name="flyer_url" id="flyer_url" value="<?= sanitizeForHtml($url_flyer) ?>" placeholder="https://…" maxlength="2000" size="50">
                <?php echo $verif->getHtmlErreur('flyer_url'); ?>
            <?php endif; ?>

            <?php echo $verif->getHtmlErreur("flyer"); ?>

            <?php
            // affichage du flyer précédent, et du bouton pour le supprimer
            // (affiché même si le champ est en erreur : sinon on ne pourrait plus supprimer
            // l'ancien flyer tant que le nouveau est refusé)
            if (isset($get['idE']) && !empty($champs['flyer']))
            {
                $apercu_flyer = sanitizeForHtml($assets->get(Evenement::getAssetPath(Evenement::getFilePath($champs['flyer']))));
            ?>
                <div class="supImg">
                    <a href="<?= $apercu_flyer ?>" class="magnific-popup" target="_blank">
                        <img src="<?= $apercu_flyer ?>" alt="Flyer de cet événement" />
                    </a>
                    <div>
                        <label for="sup_flyer" class="continu">Supprimer</label><input type="checkbox" name="sup_flyer" id="sup_flyer" value="flyer" class="checkbox" <?php if (!empty($supprimer['flyer']) && $verif->nbErreurs() > 0) { echo 'checked="checked"'; } ?> />
                    </div>
                </div>
            <?php
            }
            ?>
        </p>
            <div class="spacer"></div>

        <p style="margin-left: 0.8em;margin-bottom:0.3em;font-weight: bold">Photo</p>
        <div class="guideChamp" style="padding-left:0.8em;margin-top:0">Photo des artistes, de leurs œuvres, du lieu, etc.<br>Visible sous le flyer dans la page Événement</div>
        <p>
            <label for="image">Envoyer</label>
            <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo UPLOAD_MAX_FILESIZE ?>" /> <!-- 2 Mo -->
            <input type="file" name="image" id="image" class="js-file-upload-size-max" size="25" accept="image/jpeg,image/pjpeg,image/png,image/x-png,image/gif,image/webp" class="fichier" />
            <div class="spacer"></div>
            <?php if ($verif->nbErreurs() > 0 && !empty($fichiers['image']['name'])): ?>
                <div class="msg">Le fichier sélectionné a été retiré du formulaire par le navigateur (sécurité). Veuillez le sélectionner à nouveau</div>
            <?php endif; ?>

            <?php if ($can_import_image_url): ?>
                <div class="clear"></div>
                <label for="image_url" class="ou-separateur">ou coller une URL</label>
                <input type="url" name="image_url" id="image_url" value="<?= sanitizeForHtml($url_image) ?>" placeholder="https://…" maxlength="2000" size="50">
                <?php echo $verif->getHtmlErreur('image_url'); ?>
            <?php endif; ?>
        </p>

        <?php
        echo $verif->getHtmlErreur("image");

        //affichage de l'image précédente, et du bouton pour la supprimer
        // (affichée même si le champ est en erreur, cf. le bloc flyer ci-dessus)
        if (isset($get['idE']) && !empty($champs['image']))
        {
            $apercu_image = sanitizeForHtml($assets->get(Evenement::getAssetPath(Evenement::getFilePath($champs['image']))));
        ?>
            <div class="supImg">
                <a href="<?= $apercu_image ?>" class="magnific-popup" target="_blank">
                    <img src="<?= $apercu_image ?>" alt="Photo" />
                </a>
                <div>
                    <label for="sup_image" class="continu">Supprimer</label><input type="checkbox" name="sup_image" id="sup_image" value="image" class="checkbox" <?php if (!empty($supprimer['image']) && $verif->nbErreurs() > 0) { echo 'checked="checked"'; } ?> />
                </div>
            </div>
        <?php
        }
        ?>
    </fieldset>

    <?php if (!$est_connecte || !empty($champs['user_email'])) { ?>
    <fieldset>
        <p><label for="remarque">Remarque à l'administrateur (non publiée)</label><textarea name="remarque" id="remarque" cols="20" rows="6" <?php echo ($est_connecte && !empty($champs['user_email'])) ? 'readonly class="readonly" ': ''; ?>><?php echo sanitizeForHtml($champs['remarque']) ?></textarea></p>
    </fieldset>
    <?php } ?>

<?php if ($can_notify_auteur):
    // Aperçu du mail : mêmes valeurs que celles utilisées à l'envoi (plus haut),
    // reconstruites par AuteurNotifier pour ne pas pouvoir diverger du mail réel.
    // Les motifs cochés et le message viennent s'intercaler entre les deux blocs,
    // exactement là où le formulaire place le <select> et le <textarea>.
    $notif_objet = AuteurNotifier::buildSubject($champs['titre'], $champs['dateEvenement']);
    $notif_intro = AuteurNotifier::buildIntro(
        $champs['titre'],
        $site_full_url . "event/evenement.php?idE=" . (int) $get['idE'],
        $tab_even_lieu['dateAjout'] ?? ''
    );
    [$notif_avant, $notif_apres] = AuteurNotifier::buildTemplateParts($tplEngine);
    $notif_debut = trim($notif_avant) . "\n\n" . $notif_intro;
    $notif_fin = trim($notif_apres);
?>
<fieldset>
    <legend>E-mail à l’auteur</legend>
    <p>
        <label>Pour</label>
            <span style="display:inline-block;margin:0.3em 0 0em 0;">
                <?php if (!empty($original_author_idPersonne)) { ?>
                <a href="/user/dashboard.php?idP=<?php echo $original_author_idPersonne ?>"><?php echo sanitizeForHtml($original_author_name) ?></a> —
                <?php } ?>
                <a href="mailto:<?php echo sanitizeForHtml($original_author_email) ?>"><?php echo sanitizeForHtml($original_author_email) ?></a>
            </span>
    </p>
    <p>
        <label for="notif_objet">Objet</label>
        <input type="text" id="notif_objet" value="<?php echo sanitizeForHtml($notif_objet) ?>" size="60" disabled class="readonly" />
        <div class="guideChamp">Reprend le titre et la date enregistrés</div>
    </p>
    <p class="notif-apercu"><?php echo sanitizeForHtml($notif_debut) ?></p>
    <p>
        <label for="notif_motifs">Motif(s)</label>
        <select name="notif_motifs[]" id="notif_motifs" multiple data-placeholder="Choisissez un ou plusieurs motifs (optionnel)">
            <?php foreach ($glo_motifs_notification_auteur as $key => $label) { ?>
            <option value="<?php echo sanitizeForHtml($key) ?>" <?php echo in_array($key, $notif_motifs, true) ? 'selected="selected"' : '' ?>><?php echo sanitizeForHtml($label) ?></option>
            <?php } ?>
        </select>
        <?php echo $verif->getHtmlErreur('notif_motifs'); ?>
    </p>
    <p>
        <label for="notif_message">Message</label>
        <textarea name="notif_message" id="notif_message" cols="20" rows="6" maxlength="2000"><?php echo sanitizeForHtml($notif_message) ?></textarea>
        <?php echo $verif->getHtmlErreur('notif_message'); ?>
    </p>
    <p class="notif-apercu"><?php echo sanitizeForHtml($notif_fin) ?></p>
</fieldset>
<?php endif; ?>

<p class="piedForm">
        <input type="hidden" name="formulaire" value="ok" />
        <input type="hidden" name="token" value="<?php echo SecurityToken::getToken(); ?>" />
    <input type="submit" name="submit" value="<?php echo (!$est_connecte)?"Envoyer":"Enregistrer"; ?>" class="submit submit-big" />
</p>

</form>

<?php
} // if action_terminee
?>

</main>

<div id="colonne_gauche" class="colonne">
    <?php include("event/_navigation_calendrier.inc.php"); ?>
</div>

<div id="colonne_droite" class="colonne">
</div>

<?php
include("_footer.inc.php");
?>
