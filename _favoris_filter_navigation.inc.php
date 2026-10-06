<?php if (!Ladecadanse\Favorites::isEnabled()) { return; } ?>
<?php
/*
 * Le filtre « Favoris » des listes d'événements, calqué sur les onglets de genre de l'agenda :
 * actif, il prend la classe .ici et montre la croix qui retire le filtre. À la différence des
 * genres, le filtrage se fait côté client (favorites.js), d'où le href="#".
 *
 * L'attribut hidden est retiré par le script dès qu'un favori figure dans la liste affichée :
 * sans favori, il n'y a rien à filtrer. Le compteur est rempli par le même script : un rendu
 * serveur ne serait jamais vu, le lien étant masqué jusque-là, et les favoris d'un visiteur
 * ne vivent que dans son navigateur.
 */
?>
<div id="favoris_filter_navigation" hidden>
    <ul>
        <li class="js-favoris-filter-item favoris-filtre-item"><a href="#" class="js-favoris-filter favoris-filtre" rel="nofollow">Favoris&nbsp;<span class="js-favoris-count favoris-filtre-compteur"></span><span class="favoris-filtre-retirer">&nbsp;<i class="fa fa-times" aria-hidden="true"></i></span></a></li>
    </ul>
</div>
