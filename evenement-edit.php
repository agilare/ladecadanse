<?php

require_once("app/bootstrap.php");

use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\ImageDriver2;
use Ladecadanse\Security\SecurityToken;
use Ladecadanse\Utils\Logger;
use Ladecadanse\Utils\Mailing;
use Ladecadanse\HtmlShrink;
use Ladecadanse\UserLevel;

$page_titre = "Proposer un événement";
$page_description = "Proposer un événement pour l'agenda";

if (isset($_SESSION['Sgroupe']))
{
    $page_titre = "ajouter/modifier un événement";
    $page_description = "Formulaire d'ajout/modification d'un événement dans l'agenda";
}

$extra_css = array("formulaires", "evenement_inc");

/*
* action choisie, ID si édition
* action "ajouter" par défaut
*/
$actions = array("ajouter", "insert", "editer", "update");
$get['action'] = "ajouter";

/*
* Vérification et attribution des variables d'URL GET
*/
if (isset($_GET['action']))
 {
    try
    {
        $get['action'] = Validateur::validateUrlQueryValue($_GET['action'], "enum", "ajouter", $actions);
    }
    catch (Exception $e)
    {
        header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
        exit;
    }
}

if (isset($_GET['idE']))
 {
    try
    {
        $get['idE'] = Validateur::validateUrlQueryValue($_GET['idE'], "int", 1);
    }
    catch (Exception $e)
    {
        header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
        exit;
    }
}

if (isset($_GET['idL']))
 {
    try
    {
        $get['idL'] = Validateur::validateUrlQueryValue($_GET['idL'], "int", 1);
    }
    catch (Exception $e)
    {
        header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
        exit;
    }
}

if (isset($_GET['idO']))
 {
    try
    {
        $get['idO'] = Validateur::validateUrlQueryValue($_GET['idO'], "int", 1);
    }
    catch (Exception $e)
    {
        header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
        exit;
    }
}


/* VERIFICATION POUR MODIFICATION
* Si c'est une modification et que la personne n'est pas l'auteur, ni du staff, ni lié au lieu de l'événement, ni lié par l'organisateur : stop
*/
if ($get['action'] != "ajouter" && $get['action'] != "insert")
{

	$req_even_cur = $connector->query("SELECT idLieu, statut FROM evenement WHERE idEvenement=".$get['idE']);
	$tab_even_cur = $connector->fetchArray($req_even_cur);

	if ((isset($_SESSION['SidPersonne']) && $authorization->isAuthor("evenement", $_SESSION['SidPersonne'], $get['idE'])) || (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 6) || (isset($_SESSION['Saffiliation_lieu']) && isset($tab_even_cur['idLieu']) && $tab_even_cur['idLieu'] == $_SESSION['Saffiliation_lieu'])
	|| (isset($_SESSION['SidPersonne']) && $authorization->isPersonneInEvenementByOrganisateur($_SESSION['SidPersonne'], $get['idE']))
	|| (isset($_SESSION['SidPersonne']) && isset($tab_even_cur['idLieu']) && $authorization->isPersonneInLieuByOrganisateur($_SESSION['SidPersonne'], $tab_even_cur['idLieu']))
    )
	{
	}
	else
	{
		HtmlShrink::msgErreur("Vous ne pouvez pas modifier cet événement car vous n'avez pas les droits suffisants ou vous n'êtes pas (ou plus) connecté");
        exit;
	}
}

$verif = new Validateur();
$champs = array("statut" => "", "genre" => "", "titre" => "", "dateEvenement" => "", "idLieu" => 0,
 "idSalle" => 0, "nomLieu" => "", "adresse" => "", "quartier" => "",  "localite_id" => "", "region" => "", "urlLieu" => "", 'organisateurs' => '', "description" => "", "ref" => "",
  "horaire_debut" => "", "horaire_fin" => "", "horaire_complement" => "", "price_type" => "", "prix" => "", "prelocations" => "", "user_email" => "", "remarque" => "");
$fichiers = array('flyer' => '', 'image' => '');
$supprimer = array();
$action_terminee = false;

if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok' )
{
	foreach ($champs as $c => $v)
	{
		if (isset($_POST[$c]) )
		{
			$champs[$c] = $_POST[$c];
		}
	}

    if (!isset($_SESSION['Sgroupe']))
    {
        $recaptcha_response = filter_input(INPUT_POST, 'g-recaptcha-response', FILTER_SANITIZE_STRING);
        // Make and decode POST request:
        $recaptcha = file_get_contents($recaptcha_url . '?secret=' . GOOGLE_RECAPTCHA_API_KEY_SERVER . '&response=' . $recaptcha_response);
        $recaptcha = json_decode($recaptcha);
//        if ($recaptcha->score <= 0.5) {
//            $verif->setErreur("global", "Le système de sécurité soupconne que vous êtes un robot, merci de réessayer; le cas échéant, contactez-nous");
//        }
        $recaptcha_score = 0;
        if (!empty($recaptcha->score))
            $recaptcha_score = $recaptcha->score;

        $logger->log('global', 'activity', "[evenement-edit] recaptcha score ".$recaptcha_score.", response : ".json_encode($recaptcha), Logger::GRAN_YEAR);
    }
    else
    {
        if (!SecurityToken::check($_POST['token'], $_SESSION['token']))
        {
            $verif->setErreur("genres", "Le système de sécurité du site n'a pu authentifier votre action. Veuillez réafficher ce formulaire et réessayer");
        }
    }


	if (isset($_POST['organisateurs']))
		$champs['organisateurs'] = $_POST['organisateurs'];

	if (isset($fichiers['flyer']))
		$fichiers['flyer'] = $_FILES['flyer'];

	if (isset($fichiers['image']))
		$fichiers['image'] = $_FILES['image'];

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

	if (!empty($champs['idLieu']) && preg_match("/^[0-9]+_[0-9]+$/", $champs['idLieu']))
	{
		//echo "match";
		$tab_idLieu = explode("_", $champs['idLieu']);
		$champs['idLieu'] = $tab_idLieu[0];
		$champs['idSalle'] = $tab_idLieu[1];
	}
	else
	{
		$champs['idSalle'] = 0;
	}

	if (!empty($champs['idLieu']) && (!preg_match("/^[0-9]+$/", $champs['idLieu']) && !preg_match("/^[0-9]+_[0-9]+$/", $champs['idLieu'])))
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
		$verif->setErreur('doublonLieux', 'Vous ne pouvez pas indiquer 2 lieux');
	}

	//$champs['dateEvenement'] = date('Y-m-d', mktime(0, 0, 0, $_POST['mois'], $_POST['jour'], $_POST['annee'])); // $annee."-".$mois."-".$annee;

	if (empty($champs['dateEvenement']))
	{
		$verif->setErreur('dateEvenement', "Il faut indiquer la date de l'événement");
	}
	else
	{
		$date_iso = date_app2iso($champs['dateEvenement']);
        $lendemain_evenement = date_lendemain($date_iso);

		$tab_date = explode('.', $champs['dateEvenement']);
		if (!checkdate((int) $tab_date[1], (int) $tab_date[0], (int) $tab_date[2])) {
			$verif->setErreur('dateEvenement', "La date n'est pas correcte");
		}
	}

	$verif->valider($champs['description'], "description", "texte", 4, 10000, 0);
	$verif->valider($champs['ref'], "ref", "texte", 1, 250, 0);

    if ($_SERVER["CONTENT_LENGTH"] > POST_MAX_SIZE)
    {
        $verif->setErreur('image', "Le poids des fichiers envoyés dépasse la limite autorisée");
    }
    else
    {
        $verif->validerFichier($fichiers['flyer'], "flyer", $glo_mimes_images_acceptees, 0);
        $verif->validerFichier($fichiers['image'], "image", $glo_mimes_images_acceptees, 0);
    }

	if (empty($champs['horaire_debut']) && empty($champs['horaire_complement']))
	{
		$verif->setErreur("horaire", "Veuillez indiquer l'horaire");
	}

	$verif->valider($champs['horaire_debut'], "horaire_debut", "texte", 1, 5, 0);

	if (!empty($champs['horaire_debut']) && !preg_match("/^[0-9]{1,2}:[0-9]{2}$/", $champs['horaire_debut']))
	{
		$verif->setErreur('horaire_debut', "Le format de l'heure n'est pas correct, veuillez écrire en hh:mm");
	}

	$verif->valider($champs['horaire_fin'], "horaire_fin", "texte", 1, 5, 0);
	if (!empty($champs['horaire_fin']) && !preg_match("/^[0-9]{1,2}:[0-9]{2}$/", $champs['horaire_fin']))
	{
		$verif->setErreur('horaire_debut', "Le format de l'heure n'est pas correct, veuillez écrire en hh:mm");
	}

	$verif->valider($champs['horaire_complement'], "horaire_complement", "texte", 1, 100, 0);
	$verif->valider($champs['prix'], "prix", "texte", 1, 100, 0);
	$verif->valider($champs['prelocations'], "prelocations", "texte", 1, 80, 0);

    $doc_desc_oblig = 0;

    if (!isset($_SESSION['Sgroupe']))
    {
        $verif->valider($champs['user_email'], "email", "email", 4, 250, 1);
        $verif->valider($champs['description'], "description", "texte", 4, 10000, 0);
    }

	/*
	 * PAS D'ERREUR, donc ajout ou update executés
	 */
	if ($verif->nbErreurs() === 0)
	{
		//creation/nettoyage des valeurs à insérer dans la table

		$champs['idPersonne'] = $_SESSION['SidPersonne'] ?? 0;
		$champs['dateEvenement'] = date_app2iso($champs['dateEvenement']);

		$descriptionOrig = $champs['description'];

		if ($champs['prix'] == "0")
		{
			$champs['prix'] = "entrée libre";
		}

		// TODO : transposer également le protocole
		if ($champs['urlLieu'] != "" && !preg_match("/^https?:\/\//", $champs['urlLieu']))
		{
			$champs['urlLieu'] = "http://".$champs['urlLieu'];
		}

		// conversion de l'heure indiquée en datetime
		if (!empty($champs['horaire_debut']))
		{
			$tab_horaire_debut = explode(":", $champs['horaire_debut']);
			//print_r($tab_horaire_debut);
			$sec_horaire_debut = $tab_horaire_debut[0] * 3600 + $tab_horaire_debut[1] * 60;
			//TEST
			//echo "sec_H:".$sec_horaire_debut;
			//
			if ($sec_horaire_debut >= 0 && $sec_horaire_debut <= 21600)
			{
				$champs['horaire_debut'] = $lendemain_evenement." ".$champs['horaire_debut'].":00";
			}
			else
			{
				$champs['horaire_debut'] = $champs['dateEvenement']." ".$champs['horaire_debut'].":00";
			}
		}
		else
		{
			$champs['horaire_debut'] = $lendemain_evenement." 06:00:01";
		}

		// conversion de l'heure indiquée en datetime
		if (!empty($champs['horaire_fin']))
		{
			$tab_horaire_fin = explode(":", $champs['horaire_fin']);
			$sec_horaire_fin = $tab_horaire_fin[0] * 3600 + $tab_horaire_fin[1] * 60;
			//TEST
			//echo "sec_H:".$sec_horaire_debut;
			//
			if ($sec_horaire_fin >= 0 && $sec_horaire_fin <= 21600)
			{
				$champs['horaire_fin'] = $lendemain_evenement." ".$champs['horaire_fin'].":00";
			}
			else
			{
				$champs['horaire_fin'] = $champs['dateEvenement']." ".$champs['horaire_fin'].":00";
			}
		}
		else
		{
			$champs['horaire_fin'] = $lendemain_evenement." 06:00:01";
		}

		//dedoublonne la liste des orgas et nettoye avec string -> int
		if (isset($_POST['organisateurs']) && is_array($_POST['organisateurs']) && count($champs['organisateurs']) > 0)
		{
			$champs['organisateurs'] = array_map('intval', array_unique($champs['organisateurs']));
		}


		// pour remplir les champs nomLieu, adresse, etc. de la table evenement
		if (!empty($champs['idLieu']))
		{
			$sql_lieu = "SELECT nom, adresse, quartier, localite_id, region, URL FROM lieu WHERE idLieu=".$connector->sanitize($champs['idLieu']);
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
            $loc_qua = explode("_", $champs['localite_id']);
            if (count($loc_qua) > 1)
            {
                $champs['localite_id'] =  $loc_qua[0];
                $champs['quartier'] = $loc_qua[1];
                $champs['region'] = 'ge';
            }
            else
            {
                $champs['quartier'] = '';

                if ($champs['localite_id'] == 'vd' || $champs['localite_id'] == 'rf' || $champs['localite_id'] == 'hs')
                {
                    $champs['region'] = $champs['localite_id'];
                    $champs['localite_id'] = 1;
                }
                elseif ($champs['localite_id'] == 529 )
                {
                    $champs['region'] = 'ge';
                }
                else
                {
                    $sql_lieu = "SELECT canton FROM localite WHERE id=".$connector->sanitize($champs['localite_id']);
                    $req_lieu = $connector->query($sql_lieu);
                    $tab_lieu = $connector->fetchArray($req_lieu);
                    $champs['region'] = $tab_lieu['canton'];
                }
            }
        }

        if (!isset($_SESSION['Sgroupe']))
        {
            $champs['statut'] = 'propose';
        }

		/*
		 * Préparation du nom du flyer et de l'image, par ex 3047_2006-02-20.jpg
		 * en cas d'ajout, obtention de l'ID du nouvel événement
		 */
		if (!empty($fichiers['flyer']['name']) || !empty($fichiers['image']['name']))
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

			if (!empty($fichiers['flyer']['name']))
			{
				$champs['flyer'] = $nouv_idE."_".$champs['dateEvenement'].mb_strrchr($fichiers['flyer']['name'], '.');
			}

			if (!empty($fichiers['image']['name']))
			{
				$champs['image'] = $nouv_idE."_".$champs['dateEvenement']."_img".mb_strrchr($fichiers['image']['name'], '.');
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

				$_SESSION['evenement-edit_flash_msg'] = "L'événement a été créé. <a href='/evenement-agenda.php?courant=".$champs['dateEvenement']."#event-".$req_id."'>Voir dans l'agenda</a>";

                if (!isset($_SESSION['Sgroupe']))
                {
                    $_SESSION['evenement-edit_flash_msg'] = "Merci pour votre proposition. Nous allons l'examiner et vous aurez une réponse dès qu'elle sera traitée (cela peut prendre quelques jours)";
                    $subject = "Nouvelle proposition d'événement : \"".$champs['titre']."\" le ".date_fr($champs['dateEvenement'], "annee", "", "", false)." à ".$champs['nomLieu'];
                    $contenu_message = "Merci de vérifier cet événement et l'accepter (statut : publié) ou le refuser (status : dépublié) : ";
                    $contenu_message .= $site_full_url."evenement.php?idE=".$req_id;
                    $contenu_message .= "\n\n";
                    $contenu_message .= "Par : ".$champs['user_email'];
                    $contenu_message .= "\n\nRemarque :\n".$champs['remarque'];

                    $mailer = new Mailing();
                    $mailer->toAdmin($subject, $contenu_message, $champs['user_email']);
                }

				$action_terminee = true;

			} else {
				HtmlShrink::msgErreur("La requête INSERT dans 'evenement' a échoué");
			}
		}
		elseif ($get['action'] == 'update')
		{

			$sql_flyer = ""; // champ SQL pour le flyer

			//si un nouveau flyer a été uploadé, suppression de l'ancien fichier
			if (!empty($champs['flyer']))
			{

				$sql_flyer = ", flyer='".$champs['flyer']."'";
				$req_flyer = $connector->query("SELECT flyer FROM evenement WHERE idEvenement=".$get['idE']);

				if ($req_flyer)
				{
					$affFly = $connector->fetchArray($req_flyer);

					//si  un ancien flyer a été effectivement trouvé suppression des fichiers
					if (!empty($affFly['flyer']))
					{
                        unlink($rep_images_even.$affFly['flyer']);
                        unlink($rep_images_even."s_".$affFly['flyer']);
					}
                }
				else
				{
					HtmlShrink::msgErreur("La requête SELECT flyer a échoué");
				}
			}

			//si le champ "supprimer le flyer" est coché sans qu'un nouveau flyer soit remplacant
			if (!empty($supprimer['flyer']))
			{

				$sql_flyer = ", flyer=''";
				$req_flyer = $connector->query("SELECT flyer FROM evenement WHERE idEvenement=".$get['idE']);

				//si  un ancien flyer a été effectivement trouvé suppression des fichiers
				if ($req_flyer)
				{
					$affFly = $connector->fetchArray($req_flyer);

					if (!empty($affFly['flyer']))
					{
						unlink($rep_images_even.$affFly['flyer']);
						unlink($rep_images_even . "s_" . $affFly['flyer']);
                    }
				}
				else
				{
					HtmlShrink::msgErreur("La requète SELECT flyer a échoué");
				}

			} //elseif supprimer flyer

			$sql_image = ""; // champ SQL pour le flyer

			//si une nouvelle image a été uploadée, suppression de l'ancien fichier
			if (!empty($champs['image']))
			{

				$sql_image = ", image='".$champs['image']."'";
				$req_image = $connector->query("SELECT image FROM evenement WHERE idEvenement=".$get['idE']);

				if ($req_image)
				{
					$affImg = $connector->fetchArray($req_image);

					//si  un ancien flyer a été effectivement trouvé suppression des fichiers
					if (!empty($affImg['image']))
					{
							unlink($rep_images_even.$affImg['image']);
							unlink($rep_images_even."s_".$affImg['image']);
					}
				}
				else
				{
					HtmlShrink::msgErreur("La requète SELECT image a échoué");
				}

			//si le champ "supprimer le flyer" est coché¡³ans qu'un nouveau flyer soit remplacant
			}

			if (!empty($supprimer['image']))
			{

				$sql_image = ", image=''";
				$req_image = $connector->query("SELECT image FROM evenement WHERE idEvenement=".$get['idE']);

				if ($req_image)
				{
					$affimage= $connector->fetchArray($req_image);

					if (!empty($affimage['image']))
					{
						unlink($rep_images_even.$affimage['image']);
						unlink($rep_images_even."s_".$affimage['image']);
					}
				}
				else
				{
					HtmlShrink::msgErreur("La requète SELECT image a échoué");
				}

			} //if supprimer image

			$sql_update = "UPDATE evenement SET ";

			foreach ($champs as $c => $v)
			{
				if ($c != "idPersonne" && $c != 'organisateurs') {
					$sql_update .= $c."='".$connector->sanitize($v)."', ";
				}
			}


			$sql_update .= "date_derniere_modif='".date("Y-m-d H:i:s")."'";
			$sql_update .= $sql_flyer.$sql_image."
			WHERE idEvenement=".$get['idE'];

			$req_update = $connector->query($sql_update);

			/*
			* MAJ réussie, message OK, et RAZ de l'action
			*/
			if ($req_update)
			{

				$lienLieu = '';
				if (!empty($lieu))
					$lienLieu = " au <a href=\"/lieu.php?idLieu=".$lieu."\"> lieu ".$lieu."</a>";

				$sql = "DELETE FROM evenement_organisateur WHERE idEvenement=".$get['idE'];
				$req = $connector->query($sql);
				$req_id = $get['idE'];

                $confirmation_flash_msg = '';

                // acceptation d'un even
                if ($tab_even_cur['statut'] == 'propose')
                {
                    if ($champs['statut'] == 'actif')
                    {
                        $subject = "Votre événement \"".$champs['titre']."\" sur La décadanse a été publié";
                        $contenu_message = "Bonjour,\n\n";
                        $contenu_message .= "Merci de nous avoir proposé un événement, nous venons de le publier : ";
                        $contenu_message .= $site_full_url."evenement.php?idE=".$req_id;
                        $contenu_message .= "\n\n";
                        $contenu_message .= "La décadanse";

                        $confirmation_flash_msg = " Un email de confirmation a été envoyé à " . $champs['user_email'];

                        $mailer = new Mailing();
                        $mailer->toUser($champs['user_email'], $subject, $contenu_message);
                    }
                }

                $_SESSION['evenement-edit_flash_msg'] = "L'événement a été modifié.$confirmation_flash_msg<br><a href='/evenement-agenda.php?courant=".$champs['dateEvenement']."#event-".$req_id."'>Voir dans l'agenda</a>";

				$get['action'] = 'editer';

				$action_terminee = true;
			}
			else
			{
				HtmlShrink::msgErreur("La requête UPDATE de la table evenement a échoué");
			}

		} //if get_action = 'insert' ou 'update'

		/*
		* TRAITEMENT DE L'IMAGE UPLOADEE
		*/

		if (!empty($fichiers['flyer']['name']))
		{
			$imD2 = new ImageDriver2("evenement");
			$erreur_image = array();
			$erreur_image[] = $imD2->processImage($_FILES['flyer'], $champs['flyer'], 600, 600);
			$erreur_image[] = $imD2->processImage($_FILES['flyer'], "s_".$champs['flyer'], 120, 190, 0, 0);
			if (!empty($erreur_image)) {
				print_r($erreur_image);
			}

            if (!empty($msg2))
                $champs['flyer'] = '';
		}

		if (!empty($fichiers['image']['name']))
		{
			$imD2 = new ImageDriver2("evenement");
			$erreur_image = array();
			$erreur_image[] = $imD2->processImage($_FILES['image'], $champs['image'], 600, 600);
			$erreur_image[] = $imD2->processImage($_FILES['image'], "s_".$champs['image'], 120, 190, 0, 0);
			if (!empty($erreur_image)) {
				print_r($erreur_image);
			}

            if (!empty($msg2))
                $champs['image'] = '';
		}

		if (isset($_POST['organisateurs']) && is_array($champs['organisateurs']))
		{
			foreach ($champs['organisateurs'] as $no => $idOrg)
			{
				if ($idOrg != 0)
				{
                    $sql = "INSERT INTO evenement_organisateur (idEvenement, idOrganisateur) VALUES (".$req_id.", ".$idOrg.")";
					$connector->query($sql);
				}
			}
		}

		//affichage de la fiche de l'événement
		$evenement = $champs;
		//echo "get_ide :".$get['idE']." action:".$get['action'];
		if ($get['action'] == "ajouter" || $get['action'] == "insert")
		{
			$evenement['idEvenement'] = $req_id;
		}
		else if ($get['action'] == "editer" || $get['action'] == "update")
		{
			$evenement['idEvenement'] = $get['idE'];
		}

		$sql_img = "SELECT image FROM evenement WHERE idEvenement=".$evenement['idEvenement'];

		$req_img = $connector->query($sql_img);
		$tab_img = $connector->fetchArray($req_img);
		if (!empty($tab_img['image']))
		{
			$evenement['image'] = $tab_img['image'];
		}

		$sql_fly = "SELECT flyer FROM evenement WHERE idEvenement=".$evenement['idEvenement'];

		$req_fly = $connector->query($sql_fly);
		$tab_fly = $connector->fetchArray($req_fly);
		if (!empty($tab_fly['flyer']))
		{
			$evenement['flyer'] = $tab_fly['flyer'];
		}

		unset($_POST); // ?
        $logger->log('global', 'activity', "[evenement-edit] ".$get['action']." of \"".$champs['titre']."\" in ".$champs['nomLieu']." /evenement.php?idE=".$evenement['idEvenement'], Logger::GRAN_YEAR);

        if (isset($_SESSION['Sgroupe']))
        {
            header("Location: /evenement.php?idE=".$req_id); die();
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

		if ($affIm = $connector->fetchArray($connector->query("SELECT flyer, image FROM evenement WHERE idEvenement =".$get['idE'])))
		{
			$champs['flyer'] = $affIm['flyer'];
			$champs['image'] = $affIm['image'];
		}

	} //if erreur == 0
} // if POST != ""

include("_header.inc.php");
?>

<div id="contenu" class="colonne evenement-edit">

<?php
if (!$action_terminee)
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
            dateEvenement, nomLieu, adresse, urlLieu, quartier, localite_id, region, description, flyer, image, prix, price_type, horaire_debut, horaire_fin, horaire_complement, ref, prelocations, remarque, user_email, dateAjout FROM evenement WHERE idEvenement =" . $get['idE']);

            if ($affEven = $connector->fetchArray($req_even))
            {
                foreach($affEven as $c => $v)
                {
                    $champs[$c] = $v;
                }
                //printr($champs);

                $champs['dateEvenement'] = date_iso2app($champs['dateEvenement']);
    /*			$tab = explode("-", $affEven['dateEvenement']);
                $annee = $tab[0];
                $mois = $tab[1];
                $jour = $tab[2];*/
                $champs['horaire_debut'] = horaire2heure($affEven['horaire_debut'], $affEven['dateEvenement']);
                $champs['horaire_fin'] = horaire2heure($affEven['horaire_fin'], $affEven['dateEvenement']);
                // if (!empty($affEven['idLieu'])) {
                    // $lieu = $affEven['idLieu'];
                // } else {
                    // $nomLieu = $affEven['nomLieu'];
                    // $adresse = $affEven['adresse'];
                // }

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

        if (PARTIAL_EDIT_MODE && $champs['dateAjout'] < PARTIAL_EDIT_FROM_DATETIME)
        {
            HtmlShrink::msgErreur(PARTIAL_EDIT_MODE_MSG);
            exit;
        }

        if ($_SESSION['Sgroupe'] <= UserLevel::ACTOR) {
            $aff_actions = '<ul class="entete_contenu_menu">';
            //Menu d'actions
            if ($_SESSION['Sgroupe'] <= 1)
            {
                $aff_actions .= "<li class=\"action_supprimer\">
                <a href=\"/multi-suppr.php?action=confirmation&amp;type=evenement&amp;id=" . $get['idE'] . "&token=" . SecurityToken::getToken() . "\" id='js-event-delete-btn'>
                Supprimer</a>
                </li>";
            }

            $aff_actions .= "<li class=\"action_copier\">
                <a href=\"/evenement-copy.php?idE=".$get['idE']."\" title=\"Copier l'événement vers une autre date\">Copier vers d'autres dates</a></li></ul>";
        }
    } // action editer

	$aff_titre = '<div id="entete_contenu">';

	$act = '';
	/*
	 * PREPARATION DES URLS SELON LES ACTIONS,
	 * update et idE en cas d'édition, insert pour ajout
	 */
	if ($get['action'] == 'update' || $get['action'] == 'editer')
	{
        $aff_titre .= '<h2>Modifier <a style="font-size:0.7em" href="/evenement.php?idE='.$get['idE'].'" title="Fiche de l\'événement" >'.$champs['titre'].'</a></h2>';
		$act = "update&amp;idE=".$get['idE'];
	}
	else
	{
        $aff_titre .= !isset($_SESSION['Sgroupe']) ? '<h2>Proposer un événement</h2>':'<h2>Ajouter un événement</h2>';
		$act = 'insert';
	}

    echo $aff_titre.$aff_actions;
?>

    <div class="spacer"></div>
</div>

<?php
if ($verif->nbErreurs() > 0)
{
	HtmlShrink::msgErreur("Il y a ".$verif->nbErreurs()." erreur(s).");
    if (!empty($verif->getHtmlErreur("global")))
    {
        echo $verif->getHtmlErreur("global");
    }
	//print_r($verif->getErreurs());
}
?>

<form method="post" id="ajouter_editer" class="js-submit-freeze-wait" enctype="multipart/form-data" action="<?php echo basename(__FILE__)."?action=".$act ?>">
    <div id="home-tmp-banner">
        <?php if (!in_array($get['action'], ['editer', 'update'])) { ?>
            <h2>Avant de commencer :</h2>
        <?php } ?>
        <?php if (!isset($_SESSION['Sgroupe'])) { ?>
            <p style="line-height: 1.6em;">Utilisez ce formulaire <strong>si vous n'avez pas déjà un compte sur La décadanse</strong>. L'événement sera publié (ou pas) après une validation de notre part, dans les prochains jours.<br>Sinon, veuillez <a href="/user-login.php">vous connecter</a> pour ajouter votre événement.</p>
        <?php } ?>
        <p>Veillez svp à ce que votre événement</p>
        <ul style="line-height:1.2em">
            <li style="margin:6px 2px;">n’est pas déjà présent dans l’<a href="/evenement-agenda.php" target="_blank">agenda</a></li>
            <li style="margin:6px 2px;">respecte notre <a href="/articles/charte-editoriale.php" target="_blank">charte&nbsp;éditoriale</a></li>
        </ul>
    </div>
        <p>Les événements annoncés sur La décadanse sont également visibles sur les sites de nos partenaires : <a href="https://epic-magazine.ch/" target="_blank">EPIC-Magazine</a> et <a href="https://noctambus.ch/" target="_blank">Noctambus</a></p>
        <details style="margin-top:-11px">
            <summary>Détails</summary>
            <ul><li><b>EPIC-Magazine</b> - webmagazine qui met en avant la culture locale et émergente à Genève et dans ses environs&nbsp;: intégration de l'agenda dans la <a href="https://epic-magazine.ch/lieux/" target="_blank">page Cartographie</a>
                </li>
                    <li><b>Noctambus</b> - réseau de bus de nuit desservant le canton de Genève et ses régions transfrontalières&nbsp;: une sélection des événements nocturnes du vendredi au samedi dans les <a href="https://noctambus.ch/noctualites" target="_blank">noctualités</a>
                    </li>
        </details>
        <h2 style="margin:20px 0 5px 0;">L’événement</h2>
    <p style="margin:5px 0;">* indique un champ obligatoire</p>

    <?php if ($get['action'] == "editer" || $get['action'] == "update")
    {?>
    <p class="piedForm">
    <input type="hidden" name="formulaire" value="ok" />
    <input type="submit" value="Enregistrer" class="submit submit-big" />
    </p>
    <?php } ?>

    <fieldset>
        <legend>Catégorie*</legend>

        <ul class="radio" style="font-size: 1.15em;">
        <?php
        foreach ($glo_tab_genre as $k => $v)
        {
            $coche = '';
            if (strcmp($k, $champs['genre']) == 0)
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
        <?php
        echo $verif->getHtmlErreur("genre");
        ?>
    </fieldset>

    <fieldset>
        <legend>Date & horaire</legend>

            <div style="display: inline-block;">
                <label for="dateEvenement">Date*</label><input type="text" name="dateEvenement" id="dateEvenement" size="9" value="<?php echo sanitizeForHtml($champs['dateEvenement']); ?>" class="datepicker" placeholder="jj.mm.aaaa" required />
                    <?php
                    echo $verif->getHtmlErreur('dateEvenement');
                    ?><div id="calendarDiv"></div>
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
            <?php if (in_array($get['action'], ["ajouter", "insert"])) { ?>

            <div class="guideChamp" style="margin-top:-0.2em">
                        <?php if (isset($_SESSION['Sgroupe'])) { ?>
                        Si l’événement se répète sur plusieurs dates, vous pouvez l’ajouter à d'autres dates avec le bouton <b>Copier</b>, à la page suivante
                    <?php }
                    else { ?>
                        Si l’événement se répète sur plusieurs dates, merci de nous indiquer précisément les jours et horaires dans le <a href="#remarque">champ Remarque</a> ci-dessous.
        <?php } ?>
                </div>
                <div class="spacer"></div>
    <?php } ?>

                    <div style="margin-top:1.3em">
                        <label for="horaire_complement">Complément</label>
                            <input type="text" name="horaire_complement" id="horaire_complement" size="60" maxlength="200" value="<?php echo sanitizeForHtml($champs['horaire_complement']) ?>" />
                            <?php
            echo $verif->getHtmlErreur('horaire_complement');
            ?>
        </div>

                    <?php
        echo $verif->getHtmlErreur('horaire');
        ?>
        </fieldset>

    <fieldset>
        <legend>Lieu*</legend>
        <p>
            <label for="idLieu"><strong>Nom du lieu :</strong></label>
        <select name="idLieu" id="idLieu" class="chosen-select" title="Un lieu dans base de données de La décadanse" style="max-width:300px"  data-placeholder="">
        <?php

        $sql_lieu_excl_fr = '';
        $sql_localite_excl_fr = '';
        if ($get['action'] == 'ajouter' || $get['action'] == 'insert')
        {
            $sql_lieu_excl_fr = " AND region != 'fr' ";
            $sql_localite_excl_fr = " AND canton != 'fr' ";
        }


        //Menu des lieux actifs de la base
        echo "<option value=\"\"></option>";
        $req_lieux = $connector->query("
        SELECT idLieu, nom FROM lieu WHERE statut='actif' ".$sql_lieu_excl_fr." ORDER BY TRIM(LEADING 'L\'' FROM (TRIM(LEADING 'Les ' FROM (TRIM(LEADING 'La ' FROM (TRIM(LEADING 'Le ' FROM nom))))))) COLLATE utf8mb4_unicode_ci"
         );


        while ($lieuTrouve = $connector->fetchArray($req_lieux))
        {
            echo "<option ";

            $nom_lieu = $lieuTrouve['nom'];
            if (preg_match("/^(Le |La |Les |L')(.*)/", $lieuTrouve['nom'], $matches))
            {
                $nom_lieu = $matches[2].', '.$matches[1];

            }

            if ($lieuTrouve['idLieu'] == $champs['idLieu'])
            {
                echo "selected=\"selected\" ";
            }

            echo "value=\"".$lieuTrouve['idLieu']."\">".$nom_lieu."</option>";


            $sql_salle = "select * from salle where idLieu=".$lieuTrouve['idLieu']. " AND salle.status='actif' ";
            $req_salle = $connector->query($sql_salle);
            while ($tab_salle = $connector->fetchArray($req_salle))

            {
                echo "<option ";
                if ($champs['idSalle'] != 0 && $tab_salle['idSalle'] == $champs['idSalle'])
                {
                    echo "selected=\"selected\" ";
                }
                echo " style=\"font-style:italic;color:#444;\" value=".$lieuTrouve['idLieu']."_".$tab_salle['idSalle'].">".$nom_lieu."&nbsp;– ".$tab_salle['nom']."</option>";

            }
        }
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
        $tab_nomLieu_label = array("for" => "nomLieu");
        echo HtmlShrink::formLabel($tab_nomLieu_label, "Nom du lieu");
        echo $verif->getHtmlErreur("nomLieuIdentique");

        $tab_nomLieu = array("type" => "text", "name" => "nomLieu", "id" => "nomLieu", "size" => "35", "maxlength" => "60", "value" => "");
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
            <label for="localite">Localité/quartier</label>&nbsp;<select name="localite_id" id="localite" class="chosen-select" style="max-width:300px;">
        <?php
        echo "<option value=\"0\">&nbsp;</option>";
        $req = $connector->query("
        SELECT id, localite, canton FROM localite WHERE id!=1 $sql_localite_excl_fr ORDER BY canton, localite "
         );

        $select_canton = '';
        while ($tab = $connector->fetchArray($req))
        {

            if ($tab['canton'] != $select_canton)
            {
                if (!empty($select_canton))
                    echo "</optgroup>";

                echo "<optgroup label='".strtoupper($tab['canton'])."'>"; // ".$glo_regions[strtolower($tab['canton'])]."
            }
            echo "<option ";

            if (empty($champs['idLieu']) && ($champs['localite_id'] == $tab['id'] && empty($champs['quartier'])) || ((isset($_POST['localite_id']) && $tab['id'] == $_POST['localite_id'])))
            {
                echo 'selected="selected" ';
            }

            echo "value=\"".$tab['id']."\">".$tab['localite']."</option>";

            // Genève quartiers
            if ($tab['id'] == 44)
            {
                // si erreur formulaire
                $champs_quartier = '';
                $loc_qua = explode("_", $champs['localite_id']);
                if (!empty($loc_qua[1]))
                   $champs_quartier = $loc_qua[1];

                // si chargement even existant
                if (!empty($champs['quartier']))
                    $champs_quartier = $champs['quartier'];

                foreach ($glo_tab_quartiers2['ge'] as $no => $quartier)
               {
                       echo "<option ";

                       if (empty($champs['idLieu']) && $champs_quartier == $quartier)
                       {
                               echo 'selected="selected" ';
                       }

                       echo " value=\"44_".$quartier."\">Genève - ".$quartier."</option>";

               }

            }

             $select_canton = $tab['canton'];
        }
        ?>
            <optgroup label="Ailleurs">
        <?php
            foreach ($glo_tab_ailleurs as $id => $nom)
           {
                   echo "<option ";

                   if (empty($champs['idLieu']) && ($champs['region'] == $id) || ((isset($_POST['localite_id']) && $id == $_POST['localite_id']))) // $form->getValeur('quartier')
                   {
                           echo ' selected="selected" ';
                   }

                   echo " value=\"".$id."\">".$nom."</option>";

           }
        ?>
            </optgroup>
        </select>
        <?php
        echo $verif->getHtmlErreur("localite_id");
        ?>
        </p>

        <p>
            <label for="urlLieu">Site web</label>
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
        <?php
        echo $verif->getHtmlErreur("titre");
        ?>
        </p>

        <p>
        <label for="description">Description</label>
        <?php
        $id_textarea = "description";
        ?>

        <textarea name="description" id="description" rows="20"><?php echo sanitizeForHtml($champs['description']) ?></textarea>
        <?php
        echo $verif->getHtmlErreur('description');
        ?>
        </p>
    </fieldset>

    <fieldset id="references">
        <legend>Références</legend>
        <p>
            <label for="ref">Sites web</label>
            <input type="text" name="ref" id="ref" value="<?php echo sanitizeForHtml($champs['ref']); ?>" placeholder="URL1;URL2; etc." />
        </p>
        <div class="guideChamp">Site de l’événement, de l’organisateur (s’il n’est pas présent ci-dessous), de la page Facebook... Séparer chaque élément par un point-virgule.</div>
            <?php
            echo $verif->getHtmlErreur('ref');
            ?>
            <div class="spacer"></div>

        <?php
        $tab_organisateurs_even = array();
        if ($get['action'] == "editer" || $get['action'] == "update")
        {

            $sql = "SELECT organisateur.idOrganisateur, nom
        FROM organisateur, evenement_organisateur
        WHERE evenement_organisateur.idEvenement=".$get['idE']." AND
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
            <select name="organisateurs[]" id="organisateurs" data-placeholder="Tapez les noms des organisateurs" class="chosen-select" multiple style="max-width:350px;">
        <?php
        echo "<option value=\"0\">&nbsp;</option>";
        $req = $connector->query("
        SELECT idOrganisateur, nom FROM organisateur WHERE statut='actif' ORDER BY TRIM(LEADING 'L\'' FROM (TRIM(LEADING 'Les ' FROM (TRIM(LEADING 'La ' FROM (TRIM(LEADING 'Le ' FROM nom))))))) COLLATE utf8mb4_unicode_ci"
         );

        while ($tab = $connector->fetchArray($req))
        {
            echo "<option ";
            if ((isset($_POST['organisateurs']) && in_array($tab['idOrganisateur'], $_POST['organisateurs'])) || in_array($tab['idOrganisateur'], $tab_organisateurs_even))
            {
                echo 'selected="selected" ';
            }
            echo "value=\"".$tab['idOrganisateur']."\">".$tab['nom']."</option>";
        }
        ?>
        </select>
            <div class="guideChamp">L’événement figurera dans la page de ces <a href="/organisateurs.php" target="_blank">organisateurs</a>. Si vous souhaitez que votre organisation soit listée, <a href="/contacteznous.php" target='_blank'>demandez-nous</a> (avec des infos : texte, liens...)</div>
        </p>
    </fieldset>

    <fieldset>
        <legend>Entrée</legend>
            <?php if ((isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] < UserLevel::AUTHOR)) { ?>
                <?php
        $price_types = ['unknown' => 'inconnu', 'gratis' => 'entrée libre', 'asyouwish' => 'prix libre', 'chargeable' => 'payant'];
        ?>
            <ul class="radio" style="list-style-type: none">
                <?php foreach ($price_types as $pt => $label)
                {
                    ?>
                <li><label class="listehoriz" style="float: none"><input class="precisions" type="radio" name="price_type" value="<?php echo $pt; ?>" <?php
                if ($pt == $champs['price_type'] ||
                        ($pt == 'gratis' && !empty($champs['prix']) && strstr($champs['prix'], 'entrée libre')) ||
                        ($pt == 'asyouwish' && !empty($champs['prix']) && strstr($champs['prix'], 'prix libre'))) { ?> checked <?php } ?>> <?php echo $label ?></label></li>
        <?php
                }
        ?>
            </ul>
<?php } ?>

        <div id="prix-precisions" <?php
        if (!isset($_SESSION['Sgroupe']) || (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] >= UserLevel::AUTHOR) || ($get['action'] == "editer" || $get['action'] == "update") && (!empty($champs['prix']) || (!empty($champs['price_type']) && ($champs['price_type'] == 'asyouwish' || $champs['price_type'] == 'chargeable') ))
                ) { ?> style="display:block" <?php } ?>>
            <p>
                <label for="prix">Prix</label>
                    <input type="text" name="prix" id="prix" size="45" maxlength="100" value="<?php echo sanitizeForHtml($champs['prix']) ?>" />
                    <?php
                echo $verif->getHtmlErreur('prix');
                ?>
            </p>
                <?php if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] > UserLevel::AUTHOR) { ?>
                    <div class="guideChamp">Vous pouvez mettre seulement <strong>0</strong> si l'entrée est libre.</div>
            <?php } ?>
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
        <div style="margin-left: 0.8em;font-weight: bold">Formats JPEG, PNG ou GIF; max. 2 Mo</div>
        <p>
            <label for="flyer">Affiche/flyer</label>
            <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo UPLOAD_MAX_FILESIZE ?>" /> <!-- 2 Mo -->
            <input type="file" name="flyer" id="flyer" class="js-file-upload-size-max" size="25" accept="image/jpeg,image/pjpeg,image/png,image/x-png,image/gif" class="fichier" />
            <?php
            echo $verif->getHtmlErreur("flyer");

            //affichage du flyer precedent, et du bouton pour supprimer
            if (isset($get['idE']) && !empty($champs['flyer']) && !$verif->getErreur($champs['flyer']))
            {
                $imgInfo = getimagesize($rep_images_even.$champs['flyer']);
                ?>
                <div class="supImg">
                    <a href="<?php echo $url_uploads_events.$champs['flyer'].'?'.filemtime($rep_images_even.$champs['flyer']) ?>" class="magnific-popup" target="_blank"><img src="<?php echo $url_uploads_events."s_".$champs['flyer'].'?'.filemtime($rep_images_even.$champs['flyer']) ?>" alt="Flyer" /></a>
                <div><label for="sup_flyer" class="continu">Supprimer</label><input type="checkbox" name="sup_flyer" id="sup_flyer" value="flyer" class="checkbox"
                <?php
                if (!empty($supprimer['flyer']) && $verif->nbErreurs() > 0)
                {
                    echo 'checked="checked"' ;
                }
                ?>
                        /></div>
                </div>
            <?php
            }
        ?>
        </p>
            <div class="spacer"></div>
        <p>
            <label for="image"><span class="tooltip">Photo <i class="fa fa-info-circle" aria-hidden="true"></i>
<span class="tooltiptext"> S’affiche à la place du flyer s’il n’y a pas de flyer, sinon en dessous de celui-ci</span></span></label>
            <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo UPLOAD_MAX_FILESIZE ?>" /> <!-- 2 Mo -->
            <input type="file" name="image" id="image" class="js-file-upload-size-max" size="25" accept="image/jpeg,image/pjpeg,image/png,image/x-png,image/gif" class="fichier" />
            <div class="guideChamp">Photo des artistes, de leurs œuvres, du lieu, etc.</div>
        </p>
        <div class="spacer"></div>
        <?php
        echo $verif->getHtmlErreur("image");

        //affichage de l'image et du bouton pour supprimer
        if (isset($get['idE']) && !empty($champs['image']) && !$verif->getErreur('image'))
        {
            $imgInfo = @getimagesize($rep_images_even.$champs['image']);
            echo "<div class=\"supImg\">";
            ?>
            <a href="<?php echo $url_uploads_events.$champs['image'].'?'.filemtime($rep_images_even.$champs['image']) ?>" class="magnific-popup"  target="_blank"><img src="<?php echo $url_uploads_events."s_".$champs['image'].'?'.filemtime($rep_images_even.$champs['image']) ?>" alt="Photo" /></a>
           <?php
            echo "<div><label for=\"sup_image\" class=\"continu\">Supprimer</label><input type=\"checkbox\" name=\"sup_image\" id=\"sup_image\" value=\"image\" class=\"checkbox\" ";

            if (!empty($supprimer['image']) && $verif->nbErreurs() == 0)
            {
                echo 'checked="checked" ';
            }
            echo "/></div></div>";
        }
        ?>
    </fieldset><?php  ?>

    <?php
    if (!isset($_SESSION['Sgroupe']) || !empty($champs['user_email'])) { ?>
    <fieldset>
        <p><label for="remarque">Remarque</label><textarea name="remarque" id="remarque" cols="20" rows="6" <?php echo (isset($_SESSION['Sgroupe']) && !empty($champs['user_email'])) ? 'readonly class="readonly" ': ''; ?>><?php echo sanitizeForHtml($champs['remarque']) ?></textarea></p>
        <p><label for="user_email">Votre email*</label><input type="email" id="user_email" name="user_email" value="<?php echo sanitizeForHtml($champs['user_email']) ?>" required size="25" <?php echo (isset($_SESSION['Sgroupe']) && !empty($champs['user_email'])) ? 'readonly class="readonly" ': ''; ?> maxlength="80"></p>
    </fieldset>
    <?php } else if (!empty($champs['user_email'])) {  ?>

    <?php } ?>
<?php
if (($get['action'] == "editer" || $get['action'] == "update") && isset($get['idE']))
{
?>

<fieldset>
    <legend>Statut de l’événement</legend>
    <ul class="radio">
    <?php

    $statuts = array('propose' => '<strong>proposé</strong> (non visible sur le site)', 'actif' => '<strong>publié</strong> (visible sur le site)',  'complet' => '<strong>complet</strong> (visible sur le site mais marqué comme étant complet)', 'annule' => '<strong>annulé</strong> (visible sur le site mais marqué comme étant annulé)', 'inactif' => '<strong>dépublié</strong> (non visible sur le site)');
    foreach ($statuts as $s => $n)
    {
        if ($s === 'propose' && ($_SESSION['Sgroupe'] > 6 || (!empty($champs['user_email']) && $champs['statut'] != 'propose')))
            continue;

        $coche = '';
        if (strcmp($s, $champs['statut']) == 0)
        {
            $coche = 'checked="checked"';
        }
        echo '<li style="display:block">
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
        <input type="hidden" name="statut" value="actif" id="statut_actif" title="statut" />
<?php
}
?>

<p class="piedForm">
    <input type="hidden" name="formulaire" value="ok" />
    <input type="text" name="name_as" value="" class="name_as" id="name_as" /><?php echo $verif->getHtmlErreur('name_as'); ?>
    <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
    <input type="hidden" name="token" value="<?php echo SecurityToken::getToken(); ?>" />
    <input type="submit" name="submit" value="<?php echo (!isset($_SESSION['Sgroupe']))?"Envoyer":"Enregistrer"; ?>" class="submit submit-big" />
</p>

</form>

<?php
} // if action_terminee
?>



</div>
<!-- fin contenu  -->


<div id="colonne_gauche" class="colonne">

<?php include("_navigation_calendrier.inc.php"); ?>
</div>
<!-- Fin Colonnegauche -->

<div id="colonne_droite" class="colonne">
</div>

<?php
include("_footer.inc.php");
?>
