# Bibliothèques front-end

Les bibliothèques servies au navigateur depuis le site même — jQuery, Leaflet, Font Awesome, Magnific Popup, select2, Zebra_Datepicker, checkboxes.js, normalize.css, pdf.js — sont déclarées dans les `dependencies` de `package.json`, à version exacte. Elles ne sont pas versionnées : `npm ci` les télécharge dans `node_modules/`, puis son hook `postinstall` lance `bin/libs-sync.mjs`, qui copie dans `web/libs/` les seuls fichiers que les pages chargent. Aucune étape de build : ce sont les fichiers publiés par chaque projet, tels quels.

TinyMCE et le SDK Sentry restent chargés depuis leur CDN : tous deux sont liés à un service (clé d'API, DSN), pas à un fichier qu'on pourrait figer. Les tuiles de la carte viennent d'OpenStreetMap pour la même raison.

> [!NOTE]
> `npm ci` avertit `EBADENGINE` sous Node 22 : select2 4.1.0 déclare exiger Node 24 pour ses propres outils de build, dont rien ne sert ici puisque seuls ses fichiers publiés sont copiés. L'avertissement est sans conséquence.

## Mettre à jour une bibliothèque

```sh
npm outdated                                # ce qui a du retard
npm install --save-exact select2@4.1.1      # met à jour package.json, package-lock.json et web/libs/
```

Si la nouvelle version déplace ou renomme un fichier, `npm install` échoue en nommant la source introuvable : corriger la liste de `bin/libs-sync.mjs`, puis `npm run libs:sync`.

## Ajouter une bibliothèque

Une entrée dans `dependencies`, une ligne par fichier dans `bin/libs-sync.mjs`, et la balise dans `_header.inc.php` ou `_footer.inc.php`.

## En production

`web/libs/` part en production avec le code, sans Node sur le serveur : voir [Déploiement](../README.md#pour-mettre-à-jour-avec-les-derniers-commits).
