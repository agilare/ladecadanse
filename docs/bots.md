# Suivi des bots

Suivi interne du trafic automatisé, destiné à repérer les robots qui surchargent le site. Désactivé par défaut, et sans effet tant qu'il ne l'est pas.

## Activation

1. créer et compléter la table par `composer db:migrate` (migrations `Version20260716000000` et `Version20261003000000`, voir [resources/database/README.md](../resources/database/README.md)) ;
2. passer `BOT_MONITORING_ENABLED` à `true` dans `app/env.php`.

Les autres réglages sont commentés dans [`app/env_model.php`](../app/env_model.php).

## Utilisation

Le tableau de bord est à `admin/bots.php`, réservé aux ADMIN et au-dessus. Les données collectées sont purgées automatiquement après quelques mois.

La page s'explique elle-même : un mode d'emploi replié en tête (« Comment lire ce tableau de bord », avec la légende des types), une phrase en tête de chaque onglet dont le détail se déplie, et un bouton « i » sur les en-têtes de colonne qui demandent une explication.

Chaque onglet répond à une question :

| Onglet | Question |
| --- | --- |
| Scrapers avérés | qui aspire le site en ignorant `robots.txt` ? |
| Humains suspects | quel visiteur qui se dit navigateur en a le volume d'un robot ? |
| Rafales | qui surcharge le site — en ce moment, et au plus fort des derniers mois ? |
| Sondeurs | qui cherche des failles, itère sur des adresses qui n'existent pas, ou insiste malgré un blocage ? |
| Statistiques des bots | que pèsent les robots déclarés, famille par famille ? |

Chaque ligne porte un **type**, qui combine ce que l'adresse annonce — son User-Agent, qui se choisit librement — et ce qu'elle a fait :

| Type | Lecture |
| --- | --- |
| Scraper | a suivi le lien piège, quel que soit le navigateur ou le robot annoncé |
| Moteur de recherche, Robot IA, Outil SEO, Autre robot | robots déclarés dans leur User-Agent, rangés par famille |
| Script | User-Agent sans signature de navigateur : `node`, `curl`, agrégateurs… |
| Navigateur suspect | navigateur annoncé, mais un volume ou un rythme de robot — ou une adresse partagée |
| Navigateur | un visiteur, jusqu'à preuve du contraire |

Le fonctionnement détaillé est documenté dans le code, en commentaires de [`librairies/BotMonitor.php`](../librairies/BotMonitor.php) — le décrire ici le rendrait contournable.
