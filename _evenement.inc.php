<?php
//Affichage du lieu selon son existence ou non dans la base
if ($evenement['idLieu'] != 0)
{	
	$listeLieu = $connector->fetchArray(
	$connector->query("SELECT nom, adresse, quartier, localite.localite AS localite, region, URL, lat, lng FROM lieu, localite 
		WHERE localite_id=localite.id AND idlieu='".$evenement['idLieu']."'"));
	
	$nom_lieu = "<a href=\"".$url_site."lieu.php?idL=".$evenement['idLieu']."\" title=\"Voir la fiche du lieu : ".securise_string($listeLieu['nom'])."\" >".htmlspecialchars($listeLieu['nom'])."</a>";

	if ($evenement['idSalle'] != 0)
	{
		$req_salle = $connector->query("SELECT nom, emplacement FROM salle
		WHERE idSalle='".$evenement['idSalle']."'");
		$tab_salle = $connector->fetchArray($req_salle);
		$nom_lieu .=  " - ".$tab_salle['nom'];
	}
}
else 
{
	$nom_lieu = $listeLieu['nom'] =  htmlspecialchars($evenement['nomLieu']);
	$listeLieu['adresse'] = htmlspecialchars($evenement['adresse']);
	$listeLieu['quartier'] = htmlspecialchars($evenement['quartier']);
    $listeLieu['localite'] = securise_string($evenement['localite']);              
}

$adresse = htmlspecialchars(get_adresse(null, $listeLieu['localite'], $listeLieu['quartier'], $listeLieu['adresse']));

echo '<div id="evenements">';
echo "<p><a href=\"".$url_site."agenda.php?courant=".$evenement['dateEvenement']."\" title=\"Agenda\">".ucfirst(date_fr($evenement['dateEvenement'], "annee"))."</a></p>";
?>
<div class="evenement">

<div class="titre">
	<span class="left">
	<?php
	
	$maxChar = trouveMaxChar($evenement['description'], 60, 9);
	
	echo '<a href="'.$url_site.'evenement.php?idE='.$evenement['idEvenement'].'" 
	title="Voir la fiche complète de l\'événement">'.titre_selon_statut($evenement['titre'], $evenement['statut']).'</a>';
	

	?>
	</span>
	<span class="right"><?php echo $nom_lieu ?></span>
	<div class="spacer"></div>	
</div>
<!-- fin titre -->

<div class="flyer">
<?php
if (!empty($evenement['flyer']))
{
	$imgInfo = getimagesize($rep_images_even.$evenement['flyer']);
?>
	<a href="<?php echo $IMGeven.$evenement['flyer']."?".filemtime($rep_images_even.$evenement['flyer']) ?>" class="magnific-popup" target="_blank">
	
		<img src="<?php echo $IMGeven.$evenement['flyer']."?".filemtime($rep_images_even.$evenement['flyer']) ?>" alt="Flyer de cet événement" width="100" />
	</a>
<?php
	//echo lien_popup($IMGeven.$evenement['flyer'].'?'.filemtime($rep_images_even.$evenement['flyer']), "flyer", $imgInfo[0]+20, $imgInfo[1]+20, "<img src=\"".$IMGeven."s_".$evenement['flyer']."?".filemtime($rep_images_even."s_".$evenement['flyer'])."\" alt=\"Flyer\" />");

	}
	else if (!empty($evenement['image']))
	{
		$imgInfo = @getimagesize($rep_images.$evenement['image']);
		//echo lien_popup($IMGeven.$evenement['image']."?".filemtime($rep_images_even.$evenement['image']), "Image", $imgInfo[0]+20, $imgInfo[1]+20,"<img src=\"".$IMGeven."s_".$evenement['image']."?".filemtime($rep_images_even.$evenement['image'])."\" alt=\"Image\" />");	
	?>
	<a href="<?php echo $IMGeven.$evenement['image']."?".filemtime($rep_images_even.$evenement['image']) ?>" class="magnific-popup" target="_blank">
	
		<img src="<?php echo $IMGeven.$evenement['image']."?".filemtime($rep_images_even.$evenement['image']) ?>" alt="Photo pour cet événement" width="100" />
	</a>
	<?php
	}
	?>
</div>

<div class="description">

<?php
//reduction de la description pour la caser dans la boite "desc"

if (mb_strlen($evenement['description']) > $maxChar)
{
	echo texteHtmlReduit(textToHtml(securise_string($evenement['description'])), 
	$maxChar, 
	" <a href=\"".$url_site."evenement.php?idE=".$evenement['idEvenement']."\" title=\"Voir la fiche complète de l'événement\"> Lire la suite".$iconeSuite."</a>");
}
else
{ 
	echo textToHtml(htmlspecialchars($evenement['description']));
}

echo "</div>
<div class=\"spacer\"></div>\n";
echo "
<div class=\"pratique\">\n
<span class=\"left\">".$adresse."</span>";
echo "
<span class=\"right\">".afficher_debut_fin($evenement['horaire_debut'], $evenement['horaire_fin'], $evenement['dateEvenement'])." ".securise_string($evenement['horaire_complement'])
." ".securise_string($evenement['prix']);
?>
</span>
<div class="spacer"></div>
</div>
<!-- fin pratique -->

</div>
</div>