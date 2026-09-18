-- 3.13.0 — note d'administration sur les fiches de lieu et d'organisateur
--
-- Texte brut, lu et écrit par les seuls administrateurs (niveau ADMIN) : fieldset « Admin »
-- des formulaires d'édition, colonne « Note » des deux listes, <details> de la fiche.
-- NULL dit « pas de note » ; les formulaires y écrivent NULL plutôt qu'une chaîne vide.
--
-- TEXT et non MEDIUMTEXT : 65 535 octets, soit au moins 16 383 caractères en utf8mb4,
-- quand la validation plafonne la note à 2 000.
--
-- Rangée juste avant la date d'ajout. MariaDB ne connaît pas BEFORE, d'où les AFTER.
--
-- Rétrocompatible : une colonne qui accepte NULL ne dérange pas le code d'avant, le script
-- peut donc passer avant la mise en ligne. Pas après : les formulaires écrivent la colonne.

ALTER TABLE `lieu`
    ADD `admin_note` TEXT COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `URL`;

ALTER TABLE `organisateur`
    ADD `admin_note` TEXT COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `statut`;
