<?php
require_once("app/bootstrap.php");

use Ladecadanse\EventCategory;
use Ladecadanse\UserLevel;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\Mailing;
use Ladecadanse\Utils\PasswordPolicy;
use Ladecadanse\Utils\QueryParamValidator;
use Ladecadanse\Security\SecurityToken;
use Ladecadanse\Security\Sentry;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Lieu;
use Ladecadanse\Localite;
use Ladecadanse\Personne;
use Ladecadanse\UserSettings;

// Tout compte modifie le sien, MEMBER (12) compris
if (!$authorization->checkGroup(UserLevel::MEMBER)) {
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
    header("Location: /user/login.php");
    die();
}

$get['action'] = QueryParamValidator::validateUrlQueryValue($_GET['action'] ?? 'ajouter', "enum", 1, $actions);
$is_edit_mode = in_array($get['action'], ['editer', 'update'], true);

$is_superadmin  = $_SESSION['Sgroupe'] == UserLevel::SUPERADMIN;

if (isset($_GET['idP']))
{
	$get['idP'] = QueryParamValidator::validateUrlQueryValue($_GET['idP'], "int", 1);
}

if (!$is_superadmin && (!$is_edit_mode || $_SESSION['SidPersonne'] != $get['idP']))
{
    $http_error = [403, 'Forbidden', "Vous n'avez pas les droit pour effectuer cette action"];
    include(__DIR__ . "/_erreur_http.inc.php");
    exit;
}

/*
* flag pour l'affichage des champs et aussi leur traitement
* MEMBER et + : identification, mot de passe, e-mail
* ORGA et + : affiliations, signature des événements ajoutés, valeurs par défaut du formulaire d'ajout
*/
$areContributorFieldsEnabled = isset($_SESSION['Sgroupe']) && (int) $_SESSION['Sgroupe'] <= UserLevel::ACTOR;

/**
 * Rattachements d'un compte : son lieu (table affiliation) et ses organisateurs.
 *
 * Relus avant puis après l'enregistrement. C'est leur différence qui décide d'un
 * e-mail d'information, et non la seule case cochée — enregistrer une autre partie
 * du formulaire n'a pas à prévenir de rattachements inchangés. Le couple
 * DELETE/INSERT de personne_organisateur, plus bas, efface l'état antérieur : il
 * faut donc le lire avant. Relus aussi à la place du POST pour qui ne peut pas les choisir.
 *
 * @return array{lieu: int, organisateurs: int[]}
 */
$getUserAffiliationsIds = function (int $idP) use ($connectorPdo): array
{
	$stmt = $connectorPdo->prepare("SELECT idAffiliation FROM affiliation WHERE idPersonne = ? AND genre = 'lieu'");
	$stmt->execute([$idP]);
	$lieuId = (int) $stmt->fetchColumn();

	$stmt = $connectorPdo->prepare("SELECT idOrganisateur FROM personne_organisateur WHERE idPersonne = ? ORDER BY idOrganisateur");
	$stmt->execute([$idP]);

	return ['lieu' => $lieuId, 'organisateurs' => array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))];
};

/*
* ACTOR and MEMBER : readonly (cannot select affiliations i.e. modify it), only AUTHORS can attach/remove affiliation
*/
$canEditAffiliations = isset($_SESSION['Sgroupe']) && (int) $_SESSION['Sgroupe'] <= UserLevel::AUTHOR;

/*
* Valeurs par défaut à l'ajout d'un événement (fieldset « Événements »).
* La colonne n'est renseignée qu'en fin de validation, à partir de ces valeurs normalisées.
*/
$ev_defaults = UserSettings::eventNewDefaults(null);


// =============================================================================
// Traitement
//
// Tout se joue avant l'inclusion de _header.inc.php : un enregistrement réussi
// se termine par une redirection vers la fiche du profil (POST/Redirect/GET),
// qui suppose que rien n'a encore été envoyé au navigateur.
// =============================================================================

$verif = new Validateur();

$champs = [
    "pseudo" => '',
    "motdepasse" => '',
    "newPass" => '',
    "newPass2" => '',
    "affiliation" => '',
    'lieu' => '',
    'organisateurs' => '',
    "email" => '',
    "groupe" => '',
    "signature" => 'pseudo',
    "avec_affiliation" => 'non',
    "statut" => '',
];

/*
* Sorties du traitement, rendues plus bas une fois l'en-tête inclus.
*
* Les requêtes en échec s'accumulent ici. Le succès, lui, voyage en session jusqu'à user/dashboard.php — sauf si
* une de ces erreurs est survenue en chemin, auquel cas la page reste affichée
*/
$erreurs_traitement = [];
$message_succes = '';
$idP_succes = 0;

$is_form_submitted = isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok';


if ($is_form_submitted)
{
	foreach ($champs as $c => $v)
	{
        if (isset($_POST[$c]))
        {
            $champs[$c] = $_POST[$c];
        }
    }

	/*
	 * Rattachements : une seule liste déroulante pour les lieux et les organisateurs (#102).
	 *
	 * Les valeurs postées portent leur type — « lieu:42 », « orga:17 » — parce que les deux
	 * tables ont chacune leurs identifiants, qui se recouvrent : c'est le type, comparé ici à
	 * deux noms littéraux, qui décide de la table, jamais la saisie.
	 *
	 * Le reste du traitement continue de raisonner sur $champs['lieu'] et
	 * $champs['organisateurs'] : ce sont deux tables distinctes à écrire, et les boucles qui
	 * composent l'INSERT et l'UPDATE de `personne` les sautent par ces noms-là.
	 */
	$champs['lieu'] = '';
	$champs['organisateurs'] = [];
	$lieux_postes = 0;

	// Les rattachements en base, pour le ré-affichage en lecture seule ; ils ne sont pas réécrits.
	if (!$canEditAffiliations && isset($get['idP']))
	{
		$affiliations_stockees = $getUserAffiliationsIds((int) $get['idP']);
		$champs['lieu'] = $affiliations_stockees['lieu'] > 0 ? $affiliations_stockees['lieu'] : '';
		$champs['organisateurs'] = $affiliations_stockees['organisateurs'];
	}

	foreach ($canEditAffiliations ? (array) ($_POST['affiliations'] ?? []) : [] as $valeur)
	{
		if (!is_scalar($valeur))
		{
			continue;
		}

		[$type, $id] = array_pad(explode(':', (string) $valeur, 2), 2, '');

		if ((int) $id <= 0)
		{
			continue;
		}

		if ($type === 'lieu')
		{
			$lieux_postes++;
			$champs['lieu'] = (int) $id;
		}
		else if ($type === 'orga')
		{
			$champs['organisateurs'][] = (int) $id;
		}
	}

	/*
	 * Un seul lieu, autant d'organisateurs qu'on veut : la table `affiliation` ne porte
	 * qu'une ligne « lieu » par personne, et $_SESSION['Saffiliation_lieu'] qu'un seul
	 * identifiant — le second choisi écraserait le premier sans rien dire.
	 */
	if ($lieux_postes > 1)
	{
		$verif->setErreur("affiliations", "Vous ne pouvez rattacher le compte qu'à un seul lieu ; les organisateurs, eux, peuvent être plusieurs.");
	}

	$ev_defaults = UserSettings::sanitizeEventNewDefaults([
		'genre' => $_POST['ev_defaults_genre'] ?? '',
		'horaire_debut' => $_POST['ev_defaults_horaire_debut'] ?? '',
		'horaire_fin' => $_POST['ev_defaults_horaire_fin'] ?? '',
		'idLieu' => $_POST['ev_defaults_idLieu'] ?? 0,
		'idOrganisateurs' => $_POST['ev_defaults_organisateurs'] ?? [],
		'prix' => $_POST['ev_defaults_prix'] ?? '',
	], EventCategory::selectable(EventCategory::isEnabled()));

	// Les selects et les <input type="time"> n'offrent que des valeurs valides : sanitize ci-dessus
	// ramène silencieusement à vide ce qui ne l'est pas. Les horaires font exception et sont
	// signalés, parce qu'un navigateur sans support de type="time" laisse saisir n'importe quoi.
	foreach (['horaire_debut' => 'de début', 'horaire_fin' => 'de fin'] as $cle => $libelle)
	{
		$horaire_saisi = isset($_POST['ev_defaults_' . $cle]) && is_string($_POST['ev_defaults_' . $cle]) ? trim($_POST['ev_defaults_' . $cle]) : '';

		if (!UserSettings::estHoraireValide($horaire_saisi))
		{
			$verif->setErreur('ev_defaults_' . $cle, "L'horaire " . $libelle . " par défaut doit être au format hh:mm");
		}
	}

	$verif->valider($champs['pseudo'], "pseudo", "texte", 2, 50, 0);

	/*
	 * Les non admin ne peuvent rendre admin quelqu'un
	 */
	if (!empty($champs['groupe']) && $_SESSION['Sgroupe'] > UserLevel::SUPERADMIN && $champs['groupe'] == UserLevel::SUPERADMIN) {
		$verif->setErreur("groupe", "Vous ne pouvez pas attribuer ce groupe");
	}

	/*
	 * Modification du mot de passe
	 * Le mot de passe ancien doit être tapé
	 */
	if ($_SESSION['Sgroupe'] > UserLevel::SUPERADMIN) {
		if ($get['action'] == "update" && (!empty($champs['newPass']) || !empty($champs['newPass2'])))
		{
			$verif->valider($champs['motdepasse'], "motdepasse", "texte", 6, 30, 1);
        }

		if ($get['action'] == "update" && !empty($champs['motdepasse']) && (empty($champs['newPass']) || empty($champs['newPass2'])))
		{
			$verif->setErreur("nouveaux_pass", "Vous devez écrire le nouveau mot de passe et le confirmer");
		}

		if ($get['action'] == "update" && empty($champs['motdepasse']) && (!empty($champs['newPass']) || !empty($champs['newPass2'])))
		{
			$verif->setErreur("motdepasse", "Vous devez entrer le mot de passe actuel");
		}
	}

	/*
	 * Pour un nouveau membre ou si un nouveau mot de passe a été tapé en update
	 * Vérification des 2 mots de passe, existants, min 4 car. et valables
	 */
	if ($get['action'] == "insert" || (!empty($champs['newPass']) || !empty($champs['newPass2'])) )
	{
		if (strcmp((string) $champs['newPass'], (string) $champs['newPass2']) != 0)
		{
			$verif->setErreur("nouveaux_pass", 'Les 2 mots de passe doivent être identiques.');
		}
		else
		{
            // La règle « au moins 1 chiffre » a été retirée ici comme dans PasswordPolicy,
            // dont cette page recopie les contrôles avec ses propres bornes (8/30).
            $verif->valider($champs['newPass'], "newPass", "texte", 8, 30, 1);
            $verif->valider($champs['newPass2'], "newPass2", "texte", 8, 30, 1);
		}
	}

    /*
     * Liste des mots de passe les plus courants (resources/bad_p.txt). Cette page garde
     * ses propres bornes de longueur (8/30, contre 10/100 à l'inscription et à la
     * réinitialisation) : les aligner est un changement de règle à traiter à part.
     */
    if (!empty($champs['newPass']) && PasswordPolicy::estRefuse((string) $champs['newPass']))
    {
        $verif->setErreur("nouveaux_pass", "Ce mot de passe est trop courant ou trop facile à deviner, veuillez en choisir un autre.");
    }

	$verif->valider($champs['email'], "email", "email", 4, 250, 1);
    // 250 comme la colonne, et comme l'inscription qui y dépose la déclaration : à 60, une
    // déclaration un peu longue passait à l'inscription puis bloquait toute modification du profil
    $verif->valider($champs['affiliation'], "affiliation", "texte", 2, 250, 0);

	/*
	 * Si l'affiliation texte et l'affiliation lieu ont été choisies
	 */
//	if ($champs['avec_affiliation'] == 'oui' && (empty($champs['affiliation']) && empty($champs['lieu']) && empty($champs['organisateurs']))) {
//        $verif->setErreur("avec_affiliation", "Vous devez choisir une affiliation");
//    }

    /*
	 * En cas d'ajout, vérification si le profil n'existe pas déjà
	 */
	if ($get['action'] == 'insert')
	{
		/*
		 * Deux contrôles distincts : une seule requête cherchait le pseudo OU l'adresse, et
		 * posait les deux messages ensemble — celui du pseudo s'affichait donc pour une
		 * adresse déjà prise. Le pseudo, devenu facultatif, n'est contrôlé que s'il est
		 * renseigné : vide, il est partagé par tous les comptes qui n'en ont pas.
		 */
		if ($champs['pseudo'] !== '' && Personne::pseudoExists((string) $champs['pseudo']))
		{
			// messages rendus tels quels par getHtmlErreur() et getErreur() : la saisie s'y échappe
			$verif->setErreur("pseudoIdentique", "Un membre ".sanitizeForHtml($champs['pseudo'])." existe déjà dans la base.");
		}

		/*
		 * Un nom d'utilisateur en forme d'adresse rendrait ambiguë la saisie de connexion :
		 * Sentry::checkLogin() cherche alors « pseudo OU email », et deux comptes peuvent
		 * répondre — dont un sans pseudo, qui n'a plus alors aucun moyen de se connecter.
		 * Contrôlé à la création seulement : des comptes anciens portent de tels pseudos, et
		 * les refuser en modification les empêcherait d'enregistrer quoi que ce soit.
		 */
		if ($champs['pseudo'] !== '' && filter_var((string) $champs['pseudo'], FILTER_VALIDATE_EMAIL) !== false)
		{
			$verif->setErreur("pseudo", "Le nom d'utilisateur ne peut pas être une adresse e-mail.");
		}

		if (Personne::emailExists((string) $champs['email']))
		{
			$verif->setErreur("emailIdentique", "L'adresse ".sanitizeForHtml($champs['email'])." est déjà celle d'un compte.");
		}

	/*
	 * En cas de mise à jour, vérification si le mot de passe ancien est correcte avec celui de la session
	 */
	}
	elseif ($get['action'] == "update" && !empty($champs['motdepasse']) && empty($erreurs['motdepasse']))
	{
		/*
		 * Le compte est désigné par son idPersonne. La requête cherchait le pseudo porté par
		 * la session : il devient facultatif, et deux comptes sans pseudo auraient fait
		 * vérifier le mot de passe du premier venu. Concaténé tel quel, il laissait de plus
		 * un pseudo bien choisi réécrire la requête.
		 *
		 * Double vérification, comme Sentry::checkLogin() : sha1 salé historique, puis password_hash().
		 */
		$stmt = $connectorPdo->prepare("SELECT mot_de_passe, gds FROM personne WHERE idPersonne = ?");
		$stmt->execute([(int) $_SESSION['SidPersonne']]);
		$tab_user = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($tab_user === false
			|| (!hash_equals((string) $tab_user['mot_de_passe'], sha1($tab_user['gds'] . sha1((string) $champs['motdepasse'])))
				&& !password_verify((string) $champs['motdepasse'], (string) $tab_user['mot_de_passe'])))
		{
			$verif->setErreur("motdepasse", "Faux mot de passe");
		}
	}

	/*
	 * Unicité de l'adresse en modification. Hors du if/elseif ci-dessus, dont la branche
	 * « update » est occupée par le contrôle du mot de passe, alors que l'adresse est à
	 * vérifier dans tous les cas. Seulement si elle change : un compte dont l'adresse est
	 * déjà partagée — il en reste des centaines en production — ne pourrait sinon plus rien
	 * enregistrer, pas même son mot de passe.
	 */
	if ($get['action'] == "update" && isset($get['idP']))
	{
		$stmt = $connectorPdo->prepare("SELECT email FROM personne WHERE idPersonne = ?");
		$stmt->execute([(int) $get['idP']]);
		$email_en_base = (string) $stmt->fetchColumn();

		if (mb_strtolower(trim((string) $champs['email'])) !== mb_strtolower($email_en_base)
			&& Personne::emailExists((string) $champs['email'], (int) $get['idP']))
		{
			$verif->setErreur("emailIdentique", "Cette adresse e-mail est déjà celle d'un autre compte.");
		}
	}

    // La clé est "global", rendue sous le décompte des erreurs pour tous les niveaux — sous
    // "pseudo", le refus ne s'affichait qu'aux superadmins, seuls à voir les erreurs du login.
    if (!SecurityToken::check($_POST['token'] ?? '', $_SESSION['token'] ?? ''))
    {
        $verif->setErreur("global", "Le système de sécurité du site n'a pu authentifier votre action. Veuillez réafficher ce formulaire et réessayer");
    }

	if ($verif->nbErreurs() === 0)
	{
		$affiliations_avant = ['lieu' => 0, 'organisateurs' => []];

		if ($get['action'] == 'update' && isset($get['idP']))
		{
			$affiliations_avant = $getUserAffiliationsIds((int) $get['idP']);
		}

		if (!empty($champs['newPass']))
		{
			$champs['gds'] = '';
            $champs['mot_de_passe'] = password_hash($champs['newPass'], PASSWORD_DEFAULT);
		}

		if ($_SESSION['Sgroupe'] > UserLevel::SUPERADMIN) {
			$champs['groupe'] = $_SESSION['Sgroupe'];
			$champs['pseudo'] = $_SESSION['user'];
		}

		/*
		 * Colonnes que le formulaire n'a pas montrées : la base fait foi.
		 *
		 * Un champ absent du formulaire ne poste rien, et la boucle de l'UPDATE écrit une colonne
		 * par clé de $champs — l'affiliation et la signature d'un compte rétrogradé auraient été
		 * remises à leur valeur par défaut au premier enregistrement, sans un mot.
		 */
		if (!$areContributorFieldsEnabled && $get['action'] == 'update' && isset($get['idP']))
		{
			$stmt = $connectorPdo->prepare("SELECT affiliation, signature, avec_affiliation FROM personne WHERE idPersonne = ?");
			$stmt->execute([(int) $get['idP']]);
			$storedValues = $stmt->fetch(PDO::FETCH_ASSOC);

			if ($storedValues !== false)
			{
				$champs['affiliation'] = $storedValues['affiliation'];
				$champs['signature'] = $storedValues['signature'];
				$champs['avec_affiliation'] = $storedValues['avec_affiliation'];
			}
		}

		// Le fieldset « Événements » n'est rendu qu'en édition, et qu'aux comptes qui ajoutent des
		// événements : on ne touche à la colonne que là.
		// Comme pour mot_de_passe et gds juste au-dessus, ajouter la clé à $champs suffit à ce que
		// la boucle de génération de l'UPDATE écrive la colonne du même nom. La relecture préalable
		// préserve les réglages d'un autre domaine que ce formulaire n'expose pas.
		if ($areContributorFieldsEnabled && $get['action'] == 'update' && isset($get['idP']))
		{
			$champs['settings'] = UserSettings::withEventNewDefaults(
				Personne::getSettingsJson((int) $get['idP']),
				$ev_defaults
			);
		}

		/*
		* Insertion dans la base : INSERT
		*/
		if ($get['action'] == 'insert')
		{
			$sql_insert_attributs = "";
			$sql_insert_valeurs = "";

			foreach ($champs as $c => $v)
			{
				if ($c != "motdepasse" && $c != "newPass" && $c != "newPass2" && $c != "lieu" && $c != 'organisateurs')
				{
					$sql_insert_attributs .= $c.", ";
					$sql_insert_valeurs .= "'".$connector->sanitize($v)."', ";
				}
			}

			$sql_insert_attributs .= "dateAjout, date_derniere_modif";
			$sql_insert_valeurs .= "'".date("Y-m-d H:i:s")."', '".date("Y-m-d H:i:s")."'";

			$sql_insert =  "INSERT INTO personne (".$sql_insert_attributs.") VALUES (".$sql_insert_valeurs.")";

			// composer psalm:taint signale ici un TaintedSql : c'est un faux positif. Les
			// noms de colonnes viennent des clés littérales de $champs (déclarées en haut
			// de ce fichier), jamais de $_POST — seules les *valeurs* sont écrasées, et
			// elles passent par sanitize(). Psalm ne distingue pas clé et valeur dans
			// `$champs[$c] = $_POST[$c]` et teinte donc aussi les clés.
			$req_insert = $connector->query($sql_insert);
			$req_id = $connector->getInsertId();

			//si un lieu a été choisi comme affiliation
			if (!empty($champs['lieu']))
            {
				$req_insAff = $connector->query("INSERT INTO affiliation
				(idPersonne, idAffiliation, genre) VALUES ('" . (int) $req_id . "','" . (int)$champs['lieu'] . "','lieu')");
            }

			/*
			* Insertion réussie : message OK et redirection vers la fiche du nouveau profil
			*/
			if ($req_insert)
			{
				$message_succes = "Personne ajoutée dans le groupe " . sanitizeForHtml($champs['groupe']);
				$idP_succes = (int) $req_id;
			}
			else
			{
				$erreurs_traitement[] = "La requête INSERT dans 'personne' a échoué";
			}
		}
		elseif ($get['action'] == 'update')
		{

			$sql_update = "UPDATE personne SET ";

			foreach ($champs as $c => $v)
			{
				if ($c != "motdepasse" && $c != "newPass" && $c != "newPass2" && $c != "lieu" && $c != 'organisateurs')
				{
					$sql_update .= $connector->sanitize($c)."='".$connector->sanitize($v)."', ";
				}
			}

			$sql_update .= "date_derniere_modif='".date("Y-m-d H:i:s")."'";
			$sql_update .= " WHERE idPersonne=" . (int) $get['idP'];

            $req_update = $connector->query($sql_update);

            //trouve si la personne a déjà une affiliation à un lieu
			$connector->query("SELECT idPersonne FROM affiliation WHERE idPersonne=" . (int) $get['idP']);

            //si la nouvelle affiliation est un lieu, update s'il en a déjà une, insert sinon
            // sans droit sur les rattachements, $champs['lieu'] est celui de la base : rien à réécrire
			if ($canEditAffiliations && !empty($champs['lieu']))
            {
				if ($connector->getAffectedRows() > 0)
				{
					$aff = "UPDATE affiliation SET idAffiliation='" . (int) $champs['lieu'] . "'
					WHERE idPersonne=" . (int) $get['idP'] . " AND genre='lieu'";
                }
				else
				{
					$aff = "INSERT INTO affiliation (idPersonne, idAffiliation, genre)
					VALUES ('" . (int) $get['idP'] . "','" . (int) $champs['lieu'] . "','lieu')";
                }

				if (!$connector->query($aff))
				{
					$erreurs_traitement[] = "La requête INSERT ou UPDATE dans 'affiliation' a échoué";
				}

			//si la nouvelle affiliation n'est pas un lieu elle ira dans la table 'personne',
			// et effacement de l'ancienne dans la table affiliation,
			}
			else
			{
				if ($canEditAffiliations && $connector->getAffectedRows() > 0)
				{
					$connector->query("DELETE FROM affiliation WHERE idPersonne=" . (int) $get['idP'] . " AND genre='lieu'");
                }
			}

			/*
			* MAJ réussie -> MAJ des infos perso de session si la personne s'autoédite
			*/
			if ($req_update)
			{
				if (!empty($champs['mot_de_passe']))
				{
					$videur->revokeRememberedDevices((int) $get['idP']);
				}

				if ($_SESSION['SidPersonne'] == $get['idP'])
				{

					$_SESSION["user"] = $champs['pseudo'];

					if (isset($champs['mot_de_passe']) && !empty($champs['mot_de_passe']))
					{
						// sans quoi checkSession() invaliderait la session à la requête suivante
						$_SESSION['pass_fingerprint'] = Sentry::passFingerprint($champs['mot_de_passe']);
					}

					if (!empty($champs['groupe']))
					{
						$_SESSION['Sgroupe'] = $champs['groupe'];
					}

                    if (!empty($champs['lieu']))
                    {
						$_SESSION["Saffiliation_lieu"] = $champs['lieu'];
					}

					$message_succes = "Votre profil a été modifié";
				}
				else
				{
					$message_succes = "Le profil a été modifié";
                }

                $logger->info('[user-edit] user updated', ['pseudo' => $champs['pseudo'], 'idP' => (int) $get['idP'], 'by' => (int) $_SESSION['SidPersonne']]);

                // Le couple efface-puis-réinsère ne vaut que si le select des organisateurs a
                // été rendu : sans lui, le POST n'en porte aucun et l'effacement serait sec.
                if ($canEditAffiliations)
                {
                    $sqld = "DELETE FROM personne_organisateur WHERE idPersonne=" . (int) $get['idP'];
                    $connector->query($sqld);
                }
				$req_id = $get['idP'];
				$idP_succes = (int) $get['idP'];
			}
			else
			{
				$erreurs_traitement[] = "La requête UPDATE dans 'personne' a échoué";
			}
		} //if action

		/*
		 * Les deux gardes d'autrefois — un tableau, des valeurs non vides — n'ont plus d'objet :
		 * l'analyse du POST ne retient que des entiers positifs préfixés « orga: ». Le tableau
		 * peut en revanche être vide, tout désélectionner ne postant rien, et le DELETE qui
		 * précède a alors suffi. Sans droit sur les rattachements, le tableau vient de la base
		 * et rien n'a été effacé : le réinsérer doublerait les lignes.
		 */
		if ($canEditAffiliations && $champs['organisateurs'] !== [])
		{
			foreach ($champs['organisateurs'] as $idOrg)
			{
				$connector->query("INSERT INTO personne_organisateur (idPersonne, idOrganisateur)
					VALUES (" . (int) $req_id . ", " . (int) $idOrg . ")");
			}

            $logger->info('[user-edit] organisateurs updated', ['idP' => (int) $req_id, 'idOrganisateurs' => $champs['organisateurs']]);
        }

		/*
		 * Information du rattachement.
		 *
		 * L'inscription ne rattache plus personne à un lieu ou à un organisateur : elle
		 * enregistre une déclaration, que l'équipe vérifie, et c'est ici que le compte gagne
		 * ces droits. La personne ne l'apprendrait pas autrement.
		 *
		 * La case dit l'intention, la comparaison décide : elle n'est volontairement pas une
		 * clé de $champs, dont la boucle de l'UPDATE écrit une colonne par clé.
		 */
		if ($get['action'] == 'update' && isset($get['idP']) && !empty($_POST['notify_affiliation']) && $erreurs_traitement === [])
		{
			$affiliations_apres = $getUserAffiliationsIds((int) $get['idP']);

			if ($affiliations_apres !== $affiliations_avant)
			{
				$noms = [];

				if ($affiliations_apres['lieu'] > 0)
				{
					$stmt = $connectorPdo->prepare("SELECT nom FROM lieu WHERE idLieu = ?");
					$stmt->execute([$affiliations_apres['lieu']]);
					$nom_lieu = $stmt->fetchColumn();

					if ($nom_lieu !== false)
					{
						$noms[] = (string) $nom_lieu;
					}
				}

				if ($affiliations_apres['organisateurs'] !== [])
				{
					list($orgas_clause, $orgas_params) = $connectorPdo->buildInClause('idOrganisateur', $affiliations_apres['organisateurs']);
					$stmt = $connectorPdo->prepare("SELECT nom FROM organisateur WHERE $orgas_clause ORDER BY nom");
					$stmt->execute($orgas_params);
					$noms = array_merge($noms, $stmt->fetchAll(PDO::FETCH_COLUMN));
				}

				$mailer = new Mailing();
				$envoye = $mailer->toUser(
					(string) $champs['email'],
					"Votre compte sur La décadanse",
					$tplEngine->render('user-affiliation-mail-body', [
						'affiliations' => $noms === [] ? '' : implode(', ', $noms),
						'dashboard_url' => $site_full_url . "user/dashboard.php?idP=" . (int) $get['idP'],
					])
				);

				$message_succes .= $envoye
					? " La personne en a été informée par e-mail."
					: " L'e-mail d'information n'a pas pu être envoyé.";

				$logger->info('[user-edit] affiliation notified', ['idP' => (int) $get['idP'], 'envoye' => $envoye]);
			}
		}

		/*
		* Enregistrement terminé : le message part en session et l'utilisateur rejoint sa fiche.
		* Placé après l'écriture des organisateurs, la dernière du traitement.
		*/
		if ($message_succes !== '' && $erreurs_traitement === [])
		{
			$_SESSION['user_flash_msg'] = $message_succes;
			header("Location: /user/dashboard.php?idP=" . $idP_succes);
			die();
		}

	} // if erreurs == 0
} // if POST != ""

// fill user $champs, $ev_defaults
if ($get['action'] == 'editer' && isset($get['idP']))
{
	$req_pers = $connector->query("SELECT * FROM personne WHERE idPersonne = " . (int) $get['idP']);
    if ($tab_pers = $connector->fetchArray($req_pers))
	{
		foreach ($tab_pers as $n => $v)
		{
			$champs[$n] = $v;
		}
	}
	else
    {
        $http_error = [404, 'Not Found', "Cet utilisateur n'existe pas ou plus"];
        include(__DIR__ . "/_erreur_http.inc.php");
        exit;
	}

	// $champs vient de recevoir toutes les colonnes de personne, dont settings.
	$ev_defaults = UserSettings::eventNewDefaults(is_string($champs['settings'] ?? null) ? $champs['settings'] : null);

	$req_aff = $connector->query("SELECT idAffiliation FROM affiliation WHERE idPersonne=" . (int) $get['idP'] . " AND genre='lieu'");
    if ($tab_aff = $connector->fetchArray($req_aff))
	{
		$champs['lieu'] = $tab_aff['idAffiliation'];
	}

}

$user_levels = UserLevel::getConstants();


/*
* Rattachements présélectionnés, sous la forme que poste la liste : « lieu:42 », « orga:17 »
* $champs['lieu'] porte déjà le bon identifiant dans les deux cas : le POST analysé plus haut
* ou la table affiliation relue à l'ouverture du formulaire
*/
if ($areContributorFieldsEnabled)
{
    $affiliations_pers = [];

    if (!empty($champs['lieu']))
    {
        $affiliations_pers[] = 'lieu:' . (int) $champs['lieu'];
    }

    // au ré-affichage après une erreur, la sélection postée fait foi À ELLE SEULE
    if ($is_form_submitted)
    {
        foreach ((array) $champs['organisateurs'] as $idOrg)
        {
            $affiliations_pers[] = 'orga:' . (int) $idOrg;
        }
    }
    elseif ($is_edit_mode)
    {
        $req = $connector->query("SELECT idOrganisateur FROM personne_organisateur
            WHERE idPersonne=" . (int) $get['idP']);

        while ($tab = $connector->fetchArray($req))
        {
            $affiliations_pers[] = 'orga:' . (int) $tab['idOrganisateur'];
        }
    }

    $tri_nom = "TRIM(LEADING 'L\'' FROM (TRIM(LEADING 'Les '
                            FROM (TRIM(LEADING 'La ' FROM (TRIM(LEADING 'Le ' FROM nom))))))) COLLATE utf8mb4_unicode_ci";
    $req_lieux_select = $connector->query("SELECT idLieu, nom FROM lieu WHERE statut='actif' ORDER BY " . $tri_nom);
    $req_orgas_select = $connector->query("SELECT idOrganisateur, nom FROM organisateur WHERE statut='actif' ORDER BY " . $tri_nom);

}

// =============================================================================
// Rendu
// =============================================================================

$page_titre = $get['action'] . " un compte";
$extra_css = ["formulaires"];
include("_header.inc.php");
?>

<main id="contenu" class="colonne user-edit">

    <?php
    $h1 = "Ajouter une personne";
    $act = 'insert';
    if ($get['action'] == 'editer' || $get['action'] == 'update')
    {
        $act = "update&amp;idP=". (int) ($get['idP'] ?? 0);
        $h1 = "Modification du compte";
    }
    ?>

    <header id="entete_contenu">
        <h1><?= $h1 ?></h1>
        <div class="spacer"></div>
    </header>

    <?php
    if ($verif->nbErreurs() > 0)
    {
        HtmlShrink::msgErreur("Il y a ".$verif->nbErreurs()." erreur(s)");
        // les erreurs qu'aucun champ ne porte, comme le refus du jeton CSRF
        echo $verif->getHtmlErreur("global");
    }

    foreach ($erreurs_traitement as $erreur_traitement)
    {
        HtmlShrink::msgErreur($erreur_traitement);
    }

    // n'arrive que si une erreur ci-dessus a retenu la redirection
    if ($message_succes !== '')
    {
        HtmlShrink::msgOk($message_succes);
    }
    ?>


    <form method="post" id="ajouter_editer" enctype="multipart/form-data" class="js-submit-freeze-wait"
          action="<?= basename(__FILE__)."?action=".$act; ?>" >

        <p>* indique un champ obligatoire</p>

        <?php if ($is_superadmin && $is_edit_mode && isset($get['idP'])) : ?>

            <fieldset>
                <legend>Statut</legend>
                <ul class="radio">
                <?php foreach (Personne::$statuts as $s) : ?>
                    <li class="listehoriz">
                        <input type="radio" name="statut" value="<?= sanitizeForHtml($s) ?>"
                            <?php if ($s == $champs['statut']) : ?> checked="checked" <?php endif; ?>
                            id="statut_<?= sanitizeForHtml($s) ?>" class="radio_horiz" />
                        <label for="statut_<?= sanitizeForHtml($s) ?>" class="continu"><?= sanitizeForHtml($s) ?></label>
                    </li>
                <?php endforeach ?>
                </ul>
                <?= $verif->getHtmlErreur("statut") ?>
            </fieldset>

        <?php else : ?>
            <input type="hidden" name="statut" value="actif" id="statut_actif" title="statut" />
        <?php endif; ?>

        <fieldset>

            <legend>Compte</legend>
            <p>
                <label for="pseudo">Nom d'utilisateur</label>
                <?php if ($is_superadmin) : ?>
                    <input type="text" name="pseudo" id="pseudo" size="30" maxlength="80" value="<?= sanitizeForHtml($champs['pseudo']) ?>" />
                    <?= $verif->getHtmlErreur('pseudo') ?>
                    <?= $verif->getHtmlErreur("pseudoIdentique") ?>
                <?php elseif (trim((string) $champs['pseudo']) !== '') : ?>
                    <input type="text" name="pseudo" id="pseudo" size="30" maxlength="80" value="<?= sanitizeForHtml($champs['pseudo']) ?>" readonly style="background:#f4f4f4" />
                <?php else : ?>
                    <?php /* Ni champ ni valeur : ce compte n'a pas de nom d'utilisateur */ ?>
                    <span class="guideChamp">aucun&nbsp;: c'est votre e-mail qui vous identifie</span>
                <?php endif; ?>
            </p>

            <p>
                <label for="email">E-mail*</label>
                <input type="email" name="email" id="email" size="40" maxlength="100" value="<?= sanitizeForHtml(stripslashes((string) $champs['email'])) ?>" required />
                <?= $verif->getHtmlErreur("email") ?>
                <?= $verif->getErreur("emailIdentique") ?>
            </p>

            <?php if ($is_superadmin) : ?>
                <p>
                    <label for="groupe">Groupe* :</label>
                    <select name="groupe" id="groupe">
                        <?php foreach ($user_levels as $nom => $id) : ?>
                            <?php /* à l'ajout, sans quoi le navigateur retient la première option : SUPERADMIN */ ?>
                            <option <?php if ($id == $champs['groupe'] || (!$is_edit_mode && $champs['groupe'] == '' && $id == UserLevel::ACTOR)) : ?>selected="selected"<?php endif ?> value="<?= (int) $id ?>">
                                <?= (int) $id ?> : <?= sanitizeForHtml($nom) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <?= $verif->getHtmlErreur("groupe") ?>
                </p>
            <?php endif; ?>
        </fieldset>

        <fieldset>
            <legend>Mot de passe</legend>

                <?php if (!$is_superadmin && $is_edit_mode) : ?>

                    <div class="guideForm">À remplir si vous souhaitez modifier votre mot de passe actuel</div>
                    <p>
                        <label for="motdepasse">Actuel</label>
                        <input type="password" name="motdepasse" id="motdepasse" size="30" value="" autocomplete="off" />
                        <?php echo $verif->getHtmlErreur("motdepasse"); ?>
                    </p>

                <?php endif ?>

                <!-- Nouveau mot de passe et confirmation en cas de mise à jour -->
                <p>
                    <label for="newPass">Nouveau<?php if (!$is_edit_mode) { echo "*"; } ?></label>
                    <input type="password" name="newPass" id="newPass" size="30" value="" autocomplete="new-password"  />
                    <?= $verif->getHtmlErreur("newPass");?>
                </p>
                <p>
                    <label for="newPass2">Confirmer le nouveau<?php if (!$is_edit_mode) { echo "*"; } ?></label>
                    <input type="password" name="newPass2" id="newPass2" size="30" value="" autocomplete="new-password" />
                    <?= $verif->getHtmlErreur("newPass2");?>
                </p>
                <?= $verif->getHtmlErreur("nouveaux_pass");?>
            </fieldset>

            <?php
            // Affiliations et signature des événements ajoutés : deux fieldsets qui ne parlent qu'aux comptes qui en ajoutent
            if ($areContributorFieldsEnabled) {
            ?>

                <fieldset id="references">

                    <legend>Affiliation(s)</legend>

                    <?php if ($canEditAffiliations) : ?>
                    <p>
                        <label for="affiliations">Lieu et organisateurs</label>
                        <?php /* Un seul lieu peut être retenu, autant d'organisateurs qu'on veut; vérifié au traitement */ ?>
                        <select name="affiliations[]" id="affiliations" multiple class="js-select2-affiliations"
                                data-placeholder="Tapez un nom de lieu ou d'organisateur..." style="max-width:350px;">
                            <optgroup label="Lieux">
                            <?php
                            while ($lieuTrouve = $connector->fetchArray($req_lieux_select))
                            {
                                $valeur = 'lieu:' . (int) $lieuTrouve['idLieu'];
                                echo '<option value="' . $valeur . '"' . (in_array($valeur, $affiliations_pers, true) ? ' selected="selected"' : '') . '>'
                                    . sanitizeForHtml($lieuTrouve['nom']) . '</option>';
                            }
                            ?>
                            </optgroup>
                            <optgroup label="Organisateurs">
                            <?php
                            while ($tab = $connector->fetchArray($req_orgas_select))
                            {
                                $valeur = 'orga:' . (int) $tab['idOrganisateur'];
                                echo '<option value="' . $valeur . '"' . (in_array($valeur, $affiliations_pers, true) ? ' selected="selected"' : '') . '>'
                                    . sanitizeForHtml($tab['nom']) . '</option>';
                            }
                            ?>
                            </optgroup>
                        </select>
                        <?= $verif->getHtmlErreur("affiliations"); ?>
                    </p>

                    <div class="guideChamp" style='padding: 0em 0 0.2em 175px;'>
                        Plusieurs organisateurs, un seul lieu possibles.<br>Le compte pourra modifier leurs fiches et les événements qui s'y rattachent
                    </div>

                    <p>
                        <label for="affiliation">Si pas dans la liste</label>
                        <input type="text" name="affiliation" id="affiliation" size="30" maxlength="250" value="<?= sanitizeForHtml($champs['affiliation']); ?>" />
                        <?= $verif->getHtmlErreur("affiliation"); ?>
                    </p>

                    <div class="guideChamp" style="padding: 0em 0 0.2em 175px;">Signe aussi les événements quand la signature (voir ci-dessous) le demande</div>

                    <?php
                    /*
                     * L'inscription de l'utilisateur enregistre une déclaration, puis l'équipe la vérifie
                     * et peut ici lier le compte et enfin notifier l'utilisateur
                     */
                    if ((int) ($_SESSION['SidPersonne'] ?? 0) !== (int) ($get['idP'] ?? 0)) :
                    ?>
                    <p>
                        <label for="notify_affiliation">Prévenir par e-mail du rattachement</label>
                        <input type="checkbox" name="notify_affiliation" id="notify_affiliation" value="1" class="checkbox" />
                    </p>
                    <?php endif; ?>

                <?php else : ?>

                    <div class="guideForm">Si vous souhaitez modifier ces informations merci de nous <a href="/misc/contacteznous.php">contacter</a></div>

                    <?php if (!empty($champs['affiliation'])) : ?>
                        <p>
                            <label></label><input name="affiliation" type="text" readonly value="<?= sanitizeForHtml($champs['affiliation']); ?>" >
                        </p>
                    <?php endif ?>

                    <?php
                    if (!empty($champs['lieu']))
                    {
                        $req_lieux = $connector->query("SELECT nom FROM lieu WHERE idLieu=".(int)$champs['lieu']);
                        $lieuTrouve = $connector->fetchArray($req_lieux);
                        ?>
                            <p>
                            <label>Lieu</label>
                            <ul style="float:left;margin:0;padding-left:1em;">
                                <li>
                                    <a href="/lieu/lieu.php?idL=<?= (int)$champs['lieu']; ?>"><?= sanitizeForHtml($lieuTrouve['nom']); ?></a>
                                </li>
                            </ul><div class="spacer"><!-- --></div>
                        </p>
                        <div class="guideChamp" style='padding: 0em 0 0.2em 175px;'>Vous pouvez modifier les informations de ce lieu et tous les événements qui s'y déroulent</div>
                    <?php
                    }

                    $sql = "SELECT organisateur.idOrganisateur, nom
                        FROM organisateur, personne_organisateur
                        WHERE personne_organisateur.idPersonne=" . (int) $get['idP'] . " AND
                         organisateur.idOrganisateur=personne_organisateur.idOrganisateur
                         ORDER BY date_ajout DESC";

                    $req = $connector->query($sql);

                    if ($connector->getNumRows($req))
                    {
                         ?>
                        <p>
                            <label>Organisateur(s)</label>
                            <ul style="float:left;margin:0;padding-left:1em;">
                                <?php
                                while ($tab = $connector->fetchArray($req))
                                {
                                    ?>
                                <li><a href="/organisateur/organisateur.php?idO=<?= (int)$tab['idOrganisateur']; ?>"><?= sanitizeForHtml($tab['nom']); ?></a>
                                    </li>
                                    <?php
                                }
                           ?>
                            </ul>
                            <div class="spacer"><!-- --></div>
                        </p>
                        <div class="guideChamp" style='padding: 0em 0 0.2em 175px;'>
                            Vous pouvez modifier la fiche de ces organisateurs, tous les événements qui y sont associés ainsi que les lieux associés à ces organisateurs
                        </div>

                    <?php
                    }
                    ?>

                <?php endif ?>
                <?= $verif->getHtmlErreur("affiliation"); ?>
                <?= $verif->getHtmlErreur("doublon_organisateur"); ?>
            </fieldset>



        <fieldset>

            <legend>Votre signature</legend>

            <div class="guideForm">Apparait sous les événements que vous avez ajoutés</div>
            <label style="display:block;float:none;width:8em" >Afficher :</label>
            <ul class="radio" style="display:block">
                <?php
                // Un compte sans nom d'utilisateur n'a rien à signer : l'option n'affichait
                // qu'un libellé suivi de rien.
                $signatures = trim((string) $champs['pseudo']) !== ''
                    ? ["pseudo" => "Le nom d'utilisateur", "aucune" => "Aucune signature"]
                    : ["aucune" => "Aucune signature"];
                foreach ($signatures as $s => $label)
                {
                    $coche = '';
                    if ($s == $champs['signature'])
                    {
                        $coche = 'checked="checked"';
                    }
                    echo '<li style="display:block" >
                    <input type="radio" name="signature" value="'.$s.'" '.$coche.' id="signature_'.$s.'" />
                    <label class="continu" for="signature_'.$s.'">'.$label.' ';

                    if ($s == 'aucune') {
                        echo "";
                    }
                    else
                    {
                        echo ": <b>" . sanitizeForHtml($champs[$s]) . "</b>";
                    }
                    echo '</label>
                    </li>';
                }
                ?>
            </ul>

            <?php
            echo $verif->getHtmlErreur("signature");
            ?>

            <label style="display:block;float:none">avec l'affiliation :</label>

            <ul class="radio" style="display:block;">

                <li style="display:block" >
                    <input type="radio" id="avec_affiliation_oui" name="avec_affiliation" value="oui" class="radio_horiz"
                    <?php
                    if ($champs['avec_affiliation'] == "oui")
                    {
                        echo  ' checked="checked"';
                    }
                    echo "/>";
                    ?>
                    <label class="continu" for="avec_affiliation_oui">oui</label>
                </li>
                <li style="display:block" ><input type="radio" id="avec_affiliation_non" name="avec_affiliation" value="non" class="radio_horiz"
                    <?php
                    if ($champs['avec_affiliation'] == "non")
                    {
                        echo  ' checked="checked"';
                    }
                    echo "/>";
                    ?>
                    <label class="continu" for="avec_affiliation_non">non</label>
                </li>
            </ul>

            <?= $verif->getHtmlErreur('avec_affiliation') ?>

        </fieldset>

        <?php
        } // areContributorFieldsEnabled


        // Réglages du formulaire d'ajout d'événement.
        if ($areContributorFieldsEnabled && $is_edit_mode)
        {
        ?>
        <fieldset>
            <legend>Événements</legend>

            <div class="guideForm">Valeurs par défaut à l'ajout d'un événement</div>
            <p>
                <label for="ev_defaults_genre">Catégorie</label>
                <select name="ev_defaults_genre" id="ev_defaults_genre" style="max-width:350px">
                    <option value=""></option>
                    <?php foreach (EventCategory::selectable(EventCategory::isEnabled()) as $genre_cle => $genre_label) : ?>
                    <option value="<?= sanitizeForHtml($genre_cle) ?>" <?php if ((string) $genre_cle === $ev_defaults['genre']) { echo 'selected="selected"'; } ?>><?= sanitizeForHtml($genre_label) ?></option>
                    <?php endforeach ?>
                </select>
                <?= $verif->getHtmlErreur("ev_defaults_genre"); ?>
            </p>

            <p>
                <label for="ev_defaults_horaire_debut">Début</label>
                <input type="time" name="ev_defaults_horaire_debut" id="ev_defaults_horaire_debut" size="5" value="<?= sanitizeForHtml($ev_defaults['horaire_debut']); ?>" />
                <?= $verif->getHtmlErreur("ev_defaults_horaire_debut"); ?>
            </p>

            <p>
                <label for="ev_defaults_horaire_fin">Fin</label>
                <input type="time" name="ev_defaults_horaire_fin" id="ev_defaults_horaire_fin" size="5" value="<?= sanitizeForHtml($ev_defaults['horaire_fin']); ?>" />
                <?= $verif->getHtmlErreur("ev_defaults_horaire_fin"); ?>
            </p>

            <p>
                <label for="ev_defaults_idLieu">Lieu</label>
                <select name="ev_defaults_idLieu" id="ev_defaults_idLieu" class="js-select2-options-with-style" style="max-width:350px" data-placeholder="">
                    <option value=""></option>
                    <?php
                    // Requête à part : $req_lieux, plus haut, a été consommé jusqu'au bout par le select
                    // d'affiliation. Mêmes lieux et même tri que le formulaire d'ajout d'événement, sans
                    // les salles (le réglage se fait au niveau du lieu).
                    $canton_courant = null;

                    foreach (Lieu::getActifsPourSelect() as $lieu_defaut)
                    {
                        $canton = (string) $lieu_defaut['canton'];

                        if ($canton !== $canton_courant)
                        {
                            if ($canton_courant !== null) { echo '</optgroup>'; }
                            echo '<optgroup label="' . (Localite::CANTONS[$canton] ?? sanitizeForHtml($canton)) . '">';
                            $canton_courant = $canton;
                        }

                        $selectionne = ((int) $lieu_defaut['idLieu'] === $ev_defaults['idLieu']) ? ' selected="selected"' : '';
                        echo '<option value="' . (int) $lieu_defaut['idLieu'] . '"' . $selectionne . '>' . sanitizeForHtml($lieu_defaut['nom']) . '</option>';
                    }

                    if ($canton_courant !== null) { echo '</optgroup>'; }
                    ?>
                </select>
                <?= $verif->getHtmlErreur("ev_defaults_idLieu"); ?>
            </p>

            <p>
                <label for="ev_defaults_organisateurs">Organisateur(s)</label>
                <select name="ev_defaults_organisateurs[]" id="ev_defaults_organisateurs" class="js-select2-options-with-style" multiple data-placeholder="Tapez les noms des organisateurs" style="max-width:350px;">
                    <?php
                    $req_orgas_defaut = $connector->query("
                    SELECT idOrganisateur, nom FROM organisateur WHERE statut='actif' ORDER BY TRIM(LEADING 'L\'' FROM (TRIM(LEADING 'Les ' FROM (TRIM(LEADING 'La ' FROM (TRIM(LEADING 'Le ' FROM nom))))))) COLLATE utf8mb4_unicode_ci"
                    );

                    while ($orga_defaut = $connector->fetchArray($req_orgas_defaut))
                    {
                        $selectionne = in_array((int) $orga_defaut['idOrganisateur'], $ev_defaults['idOrganisateurs'], true) ? ' selected="selected"' : '';
                        echo '<option value="' . (int) $orga_defaut['idOrganisateur'] . '"' . $selectionne . '>' . sanitizeForHtml($orga_defaut['nom']) . '</option>';
                    }
                    ?>
                </select>
                <?= $verif->getHtmlErreur("ev_defaults_organisateurs"); ?>
            </p>

            <p>
                <label for="ev_defaults_prix">Prix</label>
                <input type="text" name="ev_defaults_prix" id="ev_defaults_prix" size="45" maxlength="100" value="<?= sanitizeForHtml($ev_defaults['prix']); ?>" />
                <?= $verif->getHtmlErreur("ev_defaults_prix"); ?>
            </p>
        </fieldset>
        <?php
        }
        ?>

        <p class="piedForm">
            <input type="hidden" name="formulaire" value="ok" />
            <input type="hidden" name="token" value="<?= SecurityToken::getToken(); ?>" />
            <input type="submit" value="Enregistrer" class="submit submit-big" />
        </p>

    </form>

</main>

<div id="colonne_gauche" class="colonne"></div>

<?php
include("_footer.inc.php");
?>
