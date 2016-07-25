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
La décadanse est un agenda de sorties créé en 2003 pour Genève et ses environs. Le site se compose d'une sélection d'événements culturels si possible ouverts, accessibles et intéressants.</p>
<p>Les acteurs culturels (organisateurs, gérants, artistes et al.) ont la possibilité d'ajouter gratuitement leurs propres événements.<br>
Pour faire figurer un événement sur le site, l'inscription n'est pas obligatoire, même si elle encouragée s'il y en a un certain nombre à ajouter; il est en effet aussi possible de les annoncer en nous envoyant un message. Cela est résumé dans <a href="annoncerEvenement.php">cette page</a>.</p>
<p>
Les rubriques <a href="lieux.php">Lieux</a> et <a href="organisateurs.php">Organisateurs</a> répertorient ces acteurs, avec pour chacun une fiche, si possible accompagnée d'un descriptif et de photos.</p>
<p>
Le site est mis à jour quotidiennement par le webmaster et les contributeurs.</p>
</div>
	<!-- Fin  -->

	<div class="rubrique">
	<h3>Staff</h3>
	<dl>
		<dt>Webmaster</dt>
		<dd>
			<dd>Michel <a href="http://www.profession-web.ch/candidat/37/gaudry_michel/">
  <img src="http://www.profession-web.ch/img/liens/pw_link4.gif" alt="Mon profil">
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
