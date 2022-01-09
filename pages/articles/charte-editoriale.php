<?php

require_once("../../config/reglages.php");


use Ladecadanse\Security\Sentry;

$videur = new Sentry();

$nom_page = "charte-editoriale";
$page_titre = "Charte éditoriale";
$page_description = "";
include("../_header.inc.php");
?>


<!-- Deb contenu -->
<div id="contenu" class="colonne">

    <div id="entete_contenu">
        <h2>Charte éditoriale</h2>
        <div class="spacer"></div>
    </div>

    <div class="rubrique">

        <h3><a id="Gnralits_0"></a>Généralités</h3>
        <p>La décadanse est un agenda culturel pour les régions de Genève et Lausanne composé d’une sélection d’événements. Ceux-ci, ainsi que les lieux et organisateurs publiés sur le site, doivent correspondre à certains critères qui définissent notre charte éditoriale.</p>
        <p>Les contenus ajoutés par des tiers qui ne respectent pas cette charte sont corrigés ou dépubliés par les administrateurs du site.</p>

        <p>Nous souhaitons promouvoir en particulier les acteurs de la culture alternative, les organisateurs disposant de moyens modestes, désintéressés, jouant un rôle social et culturel. En revanche nous laissons à d’autres agendas le soin de diffuser les événements plus conventionnels et/ou à but plus commercial.</p>
        <p>Les points énumérés ci-dessous définissent les critères permettant de se rapprocher de cet objectif.</p>
        <h3>Les événements</h3>
        <h4>Situation</h4>
         <p>À l’heure actuelle, les zone couvertes par La décadanse sont les régions genevoises et lausannoises.</p>

        <h4>Catégories</h4>
        <p>Les événements sont répartis en 5 catégories, dont voici des précisions pour 3 d’entre elles&nbsp;:</p>
        <h5><a id="Ftes_17"></a>Fêtes</h5>
        <p>Large rubrique où figurent les événements culturels, festifs avec très souvent une part musicale. Le volet culturel doit être prépondérant, les événements où celui-ci est trop secondaire ne sont pas admis; par ex. un dîner-concert où un groupe ne joue que pour l’ambiance.</p>
        <h5><a id="Cin_20"></a>Ciné</h5>
        <p>Cette rubrique se concentre sur les projections des cinémas indépendants ou apparentés; par conséquent les multiplexes (qui ont largement de quoi communiquer) n’y figurent pas. Il se peut que, ponctuellement, la qualité du film ou la particularité de l’événement prime sur cette règle.</p>
        <h5><a id="Divers_23"></a>Divers</h5>
        <p>Ici apparaissent les événements ne trouvant pas leur place dans les autres rubriques et qui peuvent être peu ou pas artistiques tels que conférences, débats, ateliers/ cours (à condition que ceux-ci soient gratuits ou à prix modique), balades culturelles, brocantes, etc. qui ont un intérêt culturel et/ou social</p>
        <h4><a id="Ralit_26"></a>Réalité</h4>
        <p>Les événements doivent êtres dans le réel, c'est-à-dire dans un lieu défini, avec un début et une fin, où des personnes se rencontrent vraiment; l’agenda ne peut donc contenir d’annonces d’actualité, de campagnes sur le web, d’émissions (radio, TV, web), etc.</p>
        <h4><a id="Types_non_admis_30"></a>Types d'événements non admis</h4>
        <ul>
            <li>religion, croyance, esotérisme, paranormal</li>
            <li>médecine parallèle</li>
            <li>conférences/salons professionnels</li>
            <li>cours, stages complets et dispendieux</li>
            <li>commercial (par ex. présentations de produits ou entreprises, annonces de type publicitaire, achat/vente, brunchs, apéros, happy hours, dégustations, etc.)</li>
            <li>développement personnel</li>
            <li>manifestations sportives</li>
        </ul>
        <p>Les contenus (textes, images) suivants ne sont pas admis :</p>
        <ul>
            <li>sexisme</li>
            <li>discrimination</li>
            <li>nationalisme</li>
        </ul>
        <h4><a id="Prix_38"></a>Prix</h4>
        <p>La décadanse propose des événements la plupart du temps abordables financièrement que ce soit pour le prix d’entrée, les consommations ou d’autres dépenses.</p>
        <h5><a id="entre_41"></a>Entrée</h5>
        <p>Les tarifs peuvent beaucoup varier selon le type d’événement (soirée DJ, concerts, théâtre…) mais nous cherchons à ce qu’ils soient appropriés, et nous fixons une limite à 40.- CHF (à ce prix là, il s’agira plutôt d’une programmation riche ou une tête d’affiche). Des exceptions peuvent se produire, dans le cas par exemple d’un artiste spécifique, un événement avec de nombreuses activités (sur plusieurs jours par exemple), une part de soutien…</p>
        <h5><a id="consommations_44"></a>Consommations</h5>
        <p>Boissons, nourriture, locations, vestiaires doivent être à prix abordables. Au-delà de tels tarifs, on entre dans une catégorie différente des événements souhaités sur La décadanse (comme les boîtes de nuit par exemple).
            Toutefois, le prix n’est pas pris en compte unilatéralement, ainsi des tarifs un peu plus élevés peuvent être compensés par d’autres critères (qualitatifs, soutien, etc.).</p>
        <h4><a id="Accs_48"></a>Accès</h4>
        <p>Les lieux peuvent avoir différents niveaux de facilité d’accès, d’une entrée totalement libre à une entrée avec attente, fouille, exigence de tenue vestimentaire…
            Dans le même esprit que les autres critères, nous privilégions un accès aisé et un contrôle raisonnable, par contre ceux exigeant des critères stricts (tenue correcte, voire plus) ne sont pas publiés.</p>
        <h3><a id="LES_LIEUX_53"></a>Les lieux</h3>
        <p>Les lieux ayant une vocation commerciale trop prononcée ne sont pas souhaités (night-clubs, casinos, cabarets, multiplexes…). Pour pouvoir figurer dans la rubrique <a href="/pages/lieux.php">Lieux</a>, des événements doivent être organisés régulièrement et ceux-ci doivent suivre les critères déjà présentés ci-dessus.
            Nous apprécions les lieux avec une programmation de qualité, un esprit désintéressé, se rapprochant de l’autogestion et avec un certain degré d’éthique.</p>
        <p>Août 2016, mis à jour en décembre 2018</p>

    </div>
    <!-- Fin  -->







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
