# Lieux et organisateurs

## Modifier une fiche d'organisateur

`organisateur/edit.php` ajoute et modifie les fiches. Le traitement vit dans
[`OrganisateurEdition`](../librairies/OrganisateurEdition.php), la page ne fait que décider des
droits, appeler le traitement, puis afficher.

### Qui peut modifier

Une seule règle, dans `Authorization::isPersonneAllowedToEditOrganisateur()` — le formulaire et le
lien « Modifier cet organisateur » de la fiche publique l'interrogent tous deux :

- niveau `AUTHOR` (6) ou au-dessus, sur n'importe quelle fiche ;
- niveau `ACTOR` (8), sur les fiches dont la personne est membre (`personne_organisateur`) ;
- l'auteur de la fiche (`organisateur.idPersonne`), quel que soit son niveau.

`idPersonne` n'est écrit qu'à la création : il désigne l'auteur, dont dépend ce droit, et une
modification ne le déplace pas vers celui qui l'a faite.

Un refus répond **403**, et une modification sans organisateur désigné **400** ; les deux se
rendent dans la page du site, avec son en-tête et son pied.

### Longueurs des champs

`Organisateur::FIELDS` porte, pour chaque champ, son type, ses bornes et son caractère
obligatoire. Le formulaire en tire ses `maxlength` et son `required`, `OrganisateurEdition` sa
validation : ce que la page laisse saisir est ce que le serveur accepte.

### Statut

Les trois valeurs de la colonne `statut` sont montrées sous les noms qu'elles décident réellement,
listés une fois pour toutes dans `Organisateur::STATUTS` :

| base      | affiché    |
|-----------|------------|
| `actif`   | Publié     |
| `inactif` | Dépublié   |
| `ancien`  | Ancien     |

Le choix n'est proposé qu'à partir du niveau `ADMIN` (4). Pour les autres, la valeur postée est
ignorée au profit de celle déjà en base — le formulaire portait un `statut=actif` caché, qui
republiait une fiche dépubliée dès qu'un acteur la modifiait.

### Logo et photo

Le nom d'un fichier est `{idOrganisateur}_{champ}.{extension}` : `12_logo.png`, `12_photo.webp`.
L'identifiant vient de l'AUTO_INCREMENT, donc n'est connu qu'après l'INSERT ; l'extension vient du
type MIME réel du fichier, parce que c'est d'après son contenu qu'`ImageDriver2` l'écrit. Le cycle
complet — nommage, remplacement, suppression, miniature `s_` — est partagé avec les fiches de lieu
par le trait [`HandlesImageUploads`](../librairies/HandlesImageUploads.php).

Le fichier déjà en place est relu en base, jamais repris d'un champ caché du formulaire.

## Activité mensuelle, en vue d'administration

Les listes `lieu/lieux.php` et `organisateur/organisateurs.php` affichent, pour les éditeurs et au-dessus, **douze colonnes** — une par mois, la dernière étant le mois en cours. Chaque cellule porte le nombre d'événements ajoutés ce mois-là, et en dessous, en bleu, celui du même mois un an plus tôt.

La date du dernier événement ne dit ni le rythme d'un lieu ni le moment où il a cessé d'annoncer. Douze colonnes en donnent la silhouette, et le rappel de l'année précédente évite de lire comme un décrochage ce qui n'est qu'une saison creuse : un lieu qui n'annonce rien en juillet n'a peut-être jamais rien annoncé en juillet.

### Ce qui est compté

- **des gestes d'ajout**, datés par `evenement.dateAjout`. Qu'un événement ait été dépublié ensuite n'y change rien : c'est l'activité de la personne qu'on mesure, pas l'état de la fiche ;
- **par des membres seulement** — les ajouts de l'équipe (groupe ≤ `AUTHOR`) mesureraient le travail des administrateurs et non celui du lieu ;
- **par des comptes actifs** — le statut filtré est celui de la personne, pas celui de l'événement ;
- les propositions sans auteur (`idPersonne = 0`) sont écartées par la jointure.

### Lecture

- un mois sans ajout laisse sa cellule vide plutôt que d'afficher « 0 » : douze zéros brouilleraient la silhouette qu'on cherche ;
- un rappel à zéro n'affiche rien non plus, mais le saut de ligne est écrit dans tous les cas — sans lui les cellules n'auraient pas la même hauteur et les compteurs cesseraient de se lire sur une ligne de base commune ;
- les mois révolus sont atténués, le mois en cours est en gras ;
- l'année n'apparaît qu'en infobulle de l'en-tête : la fenêtre de douze mois chevauche deux années, et l'écrire dans chaque colonne mangerait la place que ces colonnes étroites n'ont pas.

### Portée

Une seule requête par page ([`Stats\MonthlyAddedEvents`](../librairies/Stats/MonthlyAddedEvents.php)), qui couvre vingt-quatre mois pour que chaque colonne affichée trouve son antécédent — les douze colonnes, elles, ne bougent pas. Le rendu des cellules est isolé dans `HtmlShrink`.

Les deux pages passent en pleine largeur en desktop, la colonne de gauche disparaissant. Les colonnes mensuelles sont masquées sous 800 px.
