# Docker

Une configuration Docker est fournie pour exécuter le site en environnement local ou en production. Le [README](../README.md#installation-avec-docker) donne le démarrage rapide ; ce fichier-ci détaille le reste.

Seul Docker est requis sur l'hôte, avec Compose v2 (`docker compose`) : ni PHP, ni Composer, ni Node. À chaque démarrage, deux conteneurs à usage unique préparent le code monté avant qu'Apache ne démarre :

- `composer-dev` (ou `composer-prod`) installe `vendor/` ;
- `npm` lance `npm ci`, qui installe les [bibliothèques front-end](bibliotheques-front-end.md) et les copie dans `web/libs/`. Son `node_modules/` vit dans un volume Docker, pas dans celui de l'hôte, qui reste libre pour `npm run lint` et `npm test`.

> [!NOTE]
> Les voir `Exited` après le démarrage est le fonctionnement normal, pas un échec.

## Configuration des environnements

Le projet utilise un fichier unique `docker/env/env.php` pour tous les environnements. Les paramètres spécifiques à l'environnement (développement ou production) sont définis via des variables d'environnement Docker :

- **Développement** : `APP_ENV=dev` et `APP_DEBUG=true`
- **Production** : `APP_ENV=prod` et `APP_DEBUG=false`

Ces variables sont automatiquement configurées dans `docker-compose.yml` selon le profil Docker utilisé.

> [!IMPORTANT]
> Avant de déployer en production, assurez-vous de configurer les valeurs sensibles dans `docker/env/env.php` (clés API, identifiants SMTP, etc.).

## Permissions sur les répertoires inscriptibles (hôtes Linux)

Le code source est monté dans le conteneur depuis l'hôte. Sur un hôte Linux, les fichiers appartiennent à votre utilisateur alors qu'Apache écrit en `www-data` : sans alignement, l'application ne peut écrire ni les logs (`var/logs`) ni les images téléversées (`web/uploads`).

Créez un fichier `.env` à la racine du projet, lu automatiquement par Docker Compose :

```sh
printf 'UID=%s\nGID=%s\n' "$(id -u)" "$(id -g)" > .env
```

Puis reconstruisez l'image : `docker compose --profile dev build --no-cache`.

> [!TIP]
> Sous Docker Desktop (Windows et macOS) cette étape est inutile : les montages sont déjà permissifs et les valeurs par défaut conviennent.

Dans tous les cas, l'entrypoint du conteneur (`docker/php/docker-entrypoint.sh`) crée au démarrage les répertoires inscriptibles manquants et corrige leurs permissions si nécessaire.

## Utilisation

Les commandes passent par `docker compose` avec un profil, `dev` ou `prod`, depuis la racine du projet :

```sh
docker compose --profile dev up -d --build     # Construire si besoin et démarrer (localhost:7777)
docker compose --profile dev ps -a             # Statut des services, conteneurs à usage unique compris
docker compose --profile dev logs -f web-dev   # Logs d'Apache et de PHP
docker compose --profile dev exec web-dev bash # Shell dans le conteneur web
docker compose --profile dev down              # Arrêter
```

Pour la production, remplacer `dev` par `prod` et `web-dev` par `web-prod` (localhost:8080).

Réinstaller les dépendances sans redémarrer, après un `git pull` qui touche `composer.lock` ou `package-lock.json` :

```sh
docker compose --profile dev run --rm composer-dev   # vendor/
docker compose --profile dev run --rm npm            # web/libs/
```

## Avec Make

Le `Makefile` enveloppe ces mêmes commandes, pour qui a `make` (Linux, macOS, WSL ; il n'est pas fourni sous Windows). Toutes les cibles acceptent `PROFILE=dev` ou `PROFILE=prod`, `dev` par défaut :

```sh
make help                   # Afficher toutes les commandes disponibles
make build [PROFILE=...]    # Construire les images Docker, sans cache
make start [PROFILE=...]    # Démarrer les services
make stop [PROFILE=...]     # Arrêter les services
make restart [PROFILE=...]  # Redémarrer les services
make logs [PROFILE=...]     # Afficher les logs (mode suivi)
make shell [PROFILE=...]    # Ouvrir un shell dans le conteneur web
make status [PROFILE=...]   # Afficher le statut des services
make clean [PROFILE=...]    # Nettoyer l'environnement (conteneurs, images, volumes)
make install-deps [PROFILE=...]     # Installer les dépendances PHP et les bibliothèques front-end
make composer-update [PROFILE=...]  # Mettre à jour les dépendances Composer
make composer-require PACKAGE=...   # Ajouter un package Composer
```

## Composer et configuration Apache

Composer est installé dans le conteneur web de développement : `docker compose --profile dev exec web-dev bash`, puis `composer phpstan`, `composer test:api`, `composer config:build`, etc. Ces scripts tournent ainsi sur le PHP 8.4 de l'application et ses extensions.

Le service `composer-dev`, lui, a son propre PHP, sans les extensions de l'application, d'où son `--ignore-platform-reqs`.

Sans `.htaccess`, le site répond déjà, mais sans ses redirections ni ses règles de sécurité. `composer config:build`, lancé dans le conteneur, le compose comme en production — voir [config-serveur.md](config-serveur.md) ; l'image active pour cela `mod_rewrite` et `mod_headers`.

## Base de données

La base est initialisée depuis `resources/database/ladecadanse.sql`, puis par les fixtures de `docker/env/` : le compte `admin` et un lieu de test. Ces scripts ne tournent qu'à la **création du volume**.

Les migrations se passent ensuite depuis l'hôte, que la base soit neuve ou déjà créée : le conteneur `composer-dev` n'a pas les extensions de l'application, et le service `db` expose MariaDB sur le port 9906. Ajouter à `app/db.config.php` une entrée qui le vise, par exemple `'docker' => ['host' => '127.0.0.1;port=9906', 'dbname' => 'ladecadanse', 'user' => 'root', 'password' => 'dev']`, puis :

```sh
LADECADANSE_DB=docker composer db:migrate
```

Voir [resources/database/README.md](../resources/database/README.md).

Pour repartir d'une base neuve : `docker compose --profile dev down -v`, qui supprime les volumes, puis `docker compose --profile dev up -d`.

Le site est servi sur localhost:7777 (dev) ou localhost:8080 (prod). Le mot de passe par défaut de l'utilisateur `admin` est `admin_dev`.
