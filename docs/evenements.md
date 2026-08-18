# Événements

## Archivage des événements passés

Un événement passé est une **archive en lecture seule** : son formulaire d'édition et tous les boutons « Éditer » disparaissent, et sa suppression est refusée.

Ce qui reste possible :

- **Copier** — la bonne façon de reprogrammer un événement ancien ;
- **Dépublier**.

Avant ce verrouillage, un ancien événement pouvait être recyclé en un nouveau, ce qui écrasait silencieusement l'original : l'événement passé disparaissait de l'historique du site en changeant de date.

Les utilisateurs de **groupe strictement inférieur à 6** (éditeurs, admins, superadmins) conservent l'accès complet.

Dans la page utilisateur, les lignes archivées sont grisées, et repassent en pleine opacité au survol.

### Quand un événement est-il « passé » ?

Un événement est considéré comme passé une fois que **`06:00:00` a sonné le lendemain de `dateEvenement`** — la journée d'agenda du site ne s'arrêtant pas à minuit. Si l'heure de fin de l'événement est plus tardive, c'est elle qui fait foi.

## Valeurs par défaut personnelles à l'ajout

Chaque utilisateur peut définir, dans son profil (fieldset « Événements »), les valeurs qu'il retrouvera pré-remplies en ajoutant un événement :

- catégorie ;
- heure de début et heure de fin ;
- lieu ;
- organisateur(s) ;
- prix.

Règles d'application :

- **à la création seulement** — l'édition d'un événement existant n'est pas concernée ;
- les paramètres d'URL `?idL=` et `?idO=` restent prioritaires sur les valeurs par défaut ;
- un défaut pointant vers un lieu ou un organisateur désactivé depuis est simplement ignoré ;
- le formulaire « Ajouter un événement » affiche une note discrète renvoyant vers les réglages.

### Stockage

Les préférences sont stockées dans la colonne `personne.settings`, un champ texte contenant du JSON, sous la clé `events` > `new_defaults`. Les préférences ajoutées ultérieurement iront dans le même champ, sans nouvelle migration de schéma.

## Genres

Un `genre` absent de la configuration s'affiche comme « divers » via `Evenement::genreLabel()`, au lieu de faire échouer la page d'accueil.

## Lieu supprimé

Un événement qui référence un lieu supprimé n'interrompt plus les pages qui le listent : sa localisation retombe sur les champs texte libre stockés dans l'événement lui-même.
