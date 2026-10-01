# resources/database

`ladecadanse.sql` est le **point de départ** d'une base : le schéma de la 3.13.0, et la table
`localite` remplie. Il est **figé** — on ne le retouche plus. Tout changement de schéma passe par une
**migration**, une classe de `migrations/` que [Doctrine Migrations](https://www.doctrine-project.org/projects/migrations.html)
applique et consigne dans la table `doctrine_migration_versions` de chaque base.

Le [changelog](../../CHANGELOG.md) dit ce qui a changé dans une version, [UPGRADE.md](../../UPGRADE.md)
ce qu'il faut faire pour y passer, ce fichier-ci comment se servir des migrations.

## Commandes

À lancer depuis la racine du dépôt, sur le poste de développement : `doctrine/migrations` est une
dépendance de développement, rien de tout cela ne part sur le serveur.

```sh
composer db:status                        # ce qui est appliqué, ce qui manque
composer db:migrate                       # appliquer ce qui manque, dans l'ordre
composer db:migrate -- prev               # défaire la dernière, si elle a un down()
composer db:generate                      # créer une classe vide à remplir
```

`composer db:migrate -- --dry-run` affiche le SQL sans l'exécuter. Pour le détail migration par
migration : `php vendor/bin/doctrine-migrations list`.

La connexion vient de `app/db.config.php`, comme pour le site et `bin/prod-copy.php` (voir
`migrations-db.php`). L'entrée se choisit par la variable d'environnement `LADECADANSE_DB`, `default`
à défaut :

```sh
LADECADANSE_DB=prod composer db:status                  # Git Bash, tunnel SSH ouvert
$env:LADECADANSE_DB='prod'; composer db:status          # PowerShell
```

Une migration a besoin des droits `ALTER`, `CREATE`, `INDEX` et `DROP`, que le compte du site n'a pas
forcément. Les clés facultatives `migration_user` et `migration_password` d'une entrée lui substituent
un autre compte, pour les seules migrations — `root` en local, par exemple.

La production n'ouvre pas MySQL à l'extérieur : on l'atteint par le tunnel SSH décrit dans
[docs/prod-copy.md](../../docs/prod-copy.md), l'entrée `prod` visant `127.0.0.1;port=3307`. Faire un
`mysqldump` avant tout `db:migrate` sur la production (voir plus bas, « Pas de transaction »).

## Installation neuve

1. importer `ladecadanse.sql` ;
2. `composer db:migrate`.

Les migrations déjà portées par le dump le constatent et s'enregistrent sans rien exécuter ; seules
tournent celles qui lui sont postérieures.

## Base existante

`composer db:migrate`, quelle que soit sa version. La première fois, il **adopte** la base : chaque
migration d'avant Doctrine vérifie si son effet est déjà en place — la colonne, l'index, la table ou la
ligne qu'elle crée — et, si oui, s'enregistre sans rien exécuter. Doctrine l'accompagne d'un
avertissement « did not result in any SQL statements », attendu. Les suivantes ne consultent plus que
`doctrine_migration_versions`.

## Écrire une migration

`composer db:generate` crée `migrations/VersionAAAAMMJJHHMMSS.php` d'après `migration.tpl`. Puis :

- retirer le garde-fou `abortIf(true, …)` et écrire le SQL dans `up()`, une instruction par
  `$this->addSql('…')`. Le paramètre `$schema` ne sert pas : il n'y a pas de modèle d'entités, donc pas
  de `migrations:diff`, le SQL s'écrit à la main comme avant ;
- son inverse dans `down()`, quand il existe. Une migration qui ne se défait pas — elle déplace ou
  efface des données — le dit par `throwIrreversibleMigrationException()`, plutôt que par un inverse
  approximatif ;
- `getDescription()` en une ligne, et en tête de classe ce qu'il faut savoir avant de la passer en
  production : verrou sur une table MyISAM, ordre par rapport à la mise en ligne du code ;
- la décrire dans [UPGRADE.md](../../UPGRADE.md), sous la version en préparation.

Rien d'autre : ni report dans `ladecadanse.sql`, ni ligne à ajouter à un tableau.

Le garde-fou existe parce qu'une classe encore vide passée par `db:migrate` serait enregistrée comme
appliquée, et le SQL écrit ensuite ne tournerait jamais. Si c'est arrivé :
`php vendor/bin/doctrine-migrations version 'Ladecadanse\Migrations\VersionAAAAMMJJHHMMSS' --delete`.

Toutes les classes utilisent le trait `MigrationDefaults` : il désactive la transaction (voir
ci-dessous) et fournit `alreadyApplied()`, réservé aux migrations d'avant Doctrine. Une migration
nouvelle n'en a pas besoin — le registre suffit.

### Pas de transaction

Sur MariaDB, chaque instruction DDL (`ALTER`, `CREATE`…) valide implicitement la transaction en cours,
et la plupart des tables sont en MyISAM, qui n'en connaît pas. Une migration de trois `ALTER` dont le
troisième échoue laisse les deux premiers en place. Depuis la 10.6, un `ALTER` interrompu est annulé en
entier (DDL atomique), mais rien au-delà. D'où des migrations courtes, et un `mysqldump` avant la
production.

## Les migrations d'avant Doctrine

Jusqu'à la 3.13.0, les migrations étaient des scripts `.sql` passés à la main, et chacune devait être
reportée dans le dump : l'étape a manqué pendant quatre versions, et les bases créées entre-temps ont
tourné sans les index de recherche. Les quinze scripts sont devenus des classes, nommées d'après la
date de leur ajout, au SQL inchangé. [UPGRADE.md](../../UPGRADE.md) cite encore les anciens noms dans
les sections des versions publiées.

| Ancien script | Version | Classe | Effet |
| --- | --- | --- | --- |
| `v3-6-3_localite-add-regions_covered.sql` | 3.7.0 | `Version20250320000000` | colonne `localite.regions_covered`, et les communes du district de Nyon rattachées à `ge,vd` |
| `v3-6-3_personne-add-last_login.sql` | 3.7.0 | `Version20250321000000` | colonne `personne.last_login` |
| `v3-8-0-evenement-add-index.sql` | 3.8.0 | `Version20250628000000` | index composite `idx_ev_date_statut_genre_ajout` |
| `v3-9-0-evenement-add-fulltext-index.sql` | 3.9.0 | `Version20250726000000` | index FULLTEXT sur `evenement.titre`, `nomLieu`, `description` et sur `lieu.nom`, base de la recherche |
| `v3-9-2-evenement-add-index.sql` | 3.9.2 | `Version20251021000000` | index `idx_ev_idPersonne` |
| `v3-9-4-personne-mot-de-passe-255.sql.sql` | 3.9.4 | `Version20260307000000` | `personne.mot_de_passe` en `VARCHAR(255)`, pour les empreintes bcrypt |
| `v3-11-0_bot_monitor-create-table.sql` | 3.11.0 | `Version20260716000000` | table `bot_monitor` |
| `v3-11-0_lieu-lat-lng-decimal.sql` | 3.11.0 | `Version20260801000000` | `lieu.lat` et `lieu.lng` de `FLOAT(10,6)` à `DECIMAL(10,7)` |
| `v3-11-0_personne-add-settings.sql` | 3.11.0 | `Version20260808000000` | colonne `personne.settings` (préférences JSON) |
| `v3-12-0_localite-france.sql` | 3.12.0 | `Version20260825000000` | `localite.npa` en `VARCHAR(6)`, localité « Ailleurs en France », localité 1 renommée en canton `hs` |
| `v3-13-0_lieu-colonnes.sql` | 3.13.0 | `Version20260905000000` | `lieu.determinant` → `preposition_nom`, `lieu.categorie` → `categories`, `adresse` en `VARCHAR(255)`, colonnes facultatives à `NULL`, `photo2` et `actif` supprimées |
| `v3-13-0_lieu-categories.sql` | 3.13.0 | `Version20260908000000` | sept valeurs ajoutées à la fin du `SET` `lieu.categories` |
| `v3-13-0_lieu-organisateur-add-admin_note.sql` | 3.13.0 | `Version20260916000000` | colonnes `lieu.admin_note` et `organisateur.admin_note` |
| `v3-13-0_personne-evenement-create-table.sql` | 3.13.0 | `Version20260926000000` | table `personne_evenement`, les favoris personnels (#98) |
| `v3-13-0_evenement-purge-contact.sql` | 3.13.0 | `Version20260930000000` | `evenement.user_email` et `remarque` effacés sur les événements de plus de deux ans — données seules ; idempotente, elle tourne aussi sur une base neuve |

Trois pièges de lecture de l'ancien nommage : le préfixe est la version **visée** au moment de
l'écriture, pas toujours celle qui a livré le script (les deux `v3-6-3_*` sont partis avec la 3.7.0) ;
`v3-9-4-personne-mot-de-passe-255.sql.sql` portait deux fois son extension ; la 3.10.0 n'a pas de
migration.

Un écart connu entre le dump et les migrations : `Version20260307000000` donne à
`personne.mot_de_passe` un `DEFAULT ''` que `ladecadanse.sql` n'a pas. Il vient de l'ancien script,
repris tel quel.

## Scripts hors migration

Trois fichiers ne sont ni le schéma ni une migration, et n'ont rien à faire dans une installation :

- `evenement-fix-horaires.sql` — réparation ponctuelle des horaires faussés par le bug de copie corrigé
  en 3.5.0, passée en production le 26 juin 2023 ;
- `test-separateurs-horaires.sql` — jeu d'événements de test pour les séparateurs horaires de l'agenda
  (#105). Il **écrit dans `evenement`** : à réserver à une base de développement.
- `mailing-users-specialises.sql` — sélection des utilisateurs dont les ajouts se concentrent sur un
  seul lieu, une seule catégorie ou les mêmes organisateurs, pour un mailing par `admin/mailing.php`.
  Trois requêtes **en lecture seule**, passables telles quelles en production. Voir
  [Événements](../../docs/evenements.md#reporter-les-valeurs-récurrentes-dans-les-réglages).

## Docker

L'environnement Docker crée sa base depuis `ladecadanse.sql`, puis charge les fixtures de
`docker/env/` — le compte `admin` et un lieu de test. Ces scripts ne tournent qu'à la création du
volume ; les migrations se passent ensuite depuis l'hôte, avec une entrée de `app/db.config.php` visant
le port exposé par le service `db` (`'host' => '127.0.0.1;port=9906'`) — le conteneur `composer-dev`
n'a pas les extensions de l'application. Voir [Base de données](../../README.md#base-de-données) dans
le README.
