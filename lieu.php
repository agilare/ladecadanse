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

if (isset($_GET['idL']) && $_GET['idL'] > 0)
{
	$get['idL'] = verif_get($_GET['idL'], "int", 1);
}
else
{
	//trigger_error("id obligatoire", E_USER_WARNING);

	header("HTTP/1.1 404 Not Found");
	echo file_get_contents("404.php");
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

if ($lieu->getValue('statut') == 'inactif' && !((isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 6)))
{
    header("HTTP/1.1 404 Not Found");
    echo file_get_contents("404.php");
    exit;
}        



//printr($lieu->getValues());

$sql_lieu_localite = "
SELECT *
FROM localite
WHERE id='".$lieu->getValue('localite_id')."'";

$req_lieu_localite = $connector->query($sql_lieu_localite);

$lieu_localite = $connector->fetchAssoc($req_lieu_localite);

$page_titre = $lieu->getValue('nom').get_adresse($lieu->getValue('region'), $lieu_localite['localite'], $lieu->getValue('quartier'), '' );


$page_description = $page_titre." : accès, horaires, description, photos et prochains événements";


$extra_css = array("menu_lieux", "element_login");
include("includes/header.inc.php");

$deb_nom_lieu = mb_strtolower(mb_substr($lieu->getValue('nom'), 0, 1));
if (!isset($_GET['tranche']) && $deb_nom_lieu > "l" && $deb_nom_lieu < "z")
{
	$_GET['tranche'] = "lz";
}

include("includes/menulieux.inc.php");

/* $logo = '';
if ($lieu->getValue('logo') !='')
{
	$imgInfo = getimagesize($rep_images_lieux.$lieu->getValue('logo'));

	$logo = lien_popup($IMGlieux.$lieu->getValue('logo').'?'.filemtime($rep_images_lieux.$lieu->getValue('logo')),
	"Logo", $imgInfo[0]+20, $imgInfo[1]+20,
	"<img src=\"".$IMGlieux."s_".$lieu->getValue('logo')."?".filemtime($rep_images_lieux."s_".$lieu->getValue('logo'))."\" alt=\"Logo\" />");
}
 */
$lieu_status = '';
if ($lieu->getValue('statut') == 'ancien')
{
	$lieu_status = '<div class="spacer"><!-- --></div>
<p class="info">Ce lieu n\'existe plus</p>';
}
elseif ($lieu->getValue('statut') == 'inactif')
{
	// le staff, ainsi que l'auteur et les personnes liées par organisateur peuvent voir l'even dépublié
	if (
	isset($_SESSION['Sgroupe']) && 	
	(
	$_SESSION['Sgroupe'] <= 6	
	)
		
	)
	{
            $lieu_status = '<div class="spacer"><!-- --></div>
<p class="info">Inactif</p>';
	}
	else
	{
            header("HTTP/1.1 404 Not Found");
            echo file_get_contents("404.php");
            exit;
	}
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
	title="Enlever des favoris">'.$icone['supprimer_favori'].'Enlever des favoris</a></li>';
	}
}

$action_ajouter = '';
if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 10))
{
	$action_ajouter = '<li class="action_ajouter"><a href="'.$url_site.'ajouterEvenement.php?idL='.$get['idL'].'" title="ajouter un événement à ce lieu">Ajouter un événement à ce lieu</a></li>';
}

$action_editer = '';
if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 6 || est_affilie_lieu($_SESSION['SidPersonne'], $get['idL']) || est_organisateur_lieu($_SESSION['SidPersonne'], $get['idL'])))
{
	$action_editer = '<li class="action_editer"><a href="'.$url_site.'ajouterLieu.php?action=editer&amp;idL='.$get['idL'].'">Modifier ce lieu</a></li>';
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

	$photo_principale = lien_popup($IMGlieux.$lieu->getValue('photo1').'?'.filemtime($rep_images_lieux.$lieu->getValue('photo1')),	"Logo", $imgInfo[0]+20, $imgInfo[1]+20,	"<img src=\"".$IMGlieux."s_".$lieu->getValue('photo1')."?".filemtime($rep_images_lieux."s_".$lieu->getValue('photo1'))."\" alt=\"Photo du lieu\" />");

	


}

$illustration = "";

if (!empty($lieu->getValue('logo')))
{
	$illustration = "<img src=".$IMGlieux."s_".$lieu->getValue('logo')." style=float:left;margin-right:0.2em />";
}
else if (!empty($lieu->getValue('photo1')))
{
	$illustration = "<img src=".$IMGlieux."s_".$lieu->getValue('photo1')." height=80 style=float:left;margin-right:0.2em />";
}

$info_lieu = "<div style='width:200px'>".$illustration."<div class=details><p class=adresse><strong>".securise_string($lieu->getValue('nom'))."</strong></p><p class=adresse>".securise_string($lieu->getValue('adresse'))."</p><p class=adresse>".$lieu->getValue('quartier')."</p></div></div>";
?>
<script>
var map;
function initMap() {
	
	var myLatLng = {lat: <?php echo $lieu->getValue('lat') ?>, lng: <?php echo $lieu->getValue('lng') ?>};

	map = new google.maps.Map(document.getElementById('map'), {
		center: myLatLng,
		zoom: 14
	});

	var marker = new google.maps.Marker({
		position: myLatLng,
		map: map
	});

	var infowindow = new google.maps.InfoWindow({
		content: "<?php echo $info_lieu; ?>"
	});

	marker.addListener('click', function() {
		infowindow.open(map, marker);
	});
  
}
</script>

<!-- Début Contenu -->
<div id="contenu" class="colonne">

	<p id="btn_listelieux" class="mobile" >
	<button href="#"><i class="fa fa-list fa-lg"></i>&nbsp;Liste des lieux</button>
	</p>
	
    <?php
    if (!empty($_SESSION['lieu_flash_msg']))
    {
        msgOk($_SESSION['lieu_flash_msg']);
        unset($_SESSION['lieu_flash_msg']);
    }
    ?>    
    
	<div class="vcard">
	<div id="entete_contenu">
	

<?php 
if ($lieu->getValue('logo'))
{
?>
<a href="<?php echo $IMGlieux.$lieu->getValue('logo').'?'.filemtime($rep_images_lieux.$lieu->getValue('logo')) ?>" class="magnific-popup">
	<img src="<?php echo $IMGlieux."s_".$lieu->getValue('logo')."?".filemtime($rep_images_lieux."s_".$lieu->getValue('logo')); ?>" alt="Logo"  />
</a>
<?php 
}
?>	
	<?php //echo $logo ?>
	<?php 
	$h2_style = '';
	if (isset($logo))
		$h2_style = "width:48%";
	?>
	<h2 class="fn org" style="<?php echo $h2_style; ?>"><?php echo $lieu->getHtmlValue('nom'); ?></h2>
<?php	echo $lieu_status ?>
	<div class="spacer"></div>
	</div>

	<div class="spacer"><!-- --></div>

	<ul class="menu_actions_lieu desktop">
		<?php
		echo $menu_actions;
		echo $action_ajouter;
		echo $action_editer;
		?><!--
		<li><?php echo $lien_prec; ?></li>
		<li><?php echo $lien_suiv; ?></li>
		<li><a href="<?php echo basename(__FILE__)."?".arguments_URI($get) ?>&amp;style=imprimer" title="Format imprimable">
		<?php echo $iconeImprimer ?></a></li>-->
	</ul>

	<div class="spacer"><!-- --></div>

	<div id="fiche"<?php echo $class_vide; ?>>

		<!-- Deb medias -->
		<div id="medias">

			<div id="photo" <?php echo (!$photo_principale)?" style='  background: #eaeaea;'":""; ?>>
			<?php
			if ($lieu->getValue('photo1') != '') {
			?>
			<a href="<?php echo $IMGlieux.$lieu->getValue('photo1').'?'.filemtime($rep_images_lieux.$lieu->getValue('photo1')); ?>" class="gallery-item"><img src="<?php echo $IMGlieux."s_".$lieu->getValue('photo1').'?'.filemtime($rep_images_lieux.$lieu->getValue('photo1')); ?>" alt="Photo du lieu"></a>			
			<?php } ?>
            <?php 
            if (empty($_SESSION['Sgroupe']))
            { 
            ?>            
			<?php echo (!$photo_principale)?'<p style="font-size:0.9em;padding:2em 0.5em;line-height:1.2em">Vous gérez ce lieu ? <a href="/inscription.php">Inscrivez-vous</a> pour pouvoir ajouter ou modifier les informations et des photos</p>':""; ?>
            <?php } ?>
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
					echo '<div class="section">';
					
					
					while ($tab_galerie = $connector->fetchArray($req_galerie))
					{
						if (mb_strstr($tab_galerie['mime'], "image"))
						{
							$icone_fichier = $iconeImage;
						}

						$url_fichier = $url_images_lieu_galeries.$tab_galerie['idFichierrecu'].".".$tab_galerie['extension'];
						$rep_fichier = $rep_images_lieux_galeries.$tab_galerie['idFichierrecu'].".".$tab_galerie['extension'];
						$rep_fichier_s = $rep_images_lieux_galeries."s_".$tab_galerie['idFichierrecu'].".".$tab_galerie['extension'];
						$url_fichier_s = $url_images_lieu_galeries."s_".$tab_galerie['idFichierrecu'].".".$tab_galerie['extension'];

						//echo lien_popup($url_site."galerielieu.php?idL=".$get['idL']."&amp;idI=".$tab_galerie['idFichierrecu'],	"galerie", 860, 700, "<img class=\"galerie\" src=\"".$url_fichier."?".filemtime($chemin_fichier)."\" alt=\"photo\" />");
						?>
					
					
					<a href="<?php echo $url_fichier."?".filemtime($rep_fichier); ?>" class="gallery-item"><img src="<?php echo $url_fichier_s."?".filemtime($rep_fichier_s); ?>" alt="Photo du lieu"></a>
					<?php	
						
						
						
					}					
					
/* 				if ($connector->getNumRows($req_galerie) == 1)
				{
					$tab_galerie = $connector->fetchArray($req_galerie);
					$url_fichier = $url_images_lieu_galeries.$tab_galerie['idFichierrecu'].".".$tab_galerie['extension'];
					$rep_fichier = $rep_images_lieux_galeries.$tab_galerie['idFichierrecu'].".".$tab_galerie['extension'];
					$rep_fichier_s = $rep_images_lieux_galeries."s_".$tab_galerie['idFichierrecu'].".".$tab_galerie['extension'];
					$url_fichier_s = $url_images_lieu_galeries."s_".$tab_galerie['idFichierrecu'].".".$tab_galerie['extension'];
					$imgsize = getimagesize($rep_fichier);
					?>
					
					<a href="<?php echo $url_fichier; ?>" class="gallery-item"><img src="<?php echo $url_fichier_s."?".filemtime($rep_fichier_s); ?>" alt="Photo du lieu"></a>
					
					
					<?php
					
					//echo lien_popup($url_fichier, "images", $imgsize[0], $imgsize[1], "<img src=\"".$url_fichier_s."?".filemtime($rep_fichier_s)."\" alt=\"Photo du lieu\" />");
				}
				else
				{
					while ($tab_galerie = $connector->fetchArray($req_galerie))
					{
						if (mb_strstr($tab_galerie['mime'], "image"))
						{
							$icone_fichier = $iconeImage;
						}

						$chemin_fichier = $rep_images_lieux_galeries."s_".$tab_galerie['idFichierrecu'].".".$tab_galerie['extension'];
						$url_fichier = $url_images_lieu_galeries."s_".$tab_galerie['idFichierrecu'].".".$tab_galerie['extension'];

						echo lien_popup($url_site."galerielieu.php?idL=".$get['idL']."&amp;idI=".$tab_galerie['idFichierrecu'],
						"galerie", 860, 700, "<img class=\"galerie\" src=\"".$url_fichier."?".filemtime($chemin_fichier)."\" alt=\"photo\" />");
					}
				} */
				
				
				echo '</div>';
				
				
					echo '<div class="spacer"></div>';
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
					echo "<li><a href=\"".$url_fichier."\" >".$icone[mb_strtolower($tab_docu['extension'])].$tab_docu['description']." (".formatbytes(filesize($chemin_fichier)).", ".$tab_docu['extension'].")</a></li>";
				}
				echo "</ul>
					</div>";
			}
			?>
		</div>
		<!-- Fin medias -->

		<?php
		$categories = str_replace(",", ", ", $lieu->getValue('categorie'));
        $adresse = get_adresse($lieu->getValue('region'), $lieu_localite['localite'], $lieu->getValue('quartier'), $lieu->getValue('adresse') );
              
		$carte = '';
		if ($lieu->getValue('lat') != 0.000000 && $lieu->getValue('lng') != 0.000000)
		{
            $carte = '
            <li>
                <a href="#" class="dropdown" data-target="plan">'.$icone['plan'].' Voir sur le plan <i class="fa fa-caret-down" aria-hidden="true"></i>
</a>
                
            </li>';
		}

		$acces_tpg = '';
		if ($lieu->getValue('acces_tpg') != "")
		{
			$acces_tpg = '<li>Accès TPG : '.$lieu->getValue('acces_tpg').'</li>';
		}

		$URL = '';
		if ($lieu->getValue('URL') != '' )
		{

			if (!preg_match("/^https?:\/\//", $lieu->getValue('URL')))
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
			$salles .= '<li>Salles : ';
			$salles .= '<ul class="salles">';
			while ($tab_salle = $connector->fetchArray($req_salle))
			{
				$salles .= '<li>'.$tab_salle['nom'];

				if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 6)
				{
					$salles .= '<a href="'.$url_site.'ajouterSalle.php?action=editer&amp;idS='.$tab_salle['idSalle'].'">'.$iconeEditer.'</a>';
				}

				$salles .= '</li>';
			}
			$salles .= '</ul></li>';

		}
		if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 6)
		{
			$salles .= '<a href="'.$url_site.'ajouterSalle.php?idL='.$get['idL'].'">'.$icone['ajouts'].'ajouter une salle</a>';
		}



		$organisateurs = '';
		$sql = "SELECT organisateur.idOrganisateur AS idOrganisateur, nom FROM organisateur, lieu_organisateur

				WHERE organisateur.idOrganisateur=lieu_organisateur.idOrganisateur AND lieu_organisateur.idLieu=".$get['idL'];

		$req = $connector->query($sql);

		if ($connector->getNumRows($req) > 0)
		{
			$organisateurs .= '<li>Organisateur';
			if ($connector->getNumRows($req) > 1)
			{
				$organisateurs .= 's';
			}
			$organisateurs .= ' : ';
			$organisateurs .= '<ul class="salles">';
			while ($tab = $connector->fetchArray($req))
			{
				$organisateurs .= '<li><a href="'.$url_site.'organisateur.php?idO='.$tab['idOrganisateur'].'" >';
				$organisateurs .= $tab['nom'];

				$organisateurs .= '</a></li>';
			}
			$organisateurs .= '</ul></li>';

		}
		?>

		<div id="pratique">
			<ul>
				<li><?php echo $categories; ?></li>
                <li class="adr"><?php echo $adresse ?></li>				
				<?php echo $carte; ?>
                <?php echo $salles; ?>
                <span class="latitude">
                   <span class="value-title" title="<?php echo $lieu->getValue('lat'); ?>"></span>
                </span>
                <span class="longitude">
                   <span class="value-title" title="<?php echo $lieu->getValue('lng'); ?>"></span>
                </span>				
				<li><?php echo textToHtml($lieu->getValue('horaire_general')); ?></li>
				<li class="sitelieu"><a class="url" href="<?php echo $URL; ?>" title="Voir le site web du lieu" onclick="window.open(this.href,'_blank');return false;"><?php echo $lieu->getValue('URL'); ?></a> <?php if ($lieu->getId() == 13) { // exception pour le Rez ?><a href="http://kalvingrad.com" onclick="window.open(this.href,'_blank');return false;">kalvingrad.com</a><br><a href="http://www.ptrnet.ch" onclick="window.open(this.href,'_blank');return false;">ptrnet.ch</a><?php } ?></li>
				<?php echo $organisateurs; ?>
            </ul>
            <div id="plan" style="display:none"><div id="map"></div></div>
        </div><!-- Fin pratique -->


<div class="spacer only-mobile"></div>

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
    <h3><a href="<?php echo basename(__FILE__)."?".arguments_URI($get, 'type_descrition') ?>&amp;type_description=description">Description</a></h3>
    </li>
 <?php

 }

if ($nb_pres > 0)
{

?>
    <li <?php if ($get['type_description'] == 'presentation') { echo ' class="ici"'; }?>>
        <h3><a href="<?php echo basename(__FILE__)."?".arguments_URI($get, 'type_description') ?>&amp;type_description=presentation">Le lieu se présente</a></h3>
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
	$auteurs_de_desc = array();
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

				$dern_modif = ", modifié le ".date_fr($des->getValue('date_derniere_modif'), 'annee', '', 'non');
			}

			$editer = '';
			if ((isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 4)
			|| (isset($_SESSION['Sgroupe']) && $get['type_description'] == 'description' && $_SESSION['Sgroupe'] <= 6 && (isset($_SESSION['SidPersonne'])) && $_SESSION['SidPersonne'] == $des->getValue('idPersonne'))
			|| (isset($_SESSION['Sgroupe']) && $get['type_description'] == 'presentation' && ($_SESSION['Sgroupe'] <= 6 || ($_SESSION['Sgroupe'] <= 8 && (est_organisateur_lieu($_SESSION['SidPersonne'], $get['idL']) || est_affilie_lieu($_SESSION['SidPersonne'], $get['idL'])))))
                    )
			{
				$editer = '<span class="right">';
				$editer .= '<a href="'.$url_site.'ajouterDescription.php?action=editer&amp;type='.$get['type_description'].'&amp;idL='.$get['idL'].'&amp;idP='.$des->getValue('idPersonne').'">'.$iconeEditer.'Modifier</a>';
				$editer .= '</span>';
				
				if ($_SESSION['SidPersonne'] == $des->getValue('idPersonne'))
					$auteurs_de_desc[] = $des->getValue('idPersonne');				
			}
		 ?>

		<div class="description">
			<?php
            if (datetime_iso2time($des->getValue('date_derniere_modif')) > datetime_iso2time("2009-10-12 12:00:00"))
            {
                echo $des->getValue('contenu');             
            }
            else
            {
            echo "<p>".textToHtml($des->getHtmlValue('contenu'))."</p>";
            }
            ?>
			<p><?php 
				if ($get['type_description'] == 'description')
				{
					echo signature_auteur($des->getValue('idPersonne'));
				}	
				?></p>

			<div class="auteur">
				<span class="left"><?php echo ucfirst(date_fr($des->getValue('dateAjout'), 'annee','', 'non')) ?><?php echo $dern_modif; ?></span><?php echo $editer;?>
			</div>
			<div class="spacer"><!-- --></div>
		</div>
		<!-- Fin description -->

	<?php
		}
	}

	// un rédacteur qui n'a pas déjà écrit une description
	if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 6 && !in_array($_SESSION['SidPersonne'], $auteurs_de_desc))
	{
		echo "<a href=\"".$url_site."ajouterDescription.php?idL=".$get['idL']."&amp;type=description\">".$icone['ajouter_texte']." Ajouter une description (avis)</a><br>";
	}

	if (isset($_SESSION['Sgroupe']) &&
            ($_SESSION['Sgroupe'] <= 6 || ($_SESSION['Sgroupe'] == 8 && (est_affilie_lieu($_SESSION['SidPersonne'], $get['idL']) || est_organisateur_lieu($_SESSION['SidPersonne'], $get['idL']))))
            && $nb_pres == 0)
	{
		echo "<a href=\"ajouterDescription.php?idL=".$get['idL']."&amp;type=presentation\">".$icone['ajouter_texte']." Ajouter une présentation</a>";
	}
	?>

	</div>
	<!-- Fin descriptions -->
<div class="spacer"></div>
</div>
<!-- Fin fiche -->
<div class="spacer"><!-- --></div>
</div> <!-- fin vcard -->
<div class="spacer"><!-- --></div>



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
title="Flux RSS des prochains événements"><i class="fa fa-rss fa-lg" style="color:#f5b045"></i></a>';
}
?>

<ul id="menu_complement" style="display:none">
	<li<?php echo $evenements_ici; ?>><a href="<?php echo basename(__FILE__); ?>?<?php echo arguments_URI($get, "complement")?>&amp;complement=evenements#menu_complement" title="" >Prochains événements</a></li>
	
	<li<?php echo $commentaires_ici; ?>><a href="<?php echo basename(__FILE__); ?>?<?php echo arguments_URI($get, "complement")?>&amp;complement=commentaires#menu_complement" title="" >Commentaires (<?php echo $commentaires->getNbElements(); ?>)</a></li>
	<?php
	if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 12)
	{
		echo '<li class="ajouter_com"><a href="'.basename(__FILE__).'?'.arguments_URI($get, "complement").'&amp;complement=commentaires#menu_complement" title="">écrire un commentaire</a></li>';
	}
	?>
	<li class="rss"><?php echo $lien_rss_evenements; ?></li>
</ul>

<h2 style="font-size:1.2em;font-weight:bold;color:#5C7378;width:96%;margin:2em 2% 0.4em 2%;min-height:30px">Prochains événements</h2>

<?php


/* if ($get['complement'] == 'evenements')
{ */

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
				else if ($g == "cinéma")
				{
					$g = "ciné";
				}
				$menu_genre .= $g;
				$menu_genre .= " (".$nb_even_genre.")";

				$menu_genre .= "</a>";
			}
			else if ($nb_even_genre == 0 && $g != "tous")
			{
				if ($g == "fête")
				{
					$g .= "s";
				}
				else if ($g == "cinéma")
				{
					$g = "ciné";
				}
				$menu_genre .= ' class="rien">';
				$menu_genre .= $g;
			}
			else
			{
				$menu_genre .= "><a href=\"".$url_site."lieu.php?idL=".$get['idL']."&amp;genre_even=".$g."#prochains_even\" title=\"".$g."\">";
				if ($g == "fête")
				{
					$g .= "s";
				}
				else if ($g == "cinéma")
				{
					$g = "ciné";
				}
				$menu_genre .=  $g;
				$menu_genre .= " (".$nb_even_genre.")";
				$menu_genre .= "</a>";
			}

			$menu_genre .= "</li>";


		}
		$menu_genre .= "</ul>";
		echo $menu_genre;
	?>





	<div class="clear_mobile"></div>
	<table>

	<?php

	$nbMois = 0;
	$moisCourant = 0;
	//listage des événements
	foreach ($evenements->getElements() as $id => $even)
	{
	

		$description = '';
		if ($even->getValue('description') != '')
		{	
			$maxChar = trouveMaxChar($even->getValue('description'), 50, 2);
			
			if (mb_strlen($even->getValue('description')) > $maxChar)
			{
				//$continuer = "<span class=\"continuer\"><a href=\"".$url_site."evenement.php?idE=".$even->getValue('idEvenement')."\" title=\"Voir la fiche complète de l'événement\"> Lire la suite</a></span>";
				$description = texteHtmlReduit(textToHtml(htmlspecialchars($even->getValue('description'))), $maxChar);
						
			}
			else
			{
				$description = textToHtml(htmlspecialchars($even->getValue('description')));
			}
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

                $vcard_starttime = '';
                if (mb_substr($even->getValue('horaire_debut'), 11, 5) != '06:00')
                    $vcard_starttime = "T".mb_substr($even->getValue('horaire_debut'), 11, 5).":00";
                
	?>
	
		<tr class="<?php if ($date_debut == $even->getValue('dateEvenement')) { echo "ici"; } ?> vevent evenement">

			<td class="dtstart"><?php echo date2nomJour($even->getValue('dateEvenement')); ?>
			
                            <span class="value-title" title="<?php echo $even->getValue('dateEvenement').$vcard_starttime; ?>"></span>
			</td>
 
			<td><?php echo date2jour($even->getValue('dateEvenement'));  ?>

			</td>

			<td class="flyer photo">
			<?php
		if ($even->getValue('flyer') != '')
		{
			$imgInfo = getimagesize($rep_images_even.$even->getValue('flyer'));

			//$illustration = lien_popup($IMGeven.$even->getValue('flyer')."?".filemtime($rep_images_even.$even->getValue('even')), "Flyer", $imgInfo[0]+20,$imgInfo[1]+20,			"<img src=\"".$IMGeven."t_".$even->getValue('flyer')."?".filemtime($rep_images_even."t_".$even->getValue('flyer'))."\" alt=\"Flyer\" />");
			?>
			<a href="<?php echo $IMGeven.$even->getValue('flyer').'?'.filemtime($rep_images_even.$even->getValue('flyer')) ?>" class="magnific-popup">
				<img src="<?php echo $IMGeven."t_".$even->getValue('flyer')."?".filemtime($rep_images_even."t_".$even->getValue('flyer')); ?>" alt="Flyer" width="60" />
			</a>			
			
			<?php
			
			
		}
		else if ($even->getValue('image') != '')
		{
/* 			$imgInfo = @getimagesize($rep_images.$even->getValue('image'));
			$illustration = lien_popup($IMGeven.$even->getValue('image')."?".filemtime($rep_images_even.$even->getValue('image')), "Image", $imgInfo[0]+20, $imgInfo[1]+20, "<img src=\"".$IMGeven."s_".$even->getValue('image')."?".filemtime($rep_images_even.$even->getValue('image'))."\" alt=\"Image\" width=\"60\" />"); */
			
			?>
			<a href="<?php echo $IMGeven.$even->getValue('image').'?'.filemtime($rep_images_even.$even->getValue('image')) ?>" class="magnific-popup">
				<img src="<?php echo $IMGeven."s_".$even->getValue('image')."?".filemtime($rep_images_even."s_".$even->getValue('image')); ?>" alt="Photo" width="60" />
			</a>			
			
			<?php			
			
		}
?>		



			
			
			</td>

			<td>
			<h3 class="summary">
			<?php
			$titre_url = '<a class="url" href="'.$url_site.'evenement.php?idE='.$even->getValue('idEvenement').'" title="Voir la fiche de l\'événement">'.titre_selon_statut(securise_string($even->getValue('titre')), $even->getValue('statut')).'</a>';
			echo $titre_url; ?>
			</h3>
		
			
			
			<p class="description">
			<?php			
			echo $description; 
			
			?></p>
			<div class="location">
			<span class="value-title" title="<?php echo $lieu->getHtmlValue('nom'); ?>"></span>
			</div>
			<p class="pratique"><?php echo afficher_debut_fin($even->getValue('horaire_debut'), $even->getValue('horaire_fin'), $even->getValue('dateEvenement'))." ".$even->getValue('prix') ?></p>
			</td>

			<td><?php echo $salle; ?></td>
			<td class="category"><?php echo $glo_tab_genre[$even->getValue('genre')]; ?></td>

			<td class="lieu_actions_evenement">
			<?php
			if (
	 		(isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 6
			|| $_SESSION['SidPersonne'] == $even->getValue('idPersonne'))
			)
			||  (isset($_SESSION['Saffiliation_lieu']) && !empty($get['idL']) && $get['idL'] == $_SESSION['Saffiliation_lieu'])
			 || isset($_SESSION['SidPersonne']) && est_organisateur_evenement($_SESSION['SidPersonne'], $even->getValue('idEvenement'))
			 || isset($_SESSION['SidPersonne']) && est_organisateur_lieu($_SESSION['SidPersonne'], $get['idL'])	
			)
			{
			?>
			<ul>

				<li><a href="<?php echo $url_site ?>copierEvenement.php?idE=<?php echo $even->getValue('idEvenement') ?>" title="Copier cet événement"><?php echo $iconeCopier ?></a></li>
				<li><a href="<?php echo $url_site ?>ajouterEvenement.php?action=editer&amp;idE=<?php echo $even->getValue('idEvenement') ?>" title="Éditer cet événement"><?php echo $iconeEditer ?></a></li>
                <li class=""><a href="#" id="btn_event_unpublish_<?php echo $even->getValue('idEvenement'); ?>" class="btn_event_unpublish" data-id="<?php echo $even->getValue('idEvenement') ?>"><?php echo $icone['depublier']; ?></a></li>                   
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
		echo "<p>Pas d'événement actuellement annoncé au lieu <strong>".$lieu->getHtmlValue('nom')."</strong></p>";
	}

	if (!empty($tab_lieu['URL']))
	{
		$URLcomplete = $tab_lieu['URL'];

		if (!preg_match("/^(https?:\/\/)/i", $tab_lieu['URL']))
		{
			$URLcomplete = "http://".$tab_lieu['URL'];
		}
		echo "<p>Pour des informations complémentaires veuillez consulter <a href=\"".$URLcomplete."\" title=\"Aller sur le site web\" onclick=\"window.open(this.href,'_blank');return false;\">".$tab_lieu['URL']."</a></p>\n";
	}

	echo '</div>';


/* } //if complement
else if ($get['complement'] == 'commentaires')
{ */

	echo '<div id="commentaires"><h2 style="margin:10px;font-size:1.2em;font-weight:bold;color:#5C7378">Commentaires</h2>';
    $nb_c = 0;
	foreach ($commentaires->getElements() as $id => $commentaire)
	{
		?>

		<blockquote>
			<div class="commentaire_de" style="color:#5C7378">

			 <?php echo "<span class=\"left\">".signature_auteur($commentaire->getValue('idPersonne'))."</span>";

			 echo "<span class=\"right\">".date_fr($commentaire->getValue('dateAjout'), "annee", 1, "non"); ?>
			 <span style="background:#fafafa"><?php echo $nb_c+1;?></span></span>
			 </div> <!-- fin commentaire_de -->
			<div class="spacer"><!-- --></div>
			<p style="padding:0.5em"><?php echo textToHtml(htmlspecialchars($commentaire->getHtmlValue('contenu'))) ?></p>

		</blockquote>
		<!-- Fin commentaire -->

	<?php
    $nb_c++;
	}
    if (!$nb_c)
        echo '<p style="margin:20px;color:#5C7378">Pas encore de commentaire</p>';
        
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
				<input type="submit" value="Publier" class="submit" />
			</p>

		</form>

		<?php
	}
	else
	{
    ?>
		<p style="margin:10px;color:#5C7378" id="inscription">
<a href="<?php $url_site ?>inscription.php" title="Formulaire d'inscription"><strong>Créez un compte</strong></a> afin de pouvoir ajouter vos commentaires.
</p>
<?php
	} // if login
	?>

	</div>
	<!-- Fin commentaires -->

<?php
/* }  */// if complement
?>



</div>
<!-- fin Contenu -->


<div id="colonne_gauche" class="colonne">
    <?php include("includes/navigation_calendrier.inc.php");?>
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
