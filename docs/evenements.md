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

## Fichiers : flyer et illustration

Le nom d'un fichier encode l'événement auquel il appartient : `{idEvenement}_{date}.{ext}` pour le flyer, `{idEvenement}_{date}_img.{ext}` pour l'illustration, et le même nom préfixé de `s_` pour la vignette.

Deux règles gouvernent ce nom :

- **l'identifiant vient de l'AUTO_INCREMENT**, jamais d'un `MAX(idEvenement) + 1` lu avant l'`INSERT`. Les deux divergent dès qu'un événement a été supprimé — l'AUTO_INCREMENT ne redescend jamais — et deux ajouts simultanés lisaient le même maximum, le second écrasant l'image du premier. À l'ajout, le nom ne peut donc être arrêté qu'après l'`INSERT` : les colonnes sont complétées par un `UPDATE` dans la foulée. En modification, l'identifiant est déjà connu ;
- **l'extension suit le format réel du fichier**, pas celle de son nom d'origine. `ImageDriver2` écrit d'après le contenu : un PNG envoyé sous le nom `affiche.jpg` produisait un `.jpg` contenant du PNG, que le serveur annonçait ensuite sous un type que le navigateur refuse.

### Archivage annuel

Chaque début janvier, les images de l'année écoulée sont déplacées dans `web/uploads/evenements/<année>/`. C'est ce que reflète `Evenement::getFilePath()`, qui ne préfixe l'année qu'une fois celle-ci **révolue** : une image de l'année en cours vit encore à la racine.

Les anciennes URL sont rattrapées par une redirection 301 dans [`htaccess/50-routage.conf`](../htaccess/50-routage.conf), **à étendre d'une année à chaque déplacement**. Elle couvre jpg, png, gif et webp — tout ce qu'`ImageDriver2` sait écrire ; le webp est devenu le format le plus fréquent depuis que les PDF sont convertis à l'envoi. Elle s'arrête délibérément à l'année précédente : inclure l'année en cours changerait les 404 de ses images en redirections vers un répertoire qui n'existe pas encore.

## Genres

Un `genre` absent de la configuration s'affiche comme « divers » via `Evenement::genreLabel()`, au lieu de faire échouer la page d'accueil.

## Lieu supprimé

Un événement qui référence un lieu supprimé n'interrompt plus les pages qui le listent : sa localisation retombe sur les champs texte libre stockés dans l'événement lui-même.
