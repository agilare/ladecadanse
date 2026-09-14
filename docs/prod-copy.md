# Copie locale anonymisée de la production

`composer prod-copy` fabrique une base locale `ladecadanse_prod_copy` portant les N derniers
événements ajoutés en production et tout ce qu'ils référencent, et rapatrie les flyers et photos
correspondants dans `web/uploads/`. Aucune donnée personnelle exploitable n'y subsiste.

L'outil est `bin/prod-copy.php`. Comme `bin/build-config.php`, c'est un outil de poste de
développement : `.git-ftp-ignore` exclut `bin/` du déploiement, il n'est jamais servi en HTTP.

## Pourquoi

Une base neuve est vide, et saisir à la main de quoi éprouver l'agenda est vite décourageant.
Les données réelles sont déjà cohérentes, et reproduisent ce qu'aucun générateur ne produira :
lieux sans coordonnées, horaires improbables, flyers disparus, événements proposés par des
visiteurs non connectés, comptes inactifs, un auteur qui est aussi organisateur du lieu accueillant
son événement.

L'[issue #168](https://github.com/agilare/ladecadanse/issues/168) propose l'approche inverse — des
fixtures générées avec Faker. Les deux se complètent : celle-ci donne un environnement réaliste sur
un poste, l'autre un jeu de données qui ne doit rien à la production, ce dont la CI a besoin.

## Mise en place, une fois

### 1. L'accès SSH

C'est le seul vrai prérequis, et il sert deux fois. L'offre Infomaniak n'ouvre pas MySQL à
l'extérieur : l'hôte affiché par le manager résout vers une adresse privée en `10.x`, et aucune
bascule d'accès externe n'existe dans l'interface. Chercher une case à cocher est une perte de
temps.

La base se lit donc à travers un tunnel, à ouvrir dans un terminal à part et à laisser tourner :

```sh
ssh -N -L 3307:XXXXXX.myd.infomaniak.com:3306 utilisateur@exemple.ch
```

3307 et non 3306, que Laragon occupe déjà.

Le même accès porte la phase fichiers. Relever au passage la racine des uploads sur le serveur :

```sh
ssh utilisateur@exemple.ch 'ls -d sites/*/web/uploads; which tar'
```

### 2. Les connexions

Trois entrées dans `app/db.config.php`, que `.gitignore` exclut du dépôt. Le modèle commenté est
`app/db.config_model.php`.

`DbConnectorPdo` n'a pas de clé `port` et concatène `host` telle quelle dans le DSN : **le port du
tunnel se glisse donc dans la valeur `host`**.

```php
'prod' => [
    'host' => '127.0.0.1;port=3307',
    'dbname' => 'XXXXXX_ladecadanse',
    'user' => 'XXXXXX_ladeca',
    'password' => '…',
    'ssh' => 'utilisateur@exemple.ch',
    'ssh_uploads' => '/home/clients/XXXX/sites/exemple.ch/web/uploads',
],
'prod_copy' => [
    'host' => 'localhost',
    'dbname' => 'ladecadanse_prod_copy',
    'user' => '…',
    'password' => '…',
],
```

### 3. Le droit de créer la base

La base de destination est créée par le script — ne pas la créer à la main. Son utilisateur MySQL a
donc besoin du droit `CREATE` dessus, qu'un `GRANT` accorde même sur une base inexistante :

```sh
mysql -uroot -e "GRANT ALL PRIVILEGES ON \`ladecadanse_prod_copy\`.* TO 'ladecadanse'@'localhost'"
```

## Éprouver avant d'engager

La phase base seule, sur vingt événements, prend quelques secondes et ne touche à aucun fichier :

```sh
composer prod-copy -- --limit=20 --only=db
```

Elle doit afficher un décompte par table et un exemple d'identifiant par groupe. Si la connexion
échoue en `SQLSTATE[HY000] [2002]` avec un dépassement de délai, le tunnel du terminal 1 n'est pas
ouvert.

## Le passage réel

```sh
composer prod-copy -- --limit=1000 --force
```

Compter une minute pour la phase fichiers : environ 2700 fichiers et 290 Mo descendent en une seule
connexion SSH. `--force` est nécessaire pour écraser la base laissée par la passe d'essai.

Puis pointer l'application sur la copie — `DB_NAME` dans `app/env.php`, et l'entrée `default` de
`app/db.config.php`.

Toutes les `personne` partagent le même mot de passe, `decadanse1` par défaut, leur pseudo devenant
`user{idPersonne}` : `user1` pour le compte SUPERADMIN. Le script affiche un exemple par groupe en
fin d'exécution.

## Vérifier

Les trois requêtes doivent renvoyer 0.

```sql
SELECT COUNT(*) FROM personne
 WHERE email NOT LIKE '%@example.test' OR cookie <> '' OR gds <> '' OR affiliation <> '';

SELECT COUNT(*) FROM evenement
 WHERE (remarque IS NOT NULL AND remarque <> '')
    OR (user_email IS NOT NULL AND user_email <> '' AND user_email NOT LIKE '%@example.test');

SELECT COUNT(*) FROM evenement e
  LEFT JOIN lieu l ON e.idLieu = l.idLieu
 WHERE e.idLieu <> 0 AND l.idLieu IS NULL;
```

Puis parcourir l'agenda, une fiche d'événement avec flyer, une fiche de lieu avec galerie, et
`admin/index.php` après connexion.

## Ce que la copie contient

Treize tables : `affiliation`, `descriptionlieu`, `evenement`, `evenement_organisateur`,
`fichierrecu`, `lieu`, `lieu_fichierrecu`, `lieu_organisateur`, `localite`, `organisateur`,
`personne`, `personne_organisateur`, `salle`.

`localite` est reprise en entier : elle ne porte aucune donnée d'événement, mais
`evenement.localite_id` et `lieu.localite_id` y renvoient et aucune adresse ne s'affiche sans elle.

La sélection se fait sur `dateAjout DESC`, pas sur `dateEvenement` : la copie contient donc
naturellement les événements à venir saisis en avance, sans qu'il faille décaler la moindre date —
ce qu'il ne faut surtout pas faire, `evenement.flyer` valant `{idE}_{dateEvenement}.ext`.

Les identifiants d'origine sont conservés, pour la même raison et parce que toutes les tables de
jonction en dépendent.

## Ce qu'elle ne contient pas

L'anonymisation a lieu entre le `SELECT` et l'`INSERT` : aucun fichier intermédiaire ne porte jamais
de donnée personnelle réelle, et le gigaoctet de la production ne circule pas.

| Colonne | Remplacement |
|---|---|
| `personne.pseudo` | `user{id}` |
| `personne.mot_de_passe` | un hash unique d'un mot de passe connu |
| `personne.email` | `user{id}@example.test` — TLD réservé RFC 6761, jamais routable |
| `personne.cookie` | vidée |
| `personne.gds` | vidée — sel du stockage sha1 historique, qui ferait tenter à `Sentry` l'ancienne vérification |
| `personne.affiliation` | vidée |
| `evenement.user_email` | `visiteur{id}@example.test` |
| `evenement.remarque` | vidée — note privée à l'administrateur |
| `organisateur.email` | `orga{id}@example.test` |

`statut`, `groupe` et `last_login` sont conservés : c'est ce qui rend la copie utile pour éprouver
les permissions.

Les tables `bot_monitor` (adresses IP) et `user_reset_requests` (e-mails et jetons) ne sont pas
reprises du tout — `admin/bots.php` restera donc vide.

## Options

| Option | Effet |
|---|---|
| `--limit=1000` | nombre d'événements |
| `--only=db` / `--only=files` | n'exécuter qu'une phase |
| `--force` | supprimer et recréer une base existante |
| `--password=…` | mot de passe commun des comptes copiés |
| `--reset-uploads` | déplacer `web/uploads/` de côté avant de rapatrier |
| `--source=` / `--dest=` | autres entrées de `db.config.php` |
| `--ssh=` / `--ssh-path=` | surcharger l'accès SSH de l'entrée source |
| `--uploads-dir=` | écrire les fichiers ailleurs que dans `web/uploads/` |

## Pièges

**Le mot de passe commun doit faire au moins 4 caractères.** `user/login.php` refuse plus court
avant même de regarder la base : tous les comptes de la copie seraient inutilisables. Le script
vérifie `--password` au démarrage plutôt que de laisser le découvrir à la première connexion.

**Les uploads d'une instance antérieure répondent à une autre base.** Ils ne sont pas écrasés, mais
resteront mêlés à ceux de la copie. Le script les compte et le signale ; `--reset-uploads` les
déplace dans un `web/uploads-backup-<horodatage>/`, sans rien supprimer.

**Les images d'événements des années révolues sont archivées** dans `evenements/<année>/`,
`htaccess/50-routage.conf` redirigeant le chemin plat vers elles en 301. Le script demande les deux
emplacements et laisse `--ignore-failed-read` écarter celui qui n'existe pas.

**Pas de transaction** : les tables visées sont en MyISAM. Une exécution interrompue laisse une
copie partielle, que le `--force` de la suivante rattrape.

**Le schéma est celui de la production**, lu par `SHOW CREATE TABLE`, et non celui de
`resources/database/ladecadanse.sql`. Ce dernier est tenu à jour à la main et fait autorité sur une
installation neuve ; ici les lignes viennent de la production, donc le schéma qui les accueille doit
être le sien.

**`tar` sous Windows.** GNU tar prend `C:/…` pour un hôte distant et réclame `--force-local`, que le
bsdtar livré avec Windows rejette — et c'est celui-là que PHP trouve. L'archive est donc extraite
depuis l'entrée standard plutôt que depuis un chemin. Si la commande d'extraction est un jour
retouchée, ne pas réintroduire `-f <chemin>`.

## Pourquoi SSH plutôt que HTTP

La première version demandait les images au serveur web, une requête par fichier. La production
répond **429** passé quelques milliers de requêtes rapprochées, ce qui imposait une reprise, un
User-Agent de navigateur pour passer le pare-feu 8G, et un pool de téléchargements parallèles.

`tar` n'emporte que les membres nommés sur son entrée standard : la liste que le script calcule déjà
depuis la copie. Une connexion suffit, les horodatages d'origine sont conservés, et les fichiers que
`htaccess/30-protections.conf` refuse de servir depuis `/web/uploads/` — tout ce qui n'est pas jpg,
jpeg, png, gif ou webp — descendent comme les autres. C'est ainsi qu'une illustration nommée `.jfif`,
inaccessible en HTTP, a pu être rapatriée.
