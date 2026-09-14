# Configuration serveur : `.htaccess` et `.user.ini`

Ces deux fichiers sont **générés**. Il ne faut pas les éditer : `composer config:build`
les recompose à partir de fragments, et `composer deploy` recompose avant d'envoyer.

L'outil est `bin/build-config.php` — PHP plutôt que Make, qui n'est pas installé partout.
`make config-build` et `make deploy` existent aussi, comme simples raccourcis vers lui.

## Pourquoi

Ces fichiers mélangent deux natures de configuration, qui n'ont ni le même rythme
ni la même confidentialité :

- ce qui **suit le code** — redirections d'anciennes URL, pages d'erreur,
  protections de chemins, fuseau horaire, durée des sessions. Cette partie doit
  changer dans le même commit que le code, sinon elle se périme ;
- ce qui **suit l'exploitation** — adresses bannies, robots, domaine canonique,
  chemin du journal d'erreurs. Cette partie n'a pas sa place dans un dépôt
  public : publier quels robots sont tolérés indique quel User-Agent usurper, et
  le chemin du journal contient l'identifiant du compte d'hébergement.

Tant qu'il n'existait qu'un `.htaccess.example` non déployé, un `.htaccess` local
et un troisième fichier maintenu à la main sur le serveur, les trois divergeaient.
La page 404 personnalisée de la production a ainsi pointé pendant des mois sur
`articles/404.php`, supprimé en avril 2025.

## Où se trouve quoi

L'extension du fragment décide de la cible, son préfixe numérique décide de l'ordre.

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
| `userini/00-commun.ini` | public | fuseau horaire, durée des sessions |
| `ops/50-php-prod.ini` | privé | compression PHP, affichage et journal des erreurs |

Les fragments privés vivent dans le dépôt `ladecadanse-docs`, sous `config-serveur/`.
Le script les cherche dans `../ladecadanse-docs/config-serveur` ; sur une machine où ce
dépôt est ailleurs, avec `--ops-dir=` ou la variable d'environnement `LDD_OPS_DIR` :

```sh
php bin/build-config.php --ops-dir=/chemin/vers/config-serveur
```

Sans eux, la composition réussit et produit le socle public seul — de quoi
travailler en local. `composer deploy`, lui, refuse de partir : déployer la
production sans ses blocages serait pire que ne pas déployer.

## Un seul `.htaccess` pour les deux environnements

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

Conséquence utile : le pare-feu et les protections s'exercent aussi en local, donc
une erreur de syntaxe se voit avant le déploiement et non après.

Conséquence gênante : le pare-feu 8G renvoie 403 avant PHP sur l'User-Agent `curl`
ou sur `<script>` en query string. Les tests doivent en tenir compte — voir le
commentaire de `tests/Site/RssCest.php`.

## Le déploiement écrase-t-il la configuration locale ?

Non, parce qu'il n'existe plus de configuration locale séparée à écraser.
`composer config:build` et `composer deploy` produisent le **même** fichier sur une
machine qui a les fragments d'exploitation : le déploiement régénère, il ne
remplace pas du développement par de la production.

Le seul réglage vraiment propre au poste de travail — l'affichage des erreurs —
est dans `htaccess/70-dev-php.conf`, donc *à l'intérieur* du fichier composé,
neutralisé en production par `<IfModule php_module>`.

Ce qui se perd, en revanche, c'est ce qu'on tape à la main dans un fichier
composé : c'est le but. Un réglage local qui doit durer s'écrit dans un fragment,
pas dans le fichier généré.

Un garde-fou couvre le cas où le fichier n'a pas été composé par l'outil — un
`.htaccess` maintenu à la main, que ne suit aucun dépôt : la composition refuse
alors de l'écraser tant qu'on ne l'a pas mis de côté, ou passé `--force`.

## `.user.ini` : lu seulement là où PHP est en FastCGI

Un fichier INI n'a pas de `<IfModule>` : il ne peut porter qu'un seul jeu de
valeurs. C'est sans conséquence tant que les deux environnements diffèrent par
l'API serveur — PHP ne lit `.user.ini` que sous CGI/FastCGI, l'API d'Infomaniak,
et l'ignore sous le mod_php du poste de développement, où `70-dev-php.conf` prend
le relais.

**Le jour où le PHP local passerait en FastCGI**, cet équilibre se renverse :
`70-dev-php.conf` cesserait de s'appliquer et le `.user.ini` de production
prendrait le relais, avec `display_errors = Off` et un chemin de journal qui
n'existe pas sur la machine. Il faudrait alors un fragment `userini/` de
développement, composé à la place du fragment de production.

Le fichier reste hors d'atteinte du web par la règle `in[ci]` de
`30-protections.conf`, qui couvre `.inc` comme `.ini`.

Deux réglages y méritent l'attention parce qu'ils diffèrent silencieusement d'une
machine à l'autre :

- `session.gc_maxlifetime` vaut 3600 (une heure). Le PHP local est à 36000 par
  défaut : une expiration de session ne se voit jamais en développement ;
- `date.timezone` double `app/config.php`, qui fait foi via
  `date_default_timezone_set()` dans `app/bootstrap.php`. Le réglage INI ne
  couvre que ce qui s'exécute avant le bootstrap et les scripts qui ne passent
  pas par lui.

## Déploiement

`.htaccess` et `.user.ini` sont ignorés par git — pour que la partie exploitation
ne devienne jamais publique — mais git-ftp les envoie quand même, grâce à
`!.htaccess` et `!.user.ini` dans `.git-ftp-include`, comme il le fait déjà pour
`.well-known/`.

Le `!` signifie « à chaque push, sans condition » : git-ftp envoie **les fichiers
présents sur le disque**. C'est pourquoi `deploy` les recompose d'abord — sans
cela, un essai local oublié partirait en production.

Après un déploiement, une requête suffit à prouver que le `.htaccess` a été pris
et qu'il est valide :

```sh
curl -I https://www.ladecadanse.ch/lausanne
```

Une 301 vers `/index.php?region=vd` : le compte est bon.

## Choisir le serveur

`git ftp` travaille par *scope*, et le script n'en suppose aucun : quand plusieurs
sont configurés — dont d'anciens hébergements — il les liste et s'arrête plutôt
que de parier sur la bonne machine. S'il n'y en a qu'un, il est retenu sans rien
préciser.

```sh
composer deploy -- --scope=nom
make deploy SCOPE=nom
```

## Quand une page est déplacée

Ajouter la redirection dans `htaccess/50-routage.conf`, dans le commit qui
déplace la page. Rien d'autre à faire : la composition est refaite au
déploiement suivant.

## Ce qui n'est pas couvert

Le journal d'erreurs PHP du poste de développement reste réglé dans le `php.ini`
de Laragon : son chemin est propre à la machine et n'a sa place ni dans le dépôt
public, ni dans les fragments d'exploitation.

Le mode maintenance se décommente désormais dans `ops/10-blocages.conf` puis se
déploie, là où il s'activait en éditant le fichier sur le serveur. Sur un dépôt
propre, `composer deploy` n'envoie que les deux fichiers composés : l'aller-retour
reste court.
