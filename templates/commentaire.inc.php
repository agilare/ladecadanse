<?php

$req_auteur = $connector->query("SELECT pseudo FROM personne WHERE idPersonne=".$breve['idPersonne']);
$listeAut = $connector->fetchArray($req_auteur);

$da = explode(" ", $breve['dateAjout']);


echo "<h2 class=\"jour\">".date_fr($da[0])."</h2>";


echo "<div class=\"breve_contenu\">
<h3>".securise_string($breve['titre'])."</h3>\n
<div class=\"spacer\"></div>\n";	

if (!empty($breve['img_breve']))
{
	$imgInfo = getimagesize($rep_images_breves.$breve['img_breve']);	
	echo "<div class=\"image\">";
	echo lien_popup($IMGbreves.$breve['img_breve'], "Image brève", $imgInfo[0]+20, $imgInfo[1]+20,
	"<img src=\"".$IMGbreves."s_".$breve['img_breve']."\" alt=\"image pour ".securise_string($breve['titre'])."\" />");
	echo "</div>";
}

echo "<p>".textToHtml(securise_string($breve['contenu']))."</p><p class=\"auteur\">".$listeAut['pseudo']."</p>";
echo "<div class=\"spacer\"></div>\n";

echo "<div class=\"spacer\"></div>\n
</div>\n";

	
?>