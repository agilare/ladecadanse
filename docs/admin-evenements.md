# Administration des événements

L'écran `admin/events.php` — anciennement `admin/gererEvenements.php`, redirigé en 301 — liste tous les événements et permet de les modifier en série. Il est réservé au groupe ADMIN et au-dessus ; un administrateur régional n'y voit que sa région, le superadmin n'en a pas.

Toute la requête est traitée **avant la première ligne de HTML**, et un remplacement comme une suppression se terminent par une redirection : un F5 ne rejoue pas l'action.

## Liste

Colonnes : image, titre, lieu, date, catégorie, horaire, organisateurs, statut, date d'ajout et auteur. Le pseudo de la colonne « par » est coupé au-delà de dix caractères — un seul pseudo long élargissait toute la colonne.

La **colonne Image** ouvre le tableau, juste avant le titre : 50×50 en administration, 30×30 sur le profil d'un utilisateur. Reconnaître un événement, ou vérifier qu'un flyer est bien arrivé, demandait jusque-là d'ouvrir sa page. La vignette couvre son cadre carré sans déformation (`object-fit:cover`), rognée en bas pour une affiche portrait et à droite pour une image paysage, l'ancrage en haut à gauche gardant la partie qui porte le titre ; un clic ouvre la taille réelle. Un événement sans flyer ni illustration laisse sa cellule vide, comme dans les listes de lieux et d'organisateurs.

Un événement **sans localité** figure bien dans la liste : la jointure sur `localite` est externe, sans quoi il disparaissait de l'écran même où on pouvait le corriger.

## Filtres, tri, pagination

Trois filtres — terme, nom du lieu, pseudo ou e-mail de l'auteur — plus le tri et le nombre de lignes sont **mémorisés en session** (`user_prefs_even_*`), comme sur `admin/users.php` : revenir d'une fiche retrouve la liste telle qu'on l'avait laissée. Seule la page reste dans l'URL, pour que les liens de pagination fonctionnent.

Le nombre de lignes vaut 50, 250 ou 500, validé contre cette liste et non pris pour un entier quelconque — `LIMIT 0,999999` ne passe plus. Le 100 a disparu de cette page seulement : il n'apporte rien entre 50 et 250, et chaque ligne y porte son lieu, ses organisateurs et son auteur.

## Édition groupée

Le formulaire agit sur les événements cochés. Il porte un jeton CSRF, qui lui manquait alors qu'il supprime.

**Seuls les champs non vides écrasent l'existant.** C'est ce que la page annonce, et c'est pourquoi la validation ne rend aucun champ obligatoire. Deux conséquences :

- **vider un champ n'est pas une demande de l'effacer**, c'est l'absence de demande. Les organisateurs suivent cette règle depuis #125 : le `DELETE` sur `evenement_organisateur` ne part plus que si le champ a été rempli. Auparavant il partait après chaque `UPDATE` réussi, si bien que changer un statut sur dix événements vidait les organisateurs des dix. Il n'est donc plus possible de retirer les organisateurs en masse — rien ne le permettait vraiment, l'ancien comportement était un effacement subi, pas une commande ;
- **les colonnes du lieu font exception** : elles s'écrivent même vides dès lors que le lieu a changé. Passer d'un lieu de la liste à une adresse saisie à la main doit effacer l'`idLieu` précédent, qu'aucune valeur non vide ne viendrait remplacer.

Le formulaire ne montre que ce que l'édition groupée utilise — statut, catégorie, lieu, horaires, organisateurs — et replie le reste dans trois `<details>` : un lieu saisi à la main, le contenu de l'événement, les fichiers. Un bloc s'ouvre de lui-même si l'un de ses champs a été posté. L'avertissement sur l'écrasement est devenu une confirmation à l'envoi (`data-confirm`), rappelée là où elle compte.

### Horaires et fichiers

Les horaires sont saisis en `hh:mm` et **reconvertis pour chaque événement traité**, à partir de sa propre date : la conversion se fait dans la boucle, sur la saisie validée une fois pour toutes en amont.

L'extension d'un fichier envoyé suit son **format réel**, jamais celle de son nom d'origine — un PNG envoyé sous `affiche.jpg` produisait un `.jpg` contenant du PNG, servi ensuite sous un type que le navigateur refuse. Le premier événement traité porte les images redimensionnées, les suivants en reçoivent une copie, pour ne pas retraiter N fois le même fichier. Les images sont en 600×600 avec miniature non rognée, comme dans `evenement-edit.php` — c'était 400×400 avec miniature rognée avant ; les images déjà en base ne sont pas retraitées.

Le champ fichier accepte les PDF quand le drapeau `PDF_CONVERSION_ENABLED` est levé — voir le [README](../README.md#accepter-les-pdf-dans-les-champs-image). Ce formulaire n'a pas de champ URL : seule la conversion côté navigateur le concerne.

### Suppression groupée

`EvenementCollection::deleteEvenement()` refuse à qui n'est ni l'auteur ni superadmin. Le message annonce donc le nombre de suppressions **effectives**, et non celui des cases cochées, en signalant les refus.
