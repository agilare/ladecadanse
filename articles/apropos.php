<?php
require_once("../app/bootstrap.php");

$page_titre = "à propos du site";
$page_description = "portrait du site La décadanse : description, liste des membres, outils utilisés";
include("../_header.inc.php");
?>

<div id="contenu" class="colonne">

    <div id="entete_contenu">
        <h2>À propos</h2>
        <div class="spacer"></div>
    </div>

    <div class="rubrique">

        <h3>Description</h3>
        <p>
            La décadanse est un agenda de sorties créé en 2003 pour Genève et ses environs. Le site se compose d'une sélection d'événements culturels si possible ouverts, accessibles et intéressants. Ceci est détaillé dans la <a href="charte-editoriale.php">charte éditoriale</a>.</p>
        <p>Les acteurs culturels (organisateurs, gérants, artistes et al.) ont la possibilité d'ajouter gratuitement leurs propres événements.<br>
            Pour faire figurer un événement sur le site, l'inscription n'est pas obligatoire, même si elle est encouragée dès qu'il y en a un certain nombre à ajouter ; cela est résumé dans <a href="annoncerEvenement.php">cette page</a>.</p>
        <p>
            Les rubriques <a href="/lieux.php">Lieux</a> et <a href="/organisateurs.php">Organisateurs</a> répertorient ces acteurs, avec pour chacun une fiche, si possible accompagnée d'un descriptif et d'illustrations.</p>
        <p>Le site est mis à jour quotidiennement par les contributeurs et par nous même</p>
        <p><a href="https://www.gbnews.ch/ladecadanse-ch-un-bouche-a-oreille-en-ligne/" target="_blank">Article "ladecadanse.ch, un bouche à oreille en ligne" sur GBNnews.ch (2015)</a></p>
    </div>
    <!-- Fin  -->

    <div class="rubrique">
        <dl>
            <!--		<dt>Webmasters</dt>

                <dd>Michel</dd>
                <dd>Sabrina</dd>
                        <dd>Anne</dd>-->

            <dt>Participer</dt>
            <ul>
                <li><a href="/user-register.php">Inscrivez-vous</a> pour annoncer des événements</li>
                <li>ou écrivez-nous via le <a href="/contacteznous.php">formulaire de contact</a>.</li>
                <li>ou <a href="/articles/faireUnDon.php">faites un don</a>.</li>
            </ul>
        </dl>

    </div>





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
