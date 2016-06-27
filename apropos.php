<?php
if (is_file("config/reglages.php"))
{
	require_once("config/reglages.php");
}
require_once($rep_librairies."Sentry.php");
$videur = new Sentry();

$nom_page = "apropos";
$page_titre = "à propos du site";
$page_description = "portrait du site La décadanse : description, liste des membres, outils utilisés";
include("includes/header.inc.php");
?>


<!-- Deb contenu -->
<div id="contenu" class="colonne">

	<div id="entete_contenu">
		<h2>À propos</h2>
	<div class="spacer"></div>
	</div>

	<div class="rubrique">

		<h3>Description</h3>
<p>
 La décadanse est un agenda culturel pour Genève et sa région, présentant une sélection d'événements nous paraissant intéressants, et donnant la possibilité aux organisateurs d'ajouter leurs propres événements.
<p>
La majeur partie du site est composée d'un agenda permettant de naviguer dans les événements passés ou futurs. Chacun de ceux-ci a sa fiche détaillée avec la possibilité donnée aux personnes inscrites d'y laisser un commentaire. Une rubrique lieu répertorie des endroits où se déroulent des événements, et sont dans le meilleure des cas accompagnés de photos et descriptifs.
<p>
Le site est mis à jour quotidiennement par le webmaster, les rédacteurs et les contributeurs. Ces derniers sont la plupart du temps des organisateurs de soirées, s'occupant de lieux et/ou faisant partie de collectifs. 


	</div>
	<!-- Fin  -->

	<div class="rubrique">
	<h3>Staff</h3>
	<dl>
		<dt>Webmaster</dt>
		<dd>
			<dd>Michel <a href="http://www.profession-web.ch/candidat/37/gaudry_michel/">
  <img src="http://www.profession-web.ch/img/liens/pw_link4.gif">
</a></li>
		</dd>

	
<?php
/*
	$sql = "SELECT pseudo FROM personne WHERE statut='actif' AND groupe=6 ";

	$req = $connector->query($sql);

	while ($tab = $connector->fetchArray($req))
	{
		echo '<dd>'.$tab['pseudo'].'</dd>';
	}
	*/
?>
	
		<dt>Participer</dt>
			<ul>
				<li><a href="<?php echo $url_site ?>inscription.php">Inscrivez-vous</a></li>
		<li>ou écrivez-nous via le <a href="<?php echo $url_site ?>contacteznous.php">formulaire de contact</a>.</li>
		<li>ou <a href="<?php echo $url_site ?>faireUnDon.php">faites un don</a>.</li>
			</ul>
	<h3>Divers</h3>
		<dt>Merci à</dt>
			<dd><a href="https://www.darksite.ch/" title="Darksite, portail culturel et indépendant">Darksite</a> : hébergement</dd>
		</ul>
	</dl>
	
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
