# Mise à jour

Ce fichier liste les opérations à effectuer lors du passage à une nouvelle version : migrations de base de données, nouvelles clés de configuration, effets de bord à connaître. Pour la liste des changements eux-mêmes, voir le [changelog](CHANGELOG.md) ; pour le fonctionnement des fonctionnalités, [docs/](docs/) ; pour l'inventaire des scripts SQL, la version qui a livré chacun et une requête disant ce qui manque à une base, [resources/database/README.md](resources/database/README.md).

Les versions sont listées de la plus récente à la plus ancienne.

## 3.12.0 (non publié)

### Base de données

Exécuter `resources/database/v3-12-0_localite-france.sql`, **d'une seule traite** : le fichier pose une variable de connexion (`LAST_INSERT_ID()`) que deux `UPDATE` relisent ensuite, et exécuter les instructions séparément la perdrait.

Il fait quatre choses :

1. passe `localite.npa` de `INT(4)` à `VARCHAR(6)` — un code postal français compte cinq chiffres, dont certains commencent par un zéro (01210 Ferney-Voltaire) qu'un entier perdrait. Les NPA suisses existants sont convertis tels quels
2. ajoute la localité « Ailleurs en France », de canton `rf` : elle recueille les adresses françaises dont la commune n'est pas encore listée
3. y déplace les événements et les lieux jusqu'ici rattachés à la localité 1 avec `region = 'rf'` (152 événements et 2 lieux sur la base de développement)
4. renomme la localité 1 en « Hors Genève, Vaud et France » et lui donne le canton `hs`, qu'elle n'avait pas

**À passer avec la mise en ligne du code, pas plus tard** : tant qu'elle ne l'est pas, la localité 1 garde un canton vide, les formulaires ouvrent donc un `<optgroup>` sans libellé, et la France n'est plus proposée du tout — son entrée codée en dur a disparu du code.

Les deux libellés ci-dessus sont écrits à l'identique dans le fichier SQL et dans `Ladecadanse\Localite::LOCALITES_FOURRE_TOUT` ; c'est sur cette égalité que repose leur effacement de l'affichage des adresses. Les renommer suppose de le faire des deux côtés — un test unitaire relit le `.sql` pour le vérifier.

Exécuter aussi `resources/database/v3-12-0_lieu-colonnes.sql`, qui remanie les colonnes de la table `lieu` :

1. `determinant` devient `preposition_nom` et passe après `nom` — « au », « chez », « à l' » sont des prépositions, pas des déterminants ;
2. `categorie` devient `categories` et passe après `preposition_nom` — la colonne est un `SET`, un lieu en porte plusieurs ;
3. `adresse` passe de `VARCHAR(100)` à `VARCHAR(255)` ;
4. `lat`, `lng`, `horaire_general`, `URL`, `photo1` et `logo` acceptent `NULL`, et les lignes existantes y passent de `0` ou de la chaîne vide à `NULL` ; `logo` passe après `categories` ;
5. `photo2` et `actif` sont supprimées — la seconde photo n'était proposée par aucun formulaire, et `actif` était un doublon inerte de `statut`, resté à 1 partout.

**À passer avec la mise en ligne du code, pas plus tard** : les deux renommages sont lus par les pages d'événement (`l.preposition_nom`) et par la liste des lieux (`FIND_IN_SET(…, categories)`), qui répondraient sinon une erreur SQL.

### Redirections

Six pages changent d'adresse : trois pages de compte rejoignent `user/`, à côté de `login.php` et `dashboard.php`, les formulaires d'organisateur et de lieu passent sous leur répertoire, et l'écran d'administration des événements prend un nom lisible. Les redirections 301 sont dans [`htaccess/50-routage.conf`](htaccess/50-routage.conf) et partent avec le code : il n'y a plus rien à reporter à la main sur le serveur, mais `composer config:build` doit être passé avant la mise en ligne, comme pour tout changement d'un fragment de configuration — voir [docs/config-serveur.md](docs/config-serveur.md).

| Ancienne URL | Nouvelle |
| --- | --- |
| `/user-register.php` | `/user/register.php` |
| `/user-reset.php` | `/user/reset.php` |
| `/user-reset2.php` | `/user/reset2.php` |
| `/organisateur-edit.php` | `/organisateur/edit.php` |
| `/lieu-edit.php` | `/lieu/edit.php` |
| `/admin/gererEvenements.php` | `/admin/events.php` |

La query string est reportée d'office, aucune substitution n'en portant : les liens de réinitialisation déjà envoyés par mail (`?token=`) restent valables 24 h — sans la redirection ils tomberaient en 404 pendant une journée — et les signets des organisateurs (`?action=editer&idO=…`) arrivent au bon endroit.

Aucune de ces pages n'est indexée : formulaires réservés aux connectés, écran d'administration sans lien entrant public. Les redirections sont là pour les signets, l'historique et les mails déjà partis.

### Effets de bord à connaître

- **Qui peut modifier une fiche d'organisateur** — le contrôle laissait passer tout compte de niveau ACTOR, c'est-à-dire que chaque organisateur pouvait éditer la fiche de tous les autres. Il pose maintenant la même question que le lien « Modifier cet organisateur » de la fiche : niveau AUTHOR ou au-dessus, membre de l'organisateur, ou auteur de la fiche. Conséquence : **un organisateur qui éditait jusqu'ici une fiche sans y être rattaché sera refusé**. Le rattachement se fait dans `personne_organisateur`, depuis le profil de la personne
- **Statut d'un organisateur, statut d'un lieu** — les libellés deviennent « Publié / Dépublié / Ancien » ; les valeurs en base (`actif`, `inactif`, `ancien`) ne changent pas. Le formulaire ne poste plus de statut pour qui n'a pas le droit d'en choisir un : une modification faite par un acteur laisse désormais la fiche dans l'état où elle était, là où elle la republiait
- **Qui peut modifier une fiche de lieu** — la règle ne change pas (niveau AUTHOR ou au-dessus, personne affiliée au lieu, ou membre d'un organisateur rattaché), mais elle est posée une fois, dans `Authorization::isPersonneAllowedToEditLieu()`, par le formulaire et par le lien « Modifier ce lieu » de la fiche. Un refus répond 403, une requête sans identifiant 400, un identifiant inconnu 404 — le formulaire affichait jusqu'ici un message HTML nu au-dessus d'une page vide, avec un statut 200
- **Champs réservés d'un lieu** — le nom, la préposition, les catégories et les organisateurs ne partaient plus en champs cachés à qui n'a pas le droit d'y toucher : le serveur reprend leur valeur enregistrée quel que soit le contenu du POST. Conséquence : **un POST forgé ne renomme plus un lieu ni ne le rattache à un organisateur**. La galerie d'images disparaît du formulaire — fonctionnalité abandonnée, les images se posent à la main
- **Déconnexion en POST** — `user-logout.php` disparaît sans redirection : une déconnexion en GET partait toute seule au moindre préchargement de lien. Un onglet resté ouvert sur une page rendue *avant* la mise à jour porte encore l'ancien lien et tombera en 404 ; il suffit de recharger la page. Signets et liens externes vers `/user-logout.php` cessent de fonctionner — voir [docs/comptes.md](docs/comptes.md)
- **Mots de passe** — la liste des mots de passe refusés passe de 22 à 19 999 entrées. Les mots de passe existants ne sont pas vérifiés, rien n'est bloqué rétroactivement : la règle ne s'applique qu'au prochain changement. Les comptes non actifs (`statut` autre que `actif`) ne peuvent plus demander de réinitialisation, l'ancien filtre laissait passer les comptes en attente que la connexion refuse ensuite de toute façon
- **Édition groupée et organisateurs** — un remplacement groupé effaçait jusqu'ici les organisateurs de tous les événements sélectionnés, même quand le champ était laissé vide. Il ne les touche plus que si le champ a été rempli, conformément à la règle annoncée par la page. Conséquence : **il n'est plus possible de retirer les organisateurs en masse** en envoyant le formulaire avec un champ vide. Rien ne le permettait vraiment — l'ancien comportement était un effacement subi, pas une commande
- **Préférences de liste** — filtres, tri et nombre de lignes de `admin/events.php` sont désormais mémorisés en session (`user_prefs_even_*`), comme ceux de `admin/users.php`. À la première visite après la mise à jour, la liste repart donc sur ses valeurs par défaut, et les anciens paramètres d'URL (`tri_gerer`, `ordre`, `filtre_genre`, `element`, `nblignes`) ne sont plus lus : un signet qui en porterait ouvre la liste sans filtre plutôt qu'en erreur. Le menu de lignes propose 50, 250 et 500 ; le 100 disparaît de cette page seulement, `$tab_nblignes` restant inchangé pour `admin/users.php` et `admin/bots.php`
- **Images des envois groupés** — un flyer ou une image posé par édition groupée est désormais redimensionné en 600×600 avec une miniature non rognée, comme dans `evenement-edit.php` (et non plus 400×400 avec miniature rognée). Les images déjà en base ne sont pas retraitées
- **`resources/`** — les corps de mail passent sous `resources/templates/`, les scripts sql sous `resources/database/`. Adapter tout montage ou script maison qui les référence (le `docker-compose.yml` du dépôt est à jour)
- **Localités françaises** — la France et « ailleurs » cessent d'être des entrées codées en dur dans les formulaires : ce sont des localités de la table `localite`, de cantons `rf` et `hs`. Ajouter une commune française ne demande donc plus de toucher au code, seulement un `INSERT INTO localite (localite, commune, npa, canton, regions_covered) VALUES ('Annemasse', 'Annemasse', '74100', 'rf', 'ge,rf')`. Les deux localités fourre-tout, elles, ne s'affichent jamais dans une adresse : seule la région apparaît, comme avant

### Développement

- `resources/database/ladecadanse.sql` est de nouveau le schéma courant : il avait quatre versions de retard, et une base créée à partir de lui n'avait ni les index de recherche de la 3.8 et de la 3.9, ni la table `bot_monitor`. Une installation neuve importe donc désormais le dump **seul**, sans rejouer aucune migration par-dessus. Les bases existantes ne sont pas concernées ; pour savoir ce qui manque à l'une d'elles, passer la requête de [resources/database/README.md](resources/database/README.md), qui répond fichier par fichier
- `composer rector:dry-run` fonctionne à nouveau : la configuration pointait sur un fichier de test déplacé, et le parcours partait de la racine, donc de `vendor/`
- `.htaccess` et `.user.ini` sont composés depuis des fragments par `composer config:build` : `.htaccess.example` disparaît, et l'étape d'installation `cp .htaccess.example .htaccess` devient `composer config:build`. Un `.htaccess` écrit à la main n'étant suivi par aucun dépôt, le mettre de côté avant la première composition — l'outil refuse de l'écraser sans `--force`. Voir [docs/config-serveur.md](docs/config-serveur.md)

## 3.11.0

### Base de données

Exécuter, dans cet ordre, les fichiers du répertoire `resources/database/` :

1. `v3-11-0_personne-add-settings.sql` — ajoute la colonne `personne.settings`, qui stocke les préférences personnelles au format JSON (aujourd'hui les valeurs par défaut d'ajout d'événement, sous la clé `events > new_defaults`). Les préférences ajoutées plus tard n'auront pas besoin d'une nouvelle migration
2. `v3-11-0_lieu-lat-lng-decimal.sql` — passe `lieu.lat` et `lieu.lng` de `FLOAT(10,6)` à `DECIMAL(10,7)`. La simple précision perdait environ 50 cm sur des coordonnées genevoises
3. `v3-11-0_bot_monitor-create-table.sql` — crée la table `bot_monitor`. Nécessaire uniquement si vous activez le suivi du trafic automatisé (voir ci-dessous), mais sans effet si vous ne l'activez pas

### app/env.php

Aucune de ces constantes n'est obligatoire : `app/config.php` fournit une valeur par défaut pour la première, les deux autres désactivent des fonctionnalités optionnelles. Le modèle commenté se trouve dans [`app/env_model.php`](app/env_model.php).

| Constante | Rôle |
| --- | --- |
| `SITE_CANONICAL_URL` | URL canonique du site, sans slash final. Sert aux URL absolues des flux RSS et de leurs balises d'autodiscovery. En production la valeur par défaut (`https://www.ladecadanse.ch`) convient ; **en développement**, la définir sur l'URL locale pour que les liens de flux pointent sur votre environnement et non sur la production |
| `EMAIL_COPY_TO_ADMIN` | Copie à l'admin des messages envoyés aux utilisateurs, pour de courtes périodes de surveillance. `false` par défaut |
| `BOT_MONITORING_ENABLED` | Suivi interne du trafic automatisé. `false` par défaut ; **créer la table `bot_monitor` avant d'activer** — voir [docs/bots.md](docs/bots.md) |

### Système de fichiers

Le répertoire `var/cache/rss/` doit être inscriptible par le serveur web : les flux RSS y sont mis en cache. S'il ne l'est pas, les flux sont générés directement à chaque requête — moins performant, mais sans erreur pour le visiteur.

### Effets de bord à connaître

- **Textes TinyMCE** — les présentations de lieux et d'organisateurs enregistrées **avant** cette version ont perdu les `href` de leurs liens internes vers le site (les URL relatives étaient rejetées par le sanitiseur). Le bug est corrigé, mais les textes déjà enregistrés doivent être ré-édités et ré-enregistrés pour retrouver leurs liens
- **Flux `evenement_commentaires`** — retiré il y a environ trois ans avec les commentaires, il répondait `400` ; il répond désormais `410 Gone`, ce qui amène les lecteurs de flux à signaler l'abonnement en erreur et les robots à abandonner l'URL. Les requêtes vers ce flux devraient donc décroître
- **Événements passés** — ils deviennent des archives en lecture seule pour les utilisateurs sous le groupe 6 : plus de formulaire d'édition, plus de suppression. Prévenir les organisateurs concernés, qui doivent maintenant utiliser *Copier* pour reprogrammer un ancien événement — voir [docs/evenements.md](docs/evenements.md)

### Développement

- Node : la version requise est déclarée dans `package.json` (`engines`). Lancer `npm install`, puis `npm test` pour les tests unitaires JS (Vitest)
- Tests : renseigner `LADECADANSE_SITE_URL` dans `tests/.env` pour la nouvelle suite Codeception `site`
