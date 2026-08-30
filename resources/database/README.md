# resources/database

Deux rôles cohabitent dans ce répertoire.

`ladecadanse.sql` est le **schéma de référence**, à jour de la 3.12.0 : c'est lui qu'on importe pour créer
une base de zéro. Les fichiers `vX-Y-Z_*.sql` sont les **migrations** : elles font passer une base
existante d'une version à la suivante.

Le [changelog](../../CHANGELOG.md) dit ce qui a changé dans une version, [UPGRADE.md](../../UPGRADE.md)
ce qu'il faut faire pour y passer, ce fichier-ci quel script correspond à quoi.

## Installation neuve

Importer `ladecadanse.sql`, et **rien d'autre**. Toutes les migrations y sont intégrées ; les rejouer
par-dessus échoue sur « Duplicate column name » pour les `ALTER`, ou passe en faussant les données —
`v3-12-0_localite-france.sql` rejouée ajoute une seconde localité « Ailleurs en France ».

## Base existante

Appliquer les migrations postérieures à sa version, dans l'ordre du tableau. [UPGRADE.md](../../UPGRADE.md)
donne pour chaque version le détail et les précautions — `v3-12-0_localite-france.sql` doit par exemple
passer d'une seule traite, une variable de connexion étant relue par ses `UPDATE`.

## Les migrations

La colonne de droite dit si le dump porte déjà l'effet du script. Elles sont toutes à « oui » : c'est
l'invariant à tenir, une ligne à « non » signalant que le dump a pris du retard sur les migrations.

| Fichier | Version | Ajouté le | Effet | Dans `ladecadanse.sql` |
| --- | --- | --- | --- | --- |
| `v3-6-3_localite-add-regions_covered.sql` | 3.7.0 | 2025-03-20 | colonne `localite.regions_covered`, et les communes du district de Nyon rattachées à `ge,vd` | oui |
| `v3-6-3_personne-add-last_login.sql` | 3.7.0 | 2025-03-21 | colonne `personne.last_login` | oui |
| `v3-8-0-evenement-add-index.sql` | 3.8.0 | 2025-06-28 | index composite `idx_ev_date_statut_genre_ajout` | oui |
| `v3-9-0-evenement-add-fulltext-index.sql` | 3.9.0 | 2025-07-26 | index FULLTEXT sur `evenement.titre`, `nomLieu`, `description` et sur `lieu.nom`, base de la recherche | oui |
| `v3-9-2-evenement-add-index.sql` | 3.9.2 | 2025-10-21 | index `idx_ev_idPersonne` | oui |
| `v3-9-4-personne-mot-de-passe-255.sql.sql` | 3.9.4 | 2026-03-07 | `personne.mot_de_passe` en `VARCHAR(255)`, pour les empreintes bcrypt | oui |
| `v3-11-0_personne-add-settings.sql` | 3.11.0 | 2026-08-08 | colonne `personne.settings` (préférences JSON) | oui |
| `v3-11-0_lieu-lat-lng-decimal.sql` | 3.11.0 | 2026-08-01 | `lieu.lat` et `lieu.lng` de `FLOAT(10,6)` à `DECIMAL(10,7)` | oui |
| `v3-11-0_bot_monitor-create-table.sql` | 3.11.0 | 2026-07-16 | table `bot_monitor` | oui |
| `v3-12-0_localite-france.sql` | 3.12.0 | 2026-08-25 | `localite.npa` en `VARCHAR(6)`, localité « Ailleurs en France », localité 1 renommée en canton `hs` | oui |

Trois pièges de lecture :

- le préfixe est la version **visée** au moment de l'écriture, pas toujours celle qui a livré le script.
  Les deux fichiers `v3-6-3_*` sont partis avec la 3.7.0 : le tag `v3.6.3` date du 10 mars 2025, eux du 20 et du 21 ;
- `v3-9-4-personne-mot-de-passe-255.sql.sql` porte deux fois son extension, c'est bien son nom ;
- la 3.10.0 n'a pas de migration.

## Savoir ce qui manque à une base

Rien n'enregistre la version d'une base : il faut la déduire de son schéma. Cette requête le fait, à passer
sur la base à vérifier :

```sql
SELECT 'v3-6-3_localite-add-regions_covered' AS migration, IF(COUNT(*), 'ok', 'MANQUANTE') AS etat
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'localite' AND COLUMN_NAME = 'regions_covered'
UNION ALL SELECT 'v3-6-3_personne-add-last_login', IF(COUNT(*), 'ok', 'MANQUANTE')
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personne' AND COLUMN_NAME = 'last_login'
UNION ALL SELECT 'v3-8-0-evenement-add-index', IF(COUNT(*), 'ok', 'MANQUANTE')
  FROM information_schema.STATISTICS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evenement' AND INDEX_NAME = 'idx_ev_date_statut_genre_ajout'
UNION ALL SELECT 'v3-9-0-evenement-add-fulltext-index', IF(COUNT(*), 'ok', 'MANQUANTE')
  FROM information_schema.STATISTICS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evenement' AND INDEX_NAME = 'ft_evenement_titre'
UNION ALL SELECT 'v3-9-2-evenement-add-index', IF(COUNT(*), 'ok', 'MANQUANTE')
  FROM information_schema.STATISTICS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evenement' AND INDEX_NAME = 'idx_ev_idPersonne'
UNION ALL SELECT 'v3-9-4-personne-mot-de-passe-255', IF(MAX(CHARACTER_MAXIMUM_LENGTH) >= 255, 'ok', 'MANQUANTE')
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personne' AND COLUMN_NAME = 'mot_de_passe'
UNION ALL SELECT 'v3-11-0_personne-add-settings', IF(COUNT(*), 'ok', 'MANQUANTE')
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personne' AND COLUMN_NAME = 'settings'
UNION ALL SELECT 'v3-11-0_lieu-lat-lng-decimal', IF(MAX(DATA_TYPE) = 'decimal', 'ok', 'MANQUANTE')
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lieu' AND COLUMN_NAME = 'lat'
UNION ALL SELECT 'v3-11-0_bot_monitor-create-table', IF(COUNT(*), 'ok', 'MANQUANTE')
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bot_monitor'
UNION ALL SELECT 'v3-12-0_localite-france', IF(COUNT(*), 'ok', 'MANQUANTE')
  FROM localite
 WHERE canton = 'rf' AND localite = 'Ailleurs en France';
```

Une base à jour répond `ok` sur les dix lignes. Chaque `MANQUANTE` désigne le fichier à passer, dans
l'ordre du tableau.

## Scripts hors migration

Trois fichiers ne sont ni le schéma ni une migration, et n'ont rien à faire dans une installation :

- `evenement-fix-horaires.sql` — réparation ponctuelle des horaires faussés par le bug de copie corrigé
  en 3.5.0, passée en production le 26 juin 2023 ;
- `test-separateurs-horaires.sql` — jeu d'événements de test pour les séparateurs horaires de l'agenda
  (#105). Il **écrit dans `evenement`** : à réserver à une base de développement.
- `mailing-users-specialises.sql` — sélection des utilisateurs dont les ajouts se concentrent sur un
  seul lieu, une seule catégorie ou les mêmes organisateurs, pour un mailing par `admin/mailing.php`.
  Trois requêtes **en lecture seule**, passables telles quelles en production. Voir
  [Événements](../../docs/evenements.md#reporter-les-valeurs-récurrentes-dans-les-réglages).

## Docker

L'environnement Docker crée sa base depuis `ladecadanse.sql` seul, puis charge les fixtures de
`docker/env/` — le compte `admin` et un lieu de test. Aucune migration n'y est montée et il ne faut pas
en ajouter : le dump les porte toutes. Voir [Base de données](../../README.md#base-de-données) dans le
README, notamment pour le cas d'une base déjà créée.

## Ajouter une migration

1. la nommer `vX-Y-Z_objet.sql`, avec la version qui la livrera ;
2. **la reporter dans `ladecadanse.sql`**, pour que le dump reste le schéma courant. C'est l'étape qui a
   manqué pendant quatre versions : les bases créées entre-temps ont tourné sans les index de recherche ;
3. l'ajouter à ce tableau et à la requête ci-dessus ;
4. la décrire dans [UPGRADE.md](../../UPGRADE.md), sous la version en préparation.
