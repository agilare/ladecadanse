<?php
/**
 * Bloc de texte repliable, utilisé par les descriptions et présentations de lieu et
 * d'organisateur.
 *
 * Variable requise : $texteRepliableHtml (string) - le HTML à afficher, déjà prêt à être rendu
 * Variable requise : $texteRepliableId (string)   - suffixe d'identifiant unique dans la page
 *
 * L'état replié est posé ici, côté serveur, et non par du JS après le rendu : c'est tout
 * l'intérêt du bloc. Le texte ne s'affiche donc jamais en entier avant de se raccourcir sous
 * les yeux du visiteur, comme le faisait la librairie read-smore. Même partage des rôles que
 * les descriptions de l'accueil (EvenementRenderer) : le serveur décide, le CSS plafonne la
 * hauteur, le JS ne fait que basculer et corriger.
 *
 * Le contenu n'est pas tronqué : c'est du HTML riche produit par TinyMCE, le couper en PHP
 * laisserait des balises ouvertes. Seule sa hauteur est plafonnée, en CSS.
 *
 * Le seuil ci-dessous est une estimation, pas une mesure : PHP ignore la largeur de la colonne
 * et la taille de police, donc le nombre de lignes réellement rendues. Il évite seulement de
 * poser une bascule sur un texte manifestement court ; global.js corrige les deux erreurs
 * possibles une fois la page rendue.
 */

// ~55 caractères par ligne dans une colonne de 372px, pour un repli à ~10 lignes.
// Les entités sont décodées avant le comptage : sur du texte échappé, une apostrophe
// (&#039;) pèserait 6 caractères et une esperluette 5 - même piège que Text::truncateWords().
$texteRepliableSeuil = 550;
$texteRepliableTexteBrut = html_entity_decode(strip_tags((string) $texteRepliableHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$texteRepliableEstLong = mb_strlen(trim($texteRepliableTexteBrut)) > $texteRepliableSeuil;
$texteRepliableDomId = 'texte-repliable-' . $texteRepliableId;
?>
<?php if (!$texteRepliableEstLong) : ?>

    <?= $texteRepliableHtml ?>

<?php else : ?>

    <div class="texte-repliable js-texte-repliable" id="<?= sanitizeForHtml($texteRepliableDomId) ?>">
        <?php // conteneur interne : il garde sa hauteur naturelle quand le parent est replié,
              // c'est lui que le JS mesure et observe ?>
        <div class="texte-repliable__contenu"><?= $texteRepliableHtml ?></div>
    </div>
    <?php // la bascule reste hors du bloc à overflow:hidden, comme le lien « Lire la suite »
          // des cartes d'événement reste hors du paragraphe clampé ?>
    <button type="button" class="texte-repliable__bascule js-texte-repliable-bascule" aria-expanded="false" aria-controls="<?= sanitizeForHtml($texteRepliableDomId) ?>">
        <span class="texte-repliable__label">Lire la suite</span>
        <i class="fa fa-chevron-down" aria-hidden="true"></i>
    </button>

<?php endif; ?>
