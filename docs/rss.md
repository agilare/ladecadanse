# Flux RSS

Les flux sont servis par `event/rss.php`, avec un paramètre `type` obligatoire et, pour deux d'entre eux, un `id`.

| `type` | `id` | Contenu |
| --- | --- | --- |
| `evenements_auj` | — | Événements du jour |
| `evenements_ajoutes` | — | 20 derniers événements ajoutés |
| `lieu_evenements` | `idLieu` | Prochains événements dans un lieu |
| `organisateur_evenements` | `idOrganisateur` | Prochains événements d'un organisateur |

Les balises d'autodiscovery (`<link rel="alternate">`) sont émises par `HtmlShrink::showLinkRss()` selon la page courante.

## Codes de statut

La validation du `type` et de l'`id` se fait **avant** `require_once bootstrap.php` : une requête invalide n'ouvre ni session ni connexion à la base de données. Ces requêtes représentaient la moitié du trafic du script.

| Situation | Réponse |
| --- | --- |
| `type` absent, inconnu, ou `id` invalide | `400 Bad Request` |
| Flux retiré du site (`evenement_commentaires`) | `410 Gone`, avec un cache d'un mois |
| Lieu ou organisateur inexistant | `404 Not Found` |

Le `410` (plutôt qu'un `400`) est ce qui fait qu'un lecteur de flux signale l'abonnement en erreur et qu'un robot abandonne l'URL. Le flux `evenement_commentaires`, supprimé avec les commentaires il y a environ trois ans, représentait encore 30,5 % des requêtes.

L'`id` est validé par `FILTER_VALIDATE_INT`, qui rejette `1e3`, `1.9`, `null` et les valeurs négatives — ce que `is_numeric()` acceptait. La valeur reçue n'est jamais renvoyée dans la réponse.

## Cache et requêtes conditionnelles

Les réponses sont mises en cache dans `var/cache/rss/` pendant `RSS_CACHE_TTL` (900 s) et servies avec un `ETag` et un `Last-Modified`. Un lecteur qui repasse sans changement reçoit un `304 Not Modified` : sans corps, sans requête SQL et sans rendu. Il n'y avait aucun `304` auparavant.

- le cache est lu **avant** le bootstrap : un hit n'amorce rien ;
- les deux validateurs sont dérivés du contenu, pas de `filemtime()` : ils restent stables tant que l'agenda ne bouge pas, là où l'horodatage du fichier aurait changé à chaque régénération ;
- un répertoire de cache non inscriptible fait dégrader vers la génération directe, jamais vers une erreur 500.

## URL absolues

Les balises d'autodiscovery, les liens vers les flux, les liens à l'intérieur des descriptions d'items et le `rel="self"` sont **absolus**, construits sur la constante `SITE_CANONICAL_URL` (voir [UPGRADE.md](../UPGRADE.md#appenvphp)).

Auparavant ces `href` étaient relatifs et donc résolus contre la page courante : un visiteur arrivé en `http://` enregistrait son abonnement en `http://` et payait une redirection 301 à chaque relève — 39,7 % des requêtes de flux. Le `rel="self"` est reconstruit à partir des paramètres validés et non de `REQUEST_URI`, qui donnait un self-link différent par variante d'URL.

## Contenu des items

Le bloc `<style>` autrefois inclus dans chaque description d'item a été supprimé : poids mort pour les lecteurs qui nettoient le HTML et, pour les autres, un sélecteur non délimité qui débordait sur les autres flux. Le flux d'un lieu y perd 22 % de sa taille.

## Tests

`tests/Site/RssCest.php` (suite Codeception `site`) couvre le contrat de statut, les métadonnées du canal, le self-link canonique, l'absence de `<style>` et la requête conditionnelle.

La lecture des en-têtes de réponse passe par `SiteTester::grabResponseHeader()` : PhpBrowser sait poser des en-têtes de requête, mais pas lire ceux de la réponse.
