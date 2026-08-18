<?php

/*
 * Menu de tri des événements passés d'une fiche : lieu/lieu.php et organisateur/organisateur.php.
 *
 * Attend de l'appelant :
 *  - $get                          tableau des paramètres validés, dont 'periode' et 'page'
 *  - $tab_ordre_evenements_passes  app/config.php
 *
 * Le partiel ne connaît ni idL ni idO : les deux pages nomment leur tableau de paramètres $get,
 * urlQueryArrayToString() reconduit donc l'identifiant quel qu'il soit.
 *
 * Les liens omettent 'page' à dessein. Le sens de tri change la page qui porte les événements les
 * plus récents (la dernière en ascendant, la première en descendant) ; la laisser tomber rend la
 * main à $default_page, qui atterrit sur les plus récents dans les deux sens.
 */

?>
<div id="order_navigation">
    <ul>
        <?php foreach ($tab_ordre_evenements_passes as $ordre_cle => $ordre) : ?>
            <li><a href="?<?= \Ladecadanse\HtmlShrink::urlQueryArrayToString($get, ['ordre', 'page']) ?>&amp;ordre=<?= $ordre_cle ?>"<?php if ($_SESSION['user_prefs_past_events_order'] == $ordre_cle) : ?> class="selected"<?php endif; ?> title="<?= sanitizeForHtml($ordre['titre']) ?>" aria-label="<?= sanitizeForHtml($ordre['titre']) ?>" rel="nofollow"><i class="fa <?= $ordre['icone'] ?>" aria-hidden="true"></i></a></li>
        <?php endforeach; ?>
    </ul>
</div>
