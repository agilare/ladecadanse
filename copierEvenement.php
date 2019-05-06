<?php
/**
 * Permet de copier un événement avec le flyer vers une ou plusieurs dates
 * affiché dans l'index
 *
 * Le traitement de suppression est suivi par le traitement d'ajout/edition et le formulaire
 * est à la fin
 *
 * @category   modification d'une table de la base
 * @see index.php, lieu.php
 * @author     Michel Gaudry <michel@ladecadanse.ch>
 */
if (is_file("config/reglages.php"))
{
	require_once("config/reglages.php");
}

require_once($rep_librairies."Sentry.php");
$videur = new Sentry();

if (!$videur->checkGroup(10))
{
	header("Location: index.php"); die();
}

$cache_lieu = $rep_cache."lieu/";
$cache_index = $rep_cache."index/";

$page_titre = "copier un événement";
$page_description = "Copie d'un événement vers d'autres dates";
$nom_page = "copierevenement";
$extra_css = array("formulaires", "evenement_inc", "copier_evenement");



/*
* action choisie, ID si collage
*/
$tab_actions = array("coller");
$get['action'] = "";

if (isset($_GET['action']))
{
	$get['action'] = verif_get($_GET['action'], "enum", 0, $tab_actions);
}

if (isset($_GET['idE']))
{
	$get['idE'] = verif_get($_GET['idE'], "int", 1);
}
else
{
	msgErreur("idE obligatoire");
	exit;
}


$req_lieu = $connector->query("SELECT idLieu, dateEvenement FROM evenement WHERE idEvenement=".$get['idE']);
$tab_lieu = $connector->fetchArray($req_lieu);


if (estAuteur($_SESSION['SidPersonne'], $get['idE'], "evenement") || $_SESSION['Sgroupe'] <= 6
 || (isset($_SESSION['Saffiliation_lieu']) && isset($tab_lieu['idLieu']) && $tab_lieu['idLieu'] == $_SESSION['Saffiliation_lieu'])
|| est_organisateur_evenement($_SESSION['SidPersonne'], $get['idE'])
|| (isset($tab_lieu['idLieu']) && est_organisateur_lieu($_SESSION['SidPersonne'], $tab_lieu['idLieu']))
 )

{
}
else
{
	msgErreur("Vous ne pouvez pas copier cet événement");
	exit;
}



require_once($rep_librairies.'Validateur.php');
$verif = new Validateur();

/*
* TRAITEMENT DU FORMULAIRE (COLLAGE)
*/

//jour de destination
$jour = '';
$mois = '';
$annee = '';

//fin de la tranche de destination si collage sur plusieurs jours
$jour2 = '';
$mois2 = '';
$annee2 = '';

$tab_champs = array();

if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok')
{

	//la seconde date vaut la première si collage vers seulement un seul jour
	$jour2 = $jour = $_POST['jour'];
	$mois2 = $mois = $_POST['mois'];
	$annee2 = $annee = $_POST['annee'];

	//si une 2e date à été sélectionnée, c'est la fin de la tranche de collage
	if (!empty($_POST['jour2']))
	{
		$jour2 = $_POST['jour2'];
	}
	if (!empty($_POST['mois2']))
	{
		$mois2 = $_POST['mois2'];
	}
	if (!empty($_POST['annee2']))
	{
		$annee2 = $_POST['annee2'];
	}



	//si les magic quotes sont activés, retrait des slashes
	if (get_magic_quotes_gpc())
	{
		$jour = stripslashes($jour);
		$mois = stripslashes($mois);
		$annee = stripslashes($annee);
		$jour2 = stripslashes($jour2);
		$mois2 = stripslashes($mois2);
		$annee2 = stripslashes($annee2);
	}

	/*
	 * VERIFICATION DES CHAMPS ENVOYES par POST
	 */

	//conversion des 2 dates en formats Unix et Y-m-d, -1 pour laisser php appliquer l'horaire d'hiver
	$dateEUnix = mktime(0, 0, 0, $mois, $jour, $annee);
	$dateEUnix2 = mktime(0, 0, 0, $mois2, $jour2, $annee2);
	$dateEvenement = date('Y-m-d', $dateEUnix);
	$dateEvenement2 = date('Y-m-d', $dateEUnix2);
	$date_auj = date('Y-m-d');

	//Vérifie que la date de début existe bien, qu'elle dans le futur et que la date de fin est après la date de début
	if (!checkdate($mois, $jour, $annee))
	{
		$verif->setErreur("dateEvenement", "Cette date n'existe pas");
	}
	elseif ($date_auj > $dateEvenement)
	{
		$verif->setErreur("dateEvenement", "L'événement doit être dans le futur");
	}
	elseif ($dateEUnix > $dateEUnix2)
	{
		$verif->setErreur("dateEvenement", "La première date doit être avant la deuxième");
	}

	//Vérifie que la date de fin existe bien et qu'elle dans le futur
	if (!checkdate($mois2, $jour2, $annee2))
	{
		$verif->setErreur("dateEvenement2", "Cette date n'existe pas");
	}
	elseif ($date_auj  > $dateEvenement2)
	{
		$verif->setErreur("dateEvenement2", "L'événement doit être dans le futur");
	}



	if ($verif->nbErreurs() === 0)
	{
		/*
		 * Récupération des infos de l'événement à copier
		 */
		$tab_champs = $connector->fetchAssoc(($connector->query("
SELECT idLieu, idSalle, genre, flyer, dateEvenement, image, titre, nomLieu, adresse, quartier, localite_id, region, urlLieu, description, ref, prix,
 horaire_debut, horaire_fin, horaire_complement, prelocations
FROM evenement WHERE idEvenement=".$get['idE'])));

		$tab_champs['idPersonne'] = $_SESSION['SidPersonne'];

        $hor_debfin = afficher_debut_fin($tab_champs['horaire_debut'], $tab_champs['horaire_fin'], $tab_champs['dateEvenement']);
        
		$flyer = "";

		//Initialisation de la date à incrémenter avec la date de début
		$dateIncrUnix = $dateEUnix;
		$dateIncrUnixOld = $dateIncrUnix;
		
		$_SESSION['copierEvenement_flash_msg']['msg'] = '<p style="margin:4px 0">L\'événement <a href="evenement.php?idE='.$get['idE'].'"><strong>'.securise_string($tab_champs['titre']).'</strong> du '.date_fr($tab_lieu['dateEvenement']).'</a> a été copié vers les dates suivantes :</p>';
        $_SESSION['copierEvenement_flash_msg']['table'] = '';
        
		/*
		 * Collage de l'événement entre la date de début et la date de fin
		 */
		while ($dateIncrUnix <= $dateEUnix2)
		{
			/*
			 *S'il y a un flyer création du nom de sa copie avec
			* l'ID du prochain événement inséré, la date courante et le suffixe
			*/
			$maxId = $connector->fetchArray($connector->query("SELECT MAX(idEvenement) AS max_id FROM evenement"));

			if (!empty($tab_champs['flyer']))
			{
				$flyer_orig = $tab_champs['flyer'];
				$tab_champs['flyer'] = ($maxId['max_id'] + 1)."_".date('Y-m-d', $dateIncrUnix).mb_strrchr($tab_champs['flyer'],'.');
			}

			if (!empty($tab_champs['image']))
			{

				$image_orig = $tab_champs['image'];
				$tab_champs['image'] = ($maxId['max_id'] + 1)."_".date('Y-m-d', $dateIncrUnix)."_img".mb_strrchr($tab_champs['image'],'.');
			}

			$date_originale = $tab_champs['dateEvenement'];
			$date_prec = date('Y-m-d', $dateIncrUnixOld);
			$tab_champs['dateEvenement'] = date('Y-m-d', $dateIncrUnix);
			$tab_champs['dateAjout'] = date("Y-m-d H:i:s");

			if (mb_substr($tab_champs['horaire_debut'], 11) != "06:00:01"
			&& $tab_champs['horaire_debut'] != "0000-00-00 00:00:00")
			{
				$tab_champs['horaire_debut'] = $tab_champs['dateEvenement']." ".mb_substr($tab_champs['horaire_debut'], 11);
			}
			else
			{
				$tab_champs['horaire_debut'] = date_lendemain($tab_champs['dateEvenement'])." 06:00:01";
			}


			//echo date_lendemain($tab_champs['dateEvenement'])." 06:00:01";
			if (mb_substr($tab_champs['horaire_fin'], 11) != "06:00:01"
			&& $tab_champs['horaire_fin'] != "0000-00-00 00:00:00")
			{

				$tab_champs['horaire_fin'] = $tab_champs['dateEvenement']." ".mb_substr($tab_champs['horaire_fin'], 11);
			}
			else
			{
				$tab_champs['horaire_fin'] = date_lendemain($tab_champs['dateEvenement'])." 06:00:01";
			}

			$sql_insert_attributs = "";
			$sql_insert_valeurs  = "";

			foreach ($tab_champs as $c => $v)
			{
				$sql_insert_attributs .= $c.", ";
				$sql_insert_valeurs .= "'".$connector->sanitize($v)."', ";
			}

			$sql_insert_attributs = mb_substr($sql_insert_attributs, 0, -2);
			$sql_insert_valeurs = mb_substr($sql_insert_valeurs, 0, -2);

			$sql_insert =  "INSERT INTO evenement (".$sql_insert_attributs.") VALUES (".$sql_insert_valeurs.")";
			//TEST
			//echo "<p>".$sql_insert."</p>";
			//


/* 			//FAIRE BOUCLE DE DATE1 A DATE2
			$sql_inserer = "INSERT INTO evenement (idLieu, idPersonne, genre, titre, dateEvenement,
							nomLieu, adresse, description, flyer, prix, horaire_debut, horaire_complement, prelocations,  URL1, ref, dateAjout)"."
							 VALUES ('".$detailsEven['idLieu']."', '$pers',
							 '".$detailsEven['genre']."', '".$detailsEven['titre']."', '".."',
							  '".$detailsEven['nomLieu']."','".$detailsEven['adresse']."', '".$detailsEven['description']."', '".$flyer."',
							  '".$detailsEven['prix']."',
							  '".$detailsEven['horaire_debut']."', '".$detailsEven['horaire_complement']."', '".$detailsEven['prelocations']."', '".$detailsEven['URL1']."',
							  '".$detailsEven['ref']."', '".."')"; */

			/*
			* Insertion réussie, message OK, RAZ des champs, copie du flyer (réduit et mini)
			*/
			if ($connector->query($sql_insert))
			{
				//lien d'édition de l'événement juste copié pour l'auteur ou les membres
				$edition = "";
				$nouv_id = $connector->getInsertId();
	
				$edition = " <a href=\"".$url_site."ajouterEvenement.php?action=editer&idE=".$nouv_id."\" title=\"Éditer l'événement\">".$iconeEditer."</a>";
				
                $hor_compl = '';
                if (!empty($tab_champs['horaire_complement']))
                    $hor_compl = "<br>".$tab_champs['horaire_complement'];
                
//<td><a href="'.$url_site.'evenement.php?idE='.$nouv_id.'">'.securise_string($tab_champs['titre']).'</a></td>
				$_SESSION['copierEvenement_flash_msg']['table'] .= '<tr><td><a href="evenement.php?idE='.$nouv_id.'">'.date_fr(date('Y-m-d', $dateIncrUnix)).'</a></td><td>'.$hor_debfin.$hor_compl.'</td><td><a class="action_editer" href="'.$url_site.'ajouterEvenement.php?action=editer&idE='.$nouv_id.'" title="Modifier cet événement" target="_blank" >Modifier '.$icone['popup'].'</a></td><td></tr>';
//<a href="ajax.php?action=delete&entity=event&id='.$nouv_id.'" class="action_supprimer">Supprimer</td>
				if (!empty($tab_champs['flyer']))
				{
					$src = $rep_images.$flyer_orig;
					$des = $rep_images.$tab_champs['flyer'];

					if (!copy($src, $des))
					{
				  		msgErreur("La copie du fichier ".$tab_champs['flyer']." n'a pas réussi...");
					}

					$src = $rep_images."s_".$flyer_orig;
					$des = $rep_images."s_".$tab_champs['flyer'];

					if (!copy($src, $des))
					{
				  		msgErreur("La copie du fichier ".$tab_champs['flyer']." n'a pas réussi...");
					}

					$src = $rep_images."t_".$flyer_orig;
					$des = $rep_images."t_".$tab_champs['flyer'];

					if (!copy($src, $des))
					{
				  		msgErreur("La copie du fichier ".$tab_champs['flyer']." n'a pas réussi...");
					}

					$flyer = '';
		        }

				if (!empty($tab_champs['image']))
				{
					$src = $rep_images.$image_orig;
					$des = $rep_images.$tab_champs['image'];

					if (!copy($src, $des))
					{
				  		msgErreur("La copie du fichier ".$tab_champs['image']." n'a pas réussi...");
					}

					$src = $rep_images."s_".$image_orig;
					$des = $rep_images."s_".$tab_champs['image'];

					if (!copy($src, $des))
					{
				  		msgErreur("La copie du fichier ".$tab_champs['image']." n'a pas réussi...");
					}

		        }

				$sql_docu = "SELECT idFichierrecu
				FROM evenement_fichierrecu
				WHERE idEvenement=".$get['idE'];

				$req_docu = $connector->query($sql_docu);

				while ($tab_docu = $connector->fetchArray($req_docu))
				{
					$sql_insert_fichier =  "INSERT INTO evenement_fichierrecu
					(idEvenement, idFichierrecu) VALUES (".$nouv_id.", ".$tab_docu['idFichierrecu'].")";
					$req_insert_fichier = $connector->query($sql_insert_fichier);
				}


				$req_orga = $connector->query("
		SELECT idOrganisateur
		FROM evenement_organisateur WHERE idEvenement=".$get['idE']);

				while ($tab = $connector->fetchArray($req_orga))
				{
					$sql =  "INSERT INTO evenement_organisateur
					(idEvenement, idOrganisateur) VALUES (".$nouv_id.", ".$tab['idOrganisateur'].")";
					$connector->query($sql);
				}
				
				
				

			}
			else
			{

				msgErreur("La requête INSERT dans 'evenement' pour le ".date_fr(date('Y-m-d', $dateIncrUnix))." a échoué");

			}






			//copie de la date courante, passage au jour suivant, et saut d'une heure en cas de passage à l'heure d'hiver
			$dateIncrUnixOld = $dateIncrUnix;
			$dateIncrUnix += 86400;

			if (date('Y-m-d', $dateIncrUnixOld) == date('Y-m-d', $dateIncrUnix))
			{
				$dateIncrUnix += 3600;
			}


		} //while date

		
		header("Location: copierEvenement.php?idE=".$get['idE']); die();

	} //if nberreur = 0

} // if POST != ""

include("includes/header.inc.php");

?>

<!-- Deb Contenu -->
<div id="contenu" class="colonne">
    
<div id="entete_contenu"><h2>Copier un événement</h2><div class="spacer"></div></div>

<div style="width:94%;margin:0 auto">
<?php
if (!empty($_SESSION['copierEvenement_flash_msg']))
{
    ?>
    
    <div class="msg_ok_copy">
    <?php echo $_SESSION['copierEvenement_flash_msg']['msg']; ?>
    <table class="table">
        <thead><tr><th>Date</th><th>Horaire</th><th></th></tr></thead>
        <tbody><?php echo $_SESSION['copierEvenement_flash_msg']['table']; ?></tbody>
    </table>
    </div>
    
	<?php
	unset($_SESSION['copierEvenement_flash_msg']);
}
?>
</div>
<?php


if (empty($_POST['jour2']))
{
	$jour2 = $mois2 = $annee2 = '';
}


/*
 * Récupérations des détails de l'événement à copier, affichage dans une boîte
 */
if (isset($get['idE']))
{
		$req_getEven = $connector->query("SELECT idEvenement, idLieu, idSalle, idPersonne, titre, genre, dateEvenement,
		 nomLieu, adresse, quartier, localite, region, urlLieu, description, flyer, prix, horaire_debut,horaire_fin, horaire_complement, URL1, ref, prelocations,statut
		  FROM evenement, localite WHERE evenement.localite_id=localite.id AND idEvenement =".$get['idE']);

		if ($affEven = $connector->fetchArray($req_getEven))
		{
			//si le formulaire est chargé pour la 1ère fois, on prend la date extraite de la base
			if ($get['action'] != "coller")
			{
				$tab = explode("-", $affEven['dateEvenement']);
				$annee = $tab[0];
				$mois = $tab[1];
				$jour = $tab[2];
			}

			$evenement = $affEven;



			//echo date_fr($affEven['dateEvenement']);
			include("templates/evenement.inc.php");
		}
		else
		{
			msgErreur("Aucun événement n'est associé à ".$get['idE']);
			exit;
		} // if fetchArray

} // if isset idE




//affichage du nombre d'erreurs rencontrées après l'envoi du formulaire
if (!empty($erreurs))
{
	msgErreur("Il y a ".count($erreurs)." erreur(s)");
}

?>

<form method="post" id="ajouter_editer" enctype="multipart/form-data" action="<?php echo basename(__FILE__)."?action=coller&amp;idE=".$get['idE']; ?>">
<?php

//si c'es la date d'origine de l'événement qui doit être affichée avant traitement du formulaire, on montre
//directement le jour suivant
if ($get['action'] != "coller")
{
	$lendemain = explode("-", date('Y-m-d', mktime(0, 0, 0, $mois, $jour, $annee) + 86400));
	$jour = $lendemain[2];
	$mois = $lendemain[1];
	$annee = $lendemain[0];
}
?>

<fieldset style="width: 100%;">
<legend>Coller</legend>
<p>
<label for="jour">du : </label>
<select name="jour" id="jour" title="Sélectionnez le jour">
<?php
for ($j = 1; $j < 32; $j++)
{
	echo "<option ";
	if ($j == $jour)
	{
		echo "selected=\"selected\"";
	}
	echo " value=\"".$j."\">".$j."</option>";
}
?>
</select>
<label for="mois1" class="continu">&nbsp;</label>
<select name="mois" id="mois1" title="Sélectionnez le mois">
<?php
for ($m=0; $m<12; $m++)
{
    echo "<option ";
	if (($m + 1) == $mois)
	{
		echo "selected=\"selected\"";
	}
	echo " value=\"".($m + 1)."\">".$glo_moisF[$m]."</option>";
}

?>
</select>
<label for="annee" class="continu">&nbsp;</label>
<select name="annee" id="annee" title="Sélectionnez l'année">
<?php
for ($a = date("Y"); $a < $glo_annee_max; $a++)
{
	echo "<option ";

	if ($a == $annee)
	{
		echo "selected=\"selected\"";
	}
    echo " value=\"".$a."\">".$a."</option>";
}
?>
</select>
<?php
echo $verif->getHtmlErreur('dateEvenement');
?>
</p>

<p>
<label for="jour2">au : </label>
<select name="jour2" id="jour2" title="Sélectionnez le jour de fin">
<?php

echo "<option selected=\"selected\" value=\"\"><!-- --></option>";

for ($j=1; $j<32; $j++)
{
	echo "<option ";
	if ($jour2 == $j)
	{
		echo "selected=\"selected\"";
	}
    echo " value=\"".$j."\">".$j."</option>";
}
?>
</select>
<label for="mois2" class="continu">&nbsp;</label>
<select name="mois2" id="mois2" title="Sélectionnez le mois de fin">
<?php
echo "<option selected=\"selected\" value=\"\"><!-- --></option>";

for ($m=0; $m<12; $m++)
{
    echo "<option ";
	if (($m + 1) == $mois2)
	{
		echo "selected=\"selected\"";
	}
	echo " value=\"".($m + 1)."\">".$glo_moisF[$m]."</option>";
}
?>
</select>
<label for="annee2" class="continu">&nbsp;</label>
<select name="annee2" id="annee2" title="Sélectionnez l'année de fin">
<?php

echo "<option selected=\"selected\" value=\"\"><!-- --></option>";

for ($a=date("Y"); $a<$glo_annee_max; $a++) {

	echo "<option ";

	if ($annee2 == $a) {
		echo "selected=\"selected\"";
	}
    echo " value=\"".$a."\">".$a."</option>";
}
?>
</select>
<?php
echo $verif->getHtmlErreur('dateEvenement');
?>
</p>

<div class="guideChamp">Laissez cette ligne inchangée si vous ne collez l'événement que vers un seul jour.</div>
<div class="spacer"></div>
</fieldset>

<p class="piedForm">
<input type="hidden" name="formulaire" value="ok" />
<input type="submit" value="Valider" class="submit" />
</p>
</form>


</div>
<!-- fin contenu -->
<div id="colonne_gauche" class="colonne">

<?php include("includes/navigation_calendrier.inc.php"); ?>
</div>
<!-- Fin Colonne gauche -->

<?php
include("includes/footer.inc.php");
?>