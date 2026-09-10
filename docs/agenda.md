# Agenda

## Repères de temporalité

Sur la liste du jour, chaque carte d'événement dit où il en est par rapport à **l'instant du chargement de la page** (#51) :

| État | Repère | Ce qu'il faut pour l'établir |
| --- | --- | --- |
| à venir | compte à rebours — `dans 40min`, `dans 2h30`, `dans 5h`, `dans 3j` | un horaire de début |
| en cours | une barre `<progress>` de la part écoulée | un horaire de début |
| terminé | `(terminé)` | un horaire de fin |

Sans horaire de début exploitable, la carte n'affiche rien : tout est porté par [`Ladecadanse\EvenementTimeStatus`](../librairies/EvenementTimeStatus.php), dont la fabrique rend `null` dans ce cas.

### Où cela s'affiche

Dans la colonne de droite du bloc « pratique », qui se lit en lignes :

```
20:30 – 23:00 (terminé)
[▓▓▓▓░░░░░]?
15.- / 12.-
```

L'horaire d'abord ; « terminé » le qualifie et reste sur sa ligne, entre parenthèses ; le compte à rebours et la barre prennent la ligne suivante ; le prix vient en dernier, sur sa propre ligne — là où, sans les repères, il suit l'horaire après une virgule.

### Ce que les repères arrondissent

- **Compte à rebours** — pas de dix minutes, avec un plancher à `dans 10min` pour ne jamais afficher `dans 0min` ; **au-delà de trois heures**, pas d'une heure (`dans 5h`, plus de minutes) ; au-delà de la journée, `dans 3j`. Un compte à rebours de la forme `dans 3h10` n'est donc pas atteignable : au-delà de 3h le pas est l'heure. Le seuil est la constante `COUNTDOWN_HOUR_ROUNDING_FROM_MINUTES`.
- **Part écoulée** — arrondie à 5 %, puis **bornée à [5 %, 95 %]** : `0 %` et `100 %` se liraient comme « pas commencé » et « terminé » alors que l'événement est justement en cours. Le pourcentage n'est plus écrit : il passe dans le nom accessible et l'infobulle de la barre, qui porte le rôle ARIA `progressbar`.

### Fin inconnue : une barre estimée

Les horaires de fin manquent souvent (#65). Plutôt que de ne rien dire, la barre d'un événement commencé court alors **jusqu'à minuit** — le premier minuit qui suit son début — et un `?` la suit pour que l'estimation ne passe pas pour une mesure. Minuit passé, la barre plafonne à 95 % : on ne sait toujours pas que c'est fini.

### Hors d'atteinte

Une carte hors d'atteinte est atténuée (opacité 0,4) et reprend sa pleine opacité au survol ou dès qu'un de ses liens prend le focus. Le repère qui explique cette pâleur, lui, reste net : l'opacité d'un parent ne se rattrapant pas sur un enfant, elle est posée bloc par bloc plutôt que sur la carte.

Deux situations y mènent :

- l'événement est **terminé** ;
- c'est une séance de **ciné ou de théâtre commencée depuis plus de trente minutes** : la salle est noire, la porte fermée. Une soirée, elle, se rejoint à toute heure et ne pâlit jamais tant qu'elle dure. Les genres concernés et le délai sont les constantes `GENRES_SEANCE` et `TOO_LATE_AFTER_START_MINUTES`.

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
