# La décadanse
Agenda culturel local

La décadanse présente une sélection d'événements culturels accessibles, ouverts et intéressants, et donnant la possibilité aux organisateurs d'ajouter leurs propres événements.

La majeur partie du site est composée d'un agenda permettant de naviguer dans les événements passés ou futurs. Chacun de ceux-ci a sa fiche détaillée avec la possibilité donnée aux personnes inscrites d'y laisser un commentaire. Une rubrique Lieux répertorie des endroits où se déroulent des événements, et sont dans le meilleure des cas accompagnés de photos et de descriptifs. 

## Installation

1. cloner la branche `master`
1. (optionnel) installer [Pear Mail](https://pear.php.net/package/Mail/) pour que l'envoi d'emails fonctionne (les `require_once Mail.php;` dans le code)
1. créer votre fichier de configuration du site en copiant le modèle `config/params_model.php` vers `config/params.php`
1. créer la base de données et y importer `config/ladecadanse.sql`
1. dans votre `config/params.php` saisir (davantage d'explication et exemples se trouvent dans ce fichier `params.php`) :
  - `$rep_absolu`
  - `$url_domaine`
  - `$url_site` 
  - les informations de connexion à la base de données
  - `MASTER_KEY` : un mot de passe "magique" qui fonctionne pour tous les identifiants
  - (optionel) les clés Google pour [Maps](https://developers.google.com/maps/documentation/javascript/get-api-key) (cartes des lieux) et [Recaptcha 3](https://www.google.com/recaptcha/intro/v3.html) (formulaire Proposer un événement)
1. dans la table `personne` ajouter un 1er user de type *admin* (groupe : 1) :
    ```INSERT INTO `personne` (`idPersonne`, `pseudo`, `mot_de_passe`, `cookie`, `session`, `ip`, `groupe`, `statut`, `nom`, `prenom`, `affiliation`, `adresse`, `region`, `telephone`, `email`, `URL`, `signature`, `avec_affiliation`, `notification_commentaires`, `gds`, `actif`, `remarque`, `dateAjout`, `date_derniere_modif`) VALUES (NULL, 'admin', '', '', '', '', '1', 'actif', '', '', '', '', 'ge', '', '', '', 'pseudo', 'non', 'non', '', '1', '', '0000-00-00 00:00:00.000000', '0000-00-00 00:00:00.000000');```
1. se connecter à l'administration avec ce login *admin* et le mot de passe `MASTER_KEY` défini plus haut 

Testé avec Apache 2.4, PHP 7.0, MariaDB 10