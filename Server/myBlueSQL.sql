# ************************************************************
# Sequel Pro SQL dump
# Version 4541
#
# http://www.sequelpro.com/
# https://github.com/sequelpro/sequelpro
#
# Host: 127.0.0.1 (MySQL 5.7.20-0ubuntu0.16.04.1)
# Database: BlueSky
# Generation Time: 2017-11-16 19:18:48 +0000
# ************************************************************

# Dump of table computers
# ------------------------------------------------------------

DROP TABLE IF EXISTS `computers`;

CREATE TABLE `computers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `blueskyid` smallint(6) NOT NULL,
  `serialnum` varchar(200) DEFAULT NULL,
  `hostname` varchar(250) DEFAULT NULL,
  `sshlink` text,
  `vnclink` text,
  `scplink` text,
  `username` varchar(40) DEFAULT NULL,
  `sharingname` varchar(250) DEFAULT NULL,
  `gruntwork` text,
  `datetime` varchar(100) DEFAULT NULL,
  `status` varchar(253) DEFAULT NULL,
  `registered` varchar(40) DEFAULT NULL,
  `notify` tinyint(4) DEFAULT NULL,
  `alert` tinyint(4) DEFAULT NULL,
  `email` varchar(253) DEFAULT NULL,
  `notes` text,
  `selfdestruct` tinyint(4) DEFAULT NULL,
  `downup` tinyint(4) DEFAULT NULL,
  `timestamp` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blueskyid` (`blueskyid`),
  UNIQUE KEY `serialnum` (`serialnum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


# Dump of table connections
# ------------------------------------------------------------

DROP TABLE IF EXISTS `connections`;

CREATE TABLE `connections` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `timestamp` varchar(40) DEFAULT NULL,
  `sourceIP` varchar(40) DEFAULT NULL,
  `adminkey` varchar(100) DEFAULT NULL,
  `targetPort` varchar(5) DEFAULT NULL,
  `exitStatus` varchar(250) DEFAULT NULL,
  `startTime` varchar(100) DEFAULT NULL,
  `endTime` varchar(100) DEFAULT NULL,
  `notes` varchar(254) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Dump of table global
# ------------------------------------------------------------

DROP TABLE IF EXISTS `global`;

CREATE TABLE `global` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `defaultemail` varchar(250) DEFAULT NULL,
  `adminkeys` text,
  `clickhere` varchar(40) DEFAULT NULL,
  `updateNames` varchar(2) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

LOCK TABLES `global` WRITE;

INSERT INTO `global` (`id`, `defaultemail`, `adminkeys`, `clickhere`, `updateNames`)
VALUES
	(1,NULL,NULL,'Click Here To Edit',NULL);

UNLOCK TABLES;


# Dump of table membership_grouppermissions
# ------------------------------------------------------------

DROP TABLE IF EXISTS `membership_grouppermissions`;

CREATE TABLE `membership_grouppermissions` (
  `permissionID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `groupID` int(11) DEFAULT NULL,
  `tableName` varchar(100) DEFAULT NULL,
  `allowInsert` tinyint(4) DEFAULT NULL,
  `allowView` tinyint(4) NOT NULL DEFAULT '0',
  `allowEdit` tinyint(4) NOT NULL DEFAULT '0',
  `allowDelete` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`permissionID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

LOCK TABLES `membership_grouppermissions` WRITE;

INSERT INTO `membership_grouppermissions` (`permissionID`, `groupID`, `tableName`, `allowInsert`, `allowView`, `allowEdit`, `allowDelete`)
VALUES
	(1,2,'computers',1,3,3,3),
	(2,2,'global',1,3,3,3),
	(3,2,'connections',1,3,3,3);

UNLOCK TABLES;


# Dump of table membership_groups
# ------------------------------------------------------------

DROP TABLE IF EXISTS `membership_groups`;

CREATE TABLE `membership_groups` (
  `groupID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(20) DEFAULT NULL,
  `description` text,
  `allowSignup` tinyint(4) DEFAULT NULL,
  `needsApproval` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`groupID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

LOCK TABLES `membership_groups` WRITE;

INSERT INTO `membership_groups` (`groupID`, `name`, `description`, `allowSignup`, `needsApproval`)
VALUES
	(1,'anonymous','Anonymous group created automatically on 2017-11-16',0,0),
	(2,'Admins','Admin group created automatically on 2017-11-16',0,1);

UNLOCK TABLES;


# Dump of table membership_userpermissions
# ------------------------------------------------------------

DROP TABLE IF EXISTS `membership_userpermissions`;

CREATE TABLE `membership_userpermissions` (
  `permissionID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `memberID` varchar(20) NOT NULL,
  `tableName` varchar(100) DEFAULT NULL,
  `allowInsert` tinyint(4) DEFAULT NULL,
  `allowView` tinyint(4) NOT NULL DEFAULT '0',
  `allowEdit` tinyint(4) NOT NULL DEFAULT '0',
  `allowDelete` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`permissionID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;



# Dump of table membership_userrecords
# ------------------------------------------------------------

DROP TABLE IF EXISTS `membership_userrecords`;

CREATE TABLE `membership_userrecords` (
  `recID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tableName` varchar(100) DEFAULT NULL,
  `pkValue` varchar(255) DEFAULT NULL,
  `memberID` varchar(20) DEFAULT NULL,
  `dateAdded` bigint(20) unsigned DEFAULT NULL,
  `dateUpdated` bigint(20) unsigned DEFAULT NULL,
  `groupID` int(11) DEFAULT NULL,
  PRIMARY KEY (`recID`),
  KEY `pkValue` (`pkValue`),
  KEY `tableName` (`tableName`),
  KEY `memberID` (`memberID`),
  KEY `groupID` (`groupID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;



# Dump of table membership_users
# ------------------------------------------------------------

DROP TABLE IF EXISTS `membership_users`;

CREATE TABLE `membership_users` (
  `memberID` varchar(20) NOT NULL,
  `passMD5` varchar(40) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `signupDate` date DEFAULT NULL,
  `groupID` int(10) unsigned DEFAULT NULL,
  `isBanned` tinyint(4) DEFAULT NULL,
  `isApproved` tinyint(4) DEFAULT NULL,
  `custom1` text,
  `custom2` text,
  `custom3` text,
  `custom4` text,
  `comments` text,
  `pass_reset_key` varchar(100) DEFAULT NULL,
  `pass_reset_expiry` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`memberID`),
  KEY `groupID` (`groupID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

LOCK TABLES `membership_users` WRITE;

INSERT INTO `membership_users` (`memberID`, `passMD5`, `email`, `signupDate`, `groupID`, `isBanned`, `isApproved`, `custom1`, `custom2`, `custom3`, `custom4`, `comments`, `pass_reset_key`, `pass_reset_expiry`)
VALUES
	('admin','eb8784b668ebfcfff84767eef83c2484','user@pretendco.com','2017-11-16',2,0,1,NULL,NULL,NULL,NULL,'Admin member created automatically on 2017-11-16\nRecord updated automatically on 2017-11-16',NULL,NULL),
	('guest',NULL,NULL,'2017-11-16',1,1,1,NULL,NULL,NULL,NULL,'Anonymous member created automatically on 2017-11-16',NULL,NULL);

UNLOCK TABLES;
