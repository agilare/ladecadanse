<?php
require_once("../app/bootstrap.php");

use Ladecadanse\UserLevel;

if (!$videur->checkGroup(UserLevel::ACTOR)) {
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
	header("Location: /user-login.php"); die();
}

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

        <header>
            <h2>3.9.1</h2>
            <br>
            <p>12 octobre 2025</p>
        </header>

        <h3>Organisateurs</h3>

        <ul>
            <li>pages bien <b>plus rapides</b> à charger (dans le menu j'ai ôté le prochains événements des organisateurs ce qui réduit le temps de chargement de la page à moins d'1s)</li>
            <li>page Organisateur :
                <ul>
                    <li>possibilité de voir les <b>anciens événements</b></li>
                    <li>moins de temps à scroller grâce à la coupure des textes de présentation (accompagnés d'un lien "Lire la suite")</li>
                    <li>cliquer sur "Ajouter un événement de cet organisateur" pré-sélectionne l'organisateur dans le formulaire</li>
                    <li>code nettoyé</li>
                </ul>
        </ul>

        <h3>Lieux</h3>

        <ul>
            <li><a href="/lieu/lieux.php">page Lieux</a>
                <ul>
                    <li>lorsque le lieu a un événement aujourd'hui c'est <span style="background:yellow">mise en évidence</span></li>
                    <li>tableau plus clair avec les Catégories déplacées vers le nom</li>
                </ul>
            <li>lieu : dans la liste des événements chaque date mène maintenant à l'agenda</li>
            <li>le nb d'événements par page (100) est désormais correct</li>
        </ul>

        <h3>Événements</h3>

        <ul>
            <li>recherche : le nb d'événements par page (100) est désormais correct</li>
        </ul>

        <h3>Page d'accueil</h3>

        <ul>
            <li>partenaires : nouveau logo Radio Vostok</li>
        </ul>

        <p><a href="https://github.com/agilare/ladecadanse/releases/tag/v3.9.1" class="lien_ext" target="_blank">Détails</a></p>

    </article>
    <hr>
    <article class="rubrique">

        <header>
            <h2>3.9.0</h2>
            <br>
            <p>21 septembre 2025</p>
        </header>

        <h3>Événements</h3>

        <ul>
            <li><b>recherche</b> : remaniement de la page avec de meilleurs résultats, un affichage plus rapide et une navigation plus pratique</li>
            <li>correction du lien dépublier un événement</li>
            <li>formulaire pour Signaler une erreur remanié et simplifié</li>
        </ul>

        <h3>Lieux</h3>

        <ul>
            <li><b>nouvelle <a href="/lieu/lieux.php">page Lieux</a></b> : ableau des lieux avec filtres, tri et pagination</li>
            <li>page Lieu :
                <ul>
                    <li>possibilité de voir les <b>anciens événements</b></li>
                    <li>moins de temps à scroller grâce à la coupure des textes de présentation (accompagnés d'un lien "Lire la suite")</li>
                    <li>cliquer sur "Ajouter un événement de cet organisateur" pré-sélectionne l'organisateur dans le formulaire</li>
                    <li>code nettoyé</li>
                </ul>
        </ul>

        <h3>Divers</h3>

        <ul>
            <li>affichage des page un peu plus rapide (en autorisant la mise en cache par le navigateur des fichiers de style et de scripts)</li>
            <li>boutons Organisateurs et Lieu actifs lorsqu'on est dans ces rubriques</li>
            <li>tous les liens extérieurs ont une <span class="lien_ext">icône explicite</span></li>
            <li>davantage d'HTML sémantique</li>
            <li>nombreuses améliorations techniques (nettoyage du code, mises à jour infrastructure)</li>
        </ul>

        <p><a href="https://github.com/agilare/ladecadanse/releases/tag/v3.9.0" class="lien_ext" target="_blank">Détails</a></p>

    </article>

    <hr>

    <article class="rubrique">

        <header>
            <h2>3.8.0</h2>
            <br>
            <p>20 juillet 2025</p>
        </header>

        <h3>Événements</h3>

        <h4>Page d'accueil</h4>

        <ul>
            <li>reprend les fonctionnalités de la page Agenda (cette dernière est supprimée)</li>
            <li>aller facilement vers le jour précédent ou vers le jour suivant</li>
            <li>on peut <b>trier par heure</b> de début des événements</li>
            <li>chargement <b>plus rapide</b> de la page</li>
        </ul>

        <h4>Page d'un événement</h4>

        <ul>
            <li>navigation vers l'événement précédent et vers le suivant plus complète</li>
            <li>partie horaires, prix, prélocations clarifiée</li>
        </ul>

        <h3>Divers</h3>

        <ul>

            <li>réparation de l'outil Mot de passe oublié</li>
            <li>mobile : cliquer sur le bouton de recherche focus autom. dans le champ</li>
            <li>nombreuses améliorations techniques (refactoring, mises à jour)</li>
            <li>amélioration du référencement</li>
        </ul>

        <p><a href="https://github.com/agilare/ladecadanse/releases/tag/v3.8.0" class="lien_ext" target="_blank">Détails</a></p>

    </article>

    <hr>

    <article class="rubrique">

        <header>
            <h2>3.7.4</h2>
            <br>
            <p>25 mai 2025</p>
        </header>

        <h3>Page d'accueil</h3>

        <ul>
            <li>partenaires : ôté <a href="https://fr.wikipedia.org/wiki/Noctambus_(Gen%C3%A8ve)" class="lien_ext" target="_blank">Noctambus</a> (remplacé par le réseau nocturne tpg)
            <li>les liens permettant d'aller vers la catégorie suivante plux explicites
            <li>nettoyage du code
        </ul>

        <h3>Divers</h3>

        <ul>
            <li><a href="/articles/apropos.php">À propos</a> lien vers l'<a href="https://www.gbnews.ch/ladecadanse-ch-un-bouche-a-oreille-en-ligne/" class="lien_ext" target="_blank">article GBNews.ch au sujet de La décadanse</a>
            <li>améliorations du référencement
            <li>refactoring
        </ul>

        <p><a href="https://github.com/agilare/ladecadanse/releases/tag/v3.7.4" class="lien_ext" target="_blank">Détails</a></p>

    </article>

    <hr>

    <article class="rubrique">

        <header>
            <h2>3.7.3</h2>
            <br>
            <p>11 mai 2025</p>
        </header>

        <h3>Événements</h3>

        <ul>
            <li>Meilleure intégration des événements dans dans les sites externes (Facebook, etc.) grâce à balises Opengraph
        </ul>

        <h3>Page <a href="/articles/faireUnDon.php">Faire un don</a></h3>

        <ul>
            <li>Widget <a href="https://wemakeit.com/?locale=fr" class="lien_ext" target="_blank">We make it</a>
        </ul>

        <h3>Divers</h3>

        <ul>
            <li>Mesures pour suivre les actions sur le site
            <li>Mise à jour de l'infrastructure
        </ul>

        <p><a href="https://github.com/agilare/ladecadanse/releases/tag/v3.7.3" class="lien_ext" target="_blank">Détails</a></p>

    </article>




</main>

<div id="colonne_gauche" class="colonne">
    <?php include("../event/_navigation_calendrier.inc.php"); ?>
</div>

<div id="colonne_droite" class="colonne">
</div>

<?php include("../_footer.inc.php"); ?>
