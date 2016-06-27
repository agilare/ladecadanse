<?php
if (is_file("config/reglages.php"))
{
	require_once("config/reglages.php");
}
require_once($rep_librairies."Sentry.php");
$videur = new Sentry();

$nom_page = "liens";
$page_titre = "liens";
$page_description = "Liens";
include("includes/header.inc.php");
?>


<!-- Deb contenu -->
<div id="contenu" class="colonne">

	<div id="entete_contenu">
		<h2>Liens</h2>
	<div class="spacer"></div>
	</div>



	<div class="rubrique">
		
	<ul>
		<li><a onclick="window.open(this.href,'_blank');return false;" title="Aller sur Darksite forum agenda" href="http://www.darksite.ch/forum">Forum Darksite</a></li>
		<!--<li><a onclick="window.open(this.href,'_blank');return false;" title="www.findumonde.ch" href="http://www.findumonde.ch/">Derniers bars avant la fin du monde</a> : tour de Genève des lieux typiques et pittoresques</li>
		<li><a onclick="window.open(this.href,'_blank');return false;" title="Electronism.net" href="http://www.electronism.net/">Electronism.net</a> : communauté autour de la musique électronique en Suisse romande</li>-->
		<li><a onclick="window.open(this.href,'_blank');return false;" title="Raggasessions" href="http://www.raggasessions.ch/">Raggasessions.ch</a> : agenda de concerts et soirées reggae en Suisse</li>
		<!--<li><a onclick="window.open(this.href,'_blank');return false;" title="The Fake" href="http://www.thefake.ch/">The fake</a> : webzine culturel</li>-->
		<li><a onclick="window.open(this.href,'_blank');return false;" title="Heimathome Swiss support'act" href="http://www.wilrecords.com/heimathome/index.php">Heimathome</a> : actu rock Genève et alentours</li>
		<li><a onclick="window.open(this.href,'_blank');return false;" href="http://reprezent.ch/">Reprezent</a> : actualité du hip hop en Suisse Romande</li>
		<li><a onclick="window.open(this.href,'_blank');return false;" href="http://azanya.ch/">Azanya.ch</a> : l’Agenda des événements culturels Afro-Caribéens de Genève et environs</li>
		<!--<li><a onclick="window.open(this.href,'_blank');return false;" title="Nuit.ch" href="http://www.lachaine.ch/">La Chaîne.ch</a> : webTV indépendante</li>-->
	</ul>

	</div>





</div>
<!-- fin Contenu -->



<div id="colonne_gauche" class="colonne">
<?php include("includes/navigation_calendrier.inc.php"); ?>
</div>
<!-- Fin Colonnegauche -->

<div id="colonne_droite" class="colonne">
</div>


<?php
include("includes/footer.inc.php");
?>
