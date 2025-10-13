<?php
require_once("../app/bootstrap.php");

$page_titre = "Mises à jour";
$page_description = "Mises à jour logicielles";
include("../_header.inc.php");
?>

<main id="contenu" class="colonne">

    <header id="entete_contenu">
        <h1>Mises à jour</h1>
        <div class="spacer"></div>
    </header>

    <article class="rubrique">

        <h2>3.9.1</h2>
        <br>
        <p>12 octobre 2025</p>

        <h3>Organisateurs</h3>

        <ul>
            <li>pages bien <b>plus rapides</b> à charger (dans le menu j'ôté le nb d'événement par organisateur ce qui réduit le temps de chargement de la page à moins d'1s)</li>
            <li>page Organisateur :
                <ul>
                    <li>possibilité de voir les <b>anciens événements</b></li>
                    <li>moins de temps à scroller grâce à la coupure des textes de présentation (accompagnés d'un lien "Lire la suite")</li>
                    <li>cliquer sur "Ajouter un événement de cet organisateur" préselectionne l'organisateur dans le formulaire</li>
                    <li>code nettoyé</li>
                </ul>
        </ul>

        <h3>Lieux</h3>

        <ul>
            <li><a href="/lieu/lieux.php">page Lieux</a>
                <ul>
                    <li>lorsque le lieu a un événement aujourd'hui c'est en <span style="background:yellow">jaune</span></li>
                    <li>tableau plus clair avec la colonne Catégories déplacée vers le nom</li>
                </ul>
            <li>lieu : dans la liste des événements chaque date mène maintenant à l'agenda</li>
            <li>le nb d'événements par page (100) est désormais correct</li>
        </ul>

        <h3>Événements</h3>

        <ul>
            <li>le nb d'événements par page (100) est désormais correct</li>
        </ul>

        <h3>Page d'accueil</h3>

        <ul>
            <li>partenaires : nouveau logo Radio Vostok</li>
        </ul>

        <p><a href="https://github.com/agilare/ladecadanse/releases/tag/v3.9.1" class="lien_ext" target="_blank">Détails</a></p>

    </article>
    <hr>
    <article class="rubrique">

        <h2>3.9.0</h2>
        <br>
        <p>21 septembre 2025</p>

        <h3>Événements</h3>

        <ul>
            <li>recherche : remaniement de la page, meilleurs résultats, performances, navigation
            <li>correction du lien dépublier un événement
            <li>formulaire pour Signaler une erreur remanié et simplifié
        </ul>

        <h3>Lieux</h3>

        <ul>
            <li>nouvelle <a href="/lieu/lieux.php">page Lieux</a>
                <ul>
                    <li>tableau des lieux avec filtres, navigation, pagination</li>
                    <li>tableau plus clair avec la colonne Catégories déplacée vers le nom</li>
                </ul>
            <li>page Lieu :
                <ul>
                    <li>possibilité de voir les <b>anciens événements</b></li>
                    <li>moins de temps à scroller grâce à la coupure des textes de présentation (accompagnés d'un lien "Lire la suite")</li>
                    <li>cliquer sur "Ajouter un événement de cet organisateur" préselectionne l'organisateur dans le formulaire</li>
                    <li>code nettoyé</li>
                </ul>
        </ul>

        <h3>Divers</h3>

        <ul>
            <li>boutons Organisateurs et Lieu actifs lorsqu'on est dans ces rubriques
            <li>tous les liens extérieurs ont une icône idoine
            <li>légère amélioration des performances en autorisant la mise en cache par le navigateur des fichiers de style et de scripts
            <li>davantage d'HTML sémantique
            <li>nombreuses améliorations techniques (nettoyage du code, mises à jour infrastructure)
        </ul>

        <p><a href="https://github.com/agilare/ladecadanse/releases/tag/v3.9.0" class="lien_ext" target="_blank">Détails</a></p>

    </article>

</main>

<div id="colonne_gauche" class="colonne">
    <?php include("../event/_navigation_calendrier.inc.php"); ?>
</div>

<div id="colonne_droite" class="colonne">
</div>

<?php include("../_footer.inc.php"); ?>
