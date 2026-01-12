<?php
require_once("../app/bootstrap.php");


$page_titre = "Participer";
include("../_header.inc.php");
?>

<main id="contenu" class="colonne">

    <header id="entete_contenu">
        <h1>Participer à La décadanse</h1>
        <div class="spacer"></div>
    </header>

    <article class="rubrique">

        <p>Merci de vous intéresser à ce projet qui est d&#39;une assez grande utilité pour <strong>faire connaître les événements de la région genevoise et ses environs</strong>. Les contributions bénévoles sont bienvenues car il y a actuellement pas mal à faire, notamment dans la rénovation technique et visuelle.</p>
        <p>Vous pouvez aider de plusieurs manières :</p>
        <ul>
            <li>🧑‍💻 participer à la gestion du <a href="https://www.ladecadanse.ch/">site actuel</a> (ajout d&#39;événements, aide, modération), ce qui me permettrait d&#39;avoir davantage de temps pour le développement (<a href="https://www.ladecadanse.ch/contacteznous.php">me contacter</a>)</li>
            <li>🪙 <a href="https://www.ladecadanse.ch/articles/faireUnDon.php">faire un don</a> qui paierait les frais, soutiendrait mon travail</li>
        </ul>
        <p>et spécifiquement, si vous êtes intéressé en tant que <strong>développeur</strong> :</p>
        <ul>
            <li>⌨️ ajouter ou réaliser des <a href="https://github.com/agilare/ladecadanse/issues">Issues</a> (améliorations, refactoring, bugfixes...);
                <br>👉 je propose une <a href="https://github.com/agilare/ladecadanse/wiki/Les-prochains-d%C3%A9veloppements-sugg%C3%A9r%C3%A9s">suggestion de développements utiles et intéressants</a></li>
            <li>🔫 <a href="https://github.com/agilare/ladecadanse/blob/master/SECURITY.md">signaler voire corriger des vulnérabilités</a></li>
        </ul>

        <p>La suite, pour les développeurs, sur le <a href="https://github.com/agilare/ladecadanse/blob/master/CONTRIBUTING.md#contexte" rel="external" target="_blank">CONTRIBUTING.md</a> (contexte, développements, démarrage)</p>

    </article>

</main>

<div id="colonne_gauche" class="colonne">
    <?php include("../event/_navigation_calendrier.inc.php"); ?>
</div>

<div id="colonne_droite" class="colonne">
</div>

<?php include("../_footer.inc.php"); ?>
