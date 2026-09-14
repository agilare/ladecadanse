-- 3.13.0 — colonnes de la table `lieu` (issue #117)
--
-- Renommages, colonnes retirées et valeurs par défaut, dans le même geste que la
-- réécriture de `lieu/edit.php` et de `LieuEdition`. À passer avec la mise en ligne du
-- code : les deux renommages sont lus par les pages d'événement (`l.preposition_nom`)
-- et par les listes de lieux (`FIND_IN_SET(..., categories)`).
--
-- L'ordre des clauses compte : `categories` et `preposition_nom` doivent exister avant
-- que `logo` puisse être placé après elles, d'où deux instructions plutôt qu'une.

ALTER TABLE `lieu`
    -- `determinant` disait mal ce que la colonne porte : « au », « chez », « à l' » sont
    -- des prépositions, pas des déterminants. NULL y dit « pas de préposition », que la
    -- chaîne vide disait déjà mais sans le distinguer d'une valeur jamais renseignée.
    CHANGE `determinant` `preposition_nom` VARCHAR(40) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `nom`,
    -- un lieu en porte plusieurs, la colonne est un SET : le singulier trompait
    CHANGE `categorie` `categories`
        SET('bistrot','salle','restaurant','cinema','theatre','galerie','boutique','musee','autre')
        COLLATE utf8mb4_unicode_ci NOT NULL AFTER `preposition_nom`,
    -- 100 caractères ne suffisaient plus aux adresses les plus longues (bâtiment, étage)
    MODIFY `adresse` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    -- NOT NULL DEFAULT 0 faisait porter deux sens à 0 : « pas de coordonnées » et un
    -- point réel au large du golfe de Guinée
    MODIFY `lat` DECIMAL(10,7) NULL DEFAULT NULL,
    MODIFY `lng` DECIMAL(10,7) NULL DEFAULT NULL,
    MODIFY `horaire_general` MEDIUMTEXT COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    MODIFY `URL` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    MODIFY `photo1` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    -- seconde photo jamais proposée par aucun formulaire ni lue par aucune page
    DROP `photo2`,
    -- doublon inerte de `statut`, resté à 1 partout et lu nulle part
    DROP `actif`;

-- Le logo appartient à l'identité du lieu : il se range avec le nom et les catégories,
-- et non entre les deux photos.
ALTER TABLE `lieu`
    MODIFY `logo` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `categories`;

-- Les lignes existantes portent 0 là où « pas de coordonnées » se dit désormais NULL.
-- La condition exige les deux à la fois : le formulaire ne les accepte que par paire.
UPDATE `lieu` SET `lat` = NULL, `lng` = NULL WHERE `lat` = 0 AND `lng` = 0;

-- Même chose pour les colonnes texte, où la chaîne vide était le seul « non renseigné »
-- disponible.
UPDATE `lieu` SET `preposition_nom` = NULL WHERE `preposition_nom` = '';
UPDATE `lieu` SET `horaire_general` = NULL WHERE `horaire_general` = '';
UPDATE `lieu` SET `URL` = NULL WHERE `URL` = '';
UPDATE `lieu` SET `logo` = NULL WHERE `logo` = '';
UPDATE `lieu` SET `photo1` = NULL WHERE `photo1` = '';
