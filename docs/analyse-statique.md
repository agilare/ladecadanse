# Analyse statique du code

Les analyseurs de code PHP se lancent par Composer. Le [README](../README.md#analyse-du-code) en donne la liste et les commandes ; ce fichier-ci dit comment les lire.

Points communs :

- ils sont configurés pour la version de PHP requise (8.4) ;
- le niveau d'analyse est réglé aussi haut que possible, mais pas trop, pour ne pas relever les erreurs dues à l'ancienneté du code ; certaines erreurs peu ou pas pertinentes sont ignorées ;
- les répertoires `vendor`, `var`, etc. sont ignorés.

## PHPStan

```sh
$ composer phpstan
```

Les erreurs nombreuses et peu importantes sont ignorées via `phpstan-baseline.neon`.

## Rector

Aperçu, sans modifier les fichiers :

```sh
$ composer rector:dry-run
```

## Rector Jack

Aide à repérer et mettre à jour les dépendances Composer obsolètes (`rector/jack`, séparé de Rector) :

```sh
$ ./vendor/bin/jack list
```

Commandes utiles : `breakpoint` (échoue si trop de paquets majeurs sont en retard, utile en CI), `open-versions` (assouplit les contraintes de version vers la version suivante), `raise-to-installed` (aligne `composer.json` sur les versions installées).

## Psalm

```sh
$ composer psalm
```

Doit rester vert : les problèmes connus sont dans `psalm-baseline.xml`, à régénérer avec `./vendor/bin/psalm --set-baseline=psalm-baseline.xml` après une montée de version.

Les globales du legacy (`$connector`, `$glo_*`, `$rep_*`…) sont déclarées dans la section `<globals>` de `psalm.xml` : l'ajout d'une globale dans `app/config.php` ou `app/bootstrap.php` implique de l'y déclarer aussi.

### Analyse de teinte

Recherche de données utilisateur atteignant un point sensible : SQL, `include`, en-têtes, requêtes réseau… Complémentaire de PHPStan, qui ne fait pas ce type d'analyse :

```sh
$ composer psalm:taint
```

Attention au bruit : l'essentiel des résultats est du `TaintedHtml`/`TaintedTextWithQuotes` sur le vieux code d'affichage. Les catégories à regarder en priorité sont `TaintedSql`, `TaintedFile`, `TaintedSSRF`, `TaintedHeader` et `TaintedCookie`.

L'unique `TaintedSql` restant (rapporté sur `DbConnector::query()`, tracé jusqu'à `user-edit.php`) est un faux positif documenté dans le code : Psalm teinte les *clés* de `$champs` alors que seules les valeurs viennent de `$_POST`. Il n'est pas supprimable via `@psalm-suppress`, l'erreur étant ancrée sur le sink et non sur le site d'appel.

## Phan

```sh
./vendor/bin/phan --progress-bar -o phan.txt
```

Puis éventuellement, pour abréger le rapport :

```sh
cat phan.txt | cut -d ' ' -f2 | sort | uniq -c | sort -n -r
```

## PHPCompatibility

Disponible de PHP 8.0 à 8.4. Pour 8.4 :

```sh
$ composer sniffer:php84
```

> [!NOTE]
> `squizlabs/php_codesniffer` reste volontairement sur la branche `^3.13` : la version 4.0 n'est pour l'instant supportée que par une version alpha de `phpcompatibility/php-compatibility` (`10.0.0-alpha2`). À réévaluer quand une version stable sortira.
