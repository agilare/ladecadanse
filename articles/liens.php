<?php

require_once("../app/bootstrap.php");

$page_titre = "liens";
$page_description = "Liens";
include("../_header.inc.php");
?>

<div id="contenu" class="colonne">

	<div id="entete_contenu">
		<h2>Liens</h2>
	<div class="spacer"></div>
	</div>

	<div class="rubrique">

        <ul>
            <li><a href="http://www.raggasessions.ch/" target="_blank">Raggasessions.ch</a> : agenda de concerts et soirées reggae en Suisse</li>
            <li><a href="http://reprezent.ch/" target="_blank">Reprezent</a> : actualité du hip hop en Suisse Romande</li>
            <li><a href="http://azanya.ch/" target="_blank">Azanya.ch</a> : l’Agenda des événements culturels Afro-Caribéens de Genève et environs</li>
        </ul>

    </div>

</div>
<!-- fin Contenu -->



<div id="colonne_gauche" class="colonne">
<?php include("../_navigation_calendrier.inc.php"); ?>
</div>
<!-- Fin Colonnegauche -->

<div id="colonne_droite" class="colonne">
</div>


<?php
include("../_footer.inc.php");
?>
