<?php

require_once("../app/bootstrap.php");

$page_titre = "Annoncer un événement";
$extra_css = ["apropos"];
include("../_header.inc.php");
?>


<div id="contenu" class="colonne">

	<div id="entete_contenu">
		<h2>Annoncer un événement sur La décadanse</h2>
		<div class="spacer"></div>
	</div>

	<div class="rubrique" style="padding:0 0 .8em 0;background:#f4f4f4;border-radius:3px">
	<ul >
        <li>
            <h3>vous avez des événements à&nbsp;ajouter régulièrement, <a href="/user-register.php">inscrivez-vous</a> (ou <a href="/user-login.php">connectez-vous</a> si vous avez déjà un compte)</h3>
            <p><strong>S'inscrire</strong></a> vous permettra aussitôt de&nbsp;:</p>
            <ul style="list-style-type:circle">
            <li>créer et modifier vos événements;</li>
            <li>ajouter une présentation de votre lieu s'il est enregistré sur le site;</li>
            <li>modifier vos infos d'organisateur s'il est enregistré sur le site;</li>
            </ul>
        </li>
        <li>
            <h3>vous voulez annoncer un&nbsp;événement une seule fois, sans compte</h3>
            <p><a href="/evenement-edit.php">Envoyez nous les infos via ce formulaire</a>, l'événement sera validé par nous dans les prochains jours.<br>
            Veuillez vérifier svp au préalable que l'événement n'est pas déjà présent dans l'<a href="/index.php">agenda</a>
            </p>
        </li>
</ul>

<p>Tout cela est <b>gratuit</b>, mais vous pouvez nous soutenir <a href="/articles/faireUnDon.php">en faisant un don</a></p>
<p>Veillez également à ce que vos événements respectent notre <b><a href="/articles/charte-editoriale.php">charte éditoriale</a></b>.</p>

	</div>
	<!-- Fin  -->


</div>
<!-- fin Contenu -->



<div id="colonne_gauche" class="colonne">
    <?php include("../event/_navigation_calendrier.inc.php"); ?>
</div>
<!-- Fin Colonnegauche -->

<div id="colonne_droite" class="colonne">
</div>


<?php
include("../_footer.inc.php");
?>
