<?php if (!isFavoritesEnabled()) { return; } ?>
<div id="favoris_filter_navigation" hidden>
    <ul>
        <li><a href="#" class="js-favoris-filter" data-filter="tous">Tous</a></li>
        <li><a href="#" class="js-favoris-filter" data-filter="favoris"><i class="fa fa-heart" aria-hidden="true"></i>&nbsp;Favoris&nbsp;<span class="js-favoris-count"></span></a></li>
    </ul>
</div>
