# Favoris

Marquer un événement pour le retrouver, avec ou sans compte (#98, PR #143).

## Le marque-page

Un lien `fa-bookmark-o` (à marquer) / `fa-bookmark` (marqué), rendu par [`EvenementRenderer::favoriteButtonHtml()`](../librairies/EvenementRenderer.php), posé sur l'agenda, la fiche d'un événement, les listes d'une fiche de lieu, d'une fiche d'organisateur et de la recherche, et sur la page Favoris elle-même. Un clic bascule l'état sans recharger la page.

Un onglet **Favoris** figure dans le menu principal, entre Agenda et Lieux — l'icône seule en dessous de 800 px, pour que la barre tienne sur une ligne.

## Où vivent les favoris

| | Stockage | Portée |
| --- | --- | --- |
| Visiteur | `localStorage`, clé `ladecadanse_favorites` | ce navigateur, sur cet appareil |
| Connecté | table `personne_evenement` | tous ses appareils |

Un bandeau le dit au visiteur sur la page Favoris, avec un lien vers la connexion ; sa croix le referme pour de bon (`ladecadanse_favorites_banner_dismissed`).

**La première connexion verse les favoris du navigateur dans le compte** : `FavoritesStore.init()` les envoie à `sync`, dont l'`INSERT IGNORE` les fusionne avec ceux déjà en base sans créer de doublon, puis vide le stockage local et relit la liste du compte.

Pour un membre connecté, les identifiants de ses favoris sont **rendus avec la page**, dans `window.__LADECADANSE.favoriteIds` ([`_footer.inc.php`](../_footer.inc.php)) : aucune requête n'est nécessaire au chargement pour savoir quels marque-pages allumer. L'action `list` ne sert que de secours.

## Le filtre des listes

Sur l'agenda et sur les deux fiches, un onglet **Favoris** ne garde à l'écran que les favoris de la liste. Il se comporte comme un onglet de genre : cliqué, il se marque et porte une croix ; recliqué, il retire le filtre.

- Il **n'apparaît que s'il y a au moins un favori dans la liste affichée** : rien à filtrer, rien à proposer.
- Son état est mémorisé dans le navigateur (`ladecadanse_favorites_filter`), et non dans la session ni dans l'url comme le filtre de genres de l'agenda : il suit la personne d'une page à l'autre sans salir les adresses partagées.
- Filtrer masque aussi ce qui devient vide : les sections de genre de l'agenda et les lignes de mois des tableaux de fiche (`_syncGroupHeaders`).

## La page Favoris

Deux vues, que le menu de l'en-tête commute :

| Vue | Url | Ordre | Pagination |
| --- | --- | --- | --- |
| Prochains | `/favoris.php` (`?view=avenir`) | chronologique | aucune, 200 événements au plus |
| Passés | `/favoris.php?view=passes` | du plus récent au plus ancien | 50 par page |

Seuls les événements `actif` y figurent : un favori dépublié depuis disparaît de la liste sans être retiré du compte, et reparaît si l'événement est republié.

Les mois structurent la page. Sur ordinateur, une colonne de droite les liste et mène à chaque section. **En dessous de 800 px cette colonne est masquée** : une barre horizontale collante la remplace, en tête de liste, construite par le script à partir des en-têtes de mois déjà rendus — donc sans requête ni donnée de plus, pour un visiteur comme pour un membre. Le mois en cours y est marqué au départ ; le mois cliqué prend ce marquage, la barre n'en montrant jamais deux.

Un visiteur obtient la même page : le script envoie les identifiants de son navigateur (500 au plus) à l'action `events`, qui rend le HTML des événements correspondants.

## L'API `event/favorites.php`

Quatre actions, toutes en JSON.

| Action | Méthode | Qui | Jeton |
| --- | --- | --- | --- |
| `events` | POST | tout le monde | non |
| `list` | GET | connecté | non |
| `toggle` | POST | connecté | **oui** |
| `sync` | POST | connecté | **oui** |

Les deux actions qui écrivent exigent le jeton de session, comme les liens « Dépublier » et « Supprimer » d'un événement. Il est rendu dans `window.__LADECADANSE.csrfToken` et renvoyé en en-tête `X-CSRF-Token` — et non dans le corps, qui est du JSON : un formulaire ne sait pas poster `application/json`, et un en-tête ajouté par un script est hors de portée d'une requête inter-site, le préalable CORS n'étant pas accordé. Sans jeton, ou avec un jeton périmé, la réponse est **400** `invalid_token` ; sans session, **401**.

## Base de données

`personne_evenement` porte une ligne par couple (personne, événement) :

| Colonne | Rôle |
| --- | --- |
| `idPersonne`, `idEvenement` | clé primaire composite — c'est elle qui rend l'ajout idempotent, l'`INSERT IGNORE` s'appuyant dessus |
| `dateAjout` | horodatage, `DEFAULT CURRENT_TIMESTAMP` |

Un index sur `idEvenement` sert le sens inverse, compter ou lister qui a mis un événement en favori. Rien n'efface les lignes d'un événement supprimé : elles ne remontent simplement plus, la jointure ne trouvant pas l'événement.

La migration est `resources/database/migrations/Version20260926000000.php`, passée par `composer db:migrate` — voir [UPGRADE.md](../UPGRADE.md).
