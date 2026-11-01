# Documentation

Notes de fonctionnement des parties du site qui demandent plus qu'une ligne de changelog. Pour l'installation, le déploiement et les analyseurs de code, voir le [README](../README.md) ; pour les opérations à effectuer en passant à une nouvelle version, [UPGRADE.md](../UPGRADE.md).

Trois dossiers, selon ce que l'on cherche : comment le site se comporte, comment on l'exploite, comment on le développe.

## Fonctionnalités

Dans `fonctionnalites/`.

- [Agenda](fonctionnalites/agenda.md) — filtre par catégorie et repères de temporalité sur la liste du jour
- [Favoris](fonctionnalites/favoris.md) — marque-page, filtre des listes, page Favoris et API
- [Événements](fonctionnalites/evenements.md) — archivage des événements passés, valeurs par défaut personnelles à l'ajout, nommage des fichiers, catégories et leur repli en préversion, suppression et dépublication
- [Administration des événements](fonctionnalites/admin-evenements.md) — liste, filtres mémorisés, édition groupée
- [Comptes](fonctionnalites/comptes.md) — pages sous `user/`, règles du mot de passe, réinitialisation, rester connecté-e, déconnexion
- [Lieux et organisateurs](fonctionnalites/lieux-organisateurs.md) — formulaires de fiche, salles, textes d'un lieu, note d'administration, activité mensuelle
- [Interface](fonctionnalites/interface.md) — feuilles de style et seuil desktop/mobile, icônes, fiche d'un événement, annonces de l'accueil, mises en page mobiles, [raccourcis clavier](fonctionnalites/interface.md#raccourcis-clavier) et mode *mouseless*
- [Flux RSS](fonctionnalites/rss.md) — types de flux, cache, codes de statut, URL absolues
- [Vie privée](fonctionnalites/vie-privee.md) — conservation des contacts de proposition et des comptes inactifs, signature, Matomo, tiers

## Exploitation

Dans `exploitation/`.

- [Configuration serveur](exploitation/config-serveur.md) — `.htaccess` et `.user.ini` composés depuis des fragments, publics et privés, et déployés avec le code
- [Déploiement](exploitation/deploiement.md) — `composer deploy`, `.htaccess`, migration de la base de production
- [Copie de la production](exploitation/prod-copy.md) — peupler une base locale anonymisée et ses uploads depuis la production, par SSH
- [Conversion des PDF](exploitation/conversion-pdf.md) — drapeau `PDF_CONVERSION_ENABLED`, pdf.js et Imagick
- [Suivi des bots](exploitation/bots.md) — activation et tableau de bord

## Développement

Dans `developpement/`.

- [Docker](developpement/docker.md) — environnements, permissions sous Linux, Make, base de données et migrations
- [Bibliothèques front-end](developpement/bibliotheques-front-end.md) — `npm ci`, `web/libs/`, mise à jour et ajout d'une bibliothèque
- [Analyse statique](developpement/analyse-statique.md) — PHPStan, Psalm, Rector, PHPCompatibility

## Où ranger une nouvelle note

Ce qu'un visiteur ou un contributeur voit du site va dans `fonctionnalites/`, ce qui touche au serveur, à la production ou à ses données dans `exploitation/`, l'outillage d'un poste de développement dans `developpement/`. Les noms de fichiers sont en minuscules, sans accent, séparés par des tirets ; toute nouvelle note s'ajoute à la liste ci-dessus.
