# BotSector

See <http://www.dugwood.com/botsector/index.html>.

## Installation
- clone the github repo:
```
git clone https://github.com/dugwood/botsector.git
```
- copy the config/config.sample.ini.php to config/config.ini.php
- edit the config/config.ini.php to suit your needs
- execute the SQL script below in a (new) database
- point to botsector/index.php


## Database schema

Current database schema (MySQL InnoDB) :
```sql
--
-- Structure de la table `BOTSECTOR_CRAWLERS`
--

CREATE TABLE IF NOT EXISTS `BOTSECTOR_CRAWLERS` (
  `BCR_ID` smallint(5) unsigned NOT NULL,
  `BCR_NAME` varchar(50) CHARACTER SET utf8mb4 NOT NULL,
  `BCR_SIGNATURE` text CHARACTER SET utf8mb4 NOT NULL,
  `BCR_WEBSITE` varchar(200) CHARACTER SET utf8mb4 NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `BOTSECTOR_DIRECTORIES`
--

CREATE TABLE IF NOT EXISTS `BOTSECTOR_DIRECTORIES` (
`BDR_ID` mediumint(9) unsigned NOT NULL,
  `BDM_ID` mediumint(9) unsigned NOT NULL,
  `BDR_DIRECTORY` varchar(500) CHARACTER SET utf8mb4 NOT NULL,
  `BDR_SUBDIRECTORIES` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `BDR_CHECKSUM` varchar(60) CHARACTER SET ascii NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=10057856 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `BOTSECTOR_DOMAINS`
--

CREATE TABLE IF NOT EXISTS `BOTSECTOR_DOMAINS` (
`BDM_ID` mediumint(8) unsigned NOT NULL,
  `BDM_DOMAIN` varchar(100) CHARACTER SET utf8mb4 NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=14087 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `BOTSECTOR_LOGS`
--

CREATE TABLE IF NOT EXISTS `BOTSECTOR_LOGS` (
`BLG_ID` mediumint(8) unsigned NOT NULL,
  `BLG_SERVER` varchar(15) CHARACTER SET ascii NOT NULL DEFAULT '0.0.0.0',
  `BLG_PATH` varchar(500) CHARACTER SET ascii NOT NULL,
  `BLG_STATUS` enum('ERROR','PARSED','NEW','PARSING') CHARACTER SET ascii NOT NULL DEFAULT 'NEW',
  `BLG_MIN_DATE` date NOT NULL DEFAULT '0000-00-00',
  `BLG_MAX_DATE` date NOT NULL DEFAULT '0000-00-00',
  `BLG_STARTED` datetime NOT NULL,
  `BLG_FINISHED` datetime NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=669242 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `BOTSECTOR_STATISTICS`
--

CREATE TABLE IF NOT EXISTS `BOTSECTOR_STATISTICS` (
  `BST_DATE` date NOT NULL,
  `BLG_ID` mediumint(8) unsigned NOT NULL,
  `BDM_ID` mediumint(8) unsigned NOT NULL,
  `BDR_ID` mediumint(8) unsigned NOT NULL,
  `BCR_ID` smallint(5) unsigned NOT NULL,
  `BST_HTML_HITS` int(10) unsigned NOT NULL,
  `BST_HTML_SIZE` int(10) unsigned NOT NULL,
  `BST_XML_HITS` int(10) unsigned NOT NULL,
  `BST_XML_SIZE` int(10) unsigned NOT NULL,
  `BST_RESOURCES_HITS` int(10) unsigned NOT NULL,
  `BST_RESOURCES_SIZE` int(10) unsigned NOT NULL,
  `BST_MEDIA_HITS` int(10) unsigned NOT NULL,
  `BST_MEDIA_SIZE` int(10) unsigned NOT NULL,
  `BST_ROBOTS_HITS` int(10) unsigned NOT NULL,
  `BST_ROBOTS_SIZE` int(10) unsigned NOT NULL,
  `BST_UNKNOWN_HITS` int(10) unsigned NOT NULL,
  `BST_UNKNOWN_SIZE` int(10) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Index pour les tables exportées
--

--
-- Index pour la table `BOTSECTOR_CRAWLERS`
--
ALTER TABLE `BOTSECTOR_CRAWLERS`
 ADD PRIMARY KEY (`BCR_ID`);

--
-- Index pour la table `BOTSECTOR_DIRECTORIES`
--
ALTER TABLE `BOTSECTOR_DIRECTORIES`
 ADD PRIMARY KEY (`BDR_ID`), ADD UNIQUE KEY `DOMAIN_CHECKSUM` (`BDM_ID`,`BDR_CHECKSUM`);

--
-- Index pour la table `BOTSECTOR_DOMAINS`
--
ALTER TABLE `BOTSECTOR_DOMAINS`
 ADD PRIMARY KEY (`BDM_ID`), ADD UNIQUE KEY `BDM_DOMAIN` (`BDM_DOMAIN`);

--
-- Index pour la table `BOTSECTOR_LOGS`
--
ALTER TABLE `BOTSECTOR_LOGS`
 ADD PRIMARY KEY (`BLG_ID`), ADD UNIQUE KEY `SERVER_AND_PATH` (`BLG_SERVER`,`BLG_PATH`);

--
-- Index pour la table `BOTSECTOR_STATISTICS`
--
ALTER TABLE `BOTSECTOR_STATISTICS`
 ADD KEY `BDR_ID` (`BDR_ID`), ADD KEY `BCR_ID` (`BCR_ID`), ADD KEY `BDM_ID` (`BDM_ID`), ADD KEY `BLG_ID` (`BLG_ID`), ADD KEY `BST_DATE` (`BST_DATE`);

--
-- AUTO_INCREMENT pour les tables exportées
--

--
-- AUTO_INCREMENT pour la table `BOTSECTOR_DIRECTORIES`
--
ALTER TABLE `BOTSECTOR_DIRECTORIES`
MODIFY `BDR_ID` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=10057856;
--
-- AUTO_INCREMENT pour la table `BOTSECTOR_DOMAINS`
--
ALTER TABLE `BOTSECTOR_DOMAINS`
MODIFY `BDM_ID` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=14087;
--
-- AUTO_INCREMENT pour la table `BOTSECTOR_LOGS`
--
ALTER TABLE `BOTSECTOR_LOGS`
MODIFY `BLG_ID` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=669242;
--
-- Contraintes pour les tables exportées
--

--
-- Contraintes pour la table `BOTSECTOR_DIRECTORIES`
--
ALTER TABLE `BOTSECTOR_DIRECTORIES`
ADD CONSTRAINT `BOTSECTOR_DIRECTORIES_ibfk_1` FOREIGN KEY (`BDM_ID`) REFERENCES `BOTSECTOR_DOMAINS` (`BDM_ID`);

--
-- Contraintes pour la table `BOTSECTOR_STATISTICS`
--
ALTER TABLE `BOTSECTOR_STATISTICS`
ADD CONSTRAINT `BOTSECTOR_STATISTICS_ibfk_1` FOREIGN KEY (`BDR_ID`) REFERENCES `BOTSECTOR_DIRECTORIES` (`BDR_ID`),
ADD CONSTRAINT `BOTSECTOR_STATISTICS_ibfk_2` FOREIGN KEY (`BCR_ID`) REFERENCES `BOTSECTOR_CRAWLERS` (`BCR_ID`),
ADD CONSTRAINT `BOTSECTOR_STATISTICS_ibfk_3` FOREIGN KEY (`BDM_ID`) REFERENCES `BOTSECTOR_DOMAINS` (`BDM_ID`),
ADD CONSTRAINT `BOTSECTOR_STATISTICS_ibfk_4` FOREIGN KEY (`BLG_ID`) REFERENCES `BOTSECTOR_LOGS` (`BLG_ID`);
```