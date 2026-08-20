<?php
/**
 * Permet d'ajouter une personne ou de la modifier
 * Un admin peut tout modifier, un membre seulement son profile
 *
 * @category   modification d'une table de la base
 * @see personne.php, user/login.php
 * @author     Michel Gaudry <michel@ladecadanse.ch>
 */

require_once("app/bootstrap.php");

use Ladecadanse\UserLevel;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\PasswordPolicy;
use Ladecadanse\Utils\QueryParamValidator;
use Ladecadanse\Security\SecurityToken;
use Ladecadanse\Security\Sentry;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Lieu;
use Ladecadanse\Personne;
use Ladecadanse\UserSettings;

if (!$authorization->checkGroup(UserLevel::ACTOR)) {
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
    header("Location: /user/login.php");
    die();
}

$get['action'] = "ajouter";

/*
* Vérification et attribution des variables d'URL GET
*/
if (isset($_GET['idP']))
{

	$get['idP'] = QueryParamValidator::validateUrlQueryValue($_GET['idP'], "int", 1);
}

if (isset($_GET['action']))
{
	$get['action'] = QueryParamValidator::validateUrlQueryValue($_GET['action'], "enum", 1, $actions);

	if (($_GET['action'] == "ajouter" || $_GET['action'] == 'insert') && $_SESSION['Sgroupe'] > UserLevel::SUPERADMIN) {
		HtmlShrink::msgErreur("Vous n'avez pas le droit d'ajouter une personne");
		exit;
	}
	elseif (($get['action'] == "update" || $get['action'] == "editer") && $_SESSION['SidPersonne'] != $get['idP'] && $_SESSION['Sgroupe'] > UserLevel::SUPERADMIN) {
		HtmlShrink::msgErreur("Vous n'avez pas le droit de modifier cette personne");
		exit;
	}

}
else
{
	echo "Vous devez faire une action";
	exit;
}

$page_titre = $get['action'] . " une personne";
$extra_css = ["formulaires"];

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
* Valeurs par défaut à l'ajout d'un événement (fieldset « Événements »).
*
* Volontairement tenues hors de $champs : les boucles qui construisent l'INSERT et l'UPDATE plus bas
* écrivent une colonne par clé de $champs, et la boucle d'hydratation y recopie $_POST tel quel. Une
* clé "settings" dans $champs reviendrait donc à écrire en base le JSON brut envoyé par le client.
* La colonne n'est renseignée qu'en fin de validation, à partir de ces valeurs normalisées.
*/
$ev_defaults = UserSettings::eventNewDefaults(null);

/*
* Sorties du traitement, rendues plus bas une fois l'en-tête inclus.
*
* Les requêtes en échec ne peuvent plus s'annoncer au fil de l'eau : elles s'accumulent ici.
* Le succès, lui, voyage en session jusqu'à user/dashboard.php — sauf si une de ces erreurs est survenue
* en chemin, auquel cas la page reste affichée pour ne pas escamoter l'erreur.
*/
$erreurs_traitement = [];
$message_succes = '';
$idP_succes = 0;

if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok')
{
	foreach ($champs as $c => $v)
	{
        if (isset($_POST[$c]))
        {
            $champs[$c] = $_POST[$c];
        }
    }

	if (isset($_POST['organisateurs']))
		$champs['organisateurs'] = $_POST['organisateurs'];

	$ev_defaults = UserSettings::sanitizeEventNewDefaults([
		'genre' => $_POST['ev_defaults_genre'] ?? '',
		'horaire_debut' => $_POST['ev_defaults_horaire_debut'] ?? '',
		'horaire_fin' => $_POST['ev_defaults_horaire_fin'] ?? '',
		'idLieu' => $_POST['ev_defaults_idLieu'] ?? 0,
		'idOrganisateurs' => $_POST['ev_defaults_organisateurs'] ?? [],
		'prix' => $_POST['ev_defaults_prix'] ?? '',
	], $glo_tab_genre);

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
            $verif->valider($champs['newPass'], "newPass", "texte", 8, 30, 1);
            $verif->valider($champs['newPass2'], "newPass2", "texte", 8, 30, 1);

            if (!preg_match("/[0-9]/", (string) $champs['newPass']) || !preg_match("/[0-9]/", (string) $champs['newPass2']))
			{
				$verif->setErreur("nouveaux_pass", 'Le nouveau mot de passe doit comporter au moins 1 chiffre.');
			}
		}
	}

    /*
     * Liste des mots de passe les plus courants (resources/bad_p.txt). Cette page garde
     * ses propres bornes de longueur (8/30, contre 10/100 à l'inscription et à la
     * réinitialisation) : les aligner est un changement de règle à traiter à part.
     */
    if (!empty($champs['newPass']) && PasswordPolicy::estRefuse((string) $champs['newPass']))
    {
        $verif->setErreur("nouveaux_pass", "Ce mot de passe est trop courant, veuillez en choisir un autre.");
    }

	$verif->valider($champs['email'], "email", "email", 4, 250, 1);
    $verif->valider($champs['affiliation'], "affiliation", "texte", 2, 60, 0);

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
		$sql_existance = "SELECT pseudo FROM personne
		WHERE pseudo='".$connector->sanitize($champs['pseudo'])."'
		OR email='".$connector->sanitize($champs['email'])."'";

		$req_existance = $connector->query($sql_existance);

		if ($connector->getNumRows($req_existance) > 0)
		{
			$verif->setErreur("pseudoIdentique", "Un membre ".$champs['pseudo']." existe déjà dans la base.");
			$verif->setErreur("emailIdentique", "Un membre ".$champs['email']." existe déjà dans la base.");
		}

	/*
	 * En cas de mise à jour, vérification si le mot de passe ancien est correcte avec celui de la session
	 */
	}
	elseif ($get['action'] == "update" && !empty($champs['motdepasse']) && empty($erreurs['motdepasse']))
	{
		$getUser = $connector->query("SELECT mot_de_passe, gds
		FROM personne
		WHERE pseudo = '".$_SESSION['user']."'");

		$tab_user = $connector->fetchArray($getUser);

		//Si au moins un enregistrement de personne est trouvé
        if ((sha1($tab_user['gds'] . sha1($champs['motdepasse'])) != $tab_user['mot_de_passe']) && !password_verify($champs['motdepasse'], $tab_user['mot_de_passe']))
		{
			$verif->setErreur("motdepasse", "Faux mot de passe");
		}
	}

    if (!SecurityToken::check($_POST['token'], $_SESSION['token']))
    {
        $verif->setErreur("pseudo", "Le système de sécurité du site n'a pu authentifier votre action. Veuillez réafficher ce formulaire et réessayer");
    }

	if ($verif->nbErreurs() === 0)
	{
		if (!empty($champs['newPass']))
		{
			$champs['gds'] = '';
            $champs['mot_de_passe'] = password_hash($champs['newPass'], PASSWORD_DEFAULT);
		}

		if ($_SESSION['Sgroupe'] > UserLevel::SUPERADMIN) {
			$champs['groupe'] = $_SESSION['Sgroupe'];
			$champs['pseudo'] = $_SESSION['user'];
		}

		// Le fieldset « Événements » n'est rendu qu'en édition : on ne touche à la colonne que là.
		// Comme pour mot_de_passe et gds juste au-dessus, ajouter la clé à $champs suffit à ce que
		// la boucle de génération de l'UPDATE écrive la colonne du même nom. La relecture préalable
		// préserve les réglages d'un autre domaine que ce formulaire n'expose pas.
		if ($get['action'] == 'update' && isset($get['idP']))
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
			if (!empty($champs['lieu']))
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
				if ($connector->getAffectedRows() > 0)
				{
					$connector->query("DELETE FROM affiliation WHERE idPersonne=" . (int) $get['idP'] . " AND genre='lieu'");
                }
			}

			/*
			* MAJ réussie -> MAJ des infos perso de session si la personne s'autoédite
			*/
			if ($req_update)
			{
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

                $logger->info('[user-edit] user updated', ['pseudo' => $champs['pseudo'], 'by' => $_SESSION["user"]]);

                $sqld = "DELETE FROM personne_organisateur WHERE idPersonne=" . (int) $get['idP'];
                $connector->query($sqld);
				$req_id = $get['idP'];
				$idP_succes = (int) $get['idP'];
			}
			else
			{
				$erreurs_traitement[] = "La requête UPDATE dans 'personne' a échoué";
			}
		} //if action

		if (is_array($champs['organisateurs']))
		{
			foreach ($champs['organisateurs'] as $idOrg)
			{
				if (!empty($idOrg))
                {
					$sql = "INSERT INTO personne_organisateur (idPersonne, idOrganisateur) VALUES (" . (int) $req_id . ", " . (int) $idOrg . ")";
                    $connector->query($sql);
				}
			}
            $logger->info('[user-edit] organisateurs updated', ['pseudo' => $champs['pseudo'], 'idOrganisateurs' => $champs['organisateurs']]);
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

// =============================================================================
// Rendu
// =============================================================================

include("_header.inc.php");
?>

<main id="contenu" class="colonne user-edit">

<?php
foreach ($erreurs_traitement as $erreur_traitement)
{
	HtmlShrink::msgErreur($erreur_traitement);
}

// n'arrive que si une erreur ci-dessus a retenu la redirection
if ($message_succes !== '')
{
	HtmlShrink::msgOk($message_succes);
}

echo '<header id="entete_contenu">';

if ($get['action'] == 'editer' && isset($get['idP']))
{
	$req_pers = $connector->query("SELECT * FROM personne WHERE idPersonne =" . (int) $get['idP']);

        if ($tab_pers = $connector->fetchArray($req_pers))
	{
		foreach ($tab_pers as $n => $v)
		{
			$champs[$n] = $v;
		}
		//printr($champs);
	}
	else
	{
		HtmlShrink::msgErreur("La personne ". (int)$get['idP']." n'existe pas");
		exit;
	}

	// $champs vient de recevoir toutes les colonnes de personne, dont settings.
	$ev_defaults = UserSettings::eventNewDefaults(is_string($champs['settings'] ?? null) ? $champs['settings'] : null);

	$req_aff = $connector->query("SELECT idAffiliation FROM affiliation WHERE
	 idPersonne=" . (int) $get['idP'] . " AND genre='lieu'");

        if ($tab_aff = $connector->fetchArray($req_aff))
	{
		$champs['lieu'] = $tab_aff['idAffiliation'];
	}

} // if GET action


    /*
 * PREPARATION DES URLS SELON LES ACTIONS,
 * update et idE en cas d'édition, insert pour ajout
 */
if ($get['action'] == 'editer' || $get['action'] == 'update')
{
	$act = "update&amp;idP=".(int)$get['idP'];
	echo "<h1>Modification du compte</h1>";
}
else
{
	$act = 'insert';
	echo '
	<h1>Ajouter une personne</h1>';
}

echo '<div class="spacer"></div></header>';

if ($verif->nbErreurs() > 0)
{
	HtmlShrink::msgErreur("Il y a ".$verif->nbErreurs()." erreur(s).");
}
?>


<form method="post" id="ajouter_editer" enctype="multipart/form-data" class="js-submit-freeze-wait" action="<?php echo basename(__FILE__)."?action=".$act; ?>" >

<p>* indique un champ obligatoire</p>

<fieldset>
    <legend>Identification</legend>

    <!-- Pseudo* (text) -->
    <p>
        <label for="pseudo">Nom d'utilisateur*</label>
            <?php
            if ($_SESSION['Sgroupe'] == UserLevel::SUPERADMIN) {
        ?>
                <input type="text" name="pseudo" id="pseudo" size="30" maxlength="80" value="<?php echo sanitizeForHtml($champs['pseudo']) ?>" required />
                <?php
        echo $verif->getHtmlErreur('pseudo');
        echo $verif->getHtmlErreur("pseudoIdentique");
        ?>
    </p>

    <?php
    }
    else
    {
    ?>

            <input type="text" name="pseudo" id="pseudo" size="30" maxlength="80" value="<?php echo sanitizeForHtml($champs['pseudo']) ?>" readonly style="background:#f4f4f4" />

    <?php
    }
    ?>


    <!-- Groupe pour admin (select) -->
    <?php
    if ($_SESSION['Sgroupe'] == UserLevel::SUPERADMIN) {

        echo "<p>
        <label for=\"groupe\">Groupe* :</label>
        <select name=\"groupe\" id=\"groupe\">";

        $groupes = UserLevel::getConstants();

        foreach ($groupes as $nom => $id)
        {
              echo "<option ";
                //en cas d'update groupe de la personne sélectionnée
                if ($id == $champs['groupe']) {
                    echo "selected=\"selected\"";
            }
                elseif (($get['action'] == 'ajouter' || $get['action'] == 'insert') && $id == UserLevel::ACTOR) {
                echo "selected=\"selected\"";
                }
                echo " value=\"" .(int) $id . "\">" .(int) $id . " : " . sanitizeForHtml($nom) . "</option>";
        }
        echo "</select>";
        echo $verif->getHtmlErreur("groupe");
        echo "</p>";
    }
    ?>
</fieldset>

<!-- Mot de passe actuel* en cas de mise à jour -->
<fieldset>
    <legend>Mot de passe</legend>

        <?php
        if ($_SESSION['Sgroupe'] > UserLevel::SUPERADMIN && ($get['action'] == 'editer' || $get['action'] == 'update')) {
    ?>
        <div class="guideForm">À remplir si vous souhaitez modifier votre mot de passe actuel</div>
        <p><label for="motdepasse">Actuel</label>
                    <input type="password" name="motdepasse" id="motdepasse" size="30" value="" autocomplete="off" />
                    <?php
        echo $verif->getHtmlErreur("motdepasse");
        ?>
        </p>

    <?php
    }
    ?>

    <!-- Nouveau mot de passe* en cas de mise à jour -->
    <p>
        <label for="newPass">Nouveau<?php if ($get['action'] != 'editer' || $get['action'] != 'update') { echo "*"; } ?></label>
            <input type="password" name="newPass" id="newPass" size="30" value="" autocomplete="new-password"  />
            <?php echo $verif->getHtmlErreur("newPass");?>
    </p>

    <!-- Nouveau mot de passe* à confirmation en cas de mise à jour -->
    <p>
        <label for="newPass2">Confirmer le nouveau<?php if ($get['action'] != 'editer' || $get['action'] != 'update') { echo "*"; } ?></label>
            <input type="password" name="newPass2" id="newPass2" size="30" value="" autocomplete="new-password" />
            <?php echo $verif->getHtmlErreur("newPass2");?>
    </p>
    <?php echo $verif->getHtmlErreur("nouveaux_pass");?>
</fieldset>


<fieldset>
    <legend>Informations</legend>

        <!-- Email* (text) -->
    <p>
    <label for="email">E-mail*</label>
        <input type="email" name="email" id="email" size="40" maxlength="80" value="<?php echo sanitizeForHtml(stripslashes((string) $champs['email'])) ?>" required />
        <?php echo $verif->getHtmlErreur("email");
    echo $verif->getErreur("emailIdentique");?>
    </p>
</fieldset>

<?php
if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= UserLevel::ACTOR)) {
?>

<!-- Affiliation (text) -->
<fieldset id="references">
    <legend>Affiliation(s)</legend>
    <div class="guideForm">Si vous souhaitez modifier ces informations merci de nous <a href="/misc/contacteznous.php">contacter</a></div>

    <?php
    $req_lieux = $connector->query("
    SELECT idLieu, nom FROM lieu WHERE actif=1 AND statut='actif' ORDER BY TRIM(LEADING 'L\'' FROM (TRIM(LEADING 'Les ' FROM (TRIM(LEADING 'La ' FROM (TRIM(LEADING 'Le ' FROM nom))))))) COLLATE utf8mb4_unicode_ci"
     );

        $sql = "SELECT idOrganisateur
    FROM personne_organisateur
    WHERE personne_organisateur.idPersonne=" . (int) $get['idP'];

        $tab_organisateurs_pers = [];
    if ($get['action'] == "editer" || $get['action'] == "update")
    {

        $sql = "SELECT idOrganisateur
    FROM personne_organisateur
    WHERE personne_organisateur.idPersonne=" . (int) $get['idP'];

            $req = $connector->query($sql);

        if ($connector->getNumRows($req))
        {
            //echo "<table class=\"fichiers_associes\"><tr><th>nom</th><th>".$iconeSupprimer."</th></tr>";
            while ($tab = $connector->fetchArray($req))
            {

                $tab_organisateurs_pers[] = $tab['idOrganisateur'];
            }
            //echo "</table>";
        }

    }

    if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= UserLevel::AUTHOR)) {
    ?>
    <p>
    <label for="affiliation">Nom</label>
                <input type="text" name="affiliation" id="affiliation" size="30" maxlength="80" value="<?php echo sanitizeForHtml($champs['affiliation']); ?>" />
                <?php echo $verif->getHtmlErreur("affiliation"); ?>

    </p>

    <p class="entreLabels"><strong>ou</strong></p>
    <div class="spacer"></div>
    <p>

    <label for="lieu">lieu</label>
    <select name="lieu" id="lieu" class="js-select2-options-with-style" style="max-width:350px;" data-placeholder="">
                    <?php

    echo "<option value=\"\">&nbsp;</option>";

                                while ($lieuTrouve = $connector->fetchArray($req_lieux))
    {
        echo "<option ";
        if ($lieuTrouve['idLieu'] == $champs['lieu'])
        {
            echo "selected=\"selected\" ";
        }
        echo "value=\"" . $lieuTrouve['idLieu'] . "\">" . sanitizeForHtml($lieuTrouve['nom']) . "</option>";
                }
    ?>
    </select>

    <p class="entreLabels"><strong>ou</strong></p>
    <div class="spacer"></div>

    <p>
    <label for="organisateurs">organisateur(s)</label>
                <select name="organisateurs[]" id="organisateurs" data-placeholder="Choisissez un ou plusieurs organisateurs" class="js-select2-options-with-style" multiple data-placeholder="" style="max-width:350px;">
                    <?php
    echo "<option value=\"\">&nbsp;</option>";
                $req = $connector->query("
    SELECT idOrganisateur, nom FROM organisateur WHERE statut='actif' ORDER BY TRIM(LEADING 'L\'' FROM (TRIM(LEADING 'Les ' FROM (TRIM(LEADING 'La ' FROM (TRIM(LEADING 'Le ' FROM nom))))))) COLLATE utf8mb4_unicode_ci"
     );

    while ($tab = $connector->fetchArray($req))
    {
        echo "<option ";

        if ((isset($_POST['organisateurs']) && in_array($tab['idOrganisateur'], $_POST['organisateurs'])) || in_array($tab['idOrganisateur'], $tab_organisateurs_pers))
        {
            echo 'selected="selected" ';

        }


        echo "value=\"" . $tab['idOrganisateur'] . "\">" . sanitizeForHtml($tab['nom']) . "</option>";
                }
    ?>
    </select>


    </p>
    <?php
    }
    else
    {
        if (!empty($champs['affiliation']))
        {
        ?>
        <p>
                            <label></label><input name="affiliation" type="text" readonly value="<?php echo sanitizeForHtml($champs['affiliation']); ?>" >
                        </p>

        <?php
        }

        if (!empty($champs['lieu']))
        {
            $req_lieux = $connector->query("SELECT nom FROM lieu WHERE idLieu=".(int)$champs['lieu']);
            $lieuTrouve = $connector->fetchArray($req_lieux);
            ?>
                <p>
                <label>Lieu</label>
                <ul style="float:left;margin:0;padding-left:1em;">
                                    <li><a href="/lieu/lieu.php?idL=<?php echo (int)$champs['lieu']; ?>"><?php echo sanitizeForHtml($lieuTrouve['nom']); ?></a>
                                        <input type="hidden" name="lieu" value="<?php echo sanitizeForHtml($champs['lieu']);?>">
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
                <li><a href="/organisateur/organisateur.php?idO=<?php echo (int)$tab['idOrganisateur']; ?>"><?php echo sanitizeForHtml($tab['nom']); ?></a>
                                        <input type="hidden" name="organisateurs[]" value="<?php echo (int)$tab['idOrganisateur']; ?>">
                    </li>
                    <?php
                }
           ?>
            </ul><div class="spacer"><!-- --></div>

            </p>
        <div class="guideChamp" style='padding: 0em 0 0.2em 175px;'>Vous pouvez modifier ces organisateurs, tous les événements qui y sont associés ainsi que les lieux associés à ces organisateurs</div>


        <?php
        }
    }
    ?>
    <?php echo $verif->getHtmlErreur("affiliation"); ?>
    <?php echo $verif->getHtmlErreur("doublon_organisateur"); ?>
</fieldset>

<?php
}
?>

<fieldset>
    <legend>Votre signature</legend>
        <div class="guideForm">Apparait sous les événements que vous avez ajoutés</div>

    <label style="display:block;float:none;width:8em" >Afficher :</label>

    <ul class="radio" style="display:block">
    <?php
    $signatures = ["pseudo" => "L'identifiant", "aucune" => "Aucune signature"];
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
    <?php
    echo $verif->getHtmlErreur('avec_affiliation');
    ?>
    </p>


</fieldset>


<?php
/*
* Réglages du formulaire d'ajout d'événement.
*
* Rendu en édition seulement : à la création d'un compte par un admin ces réglages n'ont pas de sens,
* et cela évite deux requêtes inutiles. Aucune condition de groupe : la page est déjà réservée à
* UserLevel::ACTOR, et tout utilisateur connecté peut ajouter un événement — contrairement au
* fieldset Affiliation ci-dessus, réservé lui à UserLevel::AUTHOR.
*
* Tous les champs sont préfixés ev_defaults_ : le fieldset Affiliation occupe déjà les noms « lieu »
* et « organisateurs[] », et les clés de $champs sont autant de colonnes de la table personne.
*/
if ($get['action'] == "editer" || $get['action'] == "update")
{
?>
<fieldset>
    <legend>Événements</legend>
        <div class="guideForm">Valeurs par défaut à l'ajout d'un événement</div>

    <p>
    <label for="ev_defaults_genre">Catégorie</label>
        <select name="ev_defaults_genre" id="ev_defaults_genre" style="max-width:350px">
            <option value=""></option>
            <?php foreach ($glo_tab_genre as $genre_cle => $genre_label) : ?>
            <option value="<?= sanitizeForHtml($genre_cle) ?>" <?php if ((string) $genre_cle === $ev_defaults['genre']) { echo 'selected="selected"'; } ?>><?= sanitizeForHtml($genre_label) ?></option>
            <?php endforeach ?>
        </select>
        <?php echo $verif->getHtmlErreur("ev_defaults_genre"); ?>
    </p>

    <p>
    <label for="ev_defaults_horaire_debut">Début</label>
        <input type="time" name="ev_defaults_horaire_debut" id="ev_defaults_horaire_debut" size="5" value="<?php echo sanitizeForHtml($ev_defaults['horaire_debut']); ?>" />
        <?php echo $verif->getHtmlErreur("ev_defaults_horaire_debut"); ?>
    </p>

    <p>
    <label for="ev_defaults_horaire_fin">Fin</label>
        <input type="time" name="ev_defaults_horaire_fin" id="ev_defaults_horaire_fin" size="5" value="<?php echo sanitizeForHtml($ev_defaults['horaire_fin']); ?>" />
        <?php echo $verif->getHtmlErreur("ev_defaults_horaire_fin"); ?>
    </p>

    <p>
    <label for="ev_defaults_idLieu">Lieu</label>
        <select name="ev_defaults_idLieu" id="ev_defaults_idLieu" class="js-select2-options-with-style" style="max-width:350px" data-placeholder="">
            <option value=""></option>
            <?php
            // Requête à part : $req_lieux, plus haut, a été consommé jusqu'au bout par le select
            // d'affiliation. Mêmes lieux et même tri que le formulaire d'ajout d'événement, sans
            // les salles (le réglage se fait au niveau du lieu).
            $canton_labels = ['ge' => 'Genève', 'vd' => 'Vaud', 'fr' => 'Fribourg', '' => 'France'];
            $canton_courant = null;

            foreach (Lieu::getActifsPourSelect() as $lieu_defaut)
            {
                $canton = (string) $lieu_defaut['canton'];

                if ($canton !== $canton_courant)
                {
                    if ($canton_courant !== null) { echo '</optgroup>'; }
                    echo '<optgroup label="' . ($canton_labels[$canton] ?? sanitizeForHtml($canton)) . '">';
                    $canton_courant = $canton;
                }

                $selectionne = ((int) $lieu_defaut['idLieu'] === $ev_defaults['idLieu']) ? ' selected="selected"' : '';
                echo '<option value="' . (int) $lieu_defaut['idLieu'] . '"' . $selectionne . '>' . sanitizeForHtml($lieu_defaut['nom']) . '</option>';
            }

            if ($canton_courant !== null) { echo '</optgroup>'; }
            ?>
        </select>
        <?php echo $verif->getHtmlErreur("ev_defaults_idLieu"); ?>
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
        <?php echo $verif->getHtmlErreur("ev_defaults_organisateurs"); ?>
    </p>

    <p>
    <label for="ev_defaults_prix">Prix</label>
        <input type="text" name="ev_defaults_prix" id="ev_defaults_prix" size="45" maxlength="100" value="<?php echo sanitizeForHtml($ev_defaults['prix']); ?>" />
        <?php echo $verif->getHtmlErreur("ev_defaults_prix"); ?>
    </p>
</fieldset>
<?php
}
?>


<?php if ($_SESSION['Sgroupe'] == UserLevel::SUPERADMIN && ($get['action'] == "editer" || $get['action'] == "update") && isset($get['idP'])) { ?>
    <fieldset>
    <legend>Statut</legend>
        <ul class="radio">
        <?php foreach (Personne::$statuts as $s) : ?>
            <li class="listehoriz">
                <input type="radio" name="statut" value="<?= sanitizeForHtml($s) ?>" <?php if ($s == $champs['statut']) { ?> checked="checked" <?php } ?> id="statut_<?= sanitizeForHtml($s) ?>" class="radio_horiz" />
                <label class="continu" for="statut_<?= sanitizeForHtml($s) ?>"><?= sanitizeForHtml($s) ?></label></li>
        <?php endforeach ?>
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
    <input type="hidden" name="token" value="<?php echo SecurityToken::getToken(); ?>" />
    <input type="submit" value="Enregistrer" class="submit submit-big" />
</p>

</form>

</main> <!-- fin contenu  -->

<div id="colonne_gauche" class="colonne">
<?php include("event/_navigation_calendrier.inc.php"); ?>
</div><!-- Fin Colonne gauche -->

<?php
include("_footer.inc.php");
?>
