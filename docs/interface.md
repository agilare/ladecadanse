# Interface

Notes sur l'habillage du site : feuilles de style, icônes, et les mises en page qui demandent plus qu'une ligne de changelog. Les raccourcis clavier et le mode *mouseless* sont documentés dans le [README](../README.md#raccourcis-clavier).

## Feuilles de style

`_header.inc.php` charge, dans cet ordre : `normalize.css`, `imprimer.css` (en `media="print"`), `global.css`, les feuilles annoncées par `$extra_css`, puis celle qui porte le nom de la page (`web/css/{$nom_page}.css`) si elle existe.

L'ordre compte : la feuille de la page arrive en dernier, elle peut donc redéfinir ce que les précédentes posent. Jusqu'en 3.12.0 elle passait avant les supplémentaires, et ses règles restaient sans effet.

Viennent ensuite les deux feuilles de gabarit, choisies par l'attribut `media` plutôt que par des media queries internes :

| Feuille | Condition |
| --- | --- |
| `web/css/desktop.css` | `screen and (min-width:800px)` |
| `web/css/mobile.css` | `screen and (max-width:799.98px)` |

Les deux bornes ne doivent pas se recouvrir. À exactement 800 px les deux feuilles s'appliquaient : `mobile.css` masquait le formulaire de recherche et `desktop.css` la loupe censée le remplacer, ne laissant aucun moyen de chercher. D'où le `.98`.

Un seul palier interne subsiste, à 450 px, pour la fiche d'un événement (voir plus bas).

## Icônes

Les icônes du site étaient des PNG de 16 px du jeu **famfamfam Silk**, des années 2000. Elles sont passées en glyphes **Font Awesome 4.7** (`vendor/fortawesome/font-awesome`, chargé par `_header.inc.php`) #151, et `web/interface/icons/` est tombé de 1469 fichiers — ~5 Mo, dont 49 seulement étaient référencés — à quatre, gardés parce que leur dessin n'a pas d'équivalent Font Awesome qui nous ait convaincus : `page_white_edit.png` (Éditer), `page_white_copy.png` (Copier), `calendar_delete.png` (Dépublier) et `map.png` (Plan).

Contrepartie assumée : ces quatre-là restent des bitmaps de 16 px, ils ne suivent donc pas la taille du texte.

Les icônes réutilisées d'une page à l'autre sont déclarées en un seul endroit, dans [`app/config.php`](../app/config.php) : `$iconeEditer`, `$iconeCopier`, `$iconeSupprimer`, `$iconePrecedent`, `$iconeSuivant` et le tableau `$icone`. Ce sont des fragments HTML échoés tels quels, et **une icône seule ne porte pas de nom accessible** : le lien qui la contient doit fournir un `title` ou un `aria-label` quand aucun texte ne l'accompagne.

Le reste de la conversion :

- les règles d'icônes dont la classe n'était émise nulle part (`.popup_gmaps`, `.icalendar`, `.probleme`, `.complete`, `.selection li.descendre`) ont été supprimées plutôt que converties ;
- la bannière « événement copié » retrouve la coche que sa feuille de style pointait à un mauvais chemin depuis des années ;
- les chevrons de la pagination deviennent `fa-long-arrow-left` / `-right`, sur `div.pagination #prec::before` et `#suiv::after` ;
- dans les menus d'action, l'icône passe **à l'intérieur** du lien au lieu de le précéder : les `background-image` de `.action_editer`, `.action_copier` et `.action_depublier` sont posées sur le `<a>` et non sur le `<li>`, pour que l'icône reste dans la zone cliquable.

## Fiche d'un événement

### Mise en page élargie

La fiche occupe la colonne de droite, vide sur cette page. Largeur du cadre, des illustrations et de la description, taille du titre et du nom du lieu, bouton « précédent » : tout est piloté par la classe `.vevent-experimental`, posée par [`event/evenement.php`](../event/evenement.php) et définie dans `desktop.css` — revenir en arrière tient donc au retrait d'une classe. Réservée aux administrateurs en 3.11.0, le temps de valider l'essai, elle est servie à tous les visiteurs depuis la 3.12.0.

### Sous 450 px, la description enveloppe les illustrations

À cette largeur la colonne de droite ne fait plus que ~260 px : la description s'y étirait en lignes courtes pendant que l'espace sous l'image restait vide. Le texte enveloppe donc le flottant, et les lignes passées sous l'image reprennent toute la largeur — la page y gagne une hauteur notable. Les hauteurs minimales des deux blocs sont abandonnées à ce palier, et un événement sans illustration ne réserve plus une colonne vide de 120 px.

Le palier est en fin de section « evenement » dans `mobile.css`, sous la même classe `.vevent-experimental`.

## Accueil sous 800 px

Les deux blocs de bas de page — partenaires, derniers événements ajoutés — cessent d'être des colonnes latérales et s'empilent sous la liste du jour, en reprenant la gouttière que `<main>` tient déjà. Ils restent flottants : dé-flotter une seule des trois colonnes la ferait passer sous `#contenu`, qui flotte et fait toute la hauteur de la liste.

Les partenaires reçoivent le fond gris arrondi utilisé ailleurs sur le site, et leurs cinq logos deviennent une rangée flex centrée. C'étaient des `inline-block` alignés sur la ligne de base, donc décalés verticalement les uns par rapport aux autres et ferrés à gauche sur deux colonnes de largeurs inégales.

## Formulaires et barres d'action en mobile

- **Formulaire d'événement** — les champs prennent toute la largeur, Statut et Catégorie s'empilent, et l'aide « Plusieurs dates… » passe à côté du champ de date.
- **Navigation du jour, en agenda** — les liens affichent un texte plutôt que la date, avec des flèches à la place des chevrons.
- **Barre d'actions d'un événement** — les libellés se répartissaient sur plusieurs lignes ferrées à droite, actions publiques et actions réservées aux personnes connectées partageant un même flux. Chaque groupe forme désormais sa propre liste, empilée et calée à gauche, et les icônes — images de 16 px comme glyphes Font Awesome — sont centrées sur leur libellé. « Envoyer à un ami » y prend un glyphe Font Awesome.
- **Menu d'actions d'un lieu** — il était simplement masqué.
- **Bandeaux de message** — l'icône chevauchait le texte, et la coche de « événement copié » ne se posait pas sur la première ligne.
