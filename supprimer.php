<?php
/**
 * Permet d'ajouter un événement avec ses détails, un flyer et un lieu de la base associé
 * affiché dans l'index
 *
 * Le traitement de suppression est suivi par le traitement d'ajout/edition et le formulaire
 * est à la fin
 *
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

if (!$videur->checkGroup(6))
{
	header("Location: index.php"); die();
}


$cache_lieu = $rep_cache."lieu/";
$cache_even = $rep_cache."evenement/";
$cache_index = $rep_cache."index/";

//header("Cache-Control: max-age=30, must-revalidate");
/*
header("Pragma: no-cache");
header("Expires: 0");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header_html("La dê¤¡danse : ".$get['action']." un ê·©nement", $indexMotsClef, $formsCssScreen, $formsCssPrint);
 */
$page_titre = "supprimer un élément";
$page_description = "Suppression d'un élément";
$nom_page = "supprimer";
$extra_css = array("evenement_inc", "breve_inc", "lieu_inc", "descriptionlieu_inc", "commentaire_inc");
include("includes/header.inc.php");


/*
* action choisie, ID si édition
* action "ajouter" par défaut
*/


$tab_types = array("evenement", "lieu", "descriptionlieu", "breve", "commentaire", "personne", "salle", "organisateur");

/*
* Vérification et attribution des variables d'URL GET
*/
$get['type'] = "";
if (isset($_GET['type']))
{
	$get['type'] =  verif_get($_GET['type'], "enum", 1, $tab_types);
}
else
{
	msgErreur("type obligatoire");
}


$tab_actions = array("confirmation", "suppression");
$get['action'] = "suppression";
if (isset($_GET['action']))
{
	$get['action'] =  verif_get($_GET['action'], "enum", 1, $tab_actions);
}

if (isset($_GET['id']))
{
	$get['id'] = verif_get($_GET['id'], "int", 1);
}
else
{
	msgErreur("id obligatoire");
	exit;
}

if (isset($_GET['idP']))
{
	$get['idP'] = verif_get($_GET['idP'], "int", 1);
}

?>




<!-- debut Contenu -->
<div id="contenu" class="colonne">

<?php

	echo '<div id="entete_contenu"><h2>Suppression</h2></div>';
/* SUPPRESSION CONFIRMEE
* Vérification si la personne est 'auteur' et l'auteur ou admin
* Récupération du nom du flyer, suppression des fichiers réduits et mini
* Déstruction de la brève
* Message de confirmation
*/
if ($get['action'] == 'confirmation' && isset($get['id']))
{

    if (!SecurityToken::check($_GET['token'], $_SESSION['token']))
    {
        echo "Le système de sécurité du site n'a pu authentifier votre action. Veuillez réessayer";
    }  
    
	if ($get['type'] == "evenement")
	{

		///TESTER SI L'EVENEMENT EXISTE ENCORE

		if (((estAuteur($_SESSION['SidPersonne'], $get['id'], $get['type']) && $_SESSION['Sgroupe'] <= 6) || $_SESSION['Sgroupe'] < 2))
		{
			/*
			 * Suppression du flyer
			 */
			$req_im = $connector->query("SELECT titre, flyer, image, idLieu, genre, dateEvenement
			FROM evenement WHERE idEvenement=".$get['id']);

			$val_even = $connector->fetchArray($req_im);
			$titreSup = $val_even['titre']; //pour le message apres suppression

			if (!empty($val_even['flyer']))
			{
				unlink($rep_images.$val_even['flyer']);
				unlink($rep_images."s_".$val_even['flyer']);
				unlink($rep_images."t_".$val_even['flyer']);
			}

			if (!empty($val_even['image']))
			{
				unlink($rep_images.$val_even['image']);
				unlink($rep_images."s_".$val_even['image']);
			}

	$sql_docu = "SELECT fichierrecu.idFichierrecu AS idFichierrecu, description, mime, extension, dateAjout
FROM fichierrecu, evenement_fichierrecu
WHERE evenement_fichierrecu.idEvenement=".$get['id']." AND type='document' AND
 fichierrecu.idFichierrecu=evenement_fichierrecu.idFichierrecu";

	 $req_docu = $connector->query($sql_docu);

		while ($tab_docu = $connector->fetchArray($req_docu))
		{
			$nom_fichier = $tab_docu['idFichierrecu'].".".$tab_docu['extension'];
			if(unlink($rep_fichiers_even.$nom_fichier))
			{
				//echo $nom_fichier." supprimé<br>";
			}

			$connector->query("DELETE FROM fichierrecu WHERE idFichierrecu=".$tab_docu['idFichierrecu']);
		}


		$connector->query("DELETE FROM evenement_fichierrecu WHERE idEvenement=".$get['id']);



			/*
			 * Suppression du cache si l'ê·©nement a lieu dans un lieu prê´¥nt dans la base
			 */
			/* if (!empty($val_even['idLieu']))
				@unlink($cache_lieu.$val_even['idLieu'].".php");

			@unlink($cache_even.$get['id']E.".php");

			if ($rc = opendir($cache_index)) {
				while ($fichierIndex = readdir($rc)) {
					if (preg_match('/^'.urlencode($val_even['genre']).'_'.date2sem($val_even['dateEvenement']).'/', $fichierIndex))
						@unlink($cache_index.$fichierIndex);
				}
				closedir($rc);
			} */


			if ($connector->query("DELETE FROM evenement WHERE idEvenement=".$get['id']))
			{
				msgOk('L\'événement "'.securise_string($titreSup).'" a été supprimé');
                $logger->log('global', 'activity', "[supprimer] event \"$titreSup\" (".$get['id'].") deleted", Logger::GRAN_YEAR);  

			}
			else
			{
				msgErreur("La requète DELETE a échoué");
			}
		}
		else
		{
			msgErreur("Vous ne pouvez pas supprimer cet événement.");
		}

	}
	else if ($get['type'] == "breve")
	{

		if (estAuteur($_SESSION["SidPersonne"], $get['id'], $get['type']) || $_SESSION['Sgroupe'] < 2)
		{

			$req_breve = $connector->query("SELECT titre, img_breve, actif FROM breve WHERE idBreve=".$get['id']);
			$res_breve = $connector->fetchArray($req_breve);
			$titreSup = $res_breve['titre']; //pour le message après suppression

			if (!empty($im['img_breve']))
			{
				unlink($IMGbreves.$im['img_breve']);
				unlink($IMGbreves."s_".$im['img_breve']);
			}

			/*
			 * Suppression de tous les caches index
			 */
/* 			if ($im['actif'])
			{
				if ($rc = opendir($cache_index))
				{
					while ($fichierIndex = readdir($rc))
					{
							@unlink($cache_index.$fichierIndex);
					}
					closedir($rc);
				}
			} */


			if ($connector->query("DELETE FROM breve WHERE idBreve=".$get['id']))
			{
				msgOk("La brève \"".securise_string($res_breve['titre'])."\" a été supprimée");
			}
			else
			{
				msgErreur("La requète a échoué");
			}

		}
		else
		{
			msgErreur("Vous n'avez pas les droits pour supprimer cette brève");
		}
	}
	else if ($get['type'] == "lieu")
	{
		/*
		 * EN CAS DE SUPPRESSION ou DESACTIVATION affiche dans un tableau les événements à supprimer d'abord
		 */
		$req_evLieu = $connector->query("SELECT idEvenement, dateEvenement, idPersonne, titre, dateAjout
		FROM evenement WHERE dateEvenement >= CURDATE() AND idLieu=".$get['id']);

		$nbEvLieu = $connector->getNumRows($req_evLieu);

		$req_desLieu = $connector->query("SELECT idPersonne,dateAjout,contenu
		FROM descriptionlieu WHERE idLieu=".$get['id']);

		$nbDesLieu = $connector->getNumRows($req_desLieu);

		if ($nbEvLieu > 0)
		{

			echo "<div class=\"msgForm\">Vous devez d'abord supprimer les événements suivants :</div>
			<table>
			<tr>
			<th>ID</th>
			<th>Date</th>
			<th>Auteur</th>
			<th>Titre</th>
			<th>Ajouté le</th>
			</tr>";

			while($tab_even = $connector->fetchArray($req_evLieu))
			{

				echo "<tr>
				<td>".$tab_even['idEvenement']."</td>
				<td>".$tab_even['dateEvenement']."</td>
				<td><a href=\"".$url_site."personne.php?idP=".$tab_even['idPersonne']."\" title=\"Voir le profile de la personne\">".$tab_even['idPersonne']."</a></td>
				<td>".securise_string($tab_even['titre'])."</td>
				<td>".$tab_even['dateAjout']."</td>
				</tr>";
			}

			@mysqli_free_result($req_evLieu);

			echo "</table>";
		}
		else if ($nbDesLieu > 0)
		{

			echo "<div class=\"msg\">Vous devez d'abord supprimer les descriptions suivantes :</div>
			<table>
			<tr>
			<th>Auteur</th>
			<th>Contenu</th>
			<th>Ajouté le</th>
			</tr>";

			while(list($idPersonne, $dateAjout, $contenu) = $connector->fetchArray($req_desLieu))
			{
				echo "<tr>
				<td><a href=\"".$url_site."personne.php?idP=".$idPersonne."\" title=\"Voir le profile de la personne\">".$idPersonne."</a></td>";
				if (strlen($contenu) > 200) {
					$contenu = mb_substr($contenu, 0, 200)." [...]";
				}
				echo "<td>".securise_string($contenu)."</td>
				<td>".$dateAjout."</td>
				</tr>";
			}

			echo "</table>";

		}
		else
		{
			if (((estAuteur($_SESSION['SidPersonne'], $get['id'], $get['type']) && $_SESSION['Sgroupe'] < 7) || $_SESSION['Sgroupe'] < 2) )
			{

/* 			if ($rc = opendir($cache_lieux))
				{
					while ($fichierLieux = readdir($rc))
					{
						if (!preg_match('/^\./', $fichierLieux))
							unlink($cache_lieux.$fichierLieux);
					}
					closedir($rc);
				} */

				//supression des images
				$req_imLieu = $connector->query("SELECT nom, photo1, logo FROM lieu WHERE idLieu=".$get['id']);
				$im = $connector->fetchArray($req_imLieu);

				if (!empty($im['photo1']))
				{
					unlink($rep_images_lieux.$im['photo1']);
					unlink($rep_images_lieux."s_".$im['photo1']);
				}
				if (!empty($im['logo']))
				{
					unlink($rep_images_lieux.$im['logo']);
					unlink($rep_images_lieux."s_".$im['logo']);
				}


	$sql_docu = "SELECT fichierrecu.idFichierrecu AS idFichierrecu, description, mime, extension, dateAjout
FROM fichierrecu, lieu_fichierrecu
WHERE lieu_fichierrecu.idLieu=".$get['id']." AND type='image' AND
 fichierrecu.idFichierrecu=lieu_fichierrecu.idFichierrecu";

	 $req_docu = $connector->query($sql_docu);

		while ($tab_docu = $connector->fetchArray($req_docu))
		{
			$nom_fichier = $tab_docu['idFichierrecu'].".".$tab_docu['extension'];
			if(unlink($rep_images_lieux_galeries.$nom_fichier))
			{
				echo $nom_fichier." supprimé<br>";
			}
			if(unlink($rep_images_lieux_galeries."s_".$nom_fichier))
			{
				echo "s_".$nom_fichier." supprimé<br>";
			}
			$connector->query("DELETE FROM fichierrecu WHERE idFichierrecu=".$tab_docu['idFichierrecu']);
		}

	$sql_docu = "SELECT fichierrecu.idFichierrecu AS idFichierrecu, description, mime, extension, dateAjout
FROM fichierrecu, lieu_fichierrecu
WHERE lieu_fichierrecu.idLieu=".$get['id']." AND type='document' AND
 fichierrecu.idFichierrecu=lieu_fichierrecu.idFichierrecu";

	 $req_docu = $connector->query($sql_docu);

		while ($tab_docu = $connector->fetchArray($req_docu))
		{
			$nom_fichier = $tab_docu['idFichierrecu'].".".$tab_docu['extension'];
			if(unlink($rep_fichiers_lieu.$nom_fichier))
			{
				echo $nom_fichier." supprimé<br>";
			}

			$connector->query("DELETE FROM fichierrecu WHERE idFichierrecu=".$tab_docu['idFichierrecu']);
		}

		$connector->query("DELETE FROM lieu_fichierrecu WHERE idLieu=".$get['id']);

		$connector->query("DELETE FROM salle WHERE idLieu=".$get['id']);



				/*
				 * Suppression du cache du lieu
				 */
/* 			if (file_exists($rep_cache."lieu/".$get_idL.".php"))
					@unlink($rep_cache."lieu/".$get_idL.".php"); */

				//supression du lieu
				if ($connector->query("DELETE FROM lieu WHERE idLieu=".$get['id']))
				{
					msgOk("Le lieu a été supprimé");

				}
				else
				{
					msgErreur("La requète DELETE sur 'lieu' a échoué");
				}
			}
			else
			{
				msgErreur("Vous ne pouvez pas supprimer ce lieu.");
			}
		}
	}
	else if ($get['type'] == "descriptionlieu")
	{
		if ($_SESSION['Sgroupe'] < 2)
		{
			$req_delDes = $connector->query("DELETE FROM descriptionlieu WHERE idLieu=".$get['id']." AND idPersonne=".$get['idP']);

			if ($req_delDes)
			{
				msgOk("La description a été supprimée");
                $logger->log('global', 'activity', "[supprimer] description of lieu (".$get['id'].") deleted", Logger::GRAN_YEAR);  

				/*
				 * Suppression des caches
				 * - le lieu de la description
				 * - la page Lieux
				 */
/* 				@unlink($cache_lieu);
				if ($rc = opendir($cache_lieux))
				{
					while ($fichierLieux = readdir($rc))
					{
						@unlink($cache_lieux.$fichierLieux);
					}
					closedir($rc);
				} //si le lieu est l'un des dernier ajoutés ou si son nom est changé (menu lieux) */

			}
			else
			{
				msgErreur("La requète DELETE a échoué");
			}

		}
		else
		{
			msgErreur("Vous n'avez pas les droits pour supprimer cette description");
		}
	}
	else if ($get['type'] == "commentaire")
	{
		if (estAuteur($_SESSION["SidPersonne"], $get['id'], $get['type']) || $_SESSION['Sgroupe'] < 2)
		{

			$req_breve = $connector->query("SELECT contenu FROM commentaire WHERE idCommentaire=".$get['id']);
			$res_breve = $connector->fetchArray($req_breve);

			if ($connector->query("DELETE FROM ".$get['type']." WHERE idCommentaire=".$get['id']))
			{
				msgOk("Le commentaire a été supprimée");
                $logger->log('global', 'activity', "[supprimer] comment (".$get['id'].") of ".$get['type']." deleted", Logger::GRAN_YEAR);         
				exit;
			}
			else
			{
				msgErreur("La requète a échoué");
			}

		}
		else
		{
			msgErreur("Vous n'avez pas les droits pour supprimer ce commentaire");
		}
	}
	else if ($get['type'] == "salle")
	{
		if ($_SESSION['Sgroupe'] <= 6)
		{
			$req = $connector->query("SELECT idSalle FROM evenement WHERE idSalle=".$get['id']);

			if ($connector->getNumRows($req) == 0)
			{
				if ($connector->query("DELETE FROM ".$get['type']." WHERE idSalle=".$get['id']))
				{
					msgOk("La salle a été supprimée");
                    $logger->log('global', 'activity', "[supprimer] ".$get['type']." (".$get['id'].") deleted", Logger::GRAN_YEAR);
					exit;
				}
				else
				{
					msgErreur("La requète a échoué");
				}
			}
			else
			{
				msgErreur("Il y a encore ".$connector->getNumRows($req)." événement(s) se déroulant dans cette salle.");
			}

		}
		else
		{
			msgErreur("Vous n'avez pas les droits pour supprimer cette salle");
		}
	}
	else if ($get['type'] == "organisateur")
	{

		/*
		 * EN CAS DE SUPPRESSION ou DESACTIVATION affiche dans un tableau les événements à supprimer d'abord
		 */
		$req_ev = $connector->query("SELECT evenement.idEvenement AS idE
		FROM evenement_organisateur, evenement WHERE evenement.idEvenement=evenement_organisateur.idEvenement AND dateEvenement >= CURDATE() AND idOrganisateur=".$get['id']);

		$nb_ev = $connector->getNumRows($req_ev);
		echo $nb_ev;
		$req_lieu = $connector->query("SELECT *
		FROM lieu_organisateur WHERE idOrganisateur=".$get['id']);

		$nb_lieu = $connector->getNumRows($req_lieu);

		if ($nb_ev > 0)
		{
			msgErreur('Il y a encore '.$nb_ev.' événement(s) de cet organisateur.');
		}
		else if ($nb_lieu > 0)
		{
			msgErreur('Il y a encore '.$nb_lieu.' lieu(x) géré(s) par cet organisateur.');
		}
		else
		{

			if ($_SESSION['Sgroupe'] <= 6)
			{

				if ($connector->query("DELETE FROM ".$get['type']." WHERE idOrganisateur=".$get['id']))
				{
					msgOk("L'organisateur a été supprimé");
                    $logger->log('global', 'activity', "[supprimer] organizer ".$get['id']." deleted", Logger::GRAN_YEAR);
					exit;
				}
				else
				{
					msgErreur("La requète a échoué");
				}

			}
			else
			{
				msgErreur("Vous n'avez pas les droits pour supprimer cette salle");
			}

		}
	}
}
else if ($get['action'] == 'suppression')
{

	$act = "confirmation&amp;type=".$get['type']."&amp;id=".$get['id'];
	if (isset($get['idP']))
	{
		$act .= "&amp;idP=".$get['idP'];
	}
}


/*
if (((estAuteur($_SESSION['SidPersonne'], $get['idE'], "evenement") && $_SESSION['Sgroupe'] <= 6) || $_SESSION['Sgroupe'] == 1) ) {



* POUR EDITER UN EVENEMENT, ALLER CHERCHER SES VALEURS DANS LA BASE
* Accessible par un membre
* Récupération des valeurs de la table et remplissage des champs pour le formulaire
* Affichage d'un menu d'actions pour l'admin
*/
 if ($get['action'] != 'confirmation' && isset($get['id']))
{
	if ($get['type'] == "evenement")
	{
		$req_even = $connector->query("SELECT idEvenement, idLieu, idSalle, idPersonne, titre, genre,
		dateEvenement, nomLieu, adresse, quartier, urlLieu, description, flyer, image, prix, horaire_debut, horaire_fin, horaire_complement, URL1,
		ref, prelocations FROM ".$get['type']." WHERE idEvenement =".$get['id']);

		if ($tab_even = $connector->fetchArray($req_even))
		{
			$evenement = $tab_even;
			include("templates/evenement.inc.php");

			// if (!empty($affEven['idLieu'])) {
				// $lieu = $affEven['idLieu'];
			// } else {
				// $nomLieu = $affEven['nomLieu'];
				// $adresse = $affEven['adresse'];
			// }

		}
		else
		{
			msgErreur("La requète select a échoué");
			exit;
		}

		@mysqli_free_result($req_even);
	}
	else if ($get['type'] == "breve")
	{
		$req_breve = $connector->query("SELECT idBreve, idPersonne, titre, contenu, img_breve,
		 actif, dateAjout FROM breve WHERE idBreve =".$get['id']);

		if ($affBreve = $connector->fetchArray($req_breve))
		{

			$breve = $affBreve;
			include("templates/breve.inc.php");

		}

		@mysqli_free_result($req_breve);

	}
	else if ($get['type'] == "lieu")
	{
		//récolte des détails sur le lieu
		$req_lieu = $connector->query("SELECT idLieu, nom, adresse, quartier, horaire_general, acces_tpg, entree,
		categorie, URL, photo1, logo, actif, dateAjout, date_derniere_modif FROM lieu WHERE idLieu=".$get['id']);

		if ($tab_lieu = $connector->fetchArray($req_lieu))
		{

			$lieu = $tab_lieu;
			//include("templates/lieu.inc.php");

			// if (!empty($affEven['idLieu'])) {
				// $lieu = $affEven['idLieu'];
			// } else {
				// $nomLieu = $affEven['nomLieu'];
				// $adresse = $affEven['adresse'];
			// }

		}
		else
		{
			msgErreur("La requète select a échoué");
			exit;
		}

		@mysqli_free_result($req_lieu);
	}
	else if ($get['type'] == "descriptionlieu")
	{
		$req_desc = $connector->query("SELECT descriptionlieu.idLieu, contenu, descriptionlieu.dateAjout, pseudo, nom, prenom, groupe, descriptionlieu.idPersonne AS auteur, descriptionlieu.date_derniere_modif
		FROM descriptionlieu
		INNER JOIN personne ON descriptionlieu.idPersonne = personne.idPersonne
		WHERE descriptionlieu.idLieu =".$get['id']." AND personne.idPersonne=".$get['idP']." ORDER BY descriptionlieu.dateAjout");

		if ($res_desc = $connector->fetchArray($req_desc))
		{
			$descriptionlieu = $res_desc;
			include("templates/descriptionlieu.inc.php");
		}

		@mysqli_free_result($req_desc);
	}
	else if ($get['type'] == "commentaire")
	{
		$req_comm = $connector->query("SELECT * FROM commentaire WHERE idCommentaire =".$get['id']);

		if ($res_comm = $connector->fetchArray($req_comm))
		{

			$breve = $res_comm;
			include("templates/commentaire.inc.php");

		}

		@mysqli_free_result($req_comm);
	}
	else if ($get['type'] == "salle")
	{
		$req = $connector->query("SELECT * FROM salle WHERE idSalle =".$get['id']);

		if ($salle = $connector->fetchArray($req))
		{


			include("templates/salle.inc.php");

		}

		@mysqli_free_result($req);

	}
	else if ($get['type'] == "organisateur")
	{
            
		$req = $connector->query("SELECT idOrganisateur, nom, presentation FROM organisateur WHERE idOrganisateur=".$get['id']);

		if ($organisateur = $connector->fetchArray($req))
		{
			include("templates/organisateur.inc.php");

		}

		@mysqli_free_result($req);

	}
}

if ($get['action'] == "suppression")
{
?>

<!-- FORMULAIRE POUR UN EVENEMENT -->
<form method="post" id="supprimerEvenement" action="<?php echo $_SERVER['PHP_SELF']."?action=".$act ?>">

<p>
Êtes-vous sûr de vouloir supprimer cet élément ?
<button type="submit" name="confirmation" value="oui">Supprimer</button>
</p>

</form>

<?php
}
?>


</div>
<!-- fin contenu  -->

<div id="colonne_gauche" class="colonne">
<?php
include("includes/navigation_calendrier.inc.php");
?>
</div>
<!-- Fin Colonnegauche -->

<div id="colonne_droite" class="colonne">
</div>

<?php
include("includes/footer.inc.php");
?>
