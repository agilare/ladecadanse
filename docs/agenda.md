# Agenda

## Repères de temporalité

Sur la liste du jour, chaque carte d'événement dit où il en est par rapport à **l'instant du chargement de la page** (#51) :

| État | Repère | Ce qu'il faut pour l'établir |
| --- | --- | --- |
| à venir | compte à rebours — `dans 40min`, `dans 2h30`, `dans 5h`, `dans 3j` —, suivi d'une barre vide | un horaire de début |
| en cours | une barre de la part écoulée, pourcentage inscrit au milieu | un horaire de début |
| terminé | `(terminé)` | un horaire de fin |

Sans horaire de début exploitable, la carte n'affiche rien : tout est porté par [`Ladecadanse\EvenementTimeStatus`](../librairies/EvenementTimeStatus.php), dont la fabrique rend `null` dans ce cas.

### Où cela s'affiche

Dans le bloc « pratique », qui se lit en lignes des deux côtés :

```
rue de la Chapelle 12 - Genève                  20:30 – 23:00
15.- / 12.-                     dans 2h [      0 %      ]
```

À droite, l'horaire d'abord ; « terminé » le qualifie et reste sur sa ligne, entre parenthèses ; le compte à rebours et la barre prennent la ligne suivante — la barre à la suite du compte à rebours, et dessous quand la colonne est trop étroite pour les deux.

À gauche, **le prix passe sous l'adresse**, là où, sans les repères, il suit l'horaire après une virgule. Au-delà de **40 caractères**, il est coupé au mot près et suivi de `(...)` — `30.- les soirée ; 50.- journée complète (...)` —, et rendu entier dans l'infobulle ; la constante est `EvenementRenderer::PRICE_MAX_CHARS`.

### La barre

- **Sa longueur dit la durée** : 20 px par heure (`EvenementRenderer::PROGRESS_PX_PER_HOUR`), fin estimée comprise. Deux bornes : une largeur minimale qui laisse au pourcentage la place de s'inscrire — en deçà d'environ 1h20, la barre est donc un peu plus longue que la durée —, et la largeur de la colonne, qu'elle ne dépasse pas : au-delà de huit à dix heures sur ordinateur selon la largeur d'écran, de six sur téléphone, elle cesse d'être proportionnelle.
- **Son remplissage dit la part écoulée**, en `#FACA1A`, sur une piste plus soutenue que le fond du bloc ; hauteur `0.8em`, arrondi de 2 px. Le pourcentage est inscrit au milieu.
- **Elle s'anime** : des rayures défilent sur la part remplie **pendant quatre secondes**, puis s'immobilisent. Un mouvement de plus de cinq secondes, lancé sans qu'on le demande et sans moyen de l'arrêter, enfreindrait le critère WCAG 2.2.2 — et une liste du soir en porte des dizaines. L'animation est supprimée sous `prefers-reduced-motion`. Avant le début, rien n'est rempli, donc rien ne bouge.

Le `<progress>` porte le rôle ARIA `progressbar` et un nom accessible — « En cours, 35 % écoulés » ; le pourcentage inscrit et les rayures sont des calques décoratifs posés par-dessus. Les rayures ne sont pas dessinées par le pseudo-élément de remplissage, dont l'animation n'est pas fiable (Firefox l'ignore, [Bugzilla 812442](https://bugzilla.mozilla.org/show_bug.cgi?id=812442)). La barre d'un événement à venir, vide, n'apprend rien que le compte à rebours et l'horaire ne disent déjà : elle est masquée aux technologies d'assistance.

### Ce que les repères arrondissent

- **Compte à rebours** — pas de dix minutes, avec un plancher à `dans 10min` pour ne jamais afficher `dans 0min` ; **au-delà de trois heures**, pas d'une heure (`dans 5h`, plus de minutes) ; au-delà de la journée, `dans 3j`. Un compte à rebours de la forme `dans 3h10` n'est donc pas atteignable : au-delà de 3h le pas est l'heure. Le seuil est la constante `COUNTDOWN_HOUR_ROUNDING_FROM_MINUTES`.
- **Part écoulée** — arrondie à 5 %, puis **bornée à [5 %, 95 %]** tant que l'événement est en cours : `0 %` et `100 %` se liraient comme « pas commencé » et « terminé ». Avant le début, la barre affiche justement `0 %`.

### Fin inconnue : une durée estimée

Les horaires de fin manquent souvent (#65). Plutôt que de ne rien dire, la durée est alors estimée selon la catégorie, et un `?` suit la barre pour que l'estimation ne passe pas pour une mesure — son infobulle dit à quelle heure la fin a été fixée :

- **ciné, théâtre** : deux heures après le début (`SEANCE_ESTIMATED_DURATION_MINUTES`) ;
- **fêtes, concerts, divers** — et, faute de règle propre, expos et cours : **jusqu'à minuit**, celui qui clôt la soirée de la journée d'agenda. Commencé après ce minuit, un événement court jusqu'à la fin de la journée d'agenda, 06:00, plutôt que jusqu'au minuit suivant.

L'estimation dépassée, la barre plafonne à 95 % : seule une fin réelle dit « terminé ».

### Hors d'atteinte

Une carte hors d'atteinte est atténuée (opacité 0,4) et reprend sa pleine opacité au survol ou dès qu'un de ses liens prend le focus. Le repère qui explique cette pâleur, lui, reste net : l'opacité d'un parent ne se rattrapant pas sur un enfant, elle est posée bloc par bloc plutôt que sur la carte.

Deux situations y mènent :

- l'événement est **terminé** ;
- c'est une séance de **ciné ou de théâtre commencée depuis plus d'une heure** : la salle est noire, la porte fermée. La carte pâlit mais garde sa barre, et ne dit pas « terminé » — la séance dure encore. Une soirée, elle, se rejoint à toute heure et ne pâlit jamais tant qu'elle dure. Les genres concernés et le délai sont les constantes `GENRES_SEANCE` et `TOO_LATE_AFTER_START_MINUTES`.

La catégorie est lue dans la colonne `e_genre` des lignes de l'agenda, que `index.php` sélectionne deux fois : `PDO::FETCH_GROUP` consomme la première colonne pour grouper la liste par catégorie, et la retire des lignes. Sans la seconde, `e_genre` vaut `null` sans la moindre erreur, et les deux règles propres aux séances — durée estimée et pâleur — s'éteignent en silence.

### Horaires douteux

Deux garde-fous, parce que les horaires stockés ne sont pas tous cohérents :

- une **fin antérieure au début** est ignorée, comme si la fin n'était pas renseignée : elle ne dit rien de la temporalité, et l'événement commencé bascule sur la barre estimée ;
- la **date portée par un horaire**, si elle n'est ni celle de l'événement ni le lendemain, est ramenée au jour de l'événement plutôt que prise au mot ([`DateHelper::horaireInstant()`](../librairies/Utils/DateHelper.php)). Le lendemain est admis parce qu'une journée d'agenda va de `06:00:01` à `06:00:00` le jour suivant — une soirée qui finit à 02:00 appartient encore au jour de l'événement.

La sentinelle qui marque « sans horaire » est traitée comme une absence d'horaire, au même titre qu'une valeur vide.

### Portée

**La journée du jour seulement.** Sur une journée passée, toutes les cartes diraient « terminé » et pâliraient d'un bloc ; sur une journée à venir, elles compteraient en jours.

**L'agenda seulement**, pour l'instant : la recherche, les pages lieu et organisateur et les tableaux d'administration sont inchangés.

### Activation

Le drapeau `EVENT_TIME_STATUS_ENABLED` d'`app/env.php` est à `false` par défaut. Il prend les trois états de [`Ladecadanse\FeatureFlag`](../librairies/FeatureFlag.php) — `false`, `'preview'` (administrateurs seulement), `true`. En préversion, une ligne au-dessus de la liste le signale et rappelle l'heure à laquelle tout est mesuré : sans quoi une préversion s'oublie, et l'on croit la fonctionnalité livrée.

Le mécanisme des drapeaux à trois états est décrit dans le [README](../README.md#accepter-les-pdf-dans-les-champs-image), à propos du premier d'entre eux.
