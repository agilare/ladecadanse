# La décadanse
📅 Agenda culturel local

> [!WARNING]
> En raison d'une grande partie de code legacy, et pour des raisons de sécurité, ne déployez pas cette application sur des serveurs publics. La [modernisation est en cours](https://github.com/users/agilare/projects/4/views/1), vous pouvez [contribuer](README.md#contribuer)

La décadanse est un site web qui présente aux visiteurs une sélection d'événements culturels locaux et accessibles. Il est actuellement [déployé pour Genève et les environs](https://www.ladecadanse.ch/)

![La décadanse - page d'accueil](./web/interface/ladecadanse-home-example.png)

Les organisateurs d'événements ont la possibilité de s'inscrire puis annoncer leurs événements et enfin se présenter.

Les principales sections du site sont :
- 📅 un **agenda d'événements**, chacun de ceux-ci ayant sa fiche détaillée accompagnée de quelques petits services (signaler une erreur, partager...)
- 📍 un répertoire des **Lieux** où se déroulent des événements, avec détails, présentation, photos
- 🎤 un répertoire des **Organisateurs d'événements**, similaire aux Lieux
- 🛠️ un **back-office** permettant de gérer les diverses entités du site : utilisateurs, événements, lieux, organisateurs, etc.

## Installation locale

Ces instructions vous permettront de mettre en place une copie du projet sur votre machine locale à des fins de développement et de test. Voir [déploiement](README.md#déploiement) pour des notes sur la façon de déployer le projet sur un système actif.

### Installation sans Docker

#### Prérequis
- Apache 2.4
- PHP 8.4, avec les extensions exigées par `composer.json` et ses dépendances : `composer check-platform-reqs` les liste et signale celles qui manquent (`--no-dev` pour s'en tenir à ce que le site demande en production). `composer install` refuse de toute façon de s'exécuter tant qu'il en manque une
- [Composer](https://getcomposer.org/)
- [Node.js](https://nodejs.org/) 20.19+, 22.13+ ou 24+, avec npm — pour les [bibliothèques front-end](#bibliothèques-front-end), le lint et les tests JavaScript
- MariaDB 10.11 (si possible avec `innodb_ft_min_token_size=3` et `ft_min_word_len=3`, pour de meilleurs résultats dans la recherche d'événements)

> [!NOTE]
> **Facultatif** : `imagick` et Ghostscript, pour convertir en image les PDF **collés en URL** dans le formulaire d'événement. Sans eux le site fonctionne normalement, et les PDF **envoyés en fichier** sont convertis de toute façon — c'est le navigateur qui s'en charge. L'ensemble est désactivé par défaut : voir [Accepter les PDF dans les champs image](#accepter-les-pdf-dans-les-champs-image).

#### Étapes
1. cloner la branche `master`
1. `composer install`
1. `npm ci`, qui installe les bibliothèques front-end et les copie dans `web/libs/` — voir [Bibliothèques front-end](#bibliothèques-front-end)
1. base de données
    1. créer une base de données avec `COLLATE 'utf8mb4_unicode_ci'` par ex.
        ```mysql
        CREATE DATABASE `ladecadanse` /*!40100 COLLATE 'utf8mb4_unicode_ci' */;
        ```
    1. créer un utilisateur avec les droits suffisants sur cette base de données, par ex.
        ```mysql
        CREATE USER 'ladecadanse'@'localhost' IDENTIFIED BY 'my-password';
        GRANT USAGE ON *.* TO 'ladecadanse'@'localhost';
        GRANT SELECT, INSERT, DELETE, UPDATE  ON `ladecadanse`.* TO 'ladecadanse'@'localhost';
        ```
    1. dans la base de données, exécuter `resources/database/ladecadanse.sql`, qui crée la structure et remplit la table `localite`. Ce dump est figé à la 3.13.0 : les changements de schéma postérieurs s'appliquent à l'étape `composer db:migrate` ci-dessous
    1. ajouter un 1er utilisateur, l'*admin* (groupe 1) qui vous servira à gérer le site (mot de passe : `admin_dev`) :
        ```mysql
        INSERT INTO `personne` (`idPersonne`, `pseudo`, `mot_de_passe`, `cookie`, `groupe`, `statut`, `affiliation`, `region`, `email`,  `signature`, `avec_affiliation`, `gds`, `actif`, `dateAjout`, `date_derniere_modif`) VALUES (NULL, 'admin', '$2y$10$34Z0QxaycAgPFQGtiVzPbeoZFN1kwLEdWDEBI1kEOJGK4A3xRJtMa', '', '1', 'actif', '', 'ge', 'test@ladecadanse.ch', 'pseudo', 'non', '', '1', '0000-00-00 00:00:00.000000', '0000-00-00 00:00:00.000000');
        ```
1. créer vos fichiers de configuration en faisant `cp app/env_model.php app/env.php` ainsi que `cp app/db.config_model.php app/db.config.php` et y saisir les valeurs de votre environnement (davantage d'explications et exemples se trouvent dans les fichiers même), avec au minimum les informations de connexion à la base de données
1. `composer db:migrate` applique à la base les migrations que le dump ne porte pas encore, et enregistre les autres — voir [resources/database/README.md](resources/database/README.md). Il se connecte avec l'entrée `default` de `app/db.config.php` ; si son compte n'a pas les droits `ALTER`, `CREATE`, `INDEX` et `DROP`, lui en substituer un autre par `migration_user` et `migration_password`
1. `composer config:build` compose le `.htaccess` et le `.user.ini` (configuration Apache et PHP) à partir des fragments de `htaccess/` et `userini/` — voir [docs/config-serveur.md](docs/config-serveur.md). Sans passer par Composer : `php bin/build-config.php`

### Installation avec Docker

Une configuration Docker est fournie pour exécuter le site en environnement local ou en production. Seul Docker est requis sur l'hôte, avec Compose v2 (`docker compose`) : ni PHP, ni Composer, ni Node.

```sh
docker compose --profile dev up -d --build     # Construire si besoin et démarrer (localhost:7777)
docker compose --profile dev logs -f web-dev   # Logs d'Apache et de PHP
docker compose --profile dev down              # Arrêter
```

Pour la production, remplacer `dev` par `prod` et `web-dev` par `web-prod` (localhost:8080). Le mot de passe par défaut de l'utilisateur `admin` est `admin_dev`.

> [!NOTE]
> Les conteneurs à usage unique `composer-dev` (ou `composer-prod`) et `npm`, qui préparent `vendor/` et `web/libs/` avant qu'Apache ne démarre, apparaissent `Exited` : c'est le fonctionnement normal, pas un échec.

> [!IMPORTANT]
> Avant de déployer en production, configurer les valeurs sensibles dans `docker/env/env.php` (clés API, identifiants SMTP, etc.).

La base est initialisée depuis `resources/database/ladecadanse.sql` et les fixtures de `docker/env/`, à la **création du volume** seulement. Les migrations se passent ensuite depuis l'hôte, avec `composer db:migrate` (voir [docs/docker.md](docs/docker.md#base-de-données)).

Le reste est dans [docs/docker.md](docs/docker.md) : variables d'environnement, permissions sur un hôte Linux, commandes Make, Composer et configuration Apache dans le conteneur.

### Bibliothèques front-end

Les bibliothèques servies au navigateur depuis le site même (jQuery, Leaflet, Font Awesome, select2, pdf.js…) sont déclarées à version exacte dans les `dependencies` de `package.json`. Elles ne sont pas versionnées : `npm ci` les télécharge, puis son hook `postinstall` copie dans `web/libs/` les seuls fichiers que les pages chargent (`bin/libs-sync.mjs`). Aucune étape de build.

Mettre à jour une bibliothèque :

```sh
npm outdated                                # ce qui a du retard
npm install --save-exact select2@4.1.1      # met à jour package.json, package-lock.json et web/libs/
```

> [!NOTE]
> `npm ci` avertit `EBADENGINE` sous Node 22 (select2) : sans conséquence.

Ajouter une bibliothèque, ou suivre le déplacement d'un fichier par une nouvelle version : [docs/bibliotheques-front-end.md](docs/bibliotheques-front-end.md).

### Peupler la base depuis la production

Une base neuve est vide, et saisir à la main de quoi éprouver l'agenda est vite décourageant. `composer prod-copy` fabrique une copie locale **anonymisée** de la production, réduite aux derniers événements ajoutés et à tout ce qu'ils référencent, et rapatrie les flyers et photos correspondants dans `web/uploads/`.

```sh
composer prod-copy -- --limit=1000
```

> [!NOTE]
> Le prérequis est un **accès SSH** au serveur, qui sert deux fois : l'offre Infomaniak n'ouvrant pas MySQL à l'extérieur, la base se lit à travers un tunnel, et les quelque 2700 fichiers descendent par la même voie en une minute. La mise en place, la procédure d'essai, les vérifications et ce que l'anonymisation remplace exactement sont dans [docs/prod-copy.md](docs/prod-copy.md).

### Accepter les PDF dans les champs image

Désactivé par défaut. Le drapeau `PDF_CONVERSION_ENABLED` d'`app/env.php` prend trois valeurs :

```php
define("PDF_CONVERSION_ENABLED", false);       // personne
define("PDF_CONVERSION_ENABLED", 'preview');   // administrateurs seulement
define("PDF_CONVERSION_ENABLED", true);        // tout le monde
```

Les PDF **envoyés en fichier** sont convertis par le navigateur (pdf.js), ceux **collés en URL** par le serveur, avec `imagick` et Ghostscript (déjà dans l'image Docker). Sans eux le site fonctionne normalement. Mécanisme, installation sous Windows/Laragon et dépannage : [docs/conversion-pdf.md](docs/conversion-pdf.md).

### Usage
Une fois le site fonctionnel, se connecter avec le login *admin* (créé ci-dessus) permet d'ajouter et modifier des événements, lieux, etc. (partie publique) et de les gérer (partie back-office)

### Raccourcis clavier

Le site se parcourt au clavier : `h` accueil, `s` recherche, `a` ajouter un événement, `l` lieux, `o` organisateurs, `d` dashboard admin ; flèches gauche/droite pour changer de jour ou de page, `j`/`k` pour passer d'une entité à l'autre dans une liste, `/` pour filtrer. Les admins disposent en plus d'un mode *mouseless* (`?mouseless=1`) pour apprendre ces touches. Le détail est dans [docs/interface.md](docs/interface.md#raccourcis-clavier).

## Tests

See [tests/README.md](tests/README.md)

## Déploiement

### Prérequis
Un espace sur un serveur avec l'infrastructure prérequise, une timezone définie et une base de données

### Avec Git-ftp

#### Prérequis
1. sur le poste qui déploie, l'[installation locale](#installation-sans-docker) complète, Node et npm compris : c'est lui qui prépare `web/libs/`. Le serveur, lui, n'a besoin ni de Node ni de npm
1. installer [git-ftp](https://github.com/git-ftp/git-ftp/blob/master/INSTALL.md)
1. dans le répertoire du projet, configurer les données de connexion (ici avec un scope pour le site de production : `prod`) :
    ```sh
    $ git config git-ftp.prod.user mon-login
    $ git config git-ftp.prod.url "ftp://le-serveur.ch/web"
    $ git config git-ftp.prod.password 'le-mot-de-passe'
    ```

> [!NOTE]
> Pour voir sa config git ftp : `git config -l | grep git-ftp`

#### Pour mettre en place
1. premier envoi des fichiers
    ```sh
    $ git ftp init -s prod
    ```
1. dans `app/env.php` [configurer le site  selon l'environnement](README.md#manuelle)

#### Pour mettre à jour avec les derniers commits

> [!IMPORTANT]
> Avant de lancer la commande ci-dessous, il peut falloir migrer la base de données de production : `composer deploy` ne s'en charge pas : voir [Migrer la base de données de production](#migrer-la-base-de-données-de-production).

```sh
$ composer deploy -- --scope=prod
```

`composer deploy` compose le `.htaccess` à partir de ses fragments, reconstruit `web/libs/` par `npm ci`, puis lance `git ftp push`. Le scope est obligatoire : s'il manque, le script liste ceux qui sont configurés.

> [!WARNING]
> Le `.htaccess` est envoyé tel qu'il est sur le disque : c'est pourquoi `composer deploy` le recompose d'abord. Pour cela, et pour les fragments d'exploitation (dépôt privé, `--ops-dir`), `web/libs/` et `git ftp push` seul, voir [docs/deploiement.md](docs/deploiement.md).

#### Migrer la base de données de production

🗄️ `composer deploy` ne met pas la base à jour : les migrations se passent depuis le poste, par le tunnel SSH de [docs/prod-copy.md](docs/prod-copy.md), quand `resources/database/migrations/` a de nouvelles classes depuis le dernier déploiement :

```sh
$ LADECADANSE_DB=prod composer db:status    # ce qui manque en production
$ mysqldump …                               # sauvegarde : aucune transaction ne protège un échec en cours de route
$ LADECADANSE_DB=prod composer db:migrate   # demande confirmation avant d'écrire
```

> [!TIP]
> **Quand migrer ?** Par défaut **avant** `composer deploy` : une colonne ou une table en plus ne gêne pas l'ancien code, alors que le nouveau code sur une base non migrée répond une erreur SQL. Exception : une migration qui supprime ou renomme ce que l'ancien code lit encore se passe juste **après**. [UPGRADE.md](UPGRADE.md) indique l'ordre pour chaque version ; détails dans [docs/deploiement.md](docs/deploiement.md#migrer-la-base-de-données-de-production).

#### Après le déploiement

Une requête suffit à vérifier que le `.htaccess` est arrivé et qu'il est valide :

```sh
$ curl -I https://www.ladecadanse.ch/lausanne
```

- ✅ **301** vers `/index.php?region=vd` : le compte est bon
- ❌ **500** : une directive est refusée par le serveur
- ❌ **404** : le fichier n'est pas arrivé

## Analyse du code

Des analyseurs de code PHP, configurés pour la version de PHP requise, s'exécutent via Composer :

```sh
$ composer phpstan          # analyse statique
$ composer psalm            # idem, doit rester vert
$ composer psalm:taint      # données utilisateur atteignant un point sensible (SQL, include, en-têtes…)
$ composer rector:dry-run   # aperçu des modernisations possibles, sans modifier les fichiers
$ composer sniffer:php84    # compatibilité avec PHP 8.4 (PHPCompatibility)
```

S'y ajoutent Phan (`./vendor/bin/phan`) et Rector Jack (`./vendor/bin/jack list`, dépendances Composer obsolètes). Les baselines, le bruit attendu de l'analyse de teinte et les autres détails sont dans [docs/analyse-statique.md](docs/analyse-statique.md).

## Changelog
Voir le [changelog](CHANGELOG.md) et les [releases sur GitHub](https://github.com/agilare/ladecadanse/releases)

Pour passer à une nouvelle version (migrations de base de données, nouvelles clés de configuration, effets de bord), voir [UPGRADE.md](UPGRADE.md).

## Documentation

Le fonctionnement des parties du site qui demandent plus qu'une ligne de changelog est documenté dans [docs/](docs/) :

- [agenda](docs/agenda.md)
- [événements](docs/evenements.md) et leur [administration](docs/admin-evenements.md)
- [interface](docs/interface.md), raccourcis clavier compris
- [flux RSS](docs/rss.md)
- [suivi des bots](docs/bots.md)
- [configuration serveur](docs/config-serveur.md) et [déploiement](docs/deploiement.md)
- [Docker](docs/docker.md)
- [bibliothèques front-end](docs/bibliotheques-front-end.md)
- [conversion des PDF](docs/conversion-pdf.md)
- [analyse statique](docs/analyse-statique.md)

## Contribuer

Le projet accepte volontiers de l'aide ; il y a diverses manières de contribuer comme améliorer la sécurité et la qualité du site, tester des fonctionnalités, etc.
Les [lignes directrices pour les contributions](CONTRIBUTING.md) décrivent en détail l'état actuel du projet, les possibilités d'aide et comment le faire.

## Contact
Michel Gaudry - ✉️ michel@ladecadanse.ch

[GitHub La décadanse](https://github.com/agilare/ladecadanse)

## Licence
This work is licensed under AGPL-3.0-or-later

The rejected password list `resources/bad_p.txt` comes from
[tarraschk/richelieu](https://github.com/tarraschk/richelieu) (most common French passwords),
licensed under CC BY 4.0.
