<?php
if (is_file("config/reglages.php"))
{
	require_once("config/reglages.php");
}
require_once($rep_librairies."Sentry.php");
$videur = new Sentry();

require_once($rep_librairies."Lieu.class.php");
require_once($rep_librairies."CollectionDescription.class.php");
require_once($rep_librairies."Commentaire.class.php");
require_once($rep_librairies."CollectionCommentaire.class.php");
require_once($rep_librairies."Evenement.class.php");
require_once($rep_librairies."CollectionEvenement.class.php");
echo "TEST";
include("includes/tracerVisiteur.php");

/* if (!isset($_GET['idL']) || !is_numeric($_GET['idL']))
{
	echo "Un ID lieu doit ètre désigné par un entier";
	exit;
}
else
{
	$get['idL'] = trim($_GET['idL']);
} */
/* if (isset($_GET['genre_even']))
{

	$get['genre_even'] = trim($_GET['genre_even']);
} */

if (isset($_GET['idL']))
{
	$get['idL'] = verif_get($_GET['idL'], "int", 1);
}
else
{
	trigger_error("id obligatoire", E_USER_WARNING);
	exit;
}

$tab_genre_even = array("fête", "cinéma", "théâtre", "expos", "divers", "tous");
$get['genre_even'] = "tous";
if (isset($_GET['genre_even']))
{
	$get['genre_even'] = verif_get($_GET['genre_even'], "enum", 0, $tab_genre_even);
}

$tab_complement = array("evenements", "commentaires");
$get['complement'] = "evenements";
if (isset($_GET['complement']))
{
	$get['complement'] = verif_get($_GET['complement'], "enum", 0, $tab_complement);
}

$tab_types_description = array("description", "presentation");
$get['type_description'] = "";
if (isset($_GET['type_description']))
{
	$get['type_description'] = verif_get($_GET['type_description'], "enum", 0, $tab_types_description);
}

$lieu = new Lieu();
$lieu->setId($get['idL']);
$lieu->load();

//printr($lieu->getValues());

$page_titre = $lieu->getValue('nom')." (".$lieu->getValue('quartier').")";
$tab_ext = array("Nyon", "Vaud", "France", "autre");
if (!in_array($lieu->getValue('quartier'), $tab_ext))
{
	$page_titre .= " Genève";
}
$page_description = "Fiche du lieu ".$lieu->getValue('nom')." - ".$lieu->getValue('adresse');
$page_description = ": informations pratiques, description, galerie de photos et prochains événements";

$extra_css = array("menu_lieux", "element_login");

include("includes/header.inc.php");

include("includes/menulieux.inc.php");

$logo = '';
if ($lieu->getValue('logo') !='')
{
	$imgInfo = getimagesize($rep_images_lieux.$lieu->getValue('logo'));

	$logo = lien_popup($IMGlieux.$lieu->getValue('logo').'?'.filemtime($rep_images_lieux.$lieu->getValue('logo')),
	"Logo", $imgInfo[0]+20, $imgInfo[1]+20,
	"<img src=\"".$IMGlieux."s_".$lieu->getValue('logo')."?".filemtime($rep_images_lieux."s_".$lieu->getValue('logo'))."\" alt=\"Logo\" />");

}

$lieu_ancien = '';
if ($lieu->getValue('statut') == 'ancien')
{
	$lieu_ancien = '<div class="spacer"><!-- --></div>
<p class="info"><strong>Ce lieu n\'existe plus</strong></p>';
}

$menu_actions = '';
if ((isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 12))
{
	$req_nb_favori = $connector->query("SELECT * FROM lieu_favori
	WHERE idLieu=".$get['idL']." AND idPersonne=".$_SESSION['SidPersonne']);

	$nb_favori = $connector->getNumRows($req_nb_favori);


	if ($nb_favori == 0)
	{
		$menu_actions .= '<li><a href="'.$url_site.'action_favori.php?action=ajouter&amp;element=lieu&amp;idL='.$get['idL'].'"
	title="Ajouter à vos favoris">'.$icone['ajouter_favori'].'Ajouter aux favoris</a></li>';
	}
	else
	{
		$menu_actions .= '<li><a href="'.$url_site.'action_favori.php?action=supprimer&amp;element=lieu&amp;idL='.$get['idL'].'"
	title="Ajouter à vos favoris">'.$icone['supprimer_favori'].'Enlever des favoris</a></li>';
	}
}

$action_ajouter = '';
if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 10))
{
	$action_ajouter = '<li class="action_ajouter"><a href="'.$url_site.'ajouterEvenement.php?idL='.$get['idL'].'" title="ajouter un événement à ce lieu">Ajouter un événement à ce lieu</a></li>';
}

$action_editer = '';
if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 6))
{
	$action_editer = '<li class="action_editer"><a href="'.$url_site.'ajouterLieu.php?action=editer&amp;idL='.$get['idL'].'" title="Éditer ce lieu">Modifier</a></li>';
}

$lien_prec = '';
if ($url_prec != "")
{
	$lien_prec = '<a href="'.$url_prec.'" title="Lieu précédent dans la liste">'.$iconePrecedent.'</a>';
}

$lien_suiv = '';
if ($url_suiv != "")
{
	$lien_suiv = '<a href="'.$url_suiv.'" title="Lieu suivant dans la liste">'.$iconeSuivant.'</a>';
}

$req_nb_des = $connector->query("SELECT idPersonne FROM descriptionlieu WHERE descriptionlieu.idLieu=".$get['idL']);

$class_vide = '';
if ($connector->getNumRows($req_nb_des) == 0)
{
	$class_vide = ' class="vide"';
}

$photo_principale = '';
if ($lieu->getValue('photo1') != '')
{

	$imgInfo = getimagesize($rep_images_lieux.$lieu->getValue('photo1'));

	$photo_principale = lien_popup($IMGlieux.$lieu->getValue('photo1').'?'.filemtime($rep_images_lieux.$lieu->getValue('photo1')),
	"Logo", $imgInfo[0]+20, $imgInfo[1]+20,
	"<img src=\"".$IMGlieux."s_".$lieu->getValue('photo1')."?".filemtime($rep_images_lieux."s_".$lieu->getValue('photo1'))."\" alt=\"Photo du lieu\" />");

}
?>


<!-- Début Contenu -->
<div id="contenu" class="colonne">

	<div id="entete_contenu"><?php echo $logo ?>
	<h2><?php echo $lieu->getHtmlValue('nom'); echo $lieu_ancien ?></h2>
	</div>

	<div class="spacer"><!-- --></div>

	<ul class="menu_actions_lieu">
		<?php
		echo $menu_actions;
		echo $action_ajouter;
		echo $action_editer;
		?>
		<li><?php echo $lien_prec; ?></li>
		<li><?php echo $lien_suiv; ?></li>
		<li><a href="<?php echo basename(__FILE__)."?".arguments_URI($get) ?>&amp;style=imprimer" title="Format imprimable">
		<?php echo $iconeImprimer ?></a></li>
	</ul>

	<div class="spacer"><!-- --></div>

	<div id="fiche"<?php echo $class_vide; ?>>

		<!-- Deb medias -->
		<div id="medias">

			<div id="photo">
			<?php echo $photo_principale; ?>
			</div>
			<div class="spacer"><!-- --></div>

			<?php
			/* Galerie d'images */
			$sql_galerie = "SELECT fichierrecu.idFichierrecu AS idFichierrecu, description, mime, extension
			FROM fichierrecu, lieu_fichierrecu
			WHERE lieu_fichierrecu.idLieu=".$get['idL']." AND type='image' AND fichierrecu.idFichierrecu=lieu_fichierrecu.idFichierrecu
			 ORDER BY dateAjout DESC";

			$req_galerie = $connector->query($sql_galerie);

			$req_galerie = $connector->query($sql_galerie);

			if ($connector->getNumRows($req_galerie) > 0)
			{
					echo '<div class="section">
					<h3>Galerie</h3>';
				if ($connector->getNumRows($req_galerie) == 1)
				{
					$tab_galerie = $connector->fetchArray($req_galerie);
					$url_fichier = $url_images_lieu_galeries.$tab_galerie['idFichierrecu'].".".$tab_galerie['extension'];
					$rep_fichier = $rep_images_lieux_galeries.$tab_galerie['idFichierrecu'].".".$tab_galerie['extension'];
					$rep_fichier_s = $rep_images_lieux_galeries."s_".$tab_galerie['idFichierrecu'].".".$tab_galerie['extension'];
					$url_fichier_s = $url_images_lieu_galeries."s_".$tab_galerie['idFichierrecu'].".".$tab_galerie['extension'];
					$imgsize = getimagesize($rep_fichier);

					echo lien_popup($url_fichier, "images", $imgsize[0], $imgsize[1],
					"<img src=\"".$url_fichier_s."?".filemtime($rep_fichier_s)."\" alt=\"Photo du lieu\" />");
				}
				else
				{
					while ($tab_galerie = $connector->fetchArray($req_galerie))
					{
						if (strstr($tab_galerie['mime'], "image"))
						{
							$icone_fichier = $iconeImage;
						}

						$chemin_fichier = $rep_images_lieux_galeries."s_".$tab_galerie['idFichierrecu'].".".$tab_galerie['extension'];
						$url_fichier = $url_images_lieu_galeries."s_".$tab_galerie['idFichierrecu'].".".$tab_galerie['extension'];

						echo lien_popup($url_site."galerielieu.php?idL=".$get['idL']."&amp;idI=".$tab_galerie['idFichierrecu'],
						"galerie", 700, 500, "<img class=\"galerie\" src=\"".$url_fichier."?".filemtime($chemin_fichier)."\" alt=\"photo\" />");
					}
				}
				echo '</div>
					<div class="spacer"></div>';
			}

			/* Documents */
			$sql_docu = "SELECT fichierrecu.idFichierrecu AS idFichierrecu, description, mime, extension
			FROM fichierrecu, lieu_fichierrecu
			WHERE lieu_fichierrecu.idLieu=".$get['idL']." AND type='document' AND
			 fichierrecu.idFichierrecu=lieu_fichierrecu.idFichierrecu
			 ORDER BY dateAjout DESC";

			$req_docu = $connector->query($sql_docu);

			if ($connector->getNumRows($req_docu) > 0)
			{
				echo '<div class="section">
				<h3>Fichiers</h3>
				<ul>';


				while ($tab_docu = $connector->fetchArray($req_docu))
				{
					$chemin_fichier = $rep_fichiers_lieu.$tab_docu['idFichierrecu'].".".$tab_docu['extension'];
					$url_fichier = $url_fichiers_lieu.$tab_docu['idFichierrecu'].".".$tab_docu['extension'];
					echo "<li><a href=\"".$url_fichier."\" >".$icone[strtolower($tab_docu['extension'])].$tab_docu['description']." (".formatbytes(filesize($chemin_fichier)).", ".$tab_docu['extension'].")</a></li>";
				}
				echo "</ul>
					</div>";
			}
			?>
		</div>
		<!-- Fin medias -->

		<?php
		$categories = str_replace(",", ", ", $lieu->getValue('categorie'));
		$adresse = $lieu->getValue('adresse').' - '.$lieu->getValue('quartier');

		$carte = '';
		if ($lieu->getValue('lat') != 0.000000 && $lieu->getValue('lng') != 0.000000)
		{
			$carte = '<li>';
			$carte .= lien_popup($url_site.'gmap.php?idL='.$get['idL'], 'Localisation', 600, 400, $icone['plan'].'Plan');
			$carte .= '</li>';
		}

		$acces_tpg = '';
		if ($lieu->getValue('acces_tpg') != "")
		{
			$acces_tpg = '<li>Accès TPG : '.$lieu->getValue('acces_tpg').'</li>';
		}

		$URL = '';
		if ($lieu->getValue('URL') != '' )
		{

			if (!preg_match("/^http:\/\//", $lieu->getValue('URL')))
			{
				$URL .=  "http://".$lieu->getValue('URL');
			}
			else
			{
				$URL .=  $lieu->getValue('URL');
			}

		}

		$salles = '';
		$sql_salle = "SELECT * FROM salle WHERE idLieu=".$get['idL'];
//		echo $sql_salle;
		$req_salle = $connector->query($sql_salle);

		if ($connector->getNumRows($req_salle) > 0)
		{
			$salles .= 'Salles : ';
			$salles .= '<ul id="salles">';
			while ($tab_salle = $connector->fetchArray($req_salle))
			{
				$salles .= '<li>'.$tab_salle['nom'];

				if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 6)
				{
					$salles .= '<a href="'.$url_site.'ajouterSalle.php?action=editer&amp;idS='.$tab_salle['idSalle'].'">'.$iconeEditer.'</a>';
				}

						$salles .= '</li>';
			}
			$salles .= '</ul>';
		}
		?>
		<!-- Deb pratique -->
		<div id="pratique">

			<ul>
				<li><?php echo $categories; ?></li>
				<li><?php echo $adresse ?></li>
				<?php echo $salles; ?>
				<?php echo $carte; ?>
				<?php echo $acces_tpg; ?>
				<li><?php echo $lieu->getValue('horaire_general'); ?></li>

				<li><a href="<?php echo $URL; ?>" title="Voir le site web du lieu" onclick="window.open(this.href,'_blank');return false;">
				<?php echo $lieu->getValue('URL'); ?></a></li>

			<?php
/*	Pas encore implémenté
 * $sql_even = "SELECT lieu.nom AS nom, idLieu2 FROM lieux_associes, lieu WHERE idLieu1=".$get['idL']." AND idLieu2=lieu.idLieu";


			$req_even = $connector->query($sql_even);
			if ($connector->getNumRows($req_even))
			{
				echo "<li id=\"lieux_lies\">Voir aussi :
					<ul>";
				while ($tab_des = $connector->fetchArray($req_even))
				{
					echo "<li><a href=\"".$url_site."lieu.php?idL=".$tab_des['idLieu2']."\" >".$tab_des['nom']."</a></li>";

				}
				echo "</ul></li>";
			}
*/
			?>

	</ul>

</div>
<!-- Fin pratique -->

<?php
$descriptions = new CollectionDescription();

$nb_desc = 0;
$nb_pres = 0;
	/**
	* Recolte les descriptions
	*/
	if ($descriptions->getNumRows($get['idL']))
	{

?>

<ul id="menu_descriptions">
<?php


$nb_desc = $descriptions->getNumRows($get['idL'], 'description');
$nb_pres = $descriptions->getNumRows($get['idL'], 'presentation');

if ($get['type_description'] == '')
{
	if ($nb_desc > 0)
	{
		$get['type_description'] = 'description';
	}
	else if ($nb_desc == 0 && $nb_pres > 0)
	{
		$get['type_description'] = 'presentation';
	}
}

if ($nb_desc)
{
?>

    <li <?php if ($get['type_description'] == 'description') { echo ' class="ici"'; }?>>
    <a href="<?php echo basename(__FILE__)."?".arguments_URI($get, 'type_descrition') ?>&amp;type_description=description">Description</a>
    </li>
 <?php

 }

if ($nb_pres > 0)
{

?>
    <li <?php if ($get['type_description'] == 'presentation') { echo ' class="ici"'; }?>>
<a href="<?php echo basename(__FILE__)."?".arguments_URI($get, 'type_description') ?>&amp;type_description=presentation"><?php echo $lieu->getValue('nom') ?> se présente</a>
    </li>
 <?php

 }
 ?>
  </ul>
<?php
	}
?>
<div id="descriptions">
<?php


	if ($descriptions->loadByType($get['idL'], $get['type_description']))
	{

		/**
		* Liste les descriptions du lieu
		*/
		foreach ($descriptions->getElements() as $id => $des)
		{
			$dern_modif = '';
			if ($des->getValue('date_derniere_modif') != "0000-00-00 00:00:00" && $des->getValue('date_derniere_modif') != $des->getValue('dateAjout'))
			{

				$dern_modif = "Modifié le ".date_fr($des->getValue('date_derniere_modif'), 'annee');
			}

			$editer = '';
			if ((isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 4)
			|| (isset($_SESSION['SidPersonne'])) && $_SESSION['SidPersonne'] == $des->getValue('idPersonne'))
			{

				$editer = '<span class="right">';
				$editer .= '<a href="'.$url_site.'ajouterDescription.php?action=editer&amp;type='.$get['type_description'].'&amp;idL='.$get['idL'].'&amp;idP='.$des->getValue('idPersonne').'">'.$iconeEditer.' Modifier</a>';
				$editer .= '</span>';
			}

		 ?>

		<div class="description">
			<p><?php echo textToHtml($des->getHtmlValue('contenu')); ?></p>
			<p><?php echo signature_auteur($des->getValue('idPersonne'));?></p>

			<div class="auteur">
				<span class="left">Ajouté le <?php echo date_fr($des->getValue('dateAjout'), 'annee') ?>
				<?php echo $dern_modif; ?></span><?php echo $editer;?>
			</div>
			<div class="spacer"><!-- --></div>
		</div>
		<!-- Fin description -->

	<?php
		}

	}

	if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 6)
	{
		echo "<a href=\"".$url_site."ajouterDescription.php?idL=".$get['idL']."&amp;type=description\" title=\"Ajouter une description de ce lieu\">".$icone['ajouter_texte']." Ajouter une description</a>";
	}

	if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 8 && $nb_pres == 0)
	{
		echo "<a href=\"".$url_site."ajouterDescription.php?idL=".$get['idL']."&amp;type=presentation\" title=\"Ajouter une présentation de ce lieu\">".$icone['ajouter_texte']." Ajouter une présentation</a>";
	}
	?>

	</div>
	<!-- Fin descriptions -->

</div>
<!-- Fin fiche -->

<div class="spacer"></div>

<ul class="menu_actions_lieu">
	<?php
	echo $menu_actions;
	echo $action_ajouter;
	echo $action_editer;
	?>
	<li><?php echo $lien_prec; ?></li>
	<li><?php echo $lien_suiv; ?></li>
	<li><a href="<?php echo basename(__FILE__)."?".arguments_URI($get) ?>&amp;style=imprimer" title="Format imprimable">
	<?php echo $iconeImprimer ?></a></li>
</ul>


<?php

/* Chargement des commentaires */
$commentaires = new CollectionCommentaire();
$commentaires->load($get['idL']);

$evenements_ici = '';
if ($get['complement'] == 'evenements')
{
	$evenements_ici = ' class="ici"';
}

$commentaires_ici = '';
if ($get['complement'] == 'commentaires')
{
	$commentaires_ici = ' class="ici"';
}

$lien_rss_evenements = '';
if ($get['complement'] == 'evenements')
{
	$lien_rss_evenements = '<a href="'.$url_site.'rss.php?type=lieu_evenements&amp;id='.$get['idL'].'"
title="Flux RSS des prochains événements">'.$iconeRSS.'</a>';
}
?>

<ul id="menu_complement">
	<li<?php echo $evenements_ici; ?>><a href="<?php echo basename(__FILE__); ?>?<?php echo arguments_URI($get, "complement")?>&amp;complement=evenements#menu_complement" title="" >Prochains événements</a></li>
	<li<?php echo $commentaires_ici; ?>><a href="<?php echo basename(__FILE__); ?>?<?php echo arguments_URI($get, "complement")?>&amp;complement=commentaires#menu_complement" title="" >Commentaires (<?php echo $commentaires->getNbElements(); ?>)</a></li>
	<li class="rss"><?php echo $lien_rss_evenements; ?></li>
</ul>


<?php

if ($get['complement'] == 'evenements')
{

	$date_debut = date("Y-m-d", time() - 21600);

	$genre = "";
	if (isset($get['genre_even']) && $get['genre_even'] != "tous")
	{
		$genre .= $get['genre_even'];
	}

	$evenements = new CollectionEvenement($connector);

	$evenements->loadLieu($get['idL'], $date_debut, $genre);

	echo '	<div id="prochains_evenements">';

	/* Construction du menu par genre */
	$menu_genre = '';
	if ($evenements->getNbElements() > 0)
	{
		$menu_genre .= '<ul id="menu_genre">';
		$genres_even = array("tous", "fête", "cinéma", "théâtre", "expos", "divers");

		foreach ($genres_even as $g)
		{

			$genre = "";
			if ($g != "tous")
			{
				$genre = "AND genre='".$g."'";
			}

			$sql_nb_even = "SELECT idEvenement
			 FROM evenement
			 WHERE idLieu=".$get['idL']." AND dateEvenement >= '".$date_debut."' AND statut!='inactif'".$genre;


			$req_nb_even = $connector->query($sql_nb_even);
			$nb_even_genre = $connector->getNumRows($req_nb_even);

			$menu_genre .= "<li";
			if ($g == $get['genre_even'])
			{
				$menu_genre .= " class=\"ici\"><a href=\"".$url_site."lieu.php?idL=".$get['idL']."&amp;genre_even=".urlencode($g)."#prochains_even\" title=\"".$g."\">";
				if ($g == "fête")
				{
					$g .= "s";
				}
				$menu_genre .= $g;
				$menu_genre .= " (".$nb_even_genre.")";

				$menu_genre .= "</a>";
			}
			else if ($nb_even_genre == 0 && $g != "tous")
			{
				$menu_genre .= ' class="rien">'.$g;
			}
			else
			{
				$menu_genre .= "><a href=\"".$url_site."lieu.php?idL=".$get['idL']."&amp;genre_even=".$g."#prochains_even\" title=\"".$g."\">".$g;
				$menu_genre .= " (".$nb_even_genre.")";
				$menu_genre .= "</a>";
			}

			$menu_genre .= "</li>";


		}
		$menu_genre .= "</ul>";
		echo $menu_genre;
	?>





	<table>

	<?php

	$nbMois = 0;
	$moisCourant = 0;
	//listage des événements
	foreach ($evenements->getElements() as $id => $even)
	{
		$illustration = '';
		if ($even->getValue('flyer') != '')
		{
			$imgInfo = getimagesize($rep_images_even.$even->getValue('flyer'));

			$illustration = lien_popup($IMGeven.$even->getValue('flyer')."?".filemtime($rep_images_even.$even->getValue('flyer')), "Flyer", $imgInfo[0]+20,$imgInfo[1]+20,
			"<img src=\"".$IMGeven."t_".$even->getValue('flyer')."?".filemtime($rep_images_even."t_".$even->getValue('flyer'))."\" alt=\"Flyer\" />");
		}
		else if ($even->getValue('image') != '')
		{
			$imgInfo = @getimagesize($rep_images.$even->getValue('image'));
			$illustration = lien_popup($IMGeven.$even->getValue('image')."?".filemtime($rep_images_even.$even->getValue('image')), "Image", $imgInfo[0]+20, $imgInfo[1]+20,
			"<img src=\"".$IMGeven."s_".$even->getValue('image')."?".filemtime($rep_images_even.$even->getValue('image'))."\" alt=\"Image\" width=\"60\" />");
		}

		$description = '';
		if ($even->getValue('description') != '')
		{
			$maxChar = trouveMaxChar($even->getValue('description'), 45, 2);
			$description = html_substr(html_substr($even->getValue('description')), $maxChar, 5);
			$description .= "<span class=\"continuer\"><a href=\"".$url_site."evenement.php?idE=".$even->getValue('idEvenement')."\" title=\"Voir la fiche complète de l'événement\"> Lire la suite</a></span>";
		}

		if ($nbMois == 0)
		{
			$moisCourant = date2mois($even->getValue('dateEvenement'));
			echo "<tr><td colspan=\"3\" class=\"mois\">".ucfirst(mois2fr($moisCourant))."</td></tr>";
		}

		if (date2mois($even->getValue('dateEvenement')) != $moisCourant)
		{
			echo "<tr><td colspan=\"3\" class=\"mois\">".ucfirst(mois2fr(date2mois($even->getValue('dateEvenement'))));

			if (date2mois($even->getValue('dateEvenement')) == "01")
			{
				echo " ".date2annee($even->getValue('dateEvenement'));
			}

			echo "</td></tr>";
		}

		$salle = '';
		$sql_salle = "SELECT nom FROM salle WHERE idSalle=".$even->getValue('idSalle');

		$req_salle = $connector->query($sql_salle);

		if ($connector->getNumRows($req_salle) > 0)
		{
			$tab_salle = $connector->fetchArray($req_salle);
			$salle = $tab_salle['nom'];
		}



	?>
		<tr <?php if ($date_debut == $even->getValue('dateEvenement')) { echo "class=\"ici\""; } ?>>

			<td><?php echo date2nomJour($even->getValue('dateEvenement')) ?></td>

			<td><?php echo date2jour($even->getValue('dateEvenement')) ?></td>

			<td class="flyer"><?php echo $illustration ?></td>

			<td>
			<h3>
			<?php
			$titre_url = '<a href="'.$url_site.'evenement.php?idE='.$even->getValue('idEvenement').'" title="Voir la fiche de l\'événement">'.securise_string($even->getValue('titre')).'</a>';
			echo titre_selon_statut($titre_url, $even->getValue('statut')); ?>
			</h3>
			<p class="description"><?php echo $description; ?></p>

			<p class="pratique"><?php echo afficher_debut_fin($even->getValue('horaire_debut'), $even->getValue('horaire_fin'), $even->getValue('dateEvenement'))." ".$even->getValue('prix') ?></p>
			</td>

			<td><?php echo $salle; ?></td>
			<td><?php echo $even->getValue('genre') ?></td>

			<td class="lieu_actions_evenement">
			<?php
			if (
	 		(isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 6
			|| $_SESSION['SidPersonne'] == $even->getValue('idPersonne'))
			)
			||  (isset($_SESSION['Saffiliation_lieu']) && !empty($get['idL']) && $get['idL'] == $_SESSION['Saffiliation_lieu'])

			)
			{
			?>
			<ul>

				<li><a href="<?php echo $url_site ?>copierEvenement.php?idE=<?php echo $even->getValue('idEvenement') ?>" title="Copier cet événement"><?php echo $iconeCopier ?></a></li>
				<li><a href="<?php echo $url_site ?>ajouterEvenement.php?action=editer&amp;idE=<?php echo $even->getValue('idEvenement') ?>" title="Éditer cet événement"><?php echo $iconeEditer ?></a></li>
			</ul>
			<?php
			}
			?>
			</td>
		</tr>

	<?php

		$moisCourant = date2mois($even->getValue('dateEvenement'));
		$nbMois++;
	}
	?>

	</table>

	<?php

	}
	else
	{
		echo "<p>Pas d'événement actuellement prévu au lieu <strong>".$lieu->getHtmlValue('nom')."</strong></p>";
	}

	if (!empty($tab_lieu['URL']))
	{
		$URLcomplete = $tab_lieu['URL'];

		if (!preg_match("/^(http:\/\/)/i", $tab_lieu['URL']))
		{
			$URLcomplete = "http://".$tab_lieu['URL'];
		}
		echo "<p>Pour des informations complémentaires veuillez consulter <a href=\"".$URLcomplete."\" title=\"Aller sur le site web\" onclick=\"window.open(this.href,'_blank');return false;\">".$tab_lieu['URL']."</a></p>\n";
	}

	echo '</div>';


} //if complement
else if ($get['complement'] == 'commentaires')
{

	echo '<div id="commentaires">';

	foreach ($commentaires->getElements() as $id => $commentaire)
	{
		?>

		<blockquote>
			<div class="commentaire_de">

			 <?php echo "<span class=\"left\">".signature_auteur($commentaire->getValue('idPersonne'))."</span>";

			 echo "<span class=\"right\">".date_fr($commentaire->getValue('dateAjout'), "annee"); ?>
			 </span>
			 </div> <!-- fin commentaire_de -->
			<div class="spacer"><!-- --></div>
			<p><?php echo textToHtml(htmlspecialchars($commentaire->getHtmlValue('contenu'))) ?></p>

		</blockquote>
		<!-- Fin commentaire -->

	<?php
	}

	if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 12 ))
	{
	?>
		<form method="post" id="ajouter_editer" action="ajouterCommentaire.php?action=insert&amp;element=lieu&amp;id=<?php echo $get['idL'] ?>">

			<p>
				<label for="contenu">Votre commentaire</label>
				<?php
				$id_textarea = "commentaire";

				?>
				<textarea style="margin-left:0em;" id="commentaire" name="contenu" cols="45" rows="5"></textarea>
			</p>

			<div class="spacer"><!-- --></div>
			<div style="margin-left:10em"></div>
			<p id="pied_form">
				<input type="hidden" name="formulaire" value="ok" />
				<input type="submit" value="Ajouter" class="submit" />
			</p>

		</form>

		<?php
	}
	else
	{
		include("includes/element_login.inc.php");
	} // if login
	?>

	</div>
	<!-- Fin commentaires -->

<?php
} // if complement
?>



</div>
<!-- fin Contenu -->


<div id="colonne_gauche" class="colonne">



<?php

include("includes/navigation_calendrier.inc.php");

echo '<p id="statut_lieux" class="voir_lieux">';
if ($get['statut'] == 'ancien')
{
	echo '<a href="'.basename(__FILE__).'?'.arguments_URI($get, "statut").'&amp;statut=actif">Voir les lieux actifs</a>';
}

if ($get['statut'] == 'actif')
{
	echo '<a href="'.basename(__FILE__).'?'.arguments_URI($get, "statut").'&amp;statut=ancien">Voir les lieux anciens</a>';
}

echo '</p>';


 ?>

</div>
<!-- Fin Colonnegauche -->

<div id="colonne_droite" class="colonne">

<?php echo $aff_menulieux; ?>


</div>
<!-- Fin colonne_droite -->

<div class="spacer"><!-- --></div>
<?php
include("includes/footer.inc.php");
?>