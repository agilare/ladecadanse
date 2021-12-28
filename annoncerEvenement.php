<?php
if (is_file("config/reglages.php"))
{
	require_once("config/reglages.php");
}

use Ladecadanse\Sentry;
use Ladecadanse\Logger;

$videur = new Sentry();


$nom_page = "annoncer";
$page_titre = "Annoncer un événement";
$page_description = "portrait du site La décadanse : description, liste des membres, outils utilisés";
$extra_css = array("apropos");
include("_header.inc.php");
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
            <h3>vous avez des événements à&nbsp;ajouter régulièrement, <a href="<?php echo $url_site; ?>inscription.php">inscrivez-vous</a> (ou <a href="login.php">connectez-vous</a> si vous avez déjà un compte)</h3>
            <p><strong>S'inscrire</strong></a> vous permettra aussitôt de&nbsp;:</p>
            <ul style="list-style-type:circle">
            <li>créer et modifier vos événements;</li>
            <li>ajouter une présentation de votre lieu s'il est enregistré sur le site;</li>
            <li>modifier vos infos d'organisateur s'il est enregistré sur le site;</li>
            <li>poster des commentaires.</li>
            </ul>
        </li>
        <li>
            <h3>vous voulez annoncer un&nbsp;événement une seule fois, sans compte</h3>
            <p><a href="ajouterEvenement.php">Envoyez nous les infos via ce formulaire</a>, l'événement sera validé par nous dans les prochains jours.<br>
            Veuillez vérifier svp au préalable que l'événement n'est pas déjà présent dans l'<a href="agenda.php">agenda</a>
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
<?php include("_navigation_calendrier.inc.php"); ?>
</div>
<!-- Fin Colonnegauche -->

<div id="colonne_droite" class="colonne">
</div>


<?php
include("_footer.inc.php");
?>
