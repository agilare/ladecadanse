# Agenda

## Repères de temporalité

Sur la liste du jour, chaque carte d'événement dit où il en est par rapport à **l'instant du chargement de la page** (#51) :

| État | Libellé | Ce qu'il faut pour l'établir |
| --- | --- | --- |
| à venir | compte à rebours — `-45min`, `-2h30`, `-3j` | un horaire de début |
| en cours | part écoulée — `40 %` | le début **et** la fin |
| terminé | `terminé` | un horaire de fin |

Une carte « terminé » est en outre atténuée, et reprend sa pleine opacité au survol ou dès qu'un de ses liens prend le focus.

Hors de ces trois cas, la carte n'affiche rien. C'est notamment la situation d'un événement commencé dont on ignore la fin — et les horaires manquants sont fréquents (#65) : mieux vaut ne rien dire qu'inventer une fin de soirée. Tout est porté par [`Ladecadanse\EvenementTimeStatus`](../librairies/EvenementTimeStatus.php), dont la fabrique rend `null` dans ce cas.

### Ce que les libellés arrondissent

- **Compte à rebours** — minutes arrondies vers le haut, pour ne jamais afficher `-0min` ; au-delà de l'heure, `-2h30` (les minutes rondes sont omises : `-2h`) ; au-delà de la journée, `-3j`.
- **Part écoulée** — arrondie à 10 %, puis **bornée à [10 %, 90 %]** : `0 %` et `100 %` se liraient comme « pas commencé » et « terminé » alors que l'événement est justement en cours.

### Horaires douteux

Deux garde-fous, parce que les horaires stockés ne sont pas tous cohérents :

- une **fin antérieure au début** est ignorée, comme si la fin n'était pas renseignée : elle ne dit rien de la temporalité ;
- la **date portée par un horaire**, si elle n'est ni celle de l'événement ni le lendemain, est ramenée au jour de l'événement plutôt que prise au mot ([`DateHelper::horaireInstant()`](../librairies/Utils/DateHelper.php)). Le lendemain est admis parce qu'une journée d'agenda va de `06:00:01` à `06:00:00` le jour suivant — une soirée qui finit à 02:00 appartient encore au jour de l'événement.

La sentinelle qui marque « sans horaire » est traitée comme une absence d'horaire, au même titre qu'une valeur vide.

### Portée

**La journée du jour seulement.** Sur une journée passée, toutes les cartes diraient « terminé » et pâliraient d'un bloc ; sur une journée à venir, elles compteraient en jours.

**L'agenda seulement**, pour l'instant : la recherche, les pages lieu et organisateur et les tableaux d'administration sont inchangés.

### Activation

Le drapeau `EVENT_TIME_STATUS_ENABLED` d'`app/env.php` est à `false` par défaut. Il prend les trois états de [`Ladecadanse\FeatureFlag`](../librairies/FeatureFlag.php) — `false`, `'preview'` (administrateurs seulement), `true`. En préversion, une ligne au-dessus de la liste le signale et rappelle l'heure à laquelle tout est mesuré : sans quoi une préversion s'oublie, et l'on croit la fonctionnalité livrée.

Le mécanisme des drapeaux à trois états est décrit dans le [README](../README.md#accepter-les-pdf-dans-les-champs-image), à propos du premier d'entre eux.
