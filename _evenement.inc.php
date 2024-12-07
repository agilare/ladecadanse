<?php
use Ladecadanse\HtmlShrink;
use Ladecadanse\Utils\Text;
use Ladecadanse\Evenement;

//Affichage du lieu selon son existence ou non dans la base
if ($evenement['idLieu'] != 0)
{
	$listeLieu = $connector->fetchArray(
	$connector->query("SELECT nom, adresse, quartier, localite.localite AS localite, region, URL, lat, lng FROM lieu, localite
		WHERE localite_id=localite.id AND idlieu='".$evenement['idLieu']."'"));

	$nom_lieu = "<a href=\"/lieu.php?idL=".$evenement['idLieu']."\" title=\"Voir la fiche du lieu : ".sanitizeForHtml($listeLieu['nom'])."\" >".htmlspecialchars($listeLieu['nom'])."</a>";

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
    $listeLieu['localite'] = "";
}

$adresse = htmlspecialchars(HtmlShrink::getAdressFitted(null, $listeLieu['localite'], $listeLieu['quartier'], $listeLieu['adresse']));

echo '<div id="evenements">';
echo "<p><a href=\"/evenement-agenda.php?courant=".$evenement['dateEvenement']."\" title=\"Agenda\">".ucfirst(date_fr($evenement['dateEvenement'], "annee"))."</a></p>";
?>
<div class="evenement">

<div class="titre">
	<span class="left">
	<?php

	$maxChar = Text::trouveMaxChar($evenement['description'], 60, 9);

	echo '<a href="/evenement.php?idE='.$evenement['idEvenement'].'"
	title="Voir la fiche complète de l\'événement">'.Evenement::titre_selon_statut($evenement['titre'], $evenement['statut']).'</a>';


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
?>
	<a href="<?php echo Evenement::getFileHref(Evenement::getFilePath($evenement['flyer']), true) ?>" class="magnific-popup" target="_blank">

		<img src="<?php echo Evenement::getFileHref(Evenement::getFilePath($evenement['flyer'], 's_'), true) ?>" alt="Flyer de cet événement" width="100" />
	</a>
<?php
	}
	else if (!empty($evenement['image']))
	{
	?>
	<a href="<?php echo Evenement::getFileHref(Evenement::getFilePath($evenement['image']), true) ?>" class="magnific-popup" target="_blank">

		<img src="<?php echo Evenement::getFileHref(Evenement::getFilePath($evenement['image'], 's_'), true) ?>" alt="Photo pour cet événement" width="100" />
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
	echo Text::texteHtmlReduit(Text::wikiToHtml(sanitizeForHtml($evenement['description'])),
	$maxChar,
	" <a href=\"/evenement.php?idE=".$evenement['idEvenement']."\" title=\"Voir la fiche complète de l'événement\"> Lire la suite".$iconeSuite."</a>");
}
else
{
	echo Text::wikiToHtml(htmlspecialchars($evenement['description']));
}

echo "</div>
<div class=\"spacer\"></div>\n";
echo "
<div class=\"pratique\">\n
<span class=\"left\">".$adresse."</span>";
echo "
<span class=\"right\">".afficher_debut_fin($evenement['horaire_debut'], $evenement['horaire_fin'], $evenement['dateEvenement'])." ".sanitizeForHtml($evenement['horaire_complement'])
." ".sanitizeForHtml($evenement['prix']);
?>
</span>
<div class="spacer"></div>
</div>
<!-- fin pratique -->

</div>
</div>
