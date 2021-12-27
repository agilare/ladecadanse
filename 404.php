<?php
if (is_file("config/reglages.php"))
{
	require_once("config/reglages.php");
}
require_once($rep_librairies."Sentry.php");
$videur = new Sentry();


$nom_page = "404";
$page_titre = "404 - page non trouvée";
$page_description = "Erreur 404 - page non trouvée";
include("_header.inc.php");
?>
<html>
<head>

<style type="text/css">

body {
background-color: #5C7378;
   font-family: Verdana,Arial;
    font-size: 85%;
    height: 100%;
}

#contenu
{
margin:5% 0 0 10%;
width:40%;
background:#FDFDFD;
border-radius:5px;
}

</style>
</head>

<body>
<!-- Deb contenu -->
<div id="contenu" class="colonne">

<a href="https://www.darksite.ch/ladecadanse/" title="Retour à la page d'accueil"><img src="https://www.darksite.ch/ladecadanse/images/interface/logo_titre.jpg" alt="La décadanse" height="35" width="180"></a>

	<div class="rubrique" style="margin-left:20px">
<h2 style="margin:120px 0 20px 0;font-size:2.4em;font-weight:bold;">404 - page non trouv&eacute;e</h2>
	<p>&nbsp;</p>
			<p><a href="index.php">Page d'accueil de La d&eacute;cadanse</a></p>
		<p>&nbsp;</p>		

	</div>
	<!-- Fin  -->



</div>
<!-- fin Contenu -->




<?php
include("_footer.inc.php");
?>
