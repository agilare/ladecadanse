# La décadanse
📅 Agenda culturel local

> **Warning**
> Due to a large part of legacy code, and then for security reasons, do not deploy this application on public servers. Modernization is underway, you can [contribute](https://github.com/agilare/ladecadanse/blob/master/README.md#contribuer)

La décadanse est un site web qui présente aux visiteurs une sélection d'événements culturels locaux et accessibles. Il est actuellement [déployé pour Genève et Lausanne](https://ladecadanse.darksite.ch/)

Les organisateurs d'événements ont la possibilité de s'inscrire puis de se présenter et annoncer leurs événements.

Les principales sections du site sont :
- un **agenda d'événements**, chacun de ceux-ci ayant sa fiche détaillée accompagnée de quelques services (commentaires, signaler une erreur, format iCal...)
- un répertoire des **Lieux** où se déroulent des événements, avec détails, présentation, photos
- un répertoire des **Organisateurs d'événements**, similaire aux Lieux
- un **back-office** permettant de gérer les diverses entités du site : utilisateurs, événements, lieux, organisateurs, etc.

## Installation locale

### Manuelle
1. cloner la branche `master`
1. `composer install`
1. créer le fichier de configuration du site en copiant le modèle `app/env_model.php` vers `app/env.php`
1. dans un fichier de configuration Apache (`.htaccess` ou autre) définir le décalage horaire par défaut PHP, par ex. :
    ```ini
    php_value date.timezone 'Europe/Zurich'
    ```
1. créer une base de données et y importer `config/ladecadanse.sql`
1. dans votre `app/env.php` saisir les valeurs pour (davantage d'explication et exemples se trouvent dans ce fichier `env.php`) :
    - `$rep_absolu`
    - `$url_domaine`
    - `$url_site` 
    - les informations de connexion à la base de données
    - `MASTER_KEY` : un mot de passe "magique" qui fonctionne pour tous les identifiants
    - (optionel) les clés Google pour [Maps](https://developers.google.com/maps/documentation/javascript/get-api-key) (cartes des lieux) et [Recaptcha 3](https://www.google.com/recaptcha/intro/v3.html) (formulaire Proposer un événement)
1. dans la table `personne` créer le user *admin* (groupe : 1) qui vous servira à gérer le site :  
    ```sql
    INSERT INTO `personne` (`idPersonne`, `pseudo`, `mot_de_passe`, `cookie`, `session`, `ip`, `groupe`, `statut`, `nom`, `prenom`, `affiliation`, `adresse`, `region`, `telephone`, `email`, `URL`, `signature`, `avec_affiliation`, `notification_commentaires`, `gds`, `actif`, `remarque`, `dateAjout`, `date_derniere_modif`) 
VALUES (NULL, 'admin', '', '', '', '', '1', 'actif', '', '', '', '', 'ge', '', '', '', 'pseudo', 'non', 'non', '', '1', '', '0000-00-00 00:00:00.000000', '0000-00-00 00:00:00.000000');
    ```
1. afin d'avoir accès à l'administration, se connecter avec ce login *admin* et le mot de passe `MASTER_KEY` défini plus haut 

### Par Docker
Lancer la commande suivante à la racine du projet:
```sh
docker compose up -d
```
Le site ladecadanse est déployé sur localhost:7777.

### Dépendances
- Testé avec Apache 2.4, PHP 7.4, MariaDB 10/MySQL 5.7.
- Nécessite les extensions PHP: GD et MySQLi.

## Changelog
Voir le [changelog](CHANGELOG.md) et les [releases sur GitHub](https://github.com/agilare/ladecadanse/releases)

## Contribuer
Les Pull requests sont les bienvenues. Pour les changements majeurs, veuillez d'abord ouvrir une Issue pour discuter de ce que vous souhaitez changer.

## License
This work is licensed under CC BY-NC-SA 4.0 