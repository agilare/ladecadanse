# Vie privée et conservation des données

Ce que le site garde des personnes, combien de temps, et ce qu'il laisse voir à des tiers.

## Propositions anonymes d'événement

Le formulaire public garde l'adresse du visiteur (`evenement.user_email`) et sa remarque à l'administrateur (`evenement.remarque`). **Deux ans après la date de l'événement**, les deux sont effacées par [`Ladecadanse\EventContactRetention`](../librairies/EventContactRetention.php).

La purge n'a pas de cron : comme le [suivi des bots](bots.md), elle est tentée une page vue sur mille, après l'envoi de la page (`register_shutdown_function`), et aucun échec ne remonte au visiteur. Elle ne parcourt qu'une fenêtre de 90 jours avant le seuil, 500 lignes au plus : `evenement` est en MyISAM, et un `UPDATE` sur tous les événements de plus de deux ans verrouillerait la table à chaque passage pour n'y trouver presque rien. L'arriéré antérieur à la fenêtre relève d'une migration à passer une fois, `Version20260930000000` — voir [UPGRADE.md](../UPGRADE.md).

Un administrateur qui rouvre un tel événement n'y voit plus ni l'adresse ni la remarque, et ne peut plus notifier le visiteur d'une modification.

## Comptes inactifs

Un compte sans connexion depuis **trois ans** reçoit un avertissement par e-mail, puis est **anonymisé trente jours plus tard** si personne n'est revenu — [`Ladecadanse\InactiveAccountRetention`](../librairies/InactiveAccountRetention.php), déclenché par les pages vues comme la purge ci-dessus.

- **Anonymisé, pas supprimé** (`Personne::anonymize()`) : nom d'utilisateur, adresse, affiliation et préférences sont effacés, les rattachements aux lieux et organisateurs supprimés, sans retour possible. La ligne survit, si bien que les événements et descriptions restent publiés, détachés de leur auteur.
- **L'inactivité** se lit sur `last_login`, à défaut sur la dernière modification du profil, puis sur l'inscription — `last_login` n'existe que depuis la 3.6.3. Un compte qui a ajouté un événement ou une description dans les trois ans n'est jamais retenu, quelle que soit sa date de connexion.
- **Une connexion rend son délai complet au compte** : elle remet `personne.inactivity_notified_at` à `NULL`. Le retour par le cookie « Rester connecté-e » compte comme une connexion et met `last_login` à jour, ce qu'il ne faisait pas avant la 3.13.0 : un contributeur revenant ainsi gardait la date de sa dernière saisie de mot de passe, et pouvait paraître dormant depuis des années.
- **Un avertissement qui échoue** — adresse morte, probable après trois ans — marque quand même la date : l'obligation est de prévenir, et sans cette marque le compte serait retenté à chaque passage.
- **Les administrateurs** (niveau `ADMIN` et au-dessus) sont hors du décompte.

Un seul avertissement part par passage, l'envoi SMTP retenant le processus PHP une à trois secondes ; l'anonymisation traite vingt comptes par passage.

Le traitement est commandé par `ACCOUNT_RETENTION_ENABLED` dans `app/env.php`. Tant qu'elle vaut `false` ou manque, rien ne part et rien n'est anonymisé : c'est le temps d'avertir à la main l'arriéré des comptes déjà au-delà du seuil, que le canal automatique mettrait des semaines à servir, au risque de la réputation d'expéditeur du domaine — la marche à suivre est dans [UPGRADE.md](../UPGRADE.md). L'écran `admin/inactive-accounts.php` liste qui est concerné et dit si le traitement tourne, sans rien déclencher.

## Signature des annonces

Un nom d'utilisateur qui ressemble à une adresse e-mail ne signe pas une annonce (`Personne::looksLikeEmail()`). La signature est le seul endroit public où ce champ paraisse, et des comptes y avaient mis leur adresse, publiée sous chacune de leurs annonces et hors de leur portée, le champ n'étant modifiable que par un SUPERADMIN. Le test est volontairement plus large que `FILTER_VALIDATE_EMAIL`, mais exige un point après l'arobase : un `@handle` signe toujours.

## Mesure d'audience

Matomo ne reçoit aucun identifiant de compte (`setUserId`). Un identifiant persistant permet de recoller le parcours d'une personne d'une session et d'un appareil à l'autre, ce qui fait sortir la mesure d'audience de l'exemption de consentement.

## Tiers

- **Bibliothèques front-end** — jQuery et Leaflet sont servis depuis `web/libs/` et non plus depuis `code.jquery.com` et `unpkg.com`, comme les autres [bibliothèques](bibliotheques-front-end.md). Les navigateurs cloisonnant leur cache HTTP par site, le CDN n'épargnait aucun téléchargement ; il montrait en revanche l'adresse IP de chaque visiteur à deux tiers de plus.
- **CSP** — la `Content-Security-Policy` (`app/bootstrap.php`) n'ouvre les hôtes de Known Agents et de GlitchTip que lorsque leur drapeau est actif. Celui de GlitchTip se lit dans son DSN : changer d'instance ne demande pas de seconde modification.
- **`Referrer-Policy`** — `strict-origin-when-cross-origin`, posé par `app/bootstrap.php`. Ressources tierces et liens sortants ne reçoivent que l'origine, et non plus l'URL complète, qui portait le jeton de réinitialisation sur `user/reset2.php`.
