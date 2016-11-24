<?php
if (is_file("config/reglages.php"))
{
        require_once("config/reglages.php");
}

require_once($rep_librairies."Sentry.php");
$videur = new Sentry();

require_once($rep_librairies."Evenement.class.php");
require_once($rep_librairies."CollectionEvenement.class.php");


$page_titre = "Agenda";
$page_description = "Événements culturels et festifs à Genève : concerts, soirées, films, théâtre, expos...";
include("includes/header.inc.php");


/* MOMENTS */
$tab_moments = array("journee", "soir", "nuit", "tout");
if (isset($_GET['moment']))
{
	if (in_array($_GET['moment'], $tab_moments))
	{
		$get['moment'] = $_GET['moment'];
	}
	else
	{
		trigger_error("moment non valable : ".$_GET['moment'], E_USER_WARNING);
		exit;
	}
}
else
{
	$get['moment'] = 'tout';
}

/* if (isset($get_deb_sem))
{
	$tab_deb_sem = explode("-", $get_deb_sem);
	$fin_sem = date("Y-m-d", mktime(0, 0, 0, $tab_deb_sem[1], $tab_deb_sem[2] + 6, $tab_deb_sem[0]));
	$sql_date_evenement = "> '".$get_deb_sem."' AND dateEvenement < '".$fin_sem."'";
}
else */

if ($get['tri_agenda'] == "horaire_debut")
{

	$sql_tri_agenda = "horaire_debut ASC";

}
else
{
	$sql_tri_agenda = $get['tri_agenda']." DESC";
}



if (isset($get['date_deb']) && isset($get['date_fin']))
{
	$sql_date_evenement = ">= '".date_app2iso($get['date_deb'])."' AND dateEvenement <= '".date_app2iso($get['date_fin'])."'";
}
else if ($get['sem'] == 1)
{
	$lundim = date_iso2lundim($get['courant']);
	$sql_date_evenement = ">= '".$lundim[0]."' AND dateEvenement <= '".$lundim[1]."'";
}
else
{
	$sql_date_evenement = "LIKE '".$get['courant']."%'";
}



if ($get['moment'] == "journee")
{//06:00:00 -> 17:59:59
	$sql_date_evenement .= " AND TIME_TO_SEC(SUBSTRING(horaire_debut, 9, 8)) > 21600 AND TIME_TO_SEC(SUBSTRING(horaire_debut, 9, 8)) < 64800 ";
}
else if ($get['moment'] == "soir")
{
	$sql_date_evenement .= " AND TIME_TO_SEC(SUBSTRING(horaire_debut, 9, 8)) >= '64799' AND TIME_TO_SEC(SUBSTRING(horaire_debut, 9, 8)) <  86399 ";
	//18:00:00 -> 23:59:59
}
else if ($get['moment'] == "nuit")
{
	$sql_date_evenement .= " AND TIME_TO_SEC(SUBSTRING(horaire_debut, 9, 8)) >= 0 AND TIME_TO_SEC(SUBSTRING(horaire_debut, 9, 8)) < 21600";
}



$tab_zones = array("ville", "communes", "exterieur", "tout");

if (isset($_GET['zone']))
{

	$get['zone'] = verif_get($_GET['zone'], "enum", 1, $tab_zones);


	if ($get['zone'] == "ville")
	{
		$sql_date_evenement .= " AND quartier IN (";

		foreach ($glo_tab_quartiers as $q)
		{
			if ($q == "communes")
			{
				break;
			}
			else
			{
				$sql_date_evenement .= "'".$q."', ";
			}
		}
		$sql_date_evenement = mb_substr($sql_date_evenement, 0 , -2).") ";

	}
	else if ($get['zone'] == "communes")
	{
		$sql_date_evenement .= " AND quartier IN (";

		$zone = "";

		foreach ($glo_tab_quartiers as $q)
		{
			if ($q != "communes" && empty($zone))
			{
				continue;
			}
			else if ($q == "communes")
			{
				$zone = "communes";
			}
			else if ($q == "ailleurs")
			{
				break;
			}
			else if ($zone == "communes" )
			{
				$sql_date_evenement .= "'".$q."', ";
			}

		}


		$sql_date_evenement = mb_substr($sql_date_evenement, 0 , -2).") ";
	}
	else if ($get['zone'] == "exterieur")
	{
		$sql_date_evenement .= " AND quartier IN (";
		$zone = "";

		foreach ($glo_tab_quartiers as $q)
		{
			if ($q != "ailleurs" && empty($zone))
			{
				continue;
			}
			else if ($q == "ailleurs")
			{
				$zone = "ailleurs";
			}
			else if ($zone == "ailleurs" && $q != "autre")
			{
				$sql_date_evenement .= "'".$q."', ";
			}
		}
		$sql_date_evenement = mb_substr($sql_date_evenement, 0 , -2).") ";
	}
}
else
{
	$get['zone'] = "tout";
}

if ($get['genre'] == '')
{
	$genre_titre = 'Tout';
}
else
{
	$genre_titre = $glo_tab_genre[$get['genre']];
}

$entete_contenu = "";
if ($genre_titre != 'Tout')
	$entete_contenu =  ucfirst($genre_titre)." du ";

$annee_courant = mb_substr($get['courant'], 0, 4);
$mois_courant = mb_substr($get['courant'], 5, 7);
$jour_courant = mb_substr($get['courant'], 8, 12);

$lien_precedent = '';
$lien_suivant = '';
if ($get['sem'] == 0)
{

	$entete_contenu .= date_fr($get['courant'], "annee");

	if ($genre_titre == 'Tout')	
		$entete_contenu = ucfirst($entete_contenu);
	
	$precedent = date("Y-m-d", mktime(0, 0, 0, (int)$mois_courant, $jour_courant - 1, $annee_courant));
	$lien_precedent = "<a href=\"".$url_site."agenda.php?".arguments_URI($get)."&amp;courant=".$precedent."\" style=\"border-radius:3px 0 0 3px;\">".$iconePrecedent."</a>";
	$suivant = date("Y-m-d", mktime(0, 0, 0, (int)$mois_courant, $jour_courant + 1, $annee_courant));

	$lien_suivant = "<a href=\"".$url_site."agenda.php?".arguments_URI($get)."&amp;courant=".$suivant."\" style=\"border-radius:0 3px 3px 0;\">".$iconeSuivant."</a>";
}
else if ($get['sem'] == 1)
{
	if ($genre_titre == 'Tout')	
		$entete_contenu = ucfirst($entete_contenu);
		
	$entete_contenu .= date_fr($lundim[0], "non", "non", "non")." au ".date_fr($lundim[1], "annee", "non", "non");
	$precedent = date("Y-m-d", mktime(0, 0, 0, (int)$mois_courant  ,(int)($jour_courant - 7), $annee_courant));
	$lien_precedent = "<a href=\"".$url_site."agenda.php?mode=".$get['mode']."&amp;courant=".$precedent."&amp;sem=1&amp;genre=".$get['genre']."\" style=\"border-radius:3px 0 0 3px;\">".$iconePrecedent."</a>";

	$suivant = date("Y-m-d", mktime(0, 0, 0, (int)$mois_courant  , $jour_courant + 7, $annee_courant));
	$lien_suivant = "<a href=\"".$url_site."agenda.php?mode=".$get['mode']."&amp;courant=".$suivant."&amp;sem=1&amp;genre=".$get['genre']."\" style=\"border-radius:0 3px 3px 0;\">".$iconeSuivant."</a>";

}

$vue_condense_ici = "";
$vue_etendu_ici = "";
if ($get['mode'] == "condense") {	$vue_condense_ici = " class=\"ici\""; }
if ($get['mode'] == "etendu") {	$vue_etendu_ici = " class=\"ici\""; }

$sql_genre = '';
if ($get['genre'] != '')
{
	$sql_genre = "genre='".$get['genre']."' AND";
	$sql_tri_agenda = 'dateEvenement, '.$sql_tri_agenda;
}
else if ($get['sem'] == 0)
{
	$sql_tri_agenda = " CASE `genre`
       WHEN 'fête' THEN 1
       WHEN 'cinéma' THEN 2
       WHEN 'théâtre' THEN 3
       WHEN 'expos' THEN 4
       WHEN 'divers' THEN 5 END, dateEvenement, ".$sql_tri_agenda;
}
else
{
	$sql_tri_agenda = " dateEvenement, CASE `genre`
       WHEN 'fête' THEN 1
       WHEN 'cinéma' THEN 2
       WHEN 'théâtre' THEN 3
       WHEN 'expos' THEN 4
       WHEN 'divers' THEN 5 END, ".$sql_tri_agenda;
}

if (isset($_GET['page']))
{
	$get['page'] = (int)$_GET['page'];
}
else
{
	$get['page'] = 1;
}

$sql_region = " region='".$connector->sanitize($_SESSION['region'])."' ";


$get['nblignes'] = 40;

$limite = " LIMIT ".($get['page'] - 1) * $get['nblignes'].",".$get['nblignes'];

$sql_even = "SELECT idEvenement, idLieu, idSalle, statut, genre, nomLieu, adresse, quartier, localite.localite AS localite,
 titre, idPersonne, dateEvenement, URL1, flyer, image, description, horaire_complement, horaire_debut, horaire_fin, prix, prelocations
 FROM evenement, localite
 WHERE evenement.localite_id=localite.id AND ".$sql_genre." dateEvenement ".$sql_date_evenement." AND statut!='inactif' AND ".$sql_region." 
 ORDER BY ".$sql_tri_agenda;

//echo $sql_even;
 
$req_nb = $connector->query($sql_even);
$total_even = $connector->getNumRows($req_nb);

$sql_even = $sql_even.$limite;



 
$req_even = $connector->query($sql_even);
$nb_evenements = $connector->getNumRows($req_even);

$lien_condense = '<a href="'.basename(__FILE__).'?'.$url_query_region_et.'mode=condense&amp;courant='.$get['courant'].'&amp;sem='.$get['sem'].'&amp;genre='.$get['genre'].'&amp;tri_agenda='.$get['tri_agenda'].'" title="Vue condensée" '.$vue_condense_ici.'>';
$lien_etendu = '<a href="'.basename(__FILE__).'?'.$url_query_region_et.'mode=etendu&amp;courant='.$get['courant'].'&amp;sem='.$get['sem'].'&amp;genre='.$get['genre'].'&amp;tri_agenda='.$get['tri_agenda'].'" title="Vue étendue" '.$vue_etendu_ici.' >';
$lien_imprimer = '<a href="'.basename(__FILE__).'?'.arguments_URI($get).'&amp;style=imprimer" title="Format imprimable">';
?>

<!-- Deb contenu -->
<div id="contenu" class="colonne">

  
    
	<div id="entete_contenu">

		<h2 style="font-size:1.6em">Agenda</h2>	
                
                <?php if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 1) { ?>
                <ul class="menu_region"><?php 
                    foreach ($glo_regions as $n => $v)
                    {
                        if ($n == 'ge' || $n == 'vd')
                        {
                            if ($n == 'vd')
                            {
                                $v = 'Lausanne';
                            }                            
                            
                        $ici = '';
                        if ($n == $_SESSION['region'])
                            $ici = ' class="ici" ';
                    ?><li><a href="?region=<?php echo $n; ?>" <?php echo $ici; ?>><?php echo $v; ?></a></li><?php
                        }
                    }
                    ?></ul>
		
                <?php } ?>
                <div class="spacer"></div>
                <div style="margin-top:1em;">
               
                <h3 style="color: #888;font-size: 1.2em;margin-top: 0.2em;"><?php echo $entete_contenu ?></h3> 
                
                		<ul class="entete_contenu_navigation ">

			<li class="mode">
			<?php echo $lien_condense.$icone['mode_condense'] ?></a><?php echo $lien_etendu.$icone['mode_etendu'] ?></a>
			</li>
			

                <li><?php echo $lien_precedent.$lien_suivant; ?></li></ul>
                </div>
        <div class="spacer"></div>              
                
	</div>
	<!-- Fin entete_contenu -->

	<div class="spacer"></div>
	<?php
	//echo $nb_evenements;
	echo getPaginationString($get['page'], $total_even, $get['nblignes'], 1, $_SERVER['PHP_SELF'], "?".arguments_URI($get, "page")."&page=");
?>


<?php
if ($get['mode'] == "condense")
{

	if ($nb_evenements == 0)
	{
	  	echo msgInfo("Pas d'événements", "p");
	}
	else
	{

		$dateCourante = ' ';
		$genre_courant = '';
		echo '<table id="evenements_s" summary="Liste des événements">';

		while ($tab_even = $connector->fetchArray($req_even))
		{
			$table_header = "";
			
			//ajout d'une bande avec la date et un bouton si ce n'est pas la premià¨re
			if ($dateCourante != $tab_even['dateEvenement'] && $get['sem'] == 1)
			{
		   		$dateSep = explode("-", $tab_even['dateEvenement']);
				$tab_jours_semaine[] = $tab_even['dateEvenement'];
		   		//lien interne avec le no de jour
				$nomJour = date("l", mktime(0, 0, 0, $dateSep[1], $dateSep[2], $dateSep[0]));

				$entete_membre = "";
	 			if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 6 ||
				$_SESSION['SidPersonne'] == $tab_even['idPersonne']
				|| est_organisateur_evenement($_SESSION['SidPersonne'], $tab_even['idEvenement'])
				|| est_organisateur_lieu($_SESSION['SidPersonne'], $tab_even['idLieu'])
				))
				{
					$entete_membre = "<th colspan=\"2\"></th>";
				}
				?>

				<tr>
				<th colspan="3"><a name="<?php echo $tab_even['dateEvenement'] ?>" id="date_<?php echo $tab_even['dateEvenement']; ?>"></a>
				<?php echo ucfirst(date_fr($tab_even['dateEvenement'])); ?>
				</th>
				<?php
				echo $entete_membre;
				?>
				</tr>
			<?php
			}

			if ($tab_even['genre'] != $genre_courant)
			{
				echo '<tr class="genre" ><td colspan="3">'.ucfirst(nom_genre($tab_even['genre'])).'</td></tr>';
			}

			//Affichage du lieu selon son existence ou non dans la base
			if ($tab_even['idLieu'] != 0)
			{
				$listeLieu = $connector->fetchArray(
				$connector->query("SELECT nom, adresse, quartier, localite.localite AS localite, URL FROM lieu, localite WHERE lieu.localite_id=localite.id AND idlieu='".$tab_even['idLieu']."'"));

				$infosLieu = "<a href=\"".$url_site."lieu.php?idL=".$tab_even['idLieu']."\" title=\"Voir la fiche du lieu : ".htmlspecialchars($listeLieu['nom'])."\" >".htmlspecialchars($listeLieu['nom'])."</a>";
				if ($tab_even['idSalle'])
				{
							$req_salle = $connector->query("SELECT nom FROM salle WHERE idSalle='".$tab_even['idSalle']."'");
							$tab_salle = $connector->fetchArray($req_salle);
							$infosLieu .= " - ".$tab_salle['nom'];
				}
			}
			else
			{
				$listeLieu['nom'] = htmlspecialchars($tab_even['nomLieu']);
				$infosLieu = htmlspecialchars($tab_even['nomLieu']);
				$listeLieu['adresse'] = htmlspecialchars($tab_even['adresse']);
				$listeLieu['quartier'] = htmlspecialchars($tab_even['quartier']);
				$listeLieu['localite'] = htmlspecialchars($tab_even['localite']);
			}
                        
                        $adresse = htmlspecialchars(get_adresse(null, $listeLieu['localite'], $listeLieu['quartier'], $listeLieu['adresse']));


			$maxChar = trouveMaxChar($tab_even['description'], 25, 3);
			$titre_url = "<a href=\"".$url_site."evenement.php?idE=".$tab_even['idEvenement']."\" title=\"\" >".securise_string($tab_even['titre'])."</a>";
			$titre = titre_selon_statut($titre_url, $tab_even['statut']);
			if (mb_strlen($tab_even['description']) > $maxChar)
			{
				$description = html_substr(textToHtml(htmlspecialchars($tab_even['description'])), $maxChar, "");
			}
			else
			{
				$description = textToHtml(htmlspecialchars($tab_even['description']));
			}

			$horaire = horaire2heure($tab_even['horaire_debut'], $tab_even['dateEvenement']);

			$td_actions = "";
			if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 6 ||
			$_SESSION['SidPersonne'] == $tab_even['idPersonne'])
		|| (isset($_SESSION['Saffiliation_lieu']) && !empty($tab_even['idLieu']) && $tab_even['idLieu'] == $_SESSION['Saffiliation_lieu']
		|| isset($_SESSION['Sgroupe']) && est_organisateur_evenement($_SESSION['SidPersonne'], $tab_even['idEvenement']))
		|| isset($_SESSION['Sgroupe']) && est_organisateur_lieu($_SESSION['SidPersonne'], $tab_even['idLieu'])
			)
			{
				$td_actions = "<td class=\"action\"><a href=\"".$url_site."copierEvenement.php?idE=".$tab_even['idEvenement']."\"
				title=\"Copier l'événement\">".$iconeCopier."</a></td>";
				$td_actions = "<td class=\"action\"><a href=\"".$url_site."ajouterEvenement.php?action=editer&amp;idE=".$tab_even['idEvenement']."\"
				title=\"Éditer l'événement\">".$iconeEditer."</a></td>";


			}

			?>
			<tr>
				<td><h2 class="titre"><?php echo $titre ?></h2>
					<p><?php echo $description; ?></p>
				</td>
				<td><h2><?php echo $infosLieu ?></h2><p><?php echo $adresse; ?></p></td>
				<td><?php echo $horaire; ?></td>
				<?php echo $td_actions ?>
			</tr>

			<?php
			$dateCourante = $tab_even['dateEvenement'];
			$genre_courant =  $tab_even['genre'];
		} //fin while

		echo "</table>";

	} // fin if

}
else
{
	if ($get['sem'])
	{
		$sql_genre = '';
		if ($get['genre'] != '')
		{
			$sql_genre = "genre='".$get['genre']."' AND ";
		}
		$sql_dateEven = "
		SELECT DISTINCT dateEvenement
	 FROM evenement
	 WHERE ".$sql_genre." dateEvenement ".$sql_date_evenement." AND statut!='inactif' AND ".$sql_region." 
	 ORDER BY dateEvenement ASC";

/*
		echo $sql_dateEven;
*/

		$req_dateEven = $connector->query($sql_dateEven);
		$tab_date_even = array();
		while ($listeEven = $connector->fetchArray($req_dateEven))
		{
			$tab_date_even[] = $listeEven['dateEvenement'];
		}
		//printr($tab_date_even);
	}


	if ($nb_evenements == 0)
	{
	  	echo msgInfo("Pas d'événements", "p");
	}
	else
	{
		$dateCourante = '';
		$genre_courant = '';
		$tab_jours_semaine = array();

		$i = 0;
		$nb_even_jour = 1;
	?>

<div id="evenements">
<?php

	while ($listeEven = $connector->fetchArray($req_even))
	{
		//ajout d'une bande avec la date et un bouton si ce n'est pas la première
		if ($dateCourante != $listeEven['dateEvenement'] && $get['sem'] == 1)
		{
			$nb_even_jour = 0;
	   		$dateSep = explode("-", $listeEven['dateEvenement']);
			$tab_jours_semaine[] = $listeEven['dateEvenement'];
	   		//lien interne avec le no de jour
			$nomJour = date("l", mktime(0, 0, 0, $dateSep[1], $dateSep[2], $dateSep[0]));
			$menu_date = '<div class="';
			if ($i == 0)
			{
				$menu_date .= 'menu_date_1er';
			}
			else
			{
				$menu_date .= 'menu_date';
			}

			$menu_date .= '">';

			if ($i > 0)
			{
				echo "<ul class=\"menu_ascenseur\">";

				$date_prec = "";
				$date_suiv = "";


				for ($i = 0; $i < count($tab_date_even); $i++)
				{

					/* echo $i;
					echo $tab_date_even[$i]; */
					if ($listeEven['dateEvenement'] == $tab_date_even[$i])
					{
						echo "<li>";
						if ($i > 0)
						{
							echo "<a class=\"vertical\" title=\"Remonter\" href=\"#date_".$tab_date_even[$i - 1]."\">".$icone['monter']."</a>";


						}

						if ($i < count($tab_date_even) - 1)
						{
							echo "<a class=\"vertical\" title=\"Descendre\" href=\"#date_".$tab_date_even[$i + 1]."\">".$icone['descendre']."</a>";

						}
						echo "</li>";
					}


				}
					echo "<li class=\"haut2\">
					<a title=\"Remonter en haut de la page\" href=\"#global\">".$iconeRemonter."</a></li>";

				echo "</ul>";

			}

			echo "\n<h3 class=\"menu_date\">";
			echo "<a name=\"date_".$listeEven['dateEvenement']."\" id=\"date_".$listeEven['dateEvenement']."\"></a>".ucfirst(date_fr($listeEven['dateEvenement']));
			echo "</h3>\n";
//					echo '</div>';
		} //if	
		
		
		if ($listeEven['genre'] != $genre_courant && $get['genre'] == '')
		{
			if ($genre_courant != '')
			{
				echo "</div>";
			}
			?>
			
			<div class="spacer"></div>
			<div class="genre" >

			<div class="genre-titre">
			<h4 id="<?php echo replace_accents($listeEven['genre']); ?>"><?php echo ucfirst(nom_genre($listeEven['genre'])); ?></h4>
			
			<?php if (0) { //(isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 1 && $listeEven['genre'] != 'divers') { ?>
			<a class="genre-jump" href="#<?php echo $proch; ?>"><i class="fa fa-long-arrow-down"></i></a>
			<?php } else { ?>
			<span style="float: right;margin: 0.2em;padding: 0.4em 0.8em;">&nbsp;</span>
			<?php } ?>	
			
			<?php if (0) { //isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 1 && $listeEven['genre'] != 'fête') { ?>
			<a class="genre-jump" href="#<?php echo $genre_prec; ?>"><i class="fa fa-long-arrow-up"></i></a>
			<?php } ?>
				
			
			<div class="spacer"></div>		
			</div>
			<?php
		}

		$genre_courant = $listeEven['genre'];	

	//	echo '<div class="spacer"></div>';

		if ($i != 0 && ($i % 2 != 0 && $get['sem'] == 0) ||
		($get['sem'] == 1 && $nb_even_jour % 2 == 0 && $dateCourante == $listeEven['dateEvenement']))
		{
			echo "<h5>".ucfirst(date_fr($dateCourante));
			echo "</h5><div class=\"spacer\"></div>";
		}

		$dateCourante = $listeEven['dateEvenement'];
		$nb_even_jour++;


		//Affichage du lieu selon son existence ou non dans la base
		if (!empty($listeEven['idLieu']))
		{
			$listeLieu = $connector->fetchArray($connector->query("SELECT nom, adresse, quartier, localite.localite AS localite, URL FROM lieu, localite WHERE lieu.localite_id=localite.id AND idlieu='".$listeEven['idLieu']."'"));

			$infosLieu = "<a href=\"".$url_site."lieu.php?idL=".$listeEven['idLieu']."\" title=\"Voir la fiche du lieu : ".htmlspecialchars($listeLieu['nom'])."\" >".htmlspecialchars($listeLieu['nom'])."</a>";
			if ($listeEven['idSalle'])
			{
						$req_salle = $connector->query("SELECT nom FROM salle WHERE idSalle='".$listeEven['idSalle']."'");
						$tab_salle = $connector->fetchArray($req_salle);
						$infosLieu .= " - ".$tab_salle['nom'];
			}

		}
		else
		{

			$listeLieu['nom'] = htmlspecialchars($listeEven['nomLieu']);
			$infosLieu = htmlspecialchars($listeEven['nomLieu']);
			$listeLieu['adresse'] = htmlspecialchars($listeEven['adresse']);
			$listeLieu['quartier'] = htmlspecialchars($listeEven['quartier']);
			$listeLieu['localite'] = htmlspecialchars($listeEven['localite']);

		}

                        
           
                

		$maxChar = trouveMaxChar($listeEven['description'], 70, 8);


		$titre_url = '<a class="url" href="'.$url_site.'evenement.php?idE='.$listeEven['idEvenement'].'&amp;tri_agenda='.$get['tri_agenda'].'&amp;courant='.$get['courant'].'" title="Voir la fiche complète de l\'événement">'.securise_string($listeEven['titre']).'</a>';
		$titre = titre_selon_statut($titre_url, $listeEven['statut']);

		$lien_flyer = "";
		if (!empty($listeEven['flyer']))
		{
			$imgInfo = @getimagesize($rep_images.$listeEven['flyer']);
			//$lien_flyer = lien_popup($IMGeven.$listeEven['flyer']."?".@filemtime($rep_images_even.$listeEven['flyer']), "Flyer", $imgInfo[0]+20, $imgInfo[1]+20, "<img src=\"".$IMGeven."s_".$listeEven['flyer']."?".@filemtime($rep_images_even.$listeEven['flyer'])."\" alt=\"Flyer\" />");

			$lien_flyer = '<a href="'.$IMGeven.$listeEven['flyer']."?".@filemtime($rep_images_even.$listeEven['flyer']).'" class="magnific-popup"><img src="'.$IMGeven."s_".$listeEven['flyer']."?".@filemtime($rep_images_even.$listeEven['flyer']).'"  alt="Flyer" /></a>';
			
		}
		else if ($listeEven['image'] != '')
		{
		
			$imgInfo = @getimagesize($rep_images.$listeEven['image']);
			
			//$lien_flyer = lien_popup($IMGeven.$listeEven['image']."?".@filemtime($rep_images_even.$listeEven['image']), "Image", $imgInfo[0]+20, $imgInfo[1]+20,"<img src=\"".$IMGeven."s_".$listeEven['image']."?".@filemtime($rep_images_even.$listeEven['image'])."\" alt=\"Image\" />");

			$lien_flyer = '<a href="'.$IMGeven.$listeEven['image']."?".@filemtime($rep_images_even.$listeEven['image']).'" class="magnific-popup"><img src="'.$IMGeven."s_".$listeEven['image']."?".@filemtime($rep_images_even.$listeEven['image']).'"  alt="Photo" /></a>';			
			
		}

		if (mb_strlen($listeEven['description']) > $maxChar)
		{
			$description = texteHtmlReduit(textToHtml($listeEven['description']),
			$maxChar);
			$description .= "<span class=\"continuer\">
			<a href=\"".$url_site."evenement.php?idE=".$listeEven['idEvenement']."&amp;tri_agenda=".$get['tri_agenda']."\" title=\"Voir la fiche complète de l'événement\"> Lire la suite</a></span>";

		}
		else
		{
			$description = textToHtml($listeEven['description']);
		}

		$adresse = htmlspecialchars(get_adresse(null, $listeLieu['localite'], $listeLieu['quartier'], $listeLieu['adresse']));
		
		$horaire = afficher_debut_fin($listeEven['horaire_debut'], $listeEven['horaire_fin'], $listeEven['dateEvenement']);

		// TODO : marche pas, à corriger (voir valeurs d'even sans début ou fin)
		if (($listeEven['horaire_debut'] != '0000-00-00 00:00:00' || $listeEven['horaire_fin'] != '0000-00-00 00:00:00') && !empty($listeEven['horaire_complement']) )
		{
			$horaire .= " ".lcfirst(htmlspecialchars($listeEven['horaire_complement'])) ;
		}				
		else
		{
			$horaire .= htmlspecialchars($listeEven['horaire_complement']);
		}

	if (!empty($listeEven['prix']))
	{
		if (!empty($listeEven['horaire_debut']) || !empty($listeEven['horaire_fin']) || !empty($listeEven['horaire_complement']))
		{
			$horaire .= ", ";
		}
		$horaire .= htmlspecialchars($listeEven['prix']);
		
	}


		$sql_dateEven = "
		SELECT idCommentaire
		 FROM commentaire
		 WHERE id='".$listeEven['idEvenement']."' AND statut='actif'";

		// echo $sql_even;
		$commentaires = "";
		$req_dateEven = $connector->query($sql_dateEven);
		$nb_comm = $connector->getNumRows($req_dateEven);
		if ($nb_comm > 0)
		{
			$pluriel = "";
			if ($nb_comm > 1)
				$pluriel = "s";

			$commentaires = '<li>'.$icone['commentaire'].'
			<a href="'.$url_site.'evenement.php?idE='.$listeEven['idEvenement'].'&amp;tri_agenda='.$get['tri_agenda'].'&amp;courant='.$get['courant'].'#borne_commentaires"
			title="Voir le'.$pluriel.' '.$nb_comm.' commentaires">'.$nb_comm.' commentaire'.$pluriel.'</a>';
			$commentaires .= '</li>';
		}
		
/* 		$sql = "
		SELECT idPersonne
		 FROM participation
		 WHERE idEvenement=".$listeEven['idEvenement'];

		// echo $sql_even;
		$participants = "";
		$req = $connector->query($sql);
		$nb_part = $connector->getNumRows($req);
		if ($nb_part > 0)
		{
			$pluriel = "";
			if ($nb_part > 1)
				$pluriel = "s";

			$participants = '<li>';
			$participants .= $nb_part;
			$participants .= ' participant'.$pluriel.'</li>';
		} */		

		$actions = "";
		if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 12))
		{
			if ($nb_comm == 0)
			{
			$actions .= '<li>
			<a href="'.$url_site.'evenement.php?idE='.$listeEven['idEvenement'].'&amp;tri_agenda='.$get['tri_agenda'].'&amp;courant='.$get['courant'].'#commentaires"
			title="Ajouter un commentaire à cet événement">'.$icone['ajouter_commentaire'].'</a></li>';
			}
			$req_nb_favori = $connector->query("SELECT * FROM evenement_favori
			WHERE idEvenement=".$listeEven['idEvenement']." AND idPersonne=".$_SESSION['SidPersonne']);

			$nb_favori = $connector->getNumRows($req_nb_favori);

			$actions .= '<li>';
			if ($nb_favori == 0)
			{
				$actions .= '<a href="'.$url_site.'action_favori.php?action=ajouter&amp;element=evenement&amp;idE='.$listeEven['idEvenement'].'"
			title="Ajouter à vos favoris">'.$icone['ajouter_favori'].'</a>';
			}
			else
			{
				$actions .= $icone['favori'].'en favori <a href="'.$url_site.'action_favori.php?action=supprimer&amp;element=evenement&amp;idE='.$listeEven['idEvenement'].'"
			title="Enlever de vos favoris">'.$icone['supprimer_favori'].'</a>';
			}

			$actions .= '</li>';
			
			
			
			

		}
		
		$ajouter_calendrier = '<li>'.$icone['ajouter_calendrier'].'</li>';

?>

		<div class="evenement vevent">
			<div class="dtstart">
			<span class="value-title" title="<?php echo $listeEven['dateEvenement']; ?>T<?php echo $listeEven['dateEvenement']; ?>:00"></span>
			
			</div>		
			<div class="titre">
			<span class="left summary"><?php echo $titre; ?></span><span class="right location"><?php echo $infosLieu ?></span>
			<div class="spacer"></div>
			</div>
			<div class="spacer"></div>
			<div class="flyer photo"><?php echo $lien_flyer;	?>
			</div>
			<div class="description"><?php echo $description;?></div>
			
			<div class="pratique"><span class="left"><?php echo $adresse; ?></span><span class="right"><?php echo $horaire; ?></span>
				<div class="spacer"></div>
			</div>
			<!-- fin pratique -->
			<div class="spacer"></div>
			<div class="edition">
			<ul class="menu_action">
			<?php
			echo $commentaires;
			//echo $participants;
			echo $actions;
			//echo $ajouter_calendrier;
			?>
			</ul>
			
		<?php
		if (isset($_SESSION['Sgroupe'])
		&& (
		$_SESSION['Sgroupe'] <= 6
		|| $_SESSION['SidPersonne'] == $listeEven['idPersonne']
	|| (isset($_SESSION['Saffiliation_lieu']) && !empty($listeEven['idLieu']) && $listeEven['idLieu'] == $_SESSION['Saffiliation_lieu'])
	|| est_organisateur_evenement($_SESSION['SidPersonne'], $listeEven['idEvenement'])
	|| est_organisateur_lieu($_SESSION['SidPersonne'], $listeEven['idLieu'])
		))
		{
		?>
			<ul class="menu_edition">
			<li class="action_copier">
			<a href="<?php echo $url_site; ?>copierEvenement.php?idE=<?php echo $listeEven['idEvenement']; ?>" title="Copier l'événement">Copier</a>
			</li>

		
		
			<li class="action_editer">
			<a href="<?php echo $url_site; ?>ajouterEvenement.php?idE=<?php echo $listeEven['idEvenement']; ?>&amp;action=editer" title="Éditer l'événement">Éditer</a>
			</li>
			</ul>
			<?php
		}
		?>

			</div>
		</div>

		<?php
		$i++;
	} //while
	
	if ($genre_courant != '' && $get['genre'] == '')
	{
		echo "</div>";
	}
	?>
	</div>
	<!-- fin evenements -->
<?php
} //while

} // else nbeven > 0

$lim_nbeven = 12;
if ($get['mode'] == "etendu")
{
	$lim_nbeven = 5;
}

if ($nb_evenements > $lim_nbeven)
	{ 
	echo getPaginationString($get['page'], $total_even, $get['nblignes'], 1, $_SERVER['PHP_SELF'], "?".arguments_URI($get, "page")."&page=");
	?>
	
	<?php if (0) { ?>
	<div id="entete_contenu">
		<h2><?php echo $entete_contenu ?></h2>
		<ul>
			<li><?php echo $lien_precedent ?></li>
			<li><?php echo $lien_suivant ?></li>
			<li>Vue :</li>
			<li <?php echo $vue_condense_ici; ?>><?php echo $lien_condense; echo $icone['mode_condense'] ?></a></li>
			<li <?php echo $vue_etendu_ici; ?>><?php echo $lien_etendu; echo $icone['mode_etendu'] ?></a></li>
			<li><?php echo $lien_imprimer; echo $iconeImprimer ?></a></li>
		</ul>
		<div class="spacer"></div>
	</div>
	<!-- Fin entete_contenu -->
	<?php } ?>
	
	<div id="entete_contenu">
		<h2><?php echo $entete_contenu ?></h2>			

		<ul class="entete_contenu_navigation">

			<li>
			<?php echo $lien_condense.$icone['mode_condense'] ?></a><?php echo $lien_etendu.$icone['mode_etendu'] ?></a>
			</li>
			<li><?php echo $lien_precedent.$lien_suivant; ?></li>
	</ul>
		<div class="spacer"></div>
	</div>
	<!-- Fin entete_contenu -->	


<?php
} //if nb evenement
?>

</div>
<!-- fin contenu -->

<div id="colonne_gauche" class="colonne">

<?php include("includes/navigation_calendrier.inc.php"); ?>

</div>
<!-- Fin Colonnegauche -->


<?php
$tri_ajout = '';
if ($get['tri_agenda'] == "dateAjout") $tri_ajout = "ici";

?>

<div id="colonne_droite" class="colonne">

	<!-- Deb selection -->
	<div id="selection">

		<ul class="menu_selection" id="menu_genre">

<?php
			echo "<li style=\"font-weight:bold\"";
			if ($get['genre'] == "tout" || $get['genre'] == "")
			{
				echo " class=\"ici\"";
			}
			echo ">
			<a href=\"".$url_site."agenda.php?".$url_query_region_et."courant=".$get['courant']."&amp;sem=".$get['sem']."&amp;tri_agenda=".$get['tri_agenda']."&amp;mode=".$get['mode']."&zone=".$get['zone']."&moment=".$get['moment']."\" title=\"Tous les genres d'événements\">Tout</a></li>";
		
		foreach ($glo_tab_genre as $na => $la)
		{
			echo "<li";
			if ($na == $get['genre'])
			{
				echo " class=\"ici\"";
			}

			echo ">
			<a href=\"".$url_site."agenda.php?".$url_query_region_et."genre=".$na."&amp;courant=".$get['courant']."&amp;sem=".$get['sem']."&amp;tri_agenda=".$get['tri_agenda']."&amp;mode=".$get['mode']."&zone=".$get['zone']."&moment=".$get['moment']."\" title=\"".$la."\">".ucfirst($la)."</a></li>";
		}

	?>
		</ul>
		
		<ul class="menu_selection">

			<li<?php if ($get['moment'] == "tout") echo " class=\"ici\" style=\"font-weight:bold\"" ?>>
			<a href="<?php echo $url_site ?>agenda.php?<?php echo arguments_URI($get, "moment") ?>&amp;moment=tout">Tout</a>
			</li>

			<li<?php if ($get['moment'] == "journee") echo " class=\"ici\"" ?>>
			<a href="<?php echo $url_site ?>agenda.php?<?php echo arguments_URI($get, "moment") ?>&amp;moment=journee">Journée</a>
			</li>
			<li<?php if ($get['moment'] == "soir") echo " class=\"ici\"" ?>>
			<a href="<?php echo $url_site ?>agenda.php?<?php echo arguments_URI($get, "moment") ?>&amp;moment=soir">Soir</a>
			</li>
			<li<?php if ($get['moment'] == "nuit") echo " class=\"ici\"" ?>>
			<a href="<?php echo $url_site ?>agenda.php?<?php echo arguments_URI($get, "moment") ?>&amp;moment=nuit">Nuit</a>
			</li>
		</ul>
		<ul class="menu_selection">

			<li<?php if ($get['zone'] == "tout") echo " class=\"ici\" style=\"font-weight:bold\"" ?>>
			<a href="<?php echo $url_site ?>agenda.php?<?php echo arguments_URI($get, "zone") ?>&amp;zone=tout">Tout</a>
			</li>

			<li<?php if ($get['zone'] == "ville") echo " class=\"ici\"" ?>>
			<a href="<?php echo $url_site ?>agenda.php?<?php echo arguments_URI($get, "zone") ?>&amp;zone=ville">Ville</a>
			</li>
			<li<?php if ($get['zone'] == "communes") echo " class=\"ici\"" ?>>

			<a href="<?php echo $url_site ?>agenda.php?<?php echo arguments_URI($get, "zone") ?>&amp;zone=communes">Communes</a>
			</li>
			<li<?php if ($get['zone'] == "exterieur") echo " class=\"ici\"" ?>>
			<a href="<?php echo $url_site ?>agenda.php?<?php echo arguments_URI($get, "zone") ?>&amp;zone=exterieur">Extérieur</a>
			</li>
		</ul>
		
		<h2>Trier par</h2>
		<ul class="menu_selection">
			<li class="<?php echo $tri_ajout; ?> tri_ajout">
			<a href="<?php echo $url_site ?>agenda.php?<?php echo arguments_URI($get, "tri_agenda") ?>&amp;tri_agenda=dateAjout">Date d’ajout</a>
			</li>
			<li class="<?php if ($get['tri_agenda'] == "horaire_debut") echo "ici" ?> tri_heure">
			<a href="<?php echo $url_site ?>agenda.php?<?php echo arguments_URI($get, "tri_agenda") ?>&amp;tri_agenda=horaire_debut">Heure de début</a>
			</li>
		</ul>		
		
		
		<?php
		$liste = '';
		if ($get['sem'] == 1 && $nb_evenements > 1)
		{

			for ($i = 1; $i < count($tab_jours_semaine); $i++)
			{
				$liste = "<li class=\"vers_jour\"><a href=\"#date_".$tab_jours_semaine[$i]."\" title=\"".$tab_jours_semaine[$i]."\">".
				date_fr($tab_jours_semaine[$i])."</a>
				</li>";
			}

			?>
			<h2>Aller à</h2>
			<ul class="menu_selection">
			<?php echo $liste ?>
			</ul>
			<?php
		}
			?>
	</div>
	<!-- Fin selection -->

</div>


<div class="spacer"><!-- --></div>
<?php
include("includes/footer.inc.php");
?>
