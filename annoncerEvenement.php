<?php
if (is_file("config/reglages.php"))
{
	require_once("config/reglages.php");
}
require_once($rep_librairies."Sentry.php");
$videur = new Sentry();


$nom_page = "annoncer";
$page_titre = "Annoncer un événement";
$page_description = "portrait du site La décadanse : description, liste des membres, outils utilisés";
$extra_css = array("apropos");
include("includes/header.inc.php");
?>


<!-- Deb contenu -->
<div id="contenu" class="colonne">

	<div id="entete_contenu">
		<h2>Annoncer un événement sur La décadanse</h2>
		<div class="spacer"></div>
	</div>

	<div class="rubrique" style="padding:0 0 .8em 0;background:#f4f4f4;border-radius:3px">
	<ul >
	<li>
	<h3>vous avez des événements à&nbsp;ajouter régulièrement</h3>
	<p>Le mieux est de&nbsp;<a href="<?php echo $url_site; ?>inscription.php" title="S'inscrire pour devenir membre"><strong>s'inscrire</strong></a>, ce qui vous permettra aussitôt de&nbsp;:</p>
	<ul style="list-style-type:circle">
	<li>créer et modifier vos événements;</li>
	<li>ajouter une présentation de votre lieu s'il est enregistré sur le site;</li>
	<li>modifier vos infos d'organisateur s'il est enregistré sur le site;</li>
	<li>poster des commentaires.</li>
	</ul>
	</li>
	<p>Si vous avez déjà un compte sur le site, veuillez vous <a href="login.php">connecter</a>.</p>
	<li>

	
<h3>vous voulez juste annoncer un&nbsp;événement</h3>
<p>Vous pouvez alors nous envoyer les infos : 
<script type="text/javascript" language="javascript">
<!--
// Email obfuscator script 2.1 by Tim Williams, University of Arizona
// Random encryption key feature by Andrew Moulden, Site Engineering Ltd
// This code is freeware provided these four comment lines remain intact
// A wizard to generate this code is at http://www.jottings.com/obfuscator/
{ coded = "SR2w@1DthHDtDRgh.H8"
  key = "hRVNufMo37X6xZEKHsk8QWTI2mwqPjFr5iUYglCa0vdenOBGb4z9SyLA1JDcpt"
  shift=coded.length
  link=""
  for (i=0; i<coded.length; i++) {
    if (key.indexOf(coded.charAt(i))==-1) {
      ltr = coded.charAt(i)
      link += (ltr)
    }
    else {     
      ltr = (key.indexOf(coded.charAt(i))-shift+key.length) % key.length
      link += (key.charAt(ltr))
    }
  }
document.write("<a href='mailto:"+link+"?subject=[La décadanse] Événement à annoncer&body=Titre:%0D%0ALieu et adresse:%0D%0ADate et horaires:%0D%0ADescription:%0D%0APrix:%0D%0APrélocations:%0D%0A%0D%0AAffiche ou photo ci-joint au *format JPG, PNG ou GIF*; maximum 2 Mo :%0D%0A'>"+link+"</a>")
}
//-->
</script><noscript>Sorry, you need Javascript on to email me.</noscript>
<br>
(veuillez vérifier svp au préalable qu'il n'est pas déjà présent dans l'<a href="agenda.php">agenda</a>)
</p>
</li>
</ul>

<p>Tout cela est <b>gratuit</b>, mais vous pouvez nous soutenir <a href="faireUnDon.php">en faisant un don</a></p>
<p>Veillez également à ce que vos événements respectent notre <b><a href="charte-editoriale.php">charte éditoriale</a></b>.</p>
 
	</div>
	<!-- Fin  -->


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
