<?php
if (isset($descriptionlieu['dateAjout']))
{
    $ajoute = "Ajouté le ".date_fr($descriptionlieu['dateAjout'], 'annee');
}
?>
<?php
if ($descriptionlieu['date_derniere_modif'] != "0000-00-00 00:00:00")
{
    $dern_modif =  "Dernière modification : ".date_fr($descriptionlieu['date_derniere_modif'], 'annee');
}
?>
<div id="descriptions">

<div class="description">

<p><?php echo textToHtml($descriptionlieu['contenu']) ?></p>
	
	<div class="auteur">
		<span class="left"><?php echo $ajoute; ?><br /><?php echo $dern_modif; ?></span>
		<span class="right action_editer">
		<a href="<?php echo $url_site ?>ajouterDescription.php?action=editer&amp;idL=<?php echo $descriptionlieu['idLieu'] ?>&amp;idP=<?php echo $descriptionlieu['auteur'] ?>">
		Modifier</a>
		</span>
		
	</div>
	<div class="spacer"><!-- --></div>
</div>
<!-- Fin description -->

</div>