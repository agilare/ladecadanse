# Analyse de teinte Psalm sur La décadanse

Psalm sait suivre une valeur non fiable — `$_GET`, `$_POST`, une ligne de base — depuis
son entrée jusqu'à un point dangereux — un `echo`, un `prepare()` — et signaler celles qui
y arrivent sans être passées par un échappement.

Psalm était déjà installé dans le dépôt pour son analyse de types. Ce répertoire contient
ce qu'il fallait ajouter pour que son **mode teinte** dise quelque chose de ce code-ci, et
de quoi vérifier que cette configuration mord toujours.

**Ce fichier documente l'outillage, pas ses résultats.** Le tri des signalements vit dans
`rapport.md`, que `.gitignore` garde hors du dépôt : celui-ci est public, et un rapport de
teinte nomme le fichier, la ligne et la nature de chaque défaut encore ouvert. Ce qui est
corrigé se raconte dans le CHANGELOG, une fois refermé.

## Lancer

```bash
composer psalm:taint
```

Une trentaine de secondes. Trier la sortie ailleurs que dans le dépôt.

```bash
composer psalm:banc
```

Vérifie que la configuration fait ce qu'elle prétend : 21 cas et 2 sinks, une trentaine de
secondes, sortie 0 si tout concorde. À lancer après toute mise à jour de Psalm, toute
modification de `psalm.xml`, et avant de conclure d'un rapport rassurant.

## Les fichiers

| Fichier | Rôle |
|---|---|
| `src/SessionTaintPlugin.php` | ajoute `$_SESSION` aux sources de teinte |
| `banc-de-controle.php.txt` | code volontairement vulnérable, avec ses attendus |
| `banc.xml` | configuration Psalm du banc |
| `verifier-le-banc.php` | lance le banc et compare aux attendus |

Le reste de la configuration vit dans `psalm.xml` et dans les docblocs du code.

## Ce que l'adaptation a demandé

Quatre obstacles, chacun mesuré sur ce dépôt.

### PDO n'était surveillé par rien

`PDO::query()`, `prepare()` et `exec()` sont bien des sinks SQL déclarés chez Psalm — dans
`stubs/extensions/pdo.phpstub`, que Psalm **ne charge que si l'extension est déclarée** :
par un `ext-pdo` dans la section `require` de `composer.json`, ou par un
`<enableExtensions>` dans la configuration. Ni l'un ni l'autre n'y était.

Les 85 requêtes du dépôt qui passent par `$connectorPdo` n'étaient donc surveillées par
personne, et le rapport paraissait rassurant sans l'être. `mysqli`, lui, était couvert
depuis toujours par le `ext-mysqli` de `composer.json`.

`composer.json` déclare désormais `ext-pdo` et `ext-pdo_mysql`. C'est la bonne place plutôt
que `psalm.xml` : ce sont de vraies dépendances — `app/bootstrap.php` ouvre une connexion
PDO à chaque requête et le DSN est `mysql:` — que rien ne déclarait, et les déclarer sert à
tous les outils, pas seulement à Psalm.

Deux choses à savoir. **Psalm ne reconnaît que la clé `pdo`** : `ext-pdo_mysql` seul irait
dans ses « extensions non supportées », sans un mot, et le stub ne serait pas chargé. Et
retirer `ext-pdo` de `composer.json` rendrait l'analyse muette sur tout le SQL PDO —
`composer psalm:banc` est là pour le dire aussitôt.

### Le connecteur PDO coupait la chaîne

Déclarer l'extension ne suffit pas : `DbConnectorPdo::prepare()` fait
`$this->pdo->prepare($sql)`, et la propriété était écrite `private $pdo`, sans type. Pour
Psalm, `$this->pdo` valait `mixed`, l'appel n'était pas reconnu comme un sink, et la
teinte s'arrêtait au bord du connecteur.

`private PDO $pdo` suffit à rouvrir la chaîne. Les deux corrections vont ensemble : le
typage seul ne sert à rien tant que `PDO::prepare` n'est pas un sink, et l'extension seule
ne sert à rien tant que le connecteur coupe le chemin. C'est ce qui explique que le POC
progpilot ait conclu, en ne testant que la seconde, que typer la propriété « ne change
rien » — le rapport de teinte de ce jour-là n'avait tout simplement pas de sink PDO.

### `$_SESSION` n'est pas une source pour Psalm

Psalm teinte `$_GET`, `$_POST`, `$_COOKIE` et `$_REQUEST`, pas `$_SESSION`. C'est un angle
mort qui compte ici : le site range volontiers en session des valeurs venues de la requête —
préférences d'affichage, identité du membre — avant de les rendre ou de les concaténer.

`src/SessionTaintPlugin.php` comble l'écart, par le point d'extension `AddTaintsInterface`
que Psalm prévoit pour cela : il ajoute à `$_SESSION` les mêmes teintes qu'à `$_GET`. Le
plugin est autoloadé par le mapping `Ladecadanse\Psalm\` d'`autoload-dev` — après tout
changement, `composer dump-autoload`, sinon Psalm démarre sans lui et sans le dire.

Contrepartie : la session porte aussi les jetons CSRF, affichés dans chaque formulaire.
Ces sites-là remontent désormais, et sont des faux positifs — hexadécimal issu de
`random_bytes()`.

### Les points de convergence

Quand Psalm ne sait pas distinguer deux appels d'une même fonction, il fusionne leurs flux :
un seul appelant fautif teinte alors tous les autres. Sur ce dépôt, deux endroits en
faisaient l'essentiel du bruit.

`HtmlShrink::getPaginationString()` : un seul appelant teintait les 36 sites de pagination.
`@psalm-taint-specialize` sur la méthode ramène le compte à 4.

`Validateur::$erreurs` : un seul flux teinte les 222 sites qui affichent un message
d'erreur, soit près des trois quarts de la sortie. `@psalm-taint-specialize` n'y peut
rien — la convergence se fait sur une propriété d'instance, et Psalm 6.16.1 en fait un
nœud unique quelle que soit l'instance (vérifié, cas reproduit dans le banc). Rien dans la
configuration ne corrige cela : c'est à la source du flux de se refermer.

Conséquence pratique pour la lecture d'un rapport : un compte de signalements ne mesure
pas un nombre de défauts. Quelques lignes peuvent en porter la majorité.

## Pièges à connaître

**Une description placée après un `@psalm-taint-escape` casse l'annotation.** Le parseur de
Psalm coupe l'argument sur l'espace, jamais sur le saut de ligne : le texte qui suit lui est
rattaché et l'échappement cesse silencieusement de mordre. Écrire la description **avant**
les annotations. C'est le banc qui a trouvé ce piège, sur `SecurityToken::getToken()`.

**Un stub sur une classe du dépôt est ignoré.** Déclarer les échappements maison dans un
fichier `<stubs>` séparé, comme progpilot le fait en JSON, ne marche pas : Psalm garde la
définition réelle et le stub ne sert à rien (vérifié). Les annotations vont dans le code.

**`global` au niveau racine d'un fichier ne type rien.** Psalm émet `InvalidGlobal` et la
variable reste sans type, donc sans chemin vers son sink. Dans une méthode, `global` est lu
normalement — c'est le motif du dépôt, et le banc le reproduit.

**Psalm ne rend qu'un chemin par sink.** Les 85 requêtes PDO convergent vers une seule ligne
de `DbConnectorPdo.php` : le signalement dit qu'il existe *au moins* un chemin non sûr, pas
combien. Corollaire pour le banc : y appeler `PDO::prepare()` en direct prendrait la place
du chemin qui passe par le connecteur, et le banc s'aveuglerait lui-même — d'où l'absence
de ce cas direct.

**La validation par liste blanche n'est pas lue**, pas plus que chez progpilot :
`in_array($_GET['tri'], ['asc','desc'])` ne détainte pas. C'est la première cause de faux
positifs des deux outils.

**`sanitize()` hors quotes passe pour sûr.** `DbConnector::sanitize()` est déclaré comme
échappement SQL, ce qu'il est entre quotes ; en contexte numérique il ne protège pas, et
Psalm ne fait pas la différence. Seul le cast `(int)` protège là. Le banc porte les deux
lignes côte à côte, muettes toutes les deux.

**La teinte s'arrête au bord de `vendor/`.** Ce que `UserHtmlSanitizer` passe à Symfony ne
ressort pas teinté, non parce que Symfony nettoie, mais parce que Psalm ne l'analyse pas.
L'annotation posée sur cette méthode documente l'intention ; elle ne rattrape rien.

## Recoupement avec progpilot

progpilot, autre analyseur de teinte, a été évalué sur ce dépôt avant Psalm ; sa
configuration n'est pas versionnée (voir `.gitignore`), et les renvois ci-dessus valent
pour qui l'a encore sur son poste.

Les deux outils se recoupent peu — le POC mesurait 139 sites côté Psalm, 11 côté
progpilot, 3 en commun. La configuration décrite ici déplace cette ligne de partage :
`$_SESSION` n'est plus l'angle mort de Psalm, et PDO n'est plus le sien non plus.

Ce qui reste à progpilot : il se configure sans toucher au code, là où Psalm demande des
annotations dans les fichiers. Ce qui reste à Psalm : il lit les types PHP, que progpilot
ignore, et couvre les formulaires d'édition que progpilot ne voit pas.

## Après une mise à jour de Psalm

```bash
composer psalm:banc
```

Le banc dit tout de suite si une règle a cessé de mordre. Un « signale » devenu muet est un
échappement perdu ou un plugin non chargé ; un « muet » devenu bavard est une teinte que
Psalm a appris à suivre.
