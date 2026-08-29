# Mise à jour

Ce fichier liste les opérations à effectuer lors du passage à une nouvelle version : migrations de base de données, nouvelles clés de configuration, effets de bord à connaître. Pour la liste des changements eux-mêmes, voir le [changelog](CHANGELOG.md) ; pour le fonctionnement des fonctionnalités, [docs/](docs/) ; pour l'inventaire des scripts SQL, la version qui a livré chacun et une requête disant ce qui manque à une base, [resources/database/README.md](resources/database/README.md).

Les versions sont listées de la plus récente à la plus ancienne.

## Non publié

### Redirections

`admin/gererEvenements.php` devient `admin/events.php`. La redirection 301 est dans
[`htaccess/50-routage.conf`](htaccess/50-routage.conf) et part avec le code : rien à reporter à la
main sur le serveur, mais `composer config:build` doit être passé avant la mise en ligne, comme
pour tout changement d'un fragment de configuration — voir [docs/config-serveur.md](docs/config-serveur.md).

La page n'a ni lien entrant public ni référencement : la redirection est là pour les signets et
l'historique des administrateurs.

### Effets de bord à connaître

- **Édition groupée et organisateurs** — un remplacement groupé effaçait jusqu'ici les
  organisateurs de tous les événements sélectionnés, même quand le champ était laissé vide. Il ne
  les touche plus que si le champ a été rempli, conformément à la règle annoncée par la page.
  Conséquence : **il n'est plus possible de retirer les organisateurs en masse** en envoyant le
  formulaire avec un champ vide. Rien ne le permettait vraiment — l'ancien comportement était un
  effacement subi, pas une commande.
- **Préférences de liste** — filtres, tri et nombre de lignes de `admin/events.php` sont désormais
  mémorisés en session (`user_prefs_even_*`), comme ceux de `admin/users.php`. À la première visite
  après la mise à jour, la liste repart donc sur ses valeurs par défaut. Les anciens paramètres
  d'URL (`tri_gerer`, `ordre`, `filtre_genre`, `element`, `nblignes`) ne sont plus lus : un signet
  qui en porterait ouvre la liste sans filtre plutôt qu'en erreur.
- **Nombre de lignes** — le menu propose 50, 250 et 500 ; le 100 disparaît de cette page seulement,
  `$tab_nblignes` reste inchangé pour `admin/users.php` et `admin/bots.php`.
- **Images des envois groupés** — un flyer ou une image posé par édition groupée est désormais
  redimensionné en 600×600 avec une miniature non rognée, comme dans `evenement-edit.php` (et non
  plus 400×400 avec miniature rognée). Les images déjà en base ne sont pas retraitées.

Aucune migration de base de données.

## 3.12.0

### Base de données

Exécuter `resources/database/v3-12-0_localite-france.sql`, **d'une seule traite** : le fichier pose une variable de connexion (`LAST_INSERT_ID()`) que deux `UPDATE` relisent ensuite, et exécuter les instructions séparément la perdrait.

Il fait quatre choses :

1. passe `localite.npa` de `INT(4)` à `VARCHAR(6)` — un code postal français compte cinq chiffres, dont certains commencent par un zéro (01210 Ferney-Voltaire) qu'un entier perdrait. Les NPA suisses existants sont convertis tels quels
2. ajoute la localité « Ailleurs en France », de canton `rf` : elle recueille les adresses françaises dont la commune n'est pas encore listée
3. y déplace les événements et les lieux jusqu'ici rattachés à la localité 1 avec `region = 'rf'` (152 événements et 2 lieux sur la base de développement)
4. renomme la localité 1 en « Hors Genève, Vaud et France » et lui donne le canton `hs`, qu'elle n'avait pas

**À passer avec la mise en ligne du code, pas plus tard** : tant qu'elle ne l'est pas, la localité 1 garde un canton vide, les formulaires ouvrent donc un `<optgroup>` sans libellé, et la France n'est plus proposée du tout — son entrée codée en dur a disparu du code.

Les deux libellés ci-dessus sont écrits à l'identique dans le fichier SQL et dans `Ladecadanse\Localite::LOCALITES_FOURRE_TOUT` ; c'est sur cette égalité que repose leur effacement de l'affichage des adresses. Les renommer suppose de le faire des deux côtés — un test unitaire relit le `.sql` pour le vérifier.

### Redirections

Trois pages de compte rejoignent `user/`, à côté de `login.php` et `dashboard.php`. Les redirections 301 sont dans [`htaccess/50-routage.conf`](htaccess/50-routage.conf) et partent avec le code : il n'y a plus rien à reporter à la main sur le serveur.

| Ancienne URL | Nouvelle |
| --- | --- |
| `/user-register.php` | `/user/register.php` |
| `/user-reset.php` | `/user/reset.php` |
| `/user-reset2.php` | `/user/reset2.php` |

Les liens de réinitialisation déjà envoyés par mail restent valables 24 h : sans la redirection, ils tombent en 404 pendant une journée. La query string `?token=` est reportée d'office, la substitution n'en portant aucune.

### Effets de bord à connaître

- **Déconnexion en POST** — `user-logout.php` disparaît sans redirection : une déconnexion en GET partait toute seule au moindre préchargement de lien. Un onglet resté ouvert sur une page rendue *avant* la mise à jour porte encore l'ancien lien et tombera en 404 ; il suffit de recharger la page. Signets et liens externes vers `/user-logout.php` cessent de fonctionner — voir [docs/comptes.md](docs/comptes.md)
- **Mots de passe** — la liste des mots de passe refusés passe de 22 à 19 999 entrées. Les mots de passe existants ne sont pas vérifiés, rien n'est bloqué rétroactivement : la règle ne s'applique qu'au prochain changement. Les comptes non actifs (`statut` autre que `actif`) ne peuvent plus demander de réinitialisation, l'ancien filtre laissait passer les comptes en attente que la connexion refuse ensuite de toute façon
- **`resources/`** — les corps de mail passent sous `resources/templates/`, les scripts sql sous `resources/database/`. Adapter tout montage ou script maison qui les référence (le `docker-compose.yml` du dépôt est à jour)
- **Localités françaises** — la France et « ailleurs » cessent d'être des entrées codées en dur dans les formulaires : ce sont des localités de la table `localite`, de cantons `rf` et `hs`. Ajouter une commune française ne demande donc plus de toucher au code, seulement un `INSERT INTO localite (localite, commune, npa, canton, regions_covered) VALUES ('Annemasse', 'Annemasse', '74100', 'rf', 'ge,rf')`. Les deux localités fourre-tout, elles, ne s'affichent jamais dans une adresse : seule la région apparaît, comme avant

### Développement

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
