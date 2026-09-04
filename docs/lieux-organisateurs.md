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

## Modifier une fiche de lieu

`lieu/edit.php` ajoute et modifie les fiches. Le traitement vit dans
[`LieuEdition`](../librairies/LieuEdition.php) ; comme pour l'organisateur, la page ne fait que
décider des droits, appeler le traitement, puis afficher.

### Ce que les deux formulaires partagent

[`FicheEdition`](../librairies/FicheEdition.php) est le socle commun aux deux : relecture de l'état
enregistré, garde-fous sur les champs réservés, cycle de vie des images, délégation des erreurs au
`Validateur`. Ses classes filles déclarent leur table, leur colonne d'identifiant, leurs champs
image et leurs colonnes à relire, puis n'écrivent plus que ce qui leur est propre — leur validation
et leur SQL d'écriture.

Le champ image lui-même — l'envoi, l'aperçu, la case « Supprimer » — est un seul gabarit,
[`_champ_image.inc.php`](../_champ_image.inc.php), inclus quatre fois entre les deux formulaires.

### Qui peut modifier

Une seule règle, dans `Authorization::isPersonneAllowedToEditLieu()` — le formulaire et le lien
« Modifier ce lieu » de la fiche publique l'interrogent tous deux :

- niveau `AUTHOR` (6) ou au-dessus, sur n'importe quelle fiche ;
- une personne affiliée au lieu (table `affiliation`) ;
- une personne membre d'un organisateur rattaché au lieu.

L'auteur de la fiche n'y figure pas, contrairement à la règle des organisateurs : c'est celle
qu'appliquait déjà le formulaire, et l'y ajouter ouvrirait des fiches à des comptes qui n'y ont pas
accès aujourd'hui. **Créer** un lieu reste réservé au niveau `AUTHOR` : une fiche de lieu est
partagée par tous les événements qui s'y déroulent, un doublon se paie donc cher.

Le lieu modifié est celui que désigne l'url, dont le droit vient d'être vérifié. Il arrivait par un
champ caché `idLieu`, si bien qu'une personne autorisée sur un lieu pouvait en modifier n'importe
quel autre.

Un refus répond **403**, une requête sans lieu désigné **400**, un identifiant inconnu **404**.

### Champs réservés aux éditeurs

Le nom, la préposition, les catégories et les organisateurs engagent tous les événements qui se
déroulent dans le lieu. En dessous du niveau `AUTHOR`, ils sont montrés en lecture seule, et le
formulaire renvoie vers le formulaire de contact.

Leur valeur enregistrée est reprise en base quel que soit le contenu du POST : ils partaient
jusqu'ici en champs cachés, donc modifiables par n'importe quel client.

### Longueurs des champs, statut, catégories

`Lieu::FIELDS` porte, comme `Organisateur::FIELDS`, le type, les bornes et le caractère obligatoire
de chaque champ ; le formulaire en tire ses `maxlength`, `LieuEdition` sa validation. Les quatre
`maxlength` écrits en dur contredisaient la validation — `nom` s'arrêtait à 60 caractères pour 80,
`adresse` à 80 pour 100, `URL` à 80 pour 250.

`Lieu::STATUTS` reprend les libellés des organisateurs (`actif` → Publié, `inactif` → Dépublié,
`ancien` → Ancien), et le choix n'est proposé qu'à partir du niveau `ADMIN` (4). `Lieu::CATEGORIES`
porte les neuf valeurs de la colonne `categories` (un `SET`), rendues par un Select2 multiple.

### Localité, quartier et région

Le `<select>` reste « Localité/quartier » : Genève est la seule localité à se subdiviser, et ses
quartiers voyagent dans une valeur composée « 44_Pâquis » que `LieuEdition` redécoupe. Retirer la
colonne `quartier`, envisagé par l'issue #117, supposait qu'elle ne serve plus — la base dit le
contraire : **47 lieux sur 109 en portent un**, et 355 événements avec eux (mesuré sur une copie de
production). Le quartier reste donc, et le libellé le nomme, comme dans les deux autres formulaires
qui portent ce select.

La région d'un lieu suit le canton de sa localité, et rien d'autre. Une exception codée en dur y
rattachait la localité **529** à Genève, commentée « Nyon, vaudoise mais rattachée à Genève » :
Nyon porte l'identifiant 513, et 529 est Oulens-sur-Lucens, à soixante kilomètres de là. La règle
ne s'appliquait donc pas là où elle était voulue.

Ce qu'elle cherchait à dire est déjà en base : `localite.regions_covered` porte `ge,vd` pour tout
le district de Nyon depuis la 3.6.3. C'est là-dessus qu'il faudra s'appuyer le jour où les listes
de lieux en tiendront compte — la clause qui l'exploiterait est en commentaire dans
`Lieu::getLieux()`.

### Coordonnées

Latitude et longitude passent par [`Coordinates`](../librairies/Utils/Coordinates.php), qui porte
leurs trois règles : la virgule décimale des pavés numériques européens est acceptée, les bornes du
globe sont vérifiées, et les deux vont ensemble ou pas du tout — le plan n'est affiché que si les
deux sont connues.

Une colonne à `NULL` (ou à `0`, comme avant la 3.12.0) se lit comme « pas de coordonnées » et rend
un champ vide. Une saisie non numérique est réaffichée telle quelle à côté de son message, jamais
écrite en base.

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
