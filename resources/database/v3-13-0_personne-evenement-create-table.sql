-- Favoris personnels (#98) : une ligne par couple (personne, événement).
-- Clé primaire composite : c'est elle qui rend l'ajout idempotent, l'INSERT IGNORE de
-- event/favorites.php s'appuyant dessus pour ne pas dupliquer un favori déjà posé.
-- L'index sur idEvenement sert le sens inverse, compter ou lister qui a mis un événement en favori.
CREATE TABLE `personne_evenement` (
  `idPersonne` smallint(5) unsigned NOT NULL,
  `idEvenement` mediumint(8) unsigned NOT NULL,
  `dateAjout` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idPersonne`, `idEvenement`),
  KEY `pe_idEvenement` (`idEvenement`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
