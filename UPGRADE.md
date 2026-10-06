# Mise à jour

Ce fichier liste les opérations à effectuer lors du passage à une nouvelle version : migrations de base de données, nouvelles clés de configuration, effets de bord à connaître. Pour la liste des changements eux-mêmes, voir le [changelog](CHANGELOG.md) ; pour le fonctionnement des fonctionnalités, [docs/](docs/) ; pour les migrations de base de données et la façon de les passer, [resources/database/README.md](resources/database/README.md).

Les versions sont listées de la plus récente à la plus ancienne.

## 3.13.0 (non publié)

### Base de données

**Les migrations passent désormais par [Doctrine Migrations](https://www.doctrine-project.org/projects/migrations.html)** (#231). Les scripts `resources/database/vX-Y-Z_*.sql` sont devenus des classes de `resources/database/migrations/`, et chaque base tient dans sa table `doctrine_migration_versions` la liste de ce qui lui a été appliqué. Rien n'est à déployer pour autant : ni `vendor/` ni les migrations ne partent sur le serveur, elles se passent depuis le poste de développement.

Une fois pour toutes, sur le poste : `composer install`, qui installe `doctrine/migrations` (dépendance de développement). Puis, pour la production, ouvrir le tunnel SSH décrit dans [docs/prod-copy.md](docs/prod-copy.md), faire un `mysqldump` de la base, et :

```sh
LADECADANSE_DB=prod composer db:status    # ce qui est appliqué, ce qui manque
LADECADANSE_DB=prod composer db:migrate   # demande confirmation avant d'écrire
```

Sous PowerShell : `$env:LADECADANSE_DB='prod'; composer db:migrate`. L'entrée `prod` de `app/db.config.php` sert telle quelle ; son compte doit avoir les droits `ALTER`, `CREATE`, `INDEX` et `DROP`, sinon lui substituer `migration_user` et `migration_password` (voir `app/db.config_model.php`).

Le premier passage **adopte la base** : chaque migration antérieure à Doctrine vérifie d'abord si son effet est déjà en place — la colonne, l'index, la table ou la ligne qu'elle crée — et, le cas échéant, s'enregistre sans rien exécuter. Doctrine l'accompagne d'un avertissement « did not result in any SQL statements », attendu. Une production en 3.12.0 voit ainsi les dix migrations jusqu'à la 3.12.0 enregistrées à vide, et les neuf de la 3.13.0 réellement passées, dans l'ordre :

1. `Version20260905000000` (ancien `v3-13-0_lieu-colonnes.sql`) remanie les colonnes de la table `lieu` :
    1. `determinant` devient `preposition_nom` et passe après `nom` — « au », « chez », « à l' » sont des prépositions, pas des déterminants ;
    1. `categorie` devient `categories` et passe après `preposition_nom` — la colonne est un `SET`, un lieu en porte plusieurs ;
    1. `adresse` passe de `VARCHAR(100)` à `VARCHAR(255)` ;
    1. `lat`, `lng`, `horaire_general`, `URL`, `photo1` et `logo` acceptent `NULL`, et les lignes existantes y passent de `0` ou de la chaîne vide à `NULL` ; `logo` passe après `categories` ;
    1. `photo2` et `actif` sont supprimées — la seconde photo n'était proposée par aucun formulaire, et `actif` était un doublon inerte de `statut`, resté à 1 partout.
2. `Version20260908000000` (ancien `v3-13-0_lieu-categories.sql`) ajoute sept valeurs à la fin du `SET` `categories` que la précédente vient de créer — `buvette`, `club`, `quartier`, `socioculturel`, `bibliotheque`, `ludotheque`, `ecole` — sans en retirer ni en renommer aucune. Les sept valeurs sont **appendées après `autre`**, et l'ordre du `SET` n'est pas négociable : un `SET` MariaDB est un masque de bits dont les positions viennent de l'ordre de déclaration, si bien qu'une valeur glissée au milieu de la liste réinterpréterait silencieusement toutes les lignes existantes. L'ordre de `Ladecadanse\Lieu::CATEGORIES`, lui, sert l'affichage du formulaire et garde « autre » en dernier : les deux diffèrent volontairement, il ne faut pas « ranger » le `SET` pour le faire correspondre au PHP.
3. `Version20260916000000` (ancien `v3-13-0_lieu-organisateur-add-admin_note.sql`) ajoute à `lieu` (après `URL`) et à `organisateur` (après `statut`) une colonne `admin_note` en `TEXT NULL`, la note d'administration que seuls les administrateurs lisent et écrivent.
4. `Version20260926000000` (ancien `v3-13-0_personne-evenement-create-table.sql`) crée la table `personne_evenement` des favoris : une ligne par couple (personne, événement), clé primaire composite — c'est elle qui rend l'ajout idempotent, l'`INSERT IGNORE` de `event/favorites.php` s'appuyant dessus — et un index sur `idEvenement` pour le sens inverse.

**À passer avec la mise en ligne du code, pas plus tard** : les deux renommages de la première sont lus par les pages d'événement (`l.preposition_nom`) et par la liste des lieux (`FIND_IN_SET(…, categories)`), qui répondraient sinon une erreur SQL. Les trois autres pourraient précéder le code — l'ancien code ignore une colonne qui accepte `NULL` et une table qu'il ne connaît pas —, mais pas le suivre : le formulaire proposerait sept catégories que le `SET` ne déclare pas (erreur ou troncature silencieuse selon le `sql_mode`, jamais la catégorie choisie), l'enregistrement d'une fiche écrirait une colonne `admin_note` absente, et le premier lien secret des favoris tomberait sur une erreur SQL.

Les tables `lieu` et `organisateur` sont en MyISAM : chaque `ALTER` les reconstruit et pose un verrou d'écriture. Quelques centaines de lignes, donc l'affaire d'un instant, mais à passer hors des heures de saisie. Relever `SELECT categories, COUNT(*) AS nb FROM lieu GROUP BY categories ORDER BY categories;` avant la deuxième migration et après, et comparer : les deux sorties doivent être rigoureusement identiques. Pour s'arrêter entre deux migrations, `composer db:migrate -- 'Ladecadanse\Migrations\Version20260905000000'` ne va que jusqu'à celle-là.

Aucune transaction ne protège le passage : sur MariaDB, chaque instruction DDL valide implicitement la transaction en cours, et une migration interrompue laisse en place ce qui a déjà tourné. D'où le `mysqldump` préalable.

La cinquième, `Version20260930000000` (ancien `v3-13-0_evenement-purge-contact.sql`), est indépendante des précédentes. Elle efface l'adresse (`user_email`) et la remarque des propositions anonymes d'événement datées de plus de deux ans — la durée de conservation retenue. La suite est tenue par l'application, qui purge au fil des pages vues les événements franchissant le seuil (`Ladecadanse\EventContactRetention`) ; mais elle ne regarde qu'une fenêtre de 90 jours avant le seuil, si bien que **sans cette migration l'arriéré reste en base**.

**Indifférente au moment de la mise en ligne** : le code n'en dépend pas. `evenement` est en MyISAM, l'`UPDATE` pose un verrou d'écriture le temps de parcourir les événements de plus de deux ans : à passer hors des heures de saisie. Elle n'efface rien de plus si l'ancien script a déjà tourné : l'`UPDATE` ne porte que sur les lignes encore renseignées, et la migration ne cherche donc pas à détecter un passage antérieur.

La sixième, `Version20261001000000`, ajoute `personne.inactivity_notified_at`, qui retient la date de l'avertissement envoyé à un compte inactif. **À passer avant la mise en ligne** : le code lit et écrit cette colonne à chaque connexion, et une base qui ne l'a pas répondrait par une erreur SQL. L'`ALTER` reconstruit la table `personne`, qui tient dans quelques centaines de lignes.

**Le code se déploie à l'arrêt, et l'arriéré s'avertit à la main avant la mise en route.** Au 01.10.2026, la production comptait 5 464 comptes au-dessus du seuil. Laissés au canal automatique, qui n'envoie qu'un avertissement par passage — soit une centaine par jour pour 107 500 pages servies —, ils demanderaient **55 jours d'envois continus vers des adresses vieilles de trois à vingt ans**. Le volume de rebonds abîmerait la réputation d'expéditeur du domaine, et avec elle la délivrabilité des messages qui comptent : réinitialisation de mot de passe, confirmation d'annonce.

C'est à quoi sert `ACCOUNT_RETENTION_ENABLED` (voir [app/env.php](#appenvphp) plus bas) : tant qu'elle vaut `false` ou qu'elle manque, rien ne part et rien n'est anonymisé. Le reste du code se déploie donc sans attendre, et l'écran d'administration rappelle l'arrêt en tête de liste.

La marche à suivre, une fois :

1. **Déployer**, `ACCOUNT_RETENTION_ENABLED` absente ou à `false`, la migration passée.

2. **Exporter les candidats** depuis phpMyAdmin, au format qu'attend [admin/mailing.php](admin/mailing.php) :

    ```sql
    SELECT idPersonne, pseudo, email FROM personne
    WHERE groupe > 4 AND mot_de_passe <> ''
      AND COALESCE(last_login, date_derniere_modif, dateAjout) < DATE_SUB(CURDATE(), INTERVAL 3 YEAR)
      AND NOT EXISTS (SELECT 1 FROM evenement e WHERE e.idPersonne = personne.idPersonne AND e.dateAjout > DATE_SUB(CURDATE(), INTERVAL 3 YEAR))
      AND NOT EXISTS (SELECT 1 FROM descriptionlieu d WHERE d.idPersonne = personne.idPersonne AND d.dateAjout > DATE_SUB(CURDATE(), INTERVAL 3 YEAR));
    ```

3. **Envoyer par `admin/mailing.php`**, par lots de 50. Le message doit porter la mention de l'anonymisation à venir : il tient alors lieu d'avertissement, et il n'y en aura pas d'autre. Compter une à deux minutes par lot, et 110 lots. Reprendre une séance interrompue dans l'heure : la session expire au-delà et le mailing en cours est perdu.

4. **Marquer les comptes servis**, avec exactement le même `WHERE` que l'export :

    ```sql
    UPDATE personne SET inactivity_notified_at = NOW() WHERE … /* le WHERE ci-dessus */;
    ```

    Sans cette étape, le canal automatique réavertirait ces 5 464 comptes une fois mis en route.

5. **Mettre le canal en route** : `define("ACCOUNT_RETENTION_ENABLED", true);` dans `app/env.php` sur le serveur, sans redéploiement. Les comptes marqués à l'étape 4 deviennent anonymisables trente jours après leur marquage ; il ne reste alors que du SQL, vingt comptes par passage, soit environ trois jours.

L'écran [admin/inactive-accounts.php](admin/inactive-accounts.php) donne la liste à tout moment, sans rien déclencher, et dit si le canal tourne.

La septième, `Version20261003000000`, ajoute six colonnes à `bot_monitor`, la table du suivi des bots (voir [docs/bots.md](docs/bots.md)) : la fenêtre de comptage en cours (`window_start`, `window_hits`), le plus fort pic de l'IP (`peak_hits`, `peak_start`), et ses requêtes terminées en erreur (`error_hits`, `last_error_path`). Elle suppose la table créée par `Version20260716000000`, que `db:migrate` passe avant elle — une production sans la table reçoit les deux d'un coup.

**À passer avant la mise en ligne du code**, que le suivi soit activé ou non au moment de la mise à jour : les six colonnes ont un défaut, l'ancien code les ignore donc sans dommage. L'inverse ne vaut pas. Avec `BOT_MONITORING_ENABLED` à `true` et la base en retard, aucune page ne tombe — l'échec est rattrapé — mais plus aucune visite n'est comptée, et **chaque page vue écrit un warning dans le log**. Les lignes existantes gardent leurs compteurs de fenêtre à zéro jusqu'à la prochaine visite de leur IP ; rien n'est recalculé. `bot_monitor` est en InnoDB : pas de verrou d'écriture, l'`ALTER` est l'affaire d'un instant.

La huitième, `Version20261006000000`, crée la table `remember_token` : un jeton « Rester connecté-e » par appareil, là où `personne.cookie` n'en gardait qu'un par compte (voir [docs/comptes.md](docs/comptes.md#rester-connecté-e)). **À passer avant la mise en ligne du code** : l'ancien code ignore la table, le nouveau la lit à chaque retour par le cookie et répondrait sinon par une erreur SQL. `personne.cookie` reste en place, inerte, jusqu'à une migration ultérieure.

La neuvième, `Version20261006120000`, ajoute `personne.session_epoch`, le compteur qui permet à « Se déconnecter des autres appareils » de fermer les sessions ouvertes ailleurs. **À passer avant la mise en ligne du code**, qui lit la colonne à chaque page vue d'une personne connectée. Les sessions ouvertes avant la mise à jour survivent : elles n'ont pas la valeur en session, lue comme 0, le défaut. L'`ALTER` reconstruit `personne` (MyISAM), quelques centaines de lignes.

### Redirections

Deux pages changent d'adresse :

| ancienne | nouvelle |
|---|---|
| `/lieu-edit.php` | `/lieu/edit.php` |
| `/lieu-text-edit.php` | `/lieu/text-edit.php` |

Les redirections 301 sont dans [`htaccess/50-routage.conf`](htaccess/50-routage.conf) et partent avec le code, mais `composer config:build` doit être passé avant la mise en ligne, comme pour tout changement d'un fragment de configuration — voir [docs/config-serveur.md](docs/config-serveur.md). La query string est reportée d'office : les signets des éditeurs (`?action=editer&idL=…`, `?idL=…&type=presentation`) arrivent au bon endroit. Ni l'une ni l'autre n'est indexée, formulaires réservés aux connectés ; les redirections sont là pour les signets et l'historique.

### app/env.php

Trois constantes s'ajoutent. Aucune n'est obligatoire : absentes, les deux premières valent `false` et la troisième une liste vide, et aucune page ne tombe en erreur. Le modèle commenté est dans [`app/env_model.php`](app/env_model.php).

| Constante | Rôle |
| --- | --- |
| `ACCOUNT_RETENTION_ENABLED` | Mettre en route le traitement des comptes inactifs : avertissement, puis anonymisation trente jours plus tard. `false` par défaut, le temps d'avertir l'arriéré à la main — voir [Base de données](#base-de-données) plus haut. À ne pas laisser à `false` passé cette mise en route : c'est une durée de conservation, pas une option |
| `EVENT_NEW_CATEGORIES_ENABLED` | Proposer les catégories d'événement encore en préversion — aujourd'hui « cours/ateliers/stages » seule. `false` par défaut ; `'preview'` la réserve aux administrateurs, `true` l'ouvre à tous. « concerts » ne dépend d'aucun drapeau — voir [docs/evenements.md](docs/evenements.md#catégories-en-préversion) |
| `HOME_BANNERS` | Annonces affichées au-dessus de l'agenda, une entrée par annonce : `date`, `type` (`info`, `warn`, `danger`), `audience` (`tous`, ou `contributeurs` pour le niveau ACTOR et au-dessus), `titre` et `contenu`, écrits tels quels en HTML. La date identifie l'annonce : la changer la fait réapparaître chez ceux qui l'avaient fermée. `[]` = aucune annonce |

`HOME_BANNERS` remplace les six constantes `HOME_TMP_BANNER_*` et `HOME_TMP_BACK_BANNER_*`, que plus rien ne lit : **une annonce active en production disparaît à la mise en ligne tant qu'elle n'est pas reportée** dans la nouvelle liste — l'ancienne bannière publique avec `'audience' => 'tous'` et le type `warn`, celle des connectés avec `'contributeurs'` et `info`. Les anciennes lignes peuvent ensuite être retirées, sans urgence.

Rien à passer en base : `evenement.genre` est un `varchar(20)`, il accueille les deux valeurs sans migration. Hors préversion, un événement classé `cours` s'affiche et se range en « divers » : **rétrograder le drapeau ne perd aucun reclassement**, les événements retrouvent leur catégorie dès qu'il remonte.

### Effets de bord à connaître

- **Une reconnexion pour les appareils mémorisés** — les cookies « Rester connecté-e » posés avant la mise à jour ne sont plus reconnus : chaque personne qui avait coché la case se retrouve déconnectée une fois, et la recoche. Changer son mot de passe déconnecte désormais aussi les autres appareils mémorisés, ce qu'il ne faisait pas
- **Comptes sans connexion depuis trois ans**, une fois `ACCOUNT_RETENTION_ENABLED` posée — ils reçoivent un avertissement, puis sont anonymisés un mois plus tard : nom d'utilisateur, adresse, affiliation et préférences effacés, rattachements aux lieux et organisateurs supprimés, sans retour possible. Leurs événements et descriptions restent publiés, détachés de leur auteur. Les administrateurs sont hors du décompte, et une connexion — y compris par le cookie « rester connecté-e » — rend son délai complet au compte. Un avertissement qui rebondit sur une adresse morte n'empêche pas l'anonymisation
- **« Concerts » ouverte à tous dès la mise en ligne**, sans rien régler. Un événement classé `concerts` pendant la préversion apparaît sous « Concerts » pour tout le monde ; le titre de ses articles RSS dit « concerts » là où il disait « fête », et l'API ne le renvoie plus pour `category=fête` mais pour `category=concerts`
- **Ancres des sections de l'agenda** — elles passent par `Text::slug()`, qui met en minuscules et remplace tout caractère non alphanumérique par un tiret, là où `stripAccents()` ne retirait que les diacritiques. Les cinq ancres existantes (`#fetes`, `#cine`, `#theatre`, `#expos`, `#divers`) ne bougent pas ; seule « cours/ateliers/stages » en avait besoin, son libellé portant des barres obliques
- **Charte éditoriale** — [`articles/charte-editoriale.php`](articles/charte-editoriale.php) annonce désormais six catégories et décrit « Concerts » : les prestations musicales en live, construites autour d'artistes annoncés que le public vient écouter. Une soirée où la musique accompagne surtout la fête (DJ sets, soirées dansantes) reste dans « Fêtes », et un événement qui mêle les deux va à la part qui prédomine — la règle que rappelle le texte d'aide du champ Catégorie
- **Scénarios Selenium** — `tests/ladecadanse.side` désigne les sections de l'agenda par leur rang : sur une journée qui a des concerts, `.genre:nth-child(2)` vise « Concerts » et non plus la section qui suivait « Fêtes »
- **Qui peut modifier une fiche de lieu** — la règle ne change pas (niveau AUTHOR ou au-dessus, personne affiliée au lieu, ou membre d'un organisateur rattaché), mais elle est posée une fois, dans `Authorization::isPersonneAllowedToEditLieu()`, par le formulaire et par le lien « Modifier ce lieu » de la fiche. Un refus répond 403, une requête sans identifiant 400, un identifiant inconnu 404 — le formulaire affichait jusqu'ici un message HTML nu au-dessus d'une page vide, avec un statut 200
- **Champs réservés d'un lieu** — le nom, la préposition, les catégories et les organisateurs ne partaient plus en champs cachés à qui n'a pas le droit d'y toucher : le serveur reprend leur valeur enregistrée quel que soit le contenu du POST. Conséquence : **un POST forgé ne renomme plus un lieu ni ne le rattache à un organisateur**. La galerie d'images disparaît du formulaire — fonctionnalité abandonnée, les images se posent à la main
- **Statut d'un lieu** — les libellés deviennent « Publié / Dépublié / Ancien », comme sur la fiche d'organisateur ; les valeurs en base (`actif`, `inactif`, `ancien`) ne changent pas. Le formulaire ne poste plus de statut pour qui n'a pas le droit d'en choisir un : une modification faite par un acteur laisse désormais la fiche dans l'état où elle était, là où elle la republiait
- **Sélection des lieux actifs** — les trois selects de lieux (inscription, profil, texte d'un lieu) filtraient sur `actif=1`, colonne que la migration supprime ; c'est `statut` qui fait foi. Le code et la base partent donc ensemble : l'ancien code sur une base migrée répondrait une erreur SQL sur ces trois pages
- **Affiliés et membres d'une fiche** — les personnes affiliées à un lieu et les membres d'un organisateur, avec leur e-mail, ne sont plus listés qu'aux administrateurs (niveau ADMIN). Un auteur ne voit plus les affiliés d'un lieu, et ni l'auteur d'une fiche d'organisateur ni ses membres ne voient plus la liste des membres
- **Annonces déjà fermées** — la fermeture d'une annonce n'est plus lue dans les cookies `home_tmp_banner` et `home_tmp_back_banner` mais dans le stockage local du navigateur : une annonce reportée telle quelle réapparaît une fois chez ceux qui l'avaient fermée. Les deux cookies ne sont plus posés et expirent d'eux-mêmes
- **Favoris réservés à un panel** — rien n'en paraît tant qu'on ne détient pas le cookie posé par `?favoris_beta=<secret>`, `FAVORITES_BETA_SECRET` dans [`app/config.php`](app/config.php) ; `?favoris_beta=off` le retire, et il vaut un an. Distribuer le lien, c'est ouvrir la fonctionnalité à qui le reçoit, y compris sans compte : le secret est dans un dépôt public, il ne protège de rien, il met simplement à l'écart. Les favoris d'un visiteur non connecté vivent dans le stockage local de son navigateur, sur cet appareil seulement — vider les données du site les efface —, et sa première connexion les verse au compte
- **Propositions anonymes de plus de deux ans** — un administrateur qui rouvre un tel événement n'y voit plus ni l'adresse ni la remarque du visiteur, et ne peut plus lui notifier une modification : l'une et l'autre ont été purgées

### Bibliothèques front-end

jQuery, Leaflet, Font Awesome, Magnific Popup, select2, Zebra_Datepicker, checkboxes.js, normalize.css et pdf.js sont désormais servis depuis `web/libs/`, que npm remplit et que le dépôt ne contient pas — voir [README](README.md#bibliothèques-front-end). Aucune version ne change : les fichiers de jQuery et de Leaflet sont ceux des CDN à l'octet près, leurs empreintes SRI concordent.

- **Développement** — lancer `npm ci` après le `git pull` : sans lui, `web/libs/` n'existe pas, et sans jQuery c'est tout le JavaScript du site qui tombe — icônes, sélecteur de date, listes select2, cartes. Node et npm deviennent un prérequis pour faire tourner le site, et plus seulement pour le lint et les tests. `composer install` retire de `vendor/` les trois paquets front-end qu'il portait
- **Production, dans cet ordre** — d'abord `composer deploy`, qui lance `npm ci` et envoie `web/libs/` avec le code ; ensuite seulement `composer install` par SSH. L'ordre inverse retirerait `vendor/select2`, `vendor/fortawesome` et `vendor/dimsemenov` pendant que les pages en ligne y pointent encore. `git ftp push -s prod --dry-run` doit annoncer « Including all files in web/libs/ for upload. »
- **Fichiers laissés sur le serveur** — git-ftp supprime ce que le dépôt ne porte plus : `web/js/libs/` et `web/css/normalize.css` disparaissent de la production au premier déploiement, sans rien à faire
- **CSP** — `https://code.jquery.com` et `https://unpkg.com` en sortent (`app/bootstrap.php`) : le déploiement de `web/libs/` et celui du code doivent donc partir ensemble, ce que fait `composer deploy`. Un `git ftp push` seul, sans `npm ci` préalable sur un poste à jour, laisserait les pages sans jQuery

### Développement

- **Migrations** — un changement de schéma s'écrit en classe : `composer db:generate`, le SQL dans `up()`, et c'est tout. `ladecadanse.sql` est figé au schéma de la 3.13.0 et ne se retouche plus : une installation neuve l'importe puis lance `composer db:migrate`. Une base de développement s'adopte par le même `composer db:migrate`, à n'importe quelle version. Voir [resources/database/README.md](resources/database/README.md)
- **Anciens scripts `.sql`** — les sections ci-dessous citent des fichiers `vX-Y-Z_*.sql` qui n'existent plus : le [tableau de correspondance](resources/database/README.md#les-migrations-davant-doctrine) donne la classe qui porte chacun. Une branche en cours qui ajoutait un tel fichier le convertit en classe avant de fusionner
- **`prod-copy`** — la copie reprend le registre `doctrine_migration_versions` de la production, et la structure vide de `personne_evenement` : il n'y a plus à recréer la table des favoris à la main après une copie

## 3.12.0

### Base de données

Exécuter `resources/database/v3-12-0_localite-france.sql`, **d'une seule traite** : le fichier pose une variable de connexion (`LAST_INSERT_ID()`) que deux `UPDATE` relisent ensuite, et exécuter les instructions séparément la perdrait.

Il fait quatre choses :

1. passe `localite.npa` de `INT(4)` à `VARCHAR(6)` — un code postal français compte cinq chiffres, dont certains commencent par un zéro (01210 Ferney-Voltaire) qu'un entier perdrait. Les NPA suisses existants sont convertis tels quels
2. ajoute la localité « Ailleurs en France », de canton `rf` : elle recueille les adresses françaises dont la commune n'est pas encore listée
3. y déplace les événements et les lieux jusqu'ici rattachés à la localité 1 avec `region = 'rf'` (152 événements et 2 lieux sur la base de développement)
4. renomme la localité 1 en « Hors Genève, Vaud et France » et lui donne le canton `hs`, qu'elle n'avait pas

**À passer avec la mise en ligne du code, pas plus tard** : tant qu'elle ne l'est pas, la localité 1 garde un canton vide, les formulaires ouvrent donc un `<optgroup>` sans libellé, et la France n'est plus proposée du tout — son entrée codée en dur a disparu du code.

Les deux libellés ci-dessus sont écrits à l'identique dans le fichier SQL et dans `Ladecadanse\Localite::LOCALITES_FOURRE_TOUT` ; c'est sur cette égalité que repose leur effacement de l'affichage des adresses. Les renommer suppose de le faire des deux côtés — un test unitaire relit le `.sql` pour le vérifier.

### Redirections

Cinq pages changent d'adresse : trois pages de compte rejoignent `user/`, à côté de `login.php` et `dashboard.php`, le formulaire d'organisateur passe sous `organisateur/` et l'écran d'administration des événements prend un nom lisible. Les redirections 301 sont dans [`htaccess/50-routage.conf`](htaccess/50-routage.conf) et partent avec le code : il n'y a plus rien à reporter à la main sur le serveur, mais `composer config:build` doit être passé avant la mise en ligne, comme pour tout changement d'un fragment de configuration — voir [docs/config-serveur.md](docs/config-serveur.md).

| Ancienne URL | Nouvelle |
| --- | --- |
| `/user-register.php` | `/user/register.php` |
| `/user-reset.php` | `/user/reset.php` |
| `/user-reset2.php` | `/user/reset2.php` |
| `/organisateur-edit.php` | `/organisateur/edit.php` |
| `/admin/gererEvenements.php` | `/admin/events.php` |

La query string est reportée d'office, aucune substitution n'en portant : les liens de réinitialisation déjà envoyés par mail (`?token=`) restent valables 24 h — sans la redirection ils tomberaient en 404 pendant une journée — et les signets des organisateurs (`?action=editer&idO=…`) arrivent au bon endroit.

Aucune de ces pages n'est indexée : formulaires réservés aux connectés, écran d'administration sans lien entrant public. Les redirections sont là pour les signets, l'historique et les mails déjà partis.

### Effets de bord à connaître

- **Qui peut modifier une fiche d'organisateur** — le contrôle laissait passer tout compte de niveau ACTOR, c'est-à-dire que chaque organisateur pouvait éditer la fiche de tous les autres. Il pose maintenant la même question que le lien « Modifier cet organisateur » de la fiche : niveau AUTHOR ou au-dessus, membre de l'organisateur, ou auteur de la fiche. Conséquence : **un organisateur qui éditait jusqu'ici une fiche sans y être rattaché sera refusé**. Le rattachement se fait dans `personne_organisateur`, depuis le profil de la personne
- **Statut d'un organisateur** — les libellés deviennent « Publié / Dépublié / Ancien » ; les valeurs en base (`actif`, `inactif`, `ancien`) ne changent pas. Le formulaire ne poste plus de statut pour qui n'a pas le droit d'en choisir un : une modification faite par un acteur laisse désormais la fiche dans l'état où elle était, là où elle la republiait
- **Déconnexion en POST** — `user-logout.php` disparaît sans redirection : une déconnexion en GET partait toute seule au moindre préchargement de lien. Un onglet resté ouvert sur une page rendue *avant* la mise à jour porte encore l'ancien lien et tombera en 404 ; il suffit de recharger la page. Signets et liens externes vers `/user-logout.php` cessent de fonctionner — voir [docs/comptes.md](docs/comptes.md)
- **Mots de passe** — la liste des mots de passe refusés passe de 22 à 19 999 entrées. Les mots de passe existants ne sont pas vérifiés, rien n'est bloqué rétroactivement : la règle ne s'applique qu'au prochain changement. Les comptes non actifs (`statut` autre que `actif`) ne peuvent plus demander de réinitialisation, l'ancien filtre laissait passer les comptes en attente que la connexion refuse ensuite de toute façon
- **Édition groupée et organisateurs** — un remplacement groupé effaçait jusqu'ici les organisateurs de tous les événements sélectionnés, même quand le champ était laissé vide. Il ne les touche plus que si le champ a été rempli, conformément à la règle annoncée par la page. Conséquence : **il n'est plus possible de retirer les organisateurs en masse** en envoyant le formulaire avec un champ vide. Rien ne le permettait vraiment — l'ancien comportement était un effacement subi, pas une commande
- **Préférences de liste** — filtres, tri et nombre de lignes de `admin/events.php` sont désormais mémorisés en session (`user_prefs_even_*`), comme ceux de `admin/users.php`. À la première visite après la mise à jour, la liste repart donc sur ses valeurs par défaut, et les anciens paramètres d'URL (`tri_gerer`, `ordre`, `filtre_genre`, `element`, `nblignes`) ne sont plus lus : un signet qui en porterait ouvre la liste sans filtre plutôt qu'en erreur. Le menu de lignes propose 50, 250 et 500 ; le 100 disparaît de cette page seulement, `$tab_nblignes` restant inchangé pour `admin/users.php` et `admin/bots.php`
- **Images des envois groupés** — un flyer ou une image posé par édition groupée est désormais redimensionné en 600×600 avec une miniature non rognée, comme dans `evenement-edit.php` (et non plus 400×400 avec miniature rognée). Les images déjà en base ne sont pas retraitées
- **`resources/`** — les corps de mail passent sous `resources/templates/`, les scripts sql sous `resources/database/`. Adapter tout montage ou script maison qui les référence (le `docker-compose.yml` du dépôt est à jour)
- **Localités françaises** — la France et « ailleurs » cessent d'être des entrées codées en dur dans les formulaires : ce sont des localités de la table `localite`, de cantons `rf` et `hs`. Ajouter une commune française ne demande donc plus de toucher au code, seulement un `INSERT INTO localite (localite, commune, npa, canton, regions_covered) VALUES ('Annemasse', 'Annemasse', '74100', 'rf', 'ge,rf')`. Les deux localités fourre-tout, elles, ne s'affichent jamais dans une adresse : seule la région apparaît, comme avant

### Développement

- `resources/database/ladecadanse.sql` est de nouveau le schéma courant : il avait quatre versions de retard, et une base créée à partir de lui n'avait ni les index de recherche de la 3.8 et de la 3.9, ni la table `bot_monitor`. Une installation neuve importe donc désormais le dump **seul**, sans rejouer aucune migration par-dessus. Les bases existantes ne sont pas concernées ; pour savoir ce qui manque à l'une d'elles, passer la requête de [resources/database/README.md](resources/database/README.md), qui répond fichier par fichier
- `composer rector:dry-run` fonctionne à nouveau : la configuration pointait sur un fichier de test déplacé, et le parcours partait de la racine, donc de `vendor/`
- `.htaccess` et `.user.ini` sont composés depuis des fragments par `composer config:build` : `.htaccess.example` disparaît, et l'étape d'installation `cp .htaccess.example .htaccess` devient `composer config:build`. Un `.htaccess` écrit à la main n'étant suivi par aucun dépôt, le mettre de côté avant la première composition — l'outil refuse de l'écraser sans `--force`. Voir [docs/config-serveur.md](docs/config-serveur.md)

## 3.11.0

### Base de données

Exécuter, dans cet ordre, les fichiers du répertoire `resources/database/` :

1. `v3-11-0_personne-add-settings.sql` — ajoute la colonne `personne.settings`, qui stocke les préférences personnelles au format JSON (aujourd'hui les valeurs par défaut d'ajout d'événement, sous la clé `events > new_defaults`). Les préférences ajoutées plus tard n'auront pas besoin d'une nouvelle migration
2. `v3-11-0_lieu-lat-lng-decimal.sql` — passe `lieu.lat` et `lieu.lng` de `FLOAT(10,6)` à `DECIMAL(10,7)`. La simple précision perdait environ 50 cm sur des coordonnées genevoises
3. `v3-11-0_bot_monitor-create-table.sql` — crée la table `bot_monitor`. Nécessaire uniquement si vous activez le suivi du trafic automatisé (voir ci-dessous), mais sans effet si vous ne l'activez pas

### app/env.php

Aucune de ces constantes n'est obligatoire : `app/config.php` fournit une valeur par défaut pour la première, les deux autres désactivent des fonctionnalités optionnelles. Le modèle commenté se trouve dans [`app/env_model.php`](app/env_model.php).

| Constante | Rôle |
| --- | --- |
| `SITE_CANONICAL_URL` | URL canonique du site, sans slash final. Sert aux URL absolues des flux RSS et de leurs balises d'autodiscovery. En production la valeur par défaut (`https://www.ladecadanse.ch`) convient ; **en développement**, la définir sur l'URL locale pour que les liens de flux pointent sur votre environnement et non sur la production |
| `EMAIL_COPY_TO_ADMIN` | Copie à l'admin des messages envoyés aux utilisateurs, pour de courtes périodes de surveillance. `false` par défaut |
| `BOT_MONITORING_ENABLED` | Suivi interne du trafic automatisé. `false` par défaut ; **créer la table `bot_monitor` avant d'activer** — voir [docs/bots.md](docs/bots.md) |

### Système de fichiers

Le répertoire `var/cache/rss/` doit être inscriptible par le serveur web : les flux RSS y sont mis en cache. S'il ne l'est pas, les flux sont générés directement à chaque requête — moins performant, mais sans erreur pour le visiteur.

### Effets de bord à connaître

- **Textes TinyMCE** — les présentations de lieux et d'organisateurs enregistrées **avant** cette version ont perdu les `href` de leurs liens internes vers le site (les URL relatives étaient rejetées par le sanitiseur). Le bug est corrigé, mais les textes déjà enregistrés doivent être ré-édités et ré-enregistrés pour retrouver leurs liens
- **Flux `evenement_commentaires`** — retiré il y a environ trois ans avec les commentaires, il répondait `400` ; il répond désormais `410 Gone`, ce qui amène les lecteurs de flux à signaler l'abonnement en erreur et les robots à abandonner l'URL. Les requêtes vers ce flux devraient donc décroître
- **Événements passés** — ils deviennent des archives en lecture seule pour les utilisateurs sous le groupe 6 : plus de formulaire d'édition, plus de suppression. Prévenir les organisateurs concernés, qui doivent maintenant utiliser *Copier* pour reprogrammer un ancien événement — voir [docs/evenements.md](docs/evenements.md)

### Développement

- Node : la version requise est déclarée dans `package.json` (`engines`). Lancer `npm install`, puis `npm test` pour les tests unitaires JS (Vitest)
- Tests : renseigner `LADECADANSE_SITE_URL` dans `tests/.env` pour la nouvelle suite Codeception `site`
