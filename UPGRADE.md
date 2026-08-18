# Mise à jour

Ce fichier liste les opérations à effectuer lors du passage à une nouvelle version : migrations de base de données, nouvelles clés de configuration, effets de bord à connaître. Pour la liste des changements eux-mêmes, voir le [changelog](CHANGELOG.md) ; pour le fonctionnement des fonctionnalités, [docs/](docs/).

Les versions sont listées de la plus récente à la plus ancienne.

## 3.11.0

### Base de données

Exécuter, dans cet ordre, les fichiers du répertoire `resources/` :

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
