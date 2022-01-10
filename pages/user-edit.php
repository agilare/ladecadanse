<?php
/**
 * Permet d'ajouter une personne ou de la modifier
 * Un admin peut tout modifier, un membre seulement son profile
 *
 * @category   modification d'une table de la base
 * @see personne.php, user-login.php
 * @author     Michel Gaudry <michel@ladecadanse.ch>
 */


require_once("../app/bootstrap.php");

use Ladecadanse\Security\Sentry;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Security\SecurityToken;
use Ladecadanse\Utils\Logger;
use Ladecadanse\HtmlShrink;

$videur = new Sentry();

if (!$videur->checkGroup(12))
{
	header("Location: ".$url_site."/pages/user-login.php"); die();
}


/*
* action choisie, ID si édition
* action "ajouter" par défaut
*/

$get['action'] = "ajouter";

/*
* Vérification et attribution des variables d'URL GET
*/
if (isset($_GET['idP']))
{

	$get['idP'] = Validateur::validateUrlQueryValue($_GET['idP'], "int", 1);
}

if (isset($_GET['action']))
{
	$get['action'] = Validateur::validateUrlQueryValue($_GET['action'], "enum", 1, $actions);

	if (($_GET['action'] == "ajouter" || $_GET['action'] == 'insert') && $_SESSION['Sgroupe'] > 1)
	{
		HtmlShrink::msgErreur("Vous n'avez pas le droit d'ajouter une personne");
		exit;
	}
	elseif (($get['action'] == "update" || $get['action'] == "editer") && $_SESSION['SidPersonne'] != $get['idP'] && $_SESSION['Sgroupe'] > 1)
	{
		HtmlShrink::msgErreur("Vous n'avez pas le droit de modifier cette personne");
		exit;
	}

}
else
{
	echo "Vous devez faire une action";
	exit;
}

//header("Cache-Control: max-age=600, must-revalidate");
/* $cache_index = $rep_cache."index/";
$cache_lieux = $rep_cache."lieux/"; */
$page_titre = $get['action']." d'une personne";
$page_description = "Formulaire d'ajout/edition d'un membre";
$nom_page = "user-edit";
$extra_css = array("formulaires", "user-edit_formulaire");
$extra_js = array("zebra_datepicker", "jquery.shiftcheckbox");
include("_header.inc.php");
?>





<!-- Début Evenements -->
<div id="contenu" class="colonne user-edit">

<?php

/*
 * TRAITEMENT DU FORMULAIRE (EDITION OU AJOUT)
 */

$verif = new Validateur();

$champs = array("pseudo" => '',
"motdepasse" => '',
"newPass" => '',
"newPass2" => '',
"nom" => '',
"prenom" => '',
"affiliation" => '',
"adresse" => '',
"region" => '',
"telephone" => '',
"lieu" => '',
'organisateurs' => '',
"email" => '',
"URL" => '',
"groupe" => '',
"signature" => 'pseudo',
"avec_affiliation" => '',
"statut" => '',
"remarque" => '',
"notification_commentaires" => 'non');



$action_terminee = false;

if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok')
{
	foreach ($champs as $c => $v)
	{
		if (get_magic_quotes_gpc())
		{
			$champs[$c] = stripslashes($_POST[$c]);
		}
		else if (isset($_POST[$c]))
		{
			$champs[$c] = $_POST[$c];
		}
	}
	
	if (isset($_POST['organisateurs']))
		$champs['organisateurs'] = $_POST['organisateurs'];

	$verif->valider($champs['pseudo'], "pseudo", "texte", 2, 50, 0);

	/*
	 * Les non admin ne peuvent rendre admin quelqu'un
	 */
	if (!empty($champs['groupe']) && $_SESSION['Sgroupe'] > 1 && $champs['groupe'] == 1)
	{
		$verif->setErreur("groupe", "Vous ne pouvez pas attribuer ce groupe");
	}

	/*
	 * Modification du groupe, vérifie si le groupe envoyé existe et a bien un nom
	 */
	if (!empty($champs['groupe']))
	{
		if ($connector->getNumRows($connector->query("SELECT idGroupe FROM groupes
		WHERE nom !='' AND idGroupe=".$connector->sanitize($champs['groupe']))) < 1)
		{
			$verif->setErreur("groupe", "Le groupe ".$champs['groupe']." n'est pas valable");
		}
	}

	/*
	 * Modification du mot de passe
	 * Le mot de passe ancien doit être tapé
	 */

	if ($_SESSION['Sgroupe'] > 1)
	{
		if ($get['action'] == "update" && (!empty($champs['newPass']) || !empty($champs['newPass2'])))
		{
			$verif->valider($champs['motdepasse'], "motdepasse", "texte", 6, 100, 1);
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
		if (strcmp($champs['newPass'], $champs['newPass2']) != 0)
		{
			$verif->setErreur("nouveaux_pass", 'Les 2 mots de passe doivent être identiques.');
		}
		else
		{	$verif->valider($champs['newPass'], "newPass", "texte", 8, 100, 1);
			$verif->valider($champs['newPass2'], "newPass2", "texte", 8, 100, 1);

			if (!preg_match("/[0-9]/", $champs['newPass']) || !preg_match("/[0-9]/", $champs['newPass2']))
			{
				$verif->setErreur("nouveaux_pass", 'Le nouveau mot de passe doit comporter au moins 1 chiffre.');
			}
		}
	}
	
	if (in_array($champs['newPass'], $g_mauvais_mdp))
	{
		$verif->setErreur("nouveaux_pass", "Veuillez choisir un meilleur mot de passe");	
	}

	$verif->valider($champs['nom'], "nom", "texte", 1, 80, 0);
	$verif->valider($champs['prenom'], "prenom", "texte", 1, 60, 0);
	$verif->valider($champs['email'], "email", "email", 4, 250, 1);
	$verif->valider($champs['URL'], "URL", "URL", 2, 250, 0);
	$verif->valider($champs['affiliation'], "affiliation", "texte", 2, 60, 0);

	/*
	 * Si l'affiliation texte et l'affiliation lieu ont été choisies
	 */
	if ($champs['avec_affiliation'] == 'oui' && (empty($champs['affiliation']) && empty($champs['lieu']) && empty($champs['organisateurs'])))
	{
		$verif->setErreur("avec_affiliation", "Vous devez choisir une affiliation");
	}

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

		//print_r($tab_user);
		//Si au moins un enregistrement de personne est trouvé
		if (sha1($tab_user['gds'].sha1($champs['motdepasse'])) != $tab_user['mot_de_passe'])
		{
			$verif->setErreur("motdepasse", "Faux mot de passe");
		}
	}

    if (!SecurityToken::check($_POST['token'], $_SESSION['token']))
    {
        $verif->setErreur("pseudo", "Le système de sécurité du site n'a pu authentifier votre action. Veuillez réafficher ce formulaire et réessayer");
    }       

	/*
	 * PAS D'ERREUR, donc ajout ou update executés
	 */
	if ($verif->nbErreurs() === 0)
	{
		if (!empty($champs['newPass']))
		{
			$champs['gds'] = mb_substr(sha1(uniqid(rand(), true)), 0, 5);
			$champs['mot_de_passe'] = sha1($champs['gds'].sha1($champs['newPass']));
		}

		if ($_SESSION['Sgroupe'] > 1)
		{
			$champs['groupe'] = $_SESSION['Sgroupe'];
			$champs['pseudo'] = $_SESSION['user'];
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

			$req_insert = $connector->query($sql_insert);
			$req_id = mysql_insert_id();

			//si un lieu a été choisi comme affiliation
			if (isset($champs['lieu']) && $champs['lieu'] != 0)
			{
				$req_insAff = $connector->query("INSERT INTO affiliation
				(idPersonne, idAffiliation,
				 genre) VALUES ('".$req_id."','".$champs['lieu']."','lieu')");
			}

			/*
			* Insertion réussie, message OK, et RAZ des champs
			*/
			if ($req_insert)
			{
				HtmlShrink::msgOk("Personne <em>".$champs['pseudo']."</em> ajoutée dans le groupe ".$champs['groupe']);
				foreach ($champs as $k => $v)
				{
					$champs[$k] = '';
				}

				$action_terminee = true;
			}
			else
			{
				HtmlShrink::msgErreur("La requête INSERT dans 'personne' a échoué");
			}
		}
		elseif ($get['action'] == 'update')
		{

			$sql_update = "UPDATE personne SET ";

			foreach ($champs as $c => $v)
			{
				if ($c != "motdepasse" && $c != "newPass" && $c != "newPass2" && $c != "lieu" && $c != 'organisateurs')
				{
					$sql_update .= $c."='".$connector->sanitize($v)."', ";
				}
			}

			$sql_update .= "date_derniere_modif='".date("Y-m-d H:i:s")."'";
			$sql_update .= " WHERE idPersonne=".$get['idP'];

			$req_update = $connector->query($sql_update);

			//trouve si la personne a déjà une affiliation à un lieu
			$connector->query("SELECT idPersonne FROM affiliation WHERE idPersonne=".$get['idP']);

			//si la nouvelle affiliation est un lieu, update s'il en a déjà une, insert sinon
			if (isset($champs['lieu']) && $champs['lieu'] != 0)
			{
				if ($connector->getAffectedRows() > 0)
				{
					$aff = "UPDATE affiliation SET idAffiliation='".$champs['lieu']."'
					WHERE idPersonne=".$get['idP']." AND genre='lieu'";
				}
				else
				{
					$aff = "INSERT INTO affiliation (idPersonne, idAffiliation, genre)
					VALUES ('".$get['idP']."','".$champs['lieu']."','lieu')";
				}

				if (!$connector->query($aff))
				{
					HtmlShrink::msgErreur("La requête INSERT ou UPDATE dans 'affiliation' a échoué");
				}

			//si la nouvelle affiliation n'est pas un lieu elle ira dans la table 'personne',
			// et effacement de l'ancienne dans la table affiliation,
			}
			else
			{
				if ($connector->getAffectedRows() > 0)
				{
					$connector->query("DELETE FROM affiliation WHERE idPersonne=".$get['idP']." AND genre='lieu'");
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
						$_SESSION["pass"] = $champs['mot_de_passe'];
					}

					if (!empty($champs['groupe']))
					{
						$_SESSION['Sgroupe'] = $champs['groupe'];
					}

					$_SESSION["Snom"] = $champs['nom'];
					$_SESSION["Sprenom"] = $champs['prenom'];

					if (isset($champs['lieu']) && $champs['lieu'] != 0)
					{
						$_SESSION["Saffiliation_lieu"] = $champs['lieu'];
					}

					HtmlShrink::msgOk("Votre profil a été modifié");
                    $logger->log('global', 'activity', "[user-edit] user ".$_SESSION["user"]. " updated his profile", Logger::GRAN_YEAR);
					$action_terminee = true;
				}
				else
				{
					HtmlShrink::msgOk("Le profil de <em>".$champs['pseudo']."</em> a été modifié");
				}
				
				$sqld = "DELETE FROM personne_organisateur WHERE idPersonne=".$get['idP'];	
				$connector->query($sqld);				
				$req_id = $get['idP'];
			}
			else
			{
				HtmlShrink::msgErreur("La requête UPDATE dans 'personne' a échoué");
			}
		} //if action

		if (isset($champs['organisateurs']) && is_array($champs['organisateurs']))
		{
			foreach ($champs['organisateurs'] as $idOrg)
			{
				if ($idOrg != 0)
				{
					$sql = "INSERT INTO personne_organisateur (idPersonne, idOrganisateur) VALUES (".$req_id.", ".$idOrg.")";
					$connector->query($sql);
				}
			}
		}

	} // if erreurs == 0
} // if POST != ""


if (!$action_terminee)
{

echo '<div id="entete_contenu">';

if ($get['action'] == 'editer' && isset($get['idP']))
{
	$req_pers = $connector->query("SELECT * FROM personne WHERE idPersonne =".$get['idP']);

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
		HtmlShrink::msgErreur("La personne ".$get['idP']." n'existe pas");
		exit;
	}

	$req_aff = $connector->query("SELECT idAffiliation FROM affiliation WHERE
	 idPersonne=".$get['idP']." AND genre='lieu'");

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
	$act = "update&amp;idP=".$get['idP'];
	echo "<h2>Modification du compte</h2>";
}
else
{
	$act = 'insert';
	echo '
	<h2>Ajouter une personne</h2>';
}

echo '<div class="spacer"></div></div>';

if ($verif->nbErreurs() > 0)
{
	HtmlShrink::msgErreur("Il y a ".$verif->nbErreurs()." erreur(s).");
}
?>


<form method="post" id="ajouter_editer" enctype="multipart/form-data" class="submit-freeze-wait" action="<?php echo basename(__FILE__)."?action=".$act; ?>" >

<p>* indique un champ obligatoire</p>

<fieldset>
    <legend>Identification</legend>

    <!-- Pseudo* (text) -->
    <p>
        <label for="pseudo">Nom d'utilisateur*</label>
        <?php if ($_SESSION['Sgroupe'] == 1)
        {
        ?>
        <input type="text" name="pseudo" id="pseudo" size="30" maxlength="80" value="<?php echo htmlentities($champs['pseudo']) ?>" required />
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

    <input type="text" name="pseudo" id="pseudo" size="30" maxlength="80" value="<?php echo htmlentities($champs['pseudo']) ?>" readonly style="background:#f4f4f4" />

    <?php
    }
    ?>


    <!-- Groupe pour admin (select) -->
    <?php
    if ($_SESSION['Sgroupe'] == 1)
    {

        echo "<p>
        <label for=\"groupe\">Groupe* :</label>
        <select name=\"groupe\" id=\"groupe\">";

        $req_groupe = $connector->query("SELECT idGroupe, nom FROM groupes WHERE nom!='' ORDER BY idGroupe");

        while ($groupeTrouve = $connector->fetchArray($req_groupe))
        {
              echo "<option ";
                //en cas d'update groupe de la personne sélectionnée
                if ($groupeTrouve['idGroupe'] == $champs['groupe'])
                {
                    echo "selected=\"selected\"";
                //en cas d'ajout, 10 est par défaut
                }
                elseif (($get['action'] == 'ajouter' || $get['action'] == 'insert') && $groupeTrouve['idGroupe'] == 10)
                {
                    echo "selected=\"selected\"";
                }
                echo " value=\"".$groupeTrouve['idGroupe']."\">".$groupeTrouve['idGroupe']." : ".$groupeTrouve['nom']."</option>";
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

    <?php if ($_SESSION['Sgroupe'] > 1 && ($get['action'] == 'editer' || $get['action'] == 'update'))
    {
    ?>
        <div class="guideForm">À remplir si vous souhaitez modifier votre mot de passe actuel</div>
        <p><label for="motdepasse">Actuel</label>
        <input type="password" name="motdepasse" id="motdepasse" size="20" value="" autocomplete="off" />
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
        <input type="password" name="newPass" id="newPass" size="20" value="" />
        <?php echo $verif->getHtmlErreur("newPass");?>
    </p>

    <!-- Nouveau mot de passe* à confirmation en cas de mise à jour -->
    <p>
        <label for="newPass2">Confirmer le nouveau<?php if ($get['action'] != 'editer' || $get['action'] != 'update') { echo "*"; } ?></label>
        <input type="password" name="newPass2" id="newPass2" size="20" value="" />
        <?php echo $verif->getHtmlErreur("newPass2");?>
    </p>
    <?php echo $verif->getHtmlErreur("nouveaux_pass");?>
</fieldset>


<fieldset>
    <legend>Informations</legend>
    <!-- Nom (text) -->
    <p>
    <label for="nom">Nom</label>
    <input type="text" name="nom" id="nom" size="20" maxlength="80" value="<?php echo htmlentities($champs['nom']) ?>" />
    <?php echo $verif->getHtmlErreur("nom");?>
    </p>

    <!-- Prénom (text) -->
    <p>
    <label for="prenom">Prénom</label>
    <input type="text" name="prenom" id="prenom" size="20" maxlength="80" value="<?php echo htmlentities($champs['prenom']); ?>" />
    <?php echo $verif->getHtmlErreur("prenom"); ?>
    </p>

    <!-- Site perso (text) -->
    <p>
    <label for="URL">Site web http://</label>
    <input type="url" name="URL" id="URL" size="40" maxlength="80" value="<?php echo htmlentities($champs['URL']); ?>" />
    <?php echo $verif->getHtmlErreur("URL");?>
    </p>

    <!-- Email* (text) -->
    <p>
    <label for="email">E-mail*</label>
    <input type="email" name="email" id="email" size="40" maxlength="80" value="<?php echo htmlentities(stripslashes($champs['email'])) ?>" required />
    <?php echo $verif->getHtmlErreur("email");
    echo $verif->getErreur("emailIdentique");?>
    </p>
</fieldset>

<?php
if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 8))
{
?>

<!-- Affiliation (text) -->
<fieldset id="references">
    <legend>Affiliation(s)</legend>
    <div class="guideForm">Si vous souhaitez modifier ces informations merci de nous <a href="/pages/contacteznous.php">contacter</a></div>

    <?php
    $req_lieux = $connector->query("
    SELECT idLieu, nom FROM lieu WHERE actif=1 AND statut='actif' ORDER BY TRIM(LEADING 'L\'' FROM (TRIM(LEADING 'Les ' FROM (TRIM(LEADING 'La ' FROM (TRIM(LEADING 'Le ' FROM nom))))))) COLLATE utf8mb4_unicode_ci"
     );    

        $sql = "SELECT idOrganisateur
    FROM personne_organisateur
    WHERE personne_organisateur.idPersonne=".$get['idP'];
 
    $tab_organisateurs_pers = array();
    if ($get['action'] == "editer" || $get['action'] == "update")
    {

        $sql = "SELECT idOrganisateur
    FROM personne_organisateur
    WHERE personne_organisateur.idPersonne=".$get['idP'];

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
    
    if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 6))
    {
    ?>
    <p>
    <label for="affiliation">Nom</label>
    <input type="text" name="affiliation" id="affiliation" size="30" maxlength="80" value="<?php echo htmlentities($champs['affiliation']); ?>" />
    <?php echo $verif->getHtmlErreur("affiliation"); ?>

    </p>

    <p class="entreLabels"><strong>ou</strong></p>
    <div class="spacer"></div>
    <p>

    <label for="lieu">lieu</label>
    <select name="lieu" id="lieu" class="chosen-select" style="max-width:300px;">
    <?php

    echo "<option value=\"0\">&nbsp;</option>";

    while ($lieuTrouve = $connector->fetchArray($req_lieux))
    {
        echo "<option ";
        if ($lieuTrouve['idLieu'] == $champs['lieu'])
        {
            echo "selected=\"selected\" ";
        }
        echo "value=\"".$lieuTrouve['idLieu']."\">".$lieuTrouve['nom']."</option>";

    }
    ?>
    </select>

    <p class="entreLabels"><strong>ou</strong></p>
    <div class="spacer"></div>

    <p>
    <label for="organisateurs">organisateur(s)</label>
    <select name="organisateurs[]" id="organisateurs" data-placeholder="Choisissez un ou plusieurs organisateurs" class="chosen-select" multiple  style="max-width:350px;">
    <?php
    echo "<option value=\"0\">&nbsp;</option>";
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


        echo "value=\"".$tab['idOrganisateur']."\">".$tab['nom']."</option>";
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
            <label></label><input type="text" disabled value="<?php echo htmlentities($champs['affiliation']);?>" >
        </p>
        
        <?php     
        }
        
        if (!empty($champs['lieu']))
        {
            $req_lieux = $connector->query("SELECT nom FROM lieu WHERE idLieu=".$champs['lieu']);
            $lieuTrouve = $connector->fetchArray($req_lieux);
            ?>  
                <p>
                <label>Lieu</label>
                <ul style="float:left;margin:0;padding-left:1em;">
                    <li><a href="/pages/lieu.php?idL=<?php echo $champs['lieu'];?>"><?php echo htmlentities($lieuTrouve['nom']);?></a>
                    <input type="hidden" name="lieu" value="<?php echo $champs['lieu'];?>">
                    </li>
                </ul><div class="spacer"><!-- --></div>
            </p>
        <div class="guideChamp" style='padding: 0em 0 0.2em 175px;'>Vous pouvez modifier les informations de ce lieu et tous les événements qui s'y déroulent</div>            
        <?php            
        }

        $sql = "SELECT organisateur.idOrganisateur, nom
    FROM organisateur, personne_organisateur
    WHERE personne_organisateur.idPersonne=".$get['idP']." AND
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
                    <li><a href="/pages/organisateur.php?idO=<?php echo $tab['idOrganisateur']; ?>"><?php echo $tab['nom']; ?></a>
                    <input type="hidden" name="organisateurs[]" value="<?php echo $tab['idOrganisateur']; ?>">
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
    <div class="guideForm">Apparait sous les événements, commentaires, etc. que vous avez ajoutés</div>

    <label style="display:block;float:none;width:8em" >Afficher :</label>
    
    <ul class="radio" style="display:block">
    <?php
    $signatures = array("pseudo" => "L'identifiant", "prenom" => "Le prénom", "nomcomplet" => "Le prénom et le nom", "aucune" => "Aucune signature");
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
        
        if ($s == 'nomcomplet')
        {
            echo ": <b>".$champs['prenom']." ".$champs['nom']."</b>";
        }
        elseif ($s == 'aucune')
        {
            echo "";
        }        
        else
        {
            echo ": <b>".$champs[$s]."</b>";
        }        
        echo '</label>
        </li>';
    }
    ?>
    </ul>
    <?php
    echo $verif->getHtmlErreur("signature");
    ?>

    <?php
    if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 10))
    {
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


    <?php
    }
    ?>

</fieldset>


<?php

if ($_SESSION['Sgroupe'] == 1 && ($get['action'] == "editer" || $get['action'] == "update") && isset($get['idP']))
{
?>
<fieldset>
<legend>Statut</legend>
<ul class="radio">
<?php
foreach ($glo_statuts_personne as $s)
{
	$coche = '';
	if ($s == $champs['statut'])
	{
		$coche = 'checked="checked"';
	}
	echo '<li class="listehoriz"><input type="radio" name="statut" value="'.$s.'" '.$coche.' id="statut_'.$s.'" title="statut de l\'événement" class="radio_horiz" />
	<label class="continu" for="statut_'.$s.'">'.$s.'</label></li>';
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
    <input type="hidden" name="token" value="<?php echo SecurityToken::getToken(); ?>" />
    <input type="submit" value="Enregistrer" class="submit submit-big" />
</p>

</form>

<?php
} // if action_terminee
?>
</div> <!-- fin contenu  -->

<div id="colonne_gauche" class="colonne">
<?php include("_navigation_calendrier.inc.php"); ?>
</div><!-- Fin Colonne gauche -->

<?php
include("_footer.inc.php");
?>
