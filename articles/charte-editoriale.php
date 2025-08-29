<?php

require_once("../app/bootstrap.php");

$page_titre = "Charte éditoriale";
$page_description = "";
include("../_header.inc.php");
?>

<main id="contenu" class="colonne">

    <header id="entete_contenu">
        <h1>Charte éditoriale</h1>
        <div class="spacer"></div>
    </header>

    <article class="rubrique">

        <h2><a id="Gnralits_0"></a>Généralités</h2>

        <p>La décadanse est un agenda culturel pour les régions de Genève et Lausanne composé d’une sélection d’événements. Ceux-ci, ainsi que les lieux et organisateurs publiés sur le site, doivent correspondre à certains critères qui définissent cette charte éditoriale.</p>
        <p>Les contenus ajoutés par des tiers qui ne respectent pas cette charte sont corrigés ou dépubliés par un administrateur du site.</p>

        <p>Le souhait est de promouvoir en particulier les acteurs de la culture alternative, désintéressés, jouant un rôle social et culturel. En revanche le soin est laissé à d’autres agendas la diffusion les événements plus conventionnels et/ou à intention plus commerciale.</p>
        <p>Les points énumérés ci-dessous définissent les critères permettant de se rapprocher de ce but.</p>

        <h2>Les événements</h2>

        <h3>Situation</h3>
        <p>À l’heure actuelle, les zone couvertes par La décadanse sont les régions genevoises et lausannoises.</p>

        <h3>Catégories</h3>
        <p>Les événements sont répartis en 5 catégories, dont voici des précisions pour 3 d’entre elles&nbsp;:</p>
        <h4><a id="Ftes_17"></a>Fêtes</h4>
        <p>Large rubrique où figurent les événements culturels, festifs avec très souvent une part musicale. Le volet culturel doit être prépondérant, les événements dont celui-ci est trop secondaire ne sont pas admis  (la catégorie "Divers" peut mieux convenir dans ce cas); par ex. un dîner-concert où un groupe ne joue que pour l’ambiance.</p>
        <h4><a id="Cin_20"></a>Ciné</h4>
        <p>Cette rubrique se concentre sur les projections des cinémas indépendants ou apparentés ; par conséquent les multiplexes (qui ont largement de quoi communiquer) n’y figurent pas. Il se peut que, ponctuellement, la qualité du film ou la particularité de l’événement prime sur cette règle.</p>
        <h4><a id="Divers_23"></a>Divers</h4>
        <p>Ici apparaissent les événements ne trouvant pas leur place dans les autres rubriques et qui peuvent être peu ou pas artistiques tels que conférences, débats, ateliers/cours (à condition que ceux-ci soient gratuits ou à prix modéré), balades culturelles, brocantes, etc. qui ont un intérêt culturel et/ou social.</p>
        <h3><a id="Ralit_26"></a>Réalité</h3>
        <p>Les événements doivent être dans le réel, c'est-à-dire dans un lieu défini, avec un début et une fin, où des personnes se rencontrent réellement ; l’agenda ne peut donc contenir d’annonces d’actualité, de campagnes sur le web, d’émissions (radio, TV, web), etc.</p>
        <h3><a id="Types_non_admis_30"></a>Types d'événements non admis</h3>
        <ul>
            <li>religion, croyance, esotérisme, paranormal</li>
            <li>médecine parallèle</li>
            <li>conférences/salons professionnels</li>
            <li>cours ou stages complets et dispendieux</li>
            <li>commercial (par ex. présentations de produits ou entreprises, annonces de type publicitaire, achat/vente, repas, apéros, happy hours, dégustations, etc.)</li>
            <li>développement personnel</li>
            <li>manifestations sportives</li>
        </ul>
        <p>Les contenus (textes, images) suivants ne sont pas admis :</p>
        <ul>
            <li>sexisme</li>
            <li>discrimination</li>
            <li>nationalisme</li>
        </ul>

        <h3><a id="Prix_38"></a>Prix</h3>
        <p>La décadanse propose des événements la plupart du temps abordables financièrement que ce soit pour le prix d’entrée, les consommations ou d’autres dépenses.</p>
        <h4><a id="entre_41"></a>Entrée</h4>
        <p>Les tarifs peuvent beaucoup varier selon le type d’événement (soirée DJ, concerts, théâtre…) mais nous cherchons à ce qu’ils soient appropriés, et nous fixons une limite à 40.- CHF (à ce prix là, il s’agira plutôt d’une programmation vaste ou une tête d’affiche). Des exceptions peuvent se produire, dans le cas par exemple d’un artiste spécifique, un événement avec de nombreuses activités (sur plusieurs jours par exemple), une part de soutien…</p>
        <h4><a id="consommations_44"></a>Consommations</h4>
        <p>Boissons, nourriture, locations, vestiaires doivent être à prix abordables. Au-delà de tels tarifs, on entre dans une catégorie différente des événements souhaités sur La décadanse (comme les boîtes de nuit par exemple). Toutefois, le prix n’est pas pris en compte unilatéralement, ainsi des tarifs un peu plus élevés peuvent être compensés par d’autres critères (qualitatifs, soutien, etc.).</p>

        <h3><a id="Accs_48"></a>Accès</h3>
        <p>Les lieux peuvent avoir différents niveaux de facilité d’accès, d’une entrée totalement libre à une entrée avec attente, fouille, exigence de tenue vestimentaire… Dans le même esprit que les autres critères, nous privilégions un accès aisé et un contrôle raisonnable, par contre ceux exigeant des critères stricts (tenue correcte, voire plus) ne sont pas publiés.</p>

        <h2><a id="LES_LIEUX_53"></a>Les lieux</h2>
        <p>Les lieux ayant une vocation commerciale trop prononcée ne sont pas souhaités (night-clubs, casinos, cabarets, multiplexes…). Pour pouvoir figurer dans la rubrique <a href="/lieu/lieux.php">Lieux</a>, des événements doivent être organisés régulièrement et ceux-ci doivent suivre les critères déjà présentés ci-dessus. Nous apprécions les lieux avec une programmation de qualité, un esprit désintéressé, se rapprochant de l’autogestion et avec un certain degré d’éthique.</p>

        <p>Août 2016, mis à jour en mars 2025</p>

    </article> <!-- .rubrique  -->

</main>

<div id="colonne_gauche" class="colonne">
    <?php include("../event/_navigation_calendrier.inc.php"); ?>
</div>

<div id="colonne_droite" class="colonne">
</div>

<?php
include("../_footer.inc.php");
?>
