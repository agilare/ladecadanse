/**
 * Destinataires d'un mailing : les utilisateurs « spécialisés »
 *
 * Sélectionne les comptes actifs et non administrateurs dont les ajouts d'événements
 * se concentrent sur un seul lieu, une seule catégorie, ou le ou les mêmes organisateurs.
 * Le résultat est destiné à `admin/mailing.php`, qui attend un CSV « idPersonne, pseudo, email ».
 *
 * Aucune de ces requêtes n'écrit : elles peuvent être passées telles quelles en production.
 * Pour reporter ces mêmes valeurs dans `personne.settings`, voir `bin/user-settings-defaults.php`,
 * qui refait le même calcul et n'écrit que par l'encodeur de l'application.
 *
 * Créé : 25 août 2026. Réglages ajustés après essais sur les données de production :
 * seuil 90 % → 98 % le 27 août, puis 98 % → 96 % avec plancher 100 → 50 ajouts et
 * connexion 2 ans → 1 an le 29 août 2026.
 *
 * Trois réglages, rassemblés dans la CTE `params` en tête de chaque requête :
 *
 *   depuis_login    dernière connexion exigée. `last_login` est NULL pour qui ne s'est plus
 *                   connecté depuis la v3.6.3 : ces comptes sont écartés, ce qui est bien
 *                   le sens voulu d'« actif ».
 *   min_evenements  plancher d'ancienneté : en dessous de 50 ajouts, le compte est écarté
 *                   quelle que soit sa régularité.
 *   seuil           part minimale, comparée en `>=`.
 *
 * Les deux réglages se tiennent, et c'est le plancher qui rend le seuil opérant. À 96 %, il
 * faut 25 ajouts pour s'autoriser un seul écart (24/25 = 96 %, mais 23/24 = 95,8 %) : en
 * dessous de 25, « 96 % » signifierait exactement « 100 %, aucune exception ». Le plancher
 * de 50 laisse donc deux écarts à qui vient de l'atteindre, quatre à 100 ajouts, et ainsi de
 * suite — soit la même tolérance au plancher que le couple précédent (100 ajouts / 98 %).
 *
 * Ce qui est compté : le geste d'ajout, comme dans `Ladecadanse\Stats\MonthlyAddedEvents`.
 * Le statut filtré est celui de la personne, pas celui de l'événement — qu'un administrateur
 * ait dépublié un événement ensuite ne dit rien de l'habitude de celui qui l'a saisi.
 */


-- =============================================================================
-- Requête 1 — revue avant envoi
--
-- Mêmes destinataires que la requête 2, avec le détail qui permet de juger la sélection :
-- nombre d'ajouts, motif retenu, et le lieu / la catégorie / les organisateurs en cause.
--
-- La colonne `lieu_idLieu` répond à « est-ce bien la fiche qui domine ? » : le pourcentage
-- de la colonne `lieu` confond les deux façons de désigner un lieu, mais cette colonne ne
-- porte un identifiant que si le lieu dominant est une vraie fiche. NULL = nom libre, donc
-- rien à reporter dans `settings`, dont le champ `idLieu` est un identifiant.
-- =============================================================================

WITH
params AS (
    SELECT
        DATE_SUB(CURDATE(), INTERVAL 1 YEAR) AS depuis_login,
        50   AS min_evenements,
        0.96 AS seuil
),
candidats AS (
    SELECT p.idPersonne, p.pseudo, p.email, p.last_login
    FROM personne p
    CROSS JOIN params
    WHERE p.groupe > 4              -- ni SUPERADMIN (1) ni ADMIN (4) ; > 6 écarterait aussi les AUTHOR
      AND p.statut = 'actif'
      AND p.email <> ''
      AND p.last_login >= params.depuis_login
),
ajouts AS (
    SELECT
        e.idPersonne,
        e.idevenement,
        e.idLieu,
        /*
         * Un événement peut désigner son lieu par une fiche (idLieu) ou par un simple nom
         * saisi à la main (nomLieu) : `evenement-edit.php` accepte l'un ou l'autre, et près
         * d'un ajout sur six n'a pas de fiche. D'où une clé commune aux deux formes.
         * Le troisième cas — ni fiche ni nom — reçoit une clé unique par événement : ces
         * ajouts pèsent dans le total sans jamais pouvoir former un « lieu dominant ».
         */
        CASE
            WHEN e.idLieu > 0          THEN CONCAT('L', e.idLieu)
            WHEN TRIM(e.nomLieu) <> '' THEN CONCAT('N', LOWER(TRIM(e.nomLieu)))
            ELSE CONCAT('E', e.idevenement)
        END AS cle_lieu,
        COALESCE(NULLIF(l.nom, ''), NULLIF(TRIM(e.nomLieu), ''), '(sans lieu)') AS nom_lieu,
        e.genre
    FROM evenement e
    JOIN candidats c ON c.idPersonne = e.idPersonne
    LEFT JOIN lieu l ON l.idLieu = e.idLieu
    -- ne juger que les ajouts récents : décommenter pour ignorer les habitudes anciennes
    -- WHERE e.dateAjout >= DATE_SUB(CURDATE(), INTERVAL 3 YEAR)
),
totaux AS (
    SELECT idPersonne, COUNT(*) AS nb_total
    FROM ajouts
    GROUP BY idPersonne
),
lieu_top AS (
    SELECT idPersonne, nom_lieu, nb, idLieu
    FROM (
        SELECT idPersonne,
               MAX(nom_lieu) AS nom_lieu,
               MAX(idLieu)   AS idLieu,   -- constant dans un groupe « fiche », 0 pour un nom libre
               COUNT(*)      AS nb,
               ROW_NUMBER() OVER (PARTITION BY idPersonne ORDER BY COUNT(*) DESC, cle_lieu) AS rang
        FROM ajouts
        GROUP BY idPersonne, cle_lieu
    ) x
    WHERE rang = 1
),
genre_top AS (
    SELECT idPersonne, genre, nb
    FROM (
        SELECT idPersonne, genre, COUNT(*) AS nb,
               ROW_NUMBER() OVER (PARTITION BY idPersonne ORDER BY COUNT(*) DESC, genre) AS rang
        FROM ajouts
        GROUP BY idPersonne, genre
    ) y
    WHERE rang = 1
),
orga_par_pers AS (
    SELECT a.idPersonne, eo.idOrganisateur, COUNT(*) AS nb
    FROM ajouts a
    JOIN evenement_organisateur eo ON eo.idEvenement = a.idevenement
    GROUP BY a.idPersonne, eo.idOrganisateur
),
/*
 * « Les mêmes organisateurs (1 ou plus) » : non pas un attelage identique d'un événement à
 * l'autre, mais le ou les organisateurs présents dans au moins 96 % des ajouts. Celui qui
 * saisit toujours pour A, parfois avec B en second, est bien un habitué de A — un
 * rapprochement par combinaison exacte le manquerait.
 */
orga_top AS (
    SELECT
        o.idPersonne,
        GROUP_CONCAT(COALESCE(org.nom, CONCAT('#', o.idOrganisateur)) ORDER BY o.nb DESC SEPARATOR ' + ') AS organisateurs,
        GROUP_CONCAT(o.idOrganisateur ORDER BY o.nb DESC) AS ids_organisateurs,
        MAX(o.nb) AS nb
    FROM orga_par_pers o
    JOIN totaux t ON t.idPersonne = o.idPersonne
    CROSS JOIN params
    LEFT JOIN organisateur org ON org.idOrganisateur = o.idOrganisateur
    WHERE o.nb >= t.nb_total * params.seuil
    GROUP BY o.idPersonne
)
SELECT
    c.idPersonne,
    c.pseudo,
    c.email,
    DATE(c.last_login) AS derniere_connexion,
    t.nb_total AS nb_ajouts,
    CONCAT_WS(', ',
        CASE WHEN lt.nb >= t.nb_total * pa.seuil THEN 'lieu' END,
        CASE WHEN gt.nb >= t.nb_total * pa.seuil THEN 'categorie' END,
        CASE WHEN ot.nb IS NOT NULL              THEN 'organisateurs' END
    ) AS motifs,
    CASE WHEN lt.nb >= t.nb_total * pa.seuil
         THEN CONCAT(lt.nom_lieu, ' (', ROUND(100 * lt.nb / t.nb_total), '%)') END AS lieu,
    -- NULL = le lieu dominant est un nom libre, pas une fiche
    CASE WHEN lt.nb >= t.nb_total * pa.seuil AND lt.idLieu > 0
         THEN lt.idLieu END AS lieu_idLieu,
    CASE WHEN gt.nb >= t.nb_total * pa.seuil
         THEN CONCAT(gt.genre, ' (', ROUND(100 * gt.nb / t.nb_total), '%)') END AS categorie,
    CASE WHEN ot.nb IS NOT NULL
         THEN CONCAT(ot.organisateurs, ' (', ROUND(100 * ot.nb / t.nb_total), '%)') END AS organisateurs,
    ot.ids_organisateurs AS organisateurs_ids
FROM candidats c
CROSS JOIN params pa
JOIN totaux t ON t.idPersonne = c.idPersonne
LEFT JOIN lieu_top  lt ON lt.idPersonne = c.idPersonne
LEFT JOIN genre_top gt ON gt.idPersonne = c.idPersonne
LEFT JOIN orga_top  ot ON ot.idPersonne = c.idPersonne
WHERE t.nb_total >= pa.min_evenements
  AND (   lt.nb >= t.nb_total * pa.seuil
       OR gt.nb >= t.nb_total * pa.seuil
       OR ot.nb IS NOT NULL)
ORDER BY t.nb_total DESC, c.pseudo;


-- =============================================================================
-- Requête 2 — le CSV du mailing
--
-- Exactement les trois colonnes qu'attend `parserCsvDestinataires()` : toute colonne en
-- plus fait rejeter chaque ligne (« 4 champ(s) au lieu de 3 »). Passer par l'export CSV de
-- phpMyAdmin, virgule ou point-virgule au choix, en-tête accepté ou non. Le « copier » du
-- tableau de résultats, lui, produit du tabulé que le formulaire refusera en bloc.
--
-- Seul le SELECT final diffère de la requête 1. Pour n'écrire qu'à un groupe, décommenter
-- la ligne de motif voulue tout en bas.
-- =============================================================================

WITH
params AS (
    SELECT
        DATE_SUB(CURDATE(), INTERVAL 1 YEAR) AS depuis_login,
        50   AS min_evenements,
        0.96 AS seuil
),
candidats AS (
    SELECT p.idPersonne, p.pseudo, p.email, p.last_login
    FROM personne p
    CROSS JOIN params
    WHERE p.groupe > 4
      AND p.statut = 'actif'
      AND p.email <> ''
      AND p.last_login >= params.depuis_login
),
ajouts AS (
    SELECT
        e.idPersonne,
        e.idevenement,
        CASE
            WHEN e.idLieu > 0          THEN CONCAT('L', e.idLieu)
            WHEN TRIM(e.nomLieu) <> '' THEN CONCAT('N', LOWER(TRIM(e.nomLieu)))
            ELSE CONCAT('E', e.idevenement)
        END AS cle_lieu,
        e.genre
    FROM evenement e
    JOIN candidats c ON c.idPersonne = e.idPersonne
),
totaux AS (
    SELECT idPersonne, COUNT(*) AS nb_total
    FROM ajouts
    GROUP BY idPersonne
),
lieu_top AS (
    SELECT idPersonne, MAX(nb) AS nb
    FROM (SELECT idPersonne, COUNT(*) AS nb FROM ajouts GROUP BY idPersonne, cle_lieu) x
    GROUP BY idPersonne
),
genre_top AS (
    SELECT idPersonne, MAX(nb) AS nb
    FROM (SELECT idPersonne, COUNT(*) AS nb FROM ajouts GROUP BY idPersonne, genre) y
    GROUP BY idPersonne
),
orga_top AS (
    SELECT o.idPersonne, MAX(o.nb) AS nb
    FROM (
        SELECT a.idPersonne, eo.idOrganisateur, COUNT(*) AS nb
        FROM ajouts a
        JOIN evenement_organisateur eo ON eo.idEvenement = a.idevenement
        GROUP BY a.idPersonne, eo.idOrganisateur
    ) o
    JOIN totaux t ON t.idPersonne = o.idPersonne
    CROSS JOIN params
    WHERE o.nb >= t.nb_total * params.seuil
    GROUP BY o.idPersonne
)
SELECT
    c.idPersonne,
    c.pseudo,
    c.email
FROM candidats c
CROSS JOIN params pa
JOIN totaux t ON t.idPersonne = c.idPersonne
LEFT JOIN lieu_top  lt ON lt.idPersonne = c.idPersonne
LEFT JOIN genre_top gt ON gt.idPersonne = c.idPersonne
LEFT JOIN orga_top  ot ON ot.idPersonne = c.idPersonne
WHERE t.nb_total >= pa.min_evenements
  AND (   lt.nb >= t.nb_total * pa.seuil      -- un seul lieu
       OR gt.nb >= t.nb_total * pa.seuil      -- une seule catégorie
       OR ot.nb IS NOT NULL)                  -- les mêmes organisateurs
  -- un seul groupe à la fois : décommenter une de ces trois lignes
  -- AND lt.nb >= t.nb_total * pa.seuil
  -- AND gt.nb >= t.nb_total * pa.seuil
  -- AND ot.nb IS NOT NULL
ORDER BY c.idPersonne;


-- =============================================================================
-- Requête 3 — la fiche domine-t-elle le nom libre ?
--
-- Répond à la question globalement, hors de toute notion de seuil : sur l'ensemble des
-- ajouts des comptes retenus, quelle part désigne son lieu par une fiche. Utile avant le
-- report dans `settings`, dont le champ `idLieu` ne peut rien recevoir d'un nom libre.
-- =============================================================================

WITH
params AS (
    SELECT
        DATE_SUB(CURDATE(), INTERVAL 1 YEAR) AS depuis_login,
        50 AS min_evenements
),
comptes AS (
    SELECT p.idPersonne
    FROM personne p
    CROSS JOIN params
    WHERE p.groupe > 4
      AND p.statut = 'actif'
      AND p.email <> ''
      AND p.last_login >= params.depuis_login
),
totaux AS (
    SELECT e.idPersonne, COUNT(*) AS nb_total
    FROM evenement e
    JOIN comptes c ON c.idPersonne = e.idPersonne
    GROUP BY e.idPersonne
),
candidats AS (
    -- même plancher que les requêtes 1 et 2, pour que les trois parlent de la même population
    SELECT t.idPersonne
    FROM totaux t
    CROSS JOIN params
    WHERE t.nb_total >= params.min_evenements
)
SELECT
    CASE
        WHEN e.idLieu > 0          THEN 'fiche (idLieu)'
        WHEN TRIM(e.nomLieu) <> '' THEN 'nom libre (nomLieu)'
        ELSE 'ni fiche ni nom'
    END AS designation_du_lieu,
    COUNT(*) AS nb_ajouts,
    ROUND(100 * COUNT(*) / SUM(COUNT(*)) OVER (), 1) AS pourcentage
FROM evenement e
JOIN candidats c ON c.idPersonne = e.idPersonne
GROUP BY designation_du_lieu
ORDER BY nb_ajouts DESC;
