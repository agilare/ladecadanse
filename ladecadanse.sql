-- phpMyAdmin SQL Dump
-- version 4.2.12deb2+deb8u1
-- http://www.phpmyadmin.net
--
-- Client :  localhost
-- Généré le :  Jeu 21 Juillet 2016 à 17:22
-- Version du serveur :  10.0.25-MariaDB-0+deb8u1
-- Version de PHP :  5.6.22-0+deb8u1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Base de données :  `ladecadanse2`
--

-- --------------------------------------------------------

--
-- Structure de la table `affiliation`
--

CREATE TABLE IF NOT EXISTS `affiliation` (
  `idPersonne` smallint(5) unsigned NOT NULL DEFAULT '0',
  `idAffiliation` smallint(5) unsigned NOT NULL DEFAULT '0',
  `genre` set('lieu','association','groupe') NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `breve`
--

CREATE TABLE IF NOT EXISTS `breve` (
`idBreve` mediumint(8) unsigned NOT NULL,
  `titre` varchar(255) NOT NULL DEFAULT '',
  `contenu` text NOT NULL,
  `img_breve` varchar(255) NOT NULL DEFAULT '',
  `date_debut` date NOT NULL DEFAULT '0000-00-00',
  `date_fin` date NOT NULL DEFAULT '0000-00-00',
  `idPersonne` smallint(5) unsigned NOT NULL DEFAULT '0',
  `actif` tinyint(4) unsigned NOT NULL DEFAULT '1',
  `dateAjout` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `date_derniere_modif` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `statut` enum('actif','inactif') NOT NULL DEFAULT 'actif'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `commentaire`
--

CREATE TABLE IF NOT EXISTS `commentaire` (
`idCommentaire` mediumint(11) unsigned NOT NULL,
  `idPersonne` smallint(11) unsigned NOT NULL DEFAULT '0',
  `id` mediumint(11) unsigned NOT NULL DEFAULT '0',
  `element` enum('evenement','lieu') NOT NULL DEFAULT 'evenement',
  `titre` varchar(255) NOT NULL DEFAULT '',
  `contenu` text NOT NULL,
  `titreEvenement` varchar(255) NOT NULL DEFAULT '',
  `statut` enum('actif','inactif','archive') NOT NULL DEFAULT 'actif',
  `dateAjout` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `date_derniere_modif` datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `descriptionlieu`
--

CREATE TABLE IF NOT EXISTS `descriptionlieu` (
  `idLieu` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `idPersonne` smallint(5) unsigned NOT NULL DEFAULT '0',
  `type` enum('description','presentation') NOT NULL DEFAULT 'description',
  `dateAjout` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `contenu` text,
  `date_derniere_modif` datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `evenement`
--

CREATE TABLE IF NOT EXISTS `evenement` (
`idevenement` mediumint(8) unsigned NOT NULL,
  `idLieu` smallint(5) unsigned NOT NULL DEFAULT '0',
  `idSalle` mediumint(9) NOT NULL DEFAULT '0',
  `idPersonne` smallint(5) unsigned NOT NULL DEFAULT '0',
  `statut` enum('actif','inactif','annule','complet') NOT NULL DEFAULT 'actif',
  `genre` varchar(20) NOT NULL DEFAULT 'divers',
  `titre` varchar(100) NOT NULL DEFAULT '',
  `dateEvenement` date NOT NULL DEFAULT '0000-00-00',
  `nomLieu` varchar(255) NOT NULL DEFAULT '',
  `adresse` text NOT NULL,
  `quartier` varchar(255) NOT NULL DEFAULT 'autre',
  `urlLieu` varchar(255) NOT NULL DEFAULT '',
  `horaire_debut` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `horaire_fin` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `horaire_complement` varchar(100) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `flyer` varchar(255) NOT NULL DEFAULT '',
  `image` varchar(255) NOT NULL DEFAULT '',
  `prix` varchar(255) NOT NULL DEFAULT '',
  `prelocations` varchar(80) NOT NULL DEFAULT '',
  `URL1` varchar(255) NOT NULL DEFAULT '',
  `URL2` varchar(255) NOT NULL DEFAULT '',
  `ref` varchar(255) NOT NULL DEFAULT '',
  `dateAjout` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `date_derniere_modif` datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `evenement_favori`
--

CREATE TABLE IF NOT EXISTS `evenement_favori` (
  `idPersonne` smallint(5) NOT NULL DEFAULT '0',
  `idEvenement` mediumint(8) NOT NULL DEFAULT '0',
  `date_ajout` datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `evenement_fichierrecu`
--

CREATE TABLE IF NOT EXISTS `evenement_fichierrecu` (
  `idEvenement` mediumint(9) NOT NULL DEFAULT '0',
  `idFichierrecu` mediumint(9) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `evenement_organisateur`
--

CREATE TABLE IF NOT EXISTS `evenement_organisateur` (
  `idEvenement` mediumint(9) NOT NULL DEFAULT '0',
  `idOrganisateur` mediumint(9) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `fichierrecu`
--

CREATE TABLE IF NOT EXISTS `fichierrecu` (
`idFichierrecu` mediumint(9) NOT NULL,
  `idElement` int(10) NOT NULL DEFAULT '0',
  `type_element` enum('lieu','evenement') NOT NULL DEFAULT 'lieu',
  `description` char(255) NOT NULL DEFAULT '',
  `mime` char(80) NOT NULL DEFAULT '',
  `extension` char(6) NOT NULL DEFAULT '',
  `type` enum('document','image') NOT NULL DEFAULT 'document',
  `dateAjout` datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `groupes`
--

CREATE TABLE IF NOT EXISTS `groupes` (
  `idgroupe` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `nom` varchar(80) DEFAULT NULL,
  `description` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `lieu`
--

CREATE TABLE IF NOT EXISTS `lieu` (
`idLieu` smallint(5) unsigned NOT NULL,
  `idpersonne` smallint(5) unsigned NOT NULL DEFAULT '0',
  `statut` enum('actif','inactif','ancien') NOT NULL DEFAULT 'actif',
  `nom` varchar(40) NOT NULL DEFAULT '',
  `adresse` varchar(80) NOT NULL DEFAULT '',
  `quartier` varchar(255) NOT NULL DEFAULT 'Plainpalais',
  `lat` float(10,6) NOT NULL DEFAULT '0.000000',
  `lng` float(10,6) NOT NULL DEFAULT '0.000000',
  `horaire_general` text NOT NULL,
  `horaire_evenement` text NOT NULL,
  `entree` varchar(255) NOT NULL DEFAULT '',
  `categorie` set('bistrot','salle','restaurant','cinema','theatre','galerie','boutique','musee','autre') NOT NULL DEFAULT '',
  `telephone` varchar(40) NOT NULL DEFAULT '',
  `photo1` varchar(255) NOT NULL DEFAULT '',
  `photo2` varchar(255) NOT NULL DEFAULT '',
  `logo` varchar(255) NOT NULL DEFAULT '',
  `URL` varchar(255) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL DEFAULT '',
  `plan` varchar(255) NOT NULL DEFAULT '',
  `acces_tpg` varchar(255) NOT NULL DEFAULT '',
  `dateAjout` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `actif` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `determinant` varchar(40) NOT NULL DEFAULT '',
  `date_derniere_modif` datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `lieu_favori`
--

CREATE TABLE IF NOT EXISTS `lieu_favori` (
  `idPersonne` smallint(5) NOT NULL DEFAULT '0',
  `idLieu` smallint(5) NOT NULL DEFAULT '0',
  `date_ajout` datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `lieu_fichierrecu`
--

CREATE TABLE IF NOT EXISTS `lieu_fichierrecu` (
  `idLieu` mediumint(9) NOT NULL DEFAULT '0',
  `idFichierrecu` mediumint(9) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `lieu_organisateur`
--

CREATE TABLE IF NOT EXISTS `lieu_organisateur` (
  `idOrganisateur` mediumint(9) NOT NULL DEFAULT '0',
  `idLieu` mediumint(9) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `organisateur`
--

CREATE TABLE IF NOT EXISTS `organisateur` (
`idOrganisateur` mediumint(9) NOT NULL,
  `idPersonne` mediumint(9) NOT NULL DEFAULT '0',
  `nom` varchar(255) NOT NULL DEFAULT '',
  `adresse` varchar(255) NOT NULL DEFAULT '',
  `URL` varchar(255) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL DEFAULT '',
  `telephone` varchar(255) NOT NULL DEFAULT '',
  `logo` varchar(255) NOT NULL DEFAULT '',
  `photo` varchar(255) NOT NULL DEFAULT '',
  `presentation` text NOT NULL,
  `statut` enum('actif','inactif','ancien') NOT NULL DEFAULT 'actif',
  `date_ajout` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `date_derniere_modif` datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `personne`
--

CREATE TABLE IF NOT EXISTS `personne` (
`idPersonne` smallint(5) unsigned NOT NULL,
  `pseudo` varchar(60) NOT NULL DEFAULT '',
  `mot_de_passe` varchar(40) NOT NULL DEFAULT '',
  `cookie` varchar(32) NOT NULL DEFAULT '',
  `session` varchar(32) NOT NULL DEFAULT '',
  `ip` varchar(15) NOT NULL DEFAULT '',
  `groupe` tinyint(4) unsigned NOT NULL DEFAULT '12',
  `statut` enum('actif','inactif','demande') NOT NULL DEFAULT 'actif',
  `nom` varchar(40) NOT NULL DEFAULT '',
  `prenom` varchar(40) NOT NULL DEFAULT '',
  `affiliation` varchar(255) NOT NULL DEFAULT '',
  `adresse` text NOT NULL,
  `telephone` varchar(20) NOT NULL DEFAULT '',
  `email` varchar(80) NOT NULL DEFAULT '',
  `URL` text NOT NULL,
  `signature` enum('pseudo','prenom','nomcomplet','aucune') NOT NULL DEFAULT 'pseudo',
  `avec_affiliation` enum('oui','non') NOT NULL DEFAULT 'non',
  `notification_commentaires` enum('oui','non') NOT NULL DEFAULT 'non',
  `gds` varchar(255) NOT NULL DEFAULT '',
  `actif` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `dateAjout` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `date_derniere_modif` datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `personne_organisateur`
--

CREATE TABLE IF NOT EXISTS `personne_organisateur` (
  `idOrganisateur` mediumint(9) NOT NULL DEFAULT '0',
  `idPersonne` smallint(6) NOT NULL DEFAULT '0',
  `role` varchar(255) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `salle`
--

CREATE TABLE IF NOT EXISTS `salle` (
`idSalle` mediumint(9) NOT NULL,
  `idLieu` mediumint(9) NOT NULL DEFAULT '0',
  `idPersonne` mediumint(9) NOT NULL DEFAULT '0',
  `nom` varchar(255) NOT NULL DEFAULT '',
  `emplacement` varchar(255) NOT NULL DEFAULT '',
  `dateAjout` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `date_derniere_modif` datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `temp`
--

CREATE TABLE IF NOT EXISTS `temp` (
`id` mediumint(9) NOT NULL,
  `idPersonne` mediumint(9) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `token` varchar(255) NOT NULL DEFAULT '',
  `expiration` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Index pour les tables exportées
--

--
-- Index pour la table `affiliation`
--
ALTER TABLE `affiliation`
 ADD PRIMARY KEY (`idPersonne`,`idAffiliation`);

--
-- Index pour la table `breve`
--
ALTER TABLE `breve`
 ADD PRIMARY KEY (`idBreve`), ADD KEY `breve_dateajout` (`dateAjout`), ADD KEY `breve_actif` (`actif`);

--
-- Index pour la table `commentaire`
--
ALTER TABLE `commentaire`
 ADD PRIMARY KEY (`idCommentaire`);

--
-- Index pour la table `descriptionlieu`
--
ALTER TABLE `descriptionlieu`
 ADD PRIMARY KEY (`idLieu`,`idPersonne`), ADD KEY `desclieu_dateajout` (`dateAjout`);

--
-- Index pour la table `evenement`
--
ALTER TABLE `evenement`
 ADD PRIMARY KEY (`idevenement`), ADD KEY `semaine` (`genre`,`dateEvenement`), ADD KEY `dateajout` (`dateAjout`), ADD KEY `ev_idlieu_dateev` (`idLieu`,`dateEvenement`);

--
-- Index pour la table `evenement_favori`
--
ALTER TABLE `evenement_favori`
 ADD PRIMARY KEY (`idPersonne`,`idEvenement`);

--
-- Index pour la table `evenement_fichierrecu`
--
ALTER TABLE `evenement_fichierrecu`
 ADD PRIMARY KEY (`idEvenement`,`idFichierrecu`);

--
-- Index pour la table `evenement_organisateur`
--
ALTER TABLE `evenement_organisateur`
 ADD PRIMARY KEY (`idEvenement`,`idOrganisateur`);

--
-- Index pour la table `fichierrecu`
--
ALTER TABLE `fichierrecu`
 ADD PRIMARY KEY (`idFichierrecu`);

--
-- Index pour la table `groupes`
--
ALTER TABLE `groupes`
 ADD PRIMARY KEY (`idgroupe`);

--
-- Index pour la table `lieu`
--
ALTER TABLE `lieu`
 ADD PRIMARY KEY (`idLieu`), ADD KEY `nom` (`nom`), ADD KEY `lieu_dateajout` (`dateAjout`);

--
-- Index pour la table `lieu_favori`
--
ALTER TABLE `lieu_favori`
 ADD PRIMARY KEY (`idPersonne`,`idLieu`);

--
-- Index pour la table `lieu_fichierrecu`
--
ALTER TABLE `lieu_fichierrecu`
 ADD PRIMARY KEY (`idLieu`,`idFichierrecu`);

--
-- Index pour la table `lieu_organisateur`
--
ALTER TABLE `lieu_organisateur`
 ADD PRIMARY KEY (`idOrganisateur`,`idLieu`);

--
-- Index pour la table `organisateur`
--
ALTER TABLE `organisateur`
 ADD PRIMARY KEY (`idOrganisateur`);

--
-- Index pour la table `personne`
--
ALTER TABLE `personne`
 ADD PRIMARY KEY (`idPersonne`), ADD KEY `pseudo` (`pseudo`);

--
-- Index pour la table `personne_organisateur`
--
ALTER TABLE `personne_organisateur`
 ADD PRIMARY KEY (`idOrganisateur`,`idPersonne`);

--
-- Index pour la table `salle`
--
ALTER TABLE `salle`
 ADD PRIMARY KEY (`idSalle`);

--
-- Index pour la table `temp`
--
ALTER TABLE `temp`
 ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `idPersonne` (`idPersonne`);

--
-- AUTO_INCREMENT pour les tables exportées
--

--
-- AUTO_INCREMENT pour la table `breve`
--
ALTER TABLE `breve`
MODIFY `idBreve` mediumint(8) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT pour la table `commentaire`
--
ALTER TABLE `commentaire`
MODIFY `idCommentaire` mediumint(11) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT pour la table `evenement`
--
ALTER TABLE `evenement`
MODIFY `idevenement` mediumint(8) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT pour la table `fichierrecu`
--
ALTER TABLE `fichierrecu`
MODIFY `idFichierrecu` mediumint(9) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT pour la table `lieu`
--
ALTER TABLE `lieu`
MODIFY `idLieu` smallint(5) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT pour la table `organisateur`
--
ALTER TABLE `organisateur`
MODIFY `idOrganisateur` mediumint(9) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT pour la table `personne`
--
ALTER TABLE `personne`
MODIFY `idPersonne` smallint(5) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT pour la table `salle`
--
ALTER TABLE `salle`
MODIFY `idSalle` mediumint(9) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT pour la table `temp`
--
ALTER TABLE `temp`
MODIFY `id` mediumint(9) NOT NULL AUTO_INCREMENT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

INSERT INTO `groupes` (`idgroupe`, `nom`, `description`) VALUES
(1, 'admin', 'accès à tout'),
(4, 'techno', 'inutilisé'),
(6, 'auteur', 'accès à tous les contenus'),
(8, 'organisateur', 'ajout et modif de ses even, éventuellement de sa fiche organisateur'),
(10, 'contributeur', '? (33 personnes)'),
(12, 'membre', 'favoris, commentaires');