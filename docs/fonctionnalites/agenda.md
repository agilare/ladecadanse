# Agenda

## Filtre par catégorie

Au-dessus de la liste du jour, un menu collant (`#genre_tab_navigation`) filtre les événements par catégorie (#107, PR #144). Il n'offre que les catégories qui ont des événements ce jour-là dans la région, et n'apparaît pas quand la journée est vide. Le choix passe dans l'url (`?genre_tab=`) et se retient en session (`user_prefs_agenda_genre`) : revenir d'une fiche d'événement retrouve la catégorie choisie. L'onglet actif sert à retirer le filtre.

Un filtre retenu que la liste ne propose plus — drapeau de préversion rétrogradé, session d'administrateur survivant à sa déconnexion — retombe sur « tous » : son onglet n'étant plus rendu, rien ne permettrait de le retirer.

Réservé aux administrateurs en 3.12.0, le filtre est servi à tous depuis la 3.13.0.

## Repères de temporalité

Sur la liste du jour, chaque carte d'événement dit où il en est par rapport à **l'instant du chargement de la page** (#51) :

| État | Repère | Ce qu'il faut pour l'établir |
| --- | --- | --- |
| à venir, dans deux heures ou moins | compte à rebours seul — `dans 40min`, `dans 1h30`, `dans 2h` | un horaire de début |
| à venir, plus tard | rien : l'horaire suffit | — |
| en cours | une barre de la part écoulée, pourcentage inscrit au milieu | un horaire de début |
| terminé | la même barre, pleine et grise, portant « Terminé » | un horaire de début, ou une fin annoncée |

Sans horaire exploitable — ni début, ni fin —, la carte n'affiche rien : tout est porté par [`Ladecadanse\EvenementTimeStatus`](../../librairies/EvenementTimeStatus.php), dont la fabrique rend `null` dans ce cas.

Les deux repères ne cohabitent jamais : avant le début, le compte à rebours seul, une barre vide n'apprenant rien de plus ; une fois l'événement commencé, la barre seule.

L'**horizon** du compte à rebours est `EvenementRenderer::COUNTDOWN_VISIBLE_WITHIN_MINUTES`, deux heures : au-delà, savoir qu'un concert est « dans 7h » n'aide personne à décider, et une liste du soir s'encombre d'autant de repères qu'elle porte d'événements. En deçà de `COUNTDOWN_URGENT_UNDER_MINUTES`, une demi-heure, le compte à rebours passe **en gras** — c'est le moment de partir. Ces deux seuils se lisent sur `EvenementTimeStatus::$minutesUntilStart`, le temps restant non arrondi que la classe porte à côté du libellé, qui l'est.

### Où cela s'affiche

Dans le bloc « pratique », dont les deux colonnes se partagent la largeur à parts égales (49 % chacune) et se lisent en lignes :

```
➤ rue de la Chapelle 12 - Genève                20:30 – 23:00
▤ 15.- / 12.-                                 [    35 %    ] ?
```

À droite, l'horaire d'abord, puis le repère sur la ligne suivante. À gauche, **le prix est rangé sous l'adresse**, chacun précédé de son icône, `fa-location-arrow` et `fa-money` : quatre cellules d'une grille à deux colonnes, les icônes alignées entre elles et les textes entre eux, une adresse qui passe à la ligne restant dans sa colonne plutôt que de revenir sous l'icône.

Cette mise en page du bloc « pratique » **ne dépend ni de la date ni du drapeau** : elle est celle de toutes les cartes d'événement du site, agenda d'un jour passé compris, et aussi des pages qui en rendent (copie d'un événement, signalement, favoris). Seuls les repères de temporalité sont réservés à la journée du jour.

Au-delà de **40 caractères**, le prix est coupé au mot près et suivi de `(...)` — `30.- les soirée ; 50.- journée complète (...)` —, et rendu entier dans l'infobulle ; la constante est `EvenementRenderer::PRICE_MAX_CHARS`.

### La barre

- **Sa longueur dit la durée** : 20 px par heure (`EvenementRenderer::PROGRESS_PX_PER_HOUR`), fin estimée comprise. Deux bornes : une largeur minimale qui laisse au texte la place de s'inscrire — en deçà d'environ 1h20, la barre est donc un peu plus longue que la durée, et « Terminé », plus long qu'un pourcentage, en demande davantage encore (`.even-time-bar-over`) —, et la largeur de la colonne, qu'elle ne dépasse pas : au-delà de huit à dix heures sur ordinateur selon la largeur d'écran, de six sur téléphone, elle cesse d'être proportionnelle.
- **Son remplissage dit la part écoulée**, en `#FACA1A`, sur une piste plus soutenue que le fond du bloc ; hauteur `1em`, arrondi de 3 px. Le pourcentage est inscrit au milieu.
- **Elle ne s'anime pas.** Les rayures mobiles des itérations précédentes ont été retirées : une liste du soir en portait des dizaines à la fois.

Le `<progress>` porte le rôle ARIA `progressbar` et un nom accessible — « En cours, 35 % écoulés » ; le texte inscrit au milieu est un calque décoratif posé par-dessus, que ce nom répète.

### Ce que les repères arrondissent

- **Compte à rebours** — pas de dix minutes, avec un plancher à `dans 10min` pour ne jamais afficher `dans 0min` ; **au-delà de trois heures**, pas d'une heure. Ce dernier palier ne se voit plus guère depuis que l'horizon est passé à deux heures : il ne sert que la zone entre 2h et 3h, où le pas de dix minutes s'applique encore. Le seuil est la constante `COUNTDOWN_HOUR_ROUNDING_FROM_MINUTES`.
- **Part écoulée** — arrondie à 5 %, puis **bornée à [5 %, 95 %]** tant que l'événement est en cours : `0 %` et `100 %` se liraient comme « pas commencé » et « terminé ». Une fois terminé, la barre affiche justement 100 %.

### Fin inconnue : une durée estimée

Les horaires de fin manquent souvent (#65). Plutôt que de ne rien dire, la durée est alors estimée selon la catégorie, et un bouton `?` (`fa-question-circle`) suit la barre pour que l'estimation ne passe pas pour une mesure :

- **ciné, théâtre** : deux heures après le début (`SEANCE_ESTIMATED_DURATION_MINUTES`) ;
- **fêtes, concerts, divers** — et, faute de règle propre, expos et cours : **jusqu'à minuit**, celui qui clôt la soirée de la journée d'agenda. Commencé après ce minuit, un événement court jusqu'à la fin de la journée d'agenda, 06:00, plutôt que jusqu'au minuit suivant.

**Cette fin estimée dépassée, l'événement est dit terminé**, au même titre qu'une fin annoncée. Les itérations précédentes s'y refusaient — une estimation ne prouve rien —, mais le résultat était pire : une soirée sans horaire de fin, lue à 2h du matin, restait « en cours » indéfiniment. Le `?` reste affiché, qui dit sur quoi repose la conclusion.

Le bouton ouvre une bulle — « Fin inconnue, estimée à 00:00 » — au survol, au focus et à la touche, là où une infobulle native (`title`) ne s'ouvre pas au doigt. Une seule bulle sert toute la liste : `index.php` la pose une fois, le texte voyage dans le `data-estimate` de chaque bouton, et `TimeEstimate` (dans [`web/js/global.js`](../../web/js/global.js)) la remplit et la place sous le bouton à chaque ouverture. Quelques points méritent d'être connus :

- **bouton, pas lien** : il n'emmène nulle part, et une infobulle native ne suffisait pas. Son `aria-label` porte le même texte que la bulle, si bien qu'aucune information ne dépend de son ouverture ;
- **le survol n'est branché que là où l'on pointe** (`matchMedia('(hover: hover)')`) : sur un écran tactile, le survol émulé ouvrirait la bulle juste avant que le clic émulé ne la referme ;
- **la bulle se recale dans la fenêtre** : le bouton est collé au bord droit de la colonne. Elle porte aussi `width:max-content`, sans quoi sa largeur se recalculerait sur la place restant à sa droite et la tasserait en colonne ;
- **API Popover native**, avec le même repli par classes CSS que le popover d'info-lieu (Safari < 17) ; le repli refait à la main la fermeture au clic extérieur et à Échap ;
- **la zone tactile fait 24 px de côté** (WCAG 2.5.8) par un `::after` invisible : l'icône, elle, ne mesure que 9 px dans cette colonne, et rien autour d'elle n'est poussé.

### Hors d'atteinte

Deux situations y mènent :

- l'événement est **terminé** ;
- c'est une séance de **ciné ou de théâtre commencée depuis plus d'une heure** : la salle est noire, la porte fermée. Une soirée, elle, se rejoint à toute heure et ne sort jamais d'atteinte tant qu'elle dure. Les genres concernés et le délai sont les constantes `GENRES_SEANCE` et `TOO_LATE_AFTER_START_MINUTES`.

Les deux font pâlir **l'horaire, et lui seul** (opacité 0,4) : le temps qu'il annonçait n'a plus cours. La carte, elle, reste franche — son affiche et son titre font la page.

La barre, en revanche, distingue les deux cas :

- **terminé** : elle est **pleine et grise**, et porte « Terminé » à la place de la part écoulée — le temps alloué à l'événement est consommé ;
- **séance commencée de trop** : elle garde sa part écoulée et sa couleur. La projection dure encore ; dire « Terminé » serait faux. C'est l'horaire pâli qui signale qu'on n'y entre plus.

La catégorie est lue dans la colonne `e_genre` des lignes de l'agenda, que `index.php` sélectionne deux fois : `PDO::FETCH_GROUP` consomme la première colonne pour grouper la liste par catégorie, et la retire des lignes. Sans la seconde, `e_genre` vaut `null` sans la moindre erreur, et les deux règles propres aux séances — durée estimée et pâleur — s'éteignent en silence.

### Horaires douteux

Deux garde-fous, parce que les horaires stockés ne sont pas tous cohérents :

- une **fin antérieure au début** est ignorée, comme si la fin n'était pas renseignée : elle ne dit rien de la temporalité, et l'événement bascule sur la durée estimée ;
- la **date portée par un horaire**, si elle n'est ni celle de l'événement ni le lendemain, est ramenée au jour de l'événement plutôt que prise au mot ([`DateHelper::horaireInstant()`](../../librairies/Utils/DateHelper.php)). Le lendemain est admis parce qu'une journée d'agenda va de `06:00:01` à `06:00:00` le jour suivant — une soirée qui finit à 02:00 appartient encore au jour de l'événement.

La sentinelle qui marque « sans horaire » est traitée comme une absence d'horaire, au même titre qu'une valeur vide.

### Portée

**La journée du jour seulement.** Sur une journée passée, toutes les cartes diraient « Terminé » ; sur une journée à venir, aucune n'aurait de repère, le compte à rebours s'arrêtant à deux heures.

**L'agenda seulement** : la recherche, les pages lieu et organisateur et les tableaux d'administration n'affichent pas de repère. La mise en page du bloc « pratique », elle, est partout la même.

### Activation

Le drapeau `EVENT_TIME_STATUS_ENABLED` d'`app/env.php` est à `true` : les repères sont **publics** depuis la 3.14.0, après deux versions de préversion. Il prend les trois états de [`Ladecadanse\FeatureFlag`](../../librairies/FeatureFlag.php) — `false`, `'preview'` (administrateurs seulement), `true` —, et reste en place pour pouvoir rétrograder sans déployer. En préversion, une ligne au-dessus de la liste le signale et rappelle l'heure à laquelle tout est mesuré.

`app/env.php` n'étant pas versionné, la valeur de production se change à la main sur le serveur.

Le mécanisme des drapeaux à trois états est décrit dans la [conversion des PDF](../exploitation/conversion-pdf.md#le-drapeau), à propos du premier d'entre eux.
