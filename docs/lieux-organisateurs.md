# Lieux et organisateurs

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
