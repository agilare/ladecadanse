# Déploiement

Le [README](../README.md#déploiement) donne la procédure courante avec git-ftp ; ce fichier-ci détaille ce que fait `composer deploy` et la migration de la base de production. La vérification après déploiement (`curl -I`) est dans le README.

## Ce que fait `composer deploy`

```sh
$ composer deploy -- --scope=prod
```

`composer deploy` compose le `.htaccess` à partir de ses fragments, reconstruit `web/libs/` par `npm ci`, puis lance `git ftp push`.

`web/libs/` n'est pas versionné mais git-ftp l'envoie quand même, en entier, chaque fois que `package-lock.json` ou `bin/libs-sync.mjs` a changé depuis le dernier déploiement (`.git-ftp-include`) ; les autres déploiements ne le renvoient pas. `composer install`, à passer par SSH sur le serveur quand `composer.lock` a changé, ne concerne que les dépendances PHP. Voir [bibliothèques front-end](bibliotheques-front-end.md).

Le scope n'a pas de valeur par défaut : quand plusieurs serveurs sont configurés, choisir pour vous reviendrait à parier sur la bonne machine. Le script les liste et s'arrête. Si un seul est configuré, il est retenu sans rien préciser.

> [!WARNING]
> L'enchaînement n'est pas cosmétique. Le `.htaccess` est ignoré par git — pour que les règles propres à l'exploitation (adresses bannies, robots) ne deviennent pas publiques — mais git-ftp l'envoie quand même, grâce à `!.htaccess` dans `.git-ftp-include`. Ce mécanisme envoie **le fichier présent sur le disque** : sans recomposition préalable, un essai local oublié partirait en production. Voir [config-serveur.md](config-serveur.md).

Les fragments d'exploitation vivent dans un dépôt privé annexe. `composer deploy` refuse de partir s'il ne les trouve pas, plutôt que de déployer une production sans ses blocages. Leur emplacement se surcharge au besoin :

```sh
$ composer deploy -- --scope=prod --ops-dir=/chemin/vers/htaccess
```

Pour ne pousser que le code, sans toucher au `.htaccess` :

```sh
$ git ftp push -s prod
```

## Migrer la base de données de production

Les migrations ne partent pas sur le serveur : elles se passent depuis le poste, par le tunnel SSH de [prod-copy.md](prod-copy.md), quand `resources/database/migrations/` a de nouvelles classes depuis le dernier déploiement.

```sh
$ LADECADANSE_DB=prod composer db:status    # ce qui manque en production
$ mysqldump …                               # sauvegarde : aucune transaction ne protège un échec en cours de route
$ LADECADANSE_DB=prod composer db:migrate   # demande confirmation avant d'écrire
```

Sous PowerShell : `$env:LADECADANSE_DB='prod'; composer db:migrate`.

> [!TIP]
> **Quand migrer ?** Par défaut **avant** `composer deploy`, car une colonne ou une table en plus ne gêne pas l'ancien code, alors que le nouveau code sur une base non migrée répond une erreur SQL. L'exception est une migration qui supprime ou renomme ce que l'ancien code lit encore : elle se passe juste **après**, l'intervalle étant le plus court possible.

[UPGRADE.md](../UPGRADE.md) indique l'ordre et les verrous à prévoir (tables MyISAM : hors des heures de saisie) pour chaque migration ; voir aussi [resources/database/README.md](../resources/database/README.md).
