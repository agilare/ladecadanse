# La décadanse
Agenda culturel local

La décadanse présente une sélection d'événements culturels accessibles, ouverts et intéressants, et donnant la possibilité aux organisateurs d'ajouter leurs propres événements.

La majeur partie du site est composée d'un agenda permettant de naviguer dans les événements passés ou futurs. Chacun de ceux-ci a sa fiche détaillée avec la possibilité donnée aux personnes inscrites d'y laisser un commentaire. Une rubrique Lieux répertorie des endroits où se déroulent des événements, et sont dans le meilleure des cas accompagnés de photos et de descriptifs. 

## installation

1. (optionnel) installer pear mail pour que les require_once "Mail.php"; dans le code fonctionnent
2. renommer config/params_model.php en config/params.php
3. créer la base de données et y importer ladecadanse.sql
4. dans config/params.php saisir le path et url du site ainsi que les données de connexion à la base de données
5. afin de gérer le site, créer user *admin* (groupe 1) dans la table 'personne' à la main avec pour le mot de passe : sha1($gds.sha1($pass)) (à faire par ex. en PHP CLI)

Testé avec Apache 2.4, PHP 5.6, MariaDB 10