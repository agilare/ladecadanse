# Suivi des bots

Suivi interne du trafic automatisé, destiné à repérer les robots qui surchargent le site. Désactivé par défaut, et sans effet tant qu'il ne l'est pas.

## Activation

1. créer la table avec `resources/v3-11-0_bot_monitor-create-table.sql` ;
2. passer `BOT_MONITORING_ENABLED` à `true` dans `app/env.php`.

Les autres réglages sont commentés dans [`app/env_model.php`](../app/env_model.php).

## Utilisation

Le tableau de bord est à `admin/bots.php`, réservé aux ADMIN et au-dessus. Les données collectées sont purgées automatiquement après quelques mois.

Le fonctionnement détaillé est documenté dans le code, en commentaires de [`librairies/BotMonitor.php`](../librairies/BotMonitor.php) — le décrire ici le rendrait contournable.
