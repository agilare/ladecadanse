# .htaccess

Le `.htaccess` de la racine est **généré**. Il ne faut pas l'éditer : `composer htaccess`
le recompose à partir de fragments, et `composer deploy` recompose avant d'envoyer.

L'outil est `bin/htaccess.php` — PHP plutôt que Make, qui n'est pas installé partout.
`make htaccess` et `make deploy` existent aussi, comme simples raccourcis vers lui.

## Pourquoi

Le fichier mélange deux natures de configuration, qui n'ont ni le même rythme ni
la même confidentialité :

- ce qui **suit le code** — redirections d'anciennes URL, pages d'erreur,
  protections de chemins. Cette partie doit changer dans le même commit que le
  code, sinon elle se périme ;
- ce qui **suit l'exploitation** — adresses bannies, robots, domaine canonique.
  Cette partie n'a pas sa place dans un dépôt public : publier quels robots sont
  tolérés indique quel User-Agent usurper.

Tant qu'il n'existait qu'un `.htaccess.example` non déployé, un `.htaccess` local
et un troisième fichier maintenu à la main sur le serveur, les trois divergeaient.
La page 404 personnalisée de la production a ainsi pointé pendant des mois sur
`articles/404.php`, supprimé en avril 2025.

## Où se trouve quoi

| fragment | dépôt | contenu |
|---|---|---|
| `htaccess/00-core.conf` | public | `Options`, `RewriteEngine`, protection de `.git` |
| `ops/05-hote.conf` | privé | compression, domaine canonique |
| `ops/10-blocages.conf` | privé | adresses bannies, robots, mode maintenance |
| `htaccess/20-firewall-8g.conf` | public | pare-feu 8G (composant tiers) |
| `htaccess/30-protections.conf` | public | `FilesMatch`, `app/ var/ librairies/ resources/ bin/`, `uploads/` |
| `htaccess/40-entetes.conf` | public | CORS, en-têtes de politique |
| `htaccess/50-routage.conf` | public | **suit le code** : redirections, images, SEO |
| `htaccess/60-erreurs.conf` | public | `ErrorDocument` |
| `htaccess/70-dev-php.conf` | public | réglages PHP du poste de développement |

Les fragments privés vivent dans le dépôt `ladecadanse-docs`, sous `htaccess/`.
Le script les cherche dans `../ladecadanse-docs/htaccess` ; sur une machine où ce
dépôt est ailleurs, avec `--ops-dir=` ou la variable d'environnement `LDD_OPS_DIR` :

```sh
php bin/htaccess.php --ops-dir=/chemin/vers/ladecadanse-docs/htaccess
```

Sans eux, la composition réussit et produit le socle public seul — de quoi
travailler en local. `composer deploy`, lui, refuse de partir : déployer la
production sans ses blocages serait pire que ne pas déployer.

## Un seul fichier pour les deux environnements

Le fichier composé est le même en local et en production, parce que chaque
fragment est inerte là où il ne s'applique pas :

| fragment | neutralisé en local par |
|---|---|
| domaine canonique | condition sur `HTTP_HOST` = `ladecadanse.ch` |
| compression | `<IfModule mod_deflate.c>` |
| adresses bannies | l'adresse cliente est `127.0.0.1` |
| réglages PHP | `<IfModule php_module>` — vrai en local (module), faux sous FPM |

Ce dernier point est le seul qui empêchait un fichier commun : hors `<IfModule>`,
`php_flag` provoque une erreur 500 sous FPM/FastCGI, l'API serveur d'Infomaniak.
Les réglages PHP de production vivent dans `.user.ini`, que la règle `in[ci]` de
`30-protections.conf` met hors d'atteinte du web.

Conséquence utile : le pare-feu et les protections s'exercent aussi en local, donc
une erreur de syntaxe se voit avant le déploiement et non après.

Conséquence gênante : le pare-feu 8G renvoie 403 avant PHP sur l'User-Agent `curl`
ou sur `<script>` en query string. Les tests doivent en tenir compte — voir le
commentaire de `tests/Site/RssCest.php`.

## Déploiement

`.htaccess` est ignoré par git — pour que la partie exploitation ne devienne
jamais publique — mais git-ftp l'envoie quand même, grâce à `!.htaccess` dans
`.git-ftp-include`, comme il le fait déjà pour `.well-known/`.

Le `!` signifie « à chaque push, sans condition » : git-ftp envoie **le fichier
présent sur le disque**. C'est pourquoi `deploy` dépend de `.htaccess` — sans
cette dépendance, un essai local oublié partirait en production.

Après un déploiement, une vérification suffit à prouver que le fichier a bien été
pris et qu'il est valide :

```sh
curl -I https://www.ladecadanse.ch/lausanne
```

Une 301 vers `/index.php?region=vd` : le compte est bon.

## Quand une page est déplacée

Ajouter la redirection dans `htaccess/50-routage.conf`, dans le commit qui
déplace la page. Rien d'autre à faire : la composition est refaite au
déploiement suivant.

## Ce qui n'est pas couvert

Le `.user.ini` de production — réglages PHP sous FPM — n'est toujours versionné
nulle part : il n'existe que sur le serveur. Il relève du même raisonnement que
les fragments d'exploitation et aurait sa place dans `ladecadanse-docs`, mais
son contenu n'a pas été relevé ici.

## Choisir le serveur

`git ftp` travaille par *scope*, et le script n'en suppose aucun : quand plusieurs
sont configurés, il les liste et s'arrête plutôt que de parier sur la bonne
machine. S'il n'y en a qu'un, il est retenu sans rien préciser.

```sh
composer deploy -- --scope=nom
make deploy SCOPE=nom
```
