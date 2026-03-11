CREATE TABLE `personne_evenement` (
  `idPersonne` smallint(5) unsigned NOT NULL,
  `idEvenement` mediumint(8) unsigned NOT NULL,
  `dateAjout` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idPersonne`, `idEvenement`),
  KEY `pe_idEvenement` (`idEvenement`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
