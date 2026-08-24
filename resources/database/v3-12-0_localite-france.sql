-- La France sort de son exception : elle devient un canton comme un autre dans la table
-- `localite`, où l'on pourra ajouter des localités françaises au fur et à mesure.
--
-- Avant : les événements et les lieux « en France » comme ceux « ailleurs » pointaient tous
-- sur la localité 1 (« Autre »), seule la colonne `region` ('rf' ou 'hs') les distinguait, et
-- les deux entrées étaient proposées en dur par les formulaires ($glo_tab_ailleurs).
-- Après : « autre localité en France » est une localité de canton 'rf', « Autre » garde l'id 1
-- et prend le canton 'hs'. Les trois formulaires n'offrent plus que des localités.
--
-- Attention : à exécuter d'une traite, LAST_INSERT_ID() étant propre à la connexion.

-- 1. NPA : un code postal français compte 5 chiffres, dont certains commencent par un zéro
--    (01000 Bourg-en-Bresse). VARCHAR le conserve, INT le perdrait — et la largeur d'un
--    INT(4) n'était de toute façon qu'un affichage, jamais une limite de valeur.
ALTER TABLE `localite` MODIFY `npa` VARCHAR(6) NOT NULL DEFAULT '';

-- 2. « Autre » localité française
INSERT INTO `localite` (`localite`, `commune`, `npa`, `canton`, `regions_covered`)
VALUES ('Autre', 'Autre', '0', 'rf', 'ge,rf');

SET @id_autre_france = LAST_INSERT_ID();

-- 3. Les événements et les lieux déjà situés en France la rejoignent
UPDATE `evenement` SET `localite_id` = @id_autre_france WHERE `localite_id` = 1 AND `region` = 'rf';
UPDATE `lieu`      SET `localite_id` = @id_autre_france WHERE `localite_id` = 1 AND `region` = 'rf';

-- 4. La localité 1 ne désigne plus que « ailleurs » : son canton vide devient 'hs',
--    ce qui la fait entrer dans les <optgroup> construits depuis la colonne `canton`
UPDATE `localite` SET `canton` = 'hs' WHERE `id` = 1;
