/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
CREATE DATABASE /*!32312 IF NOT EXISTS*/`bowdlerize` /*!40100 DEFAULT CHARACTER SET utf8 */;

/*Table structure for table `probes` */

DROP TABLE IF EXISTS `probes`;

CREATE TABLE `probes` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `uuid` varchar(32) NOT NULL,
  `userID` int(11) unsigned default NULL,
  `publicKey` text,
  `secret` varchar(128),
  `type` enum('raspi','android','atlas','web') NOT NULL,
  `lastSeen` datetime default NULL,
  `gcmRegID` text,
  `isPublic` tinyint(1) unsigned default '0',
  `countryCode` varchar(3) default NULL,
  `probeReqSent` int(11) unsigned default 0,
  `probeRespRecv` int(11) unsigned default 0,
  `enabled` tinyint(1) unsigned default '1',
  `frequency` int(11) unsigned default '2',
  `gcmType` int(11) unsigned default '0',
  PRIMARY KEY  (`uuid`,`id`),
  UNIQUE KEY `probeUUID` (`uuid`),
  KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

/*Table structure for table `tempURLs` */

DROP TABLE IF EXISTS `tempURLs`;

CREATE TABLE `tempURLs` (
  `tempID` int(11) unsigned NOT NULL auto_increment,
  `URL` text,
  `hash` varchar(32) default NULL,
  `headers` text,
  `content_type` text,
  `code` int(11) unsigned default NULL,
  `fullFidelityReq` tinyint(1) unsigned default '0',
  `urgency` int(11) unsigned default '0',
  `source` enum('social','user','canary','probe') default NULL,
  `targetASN` int(11) unsigned default NULL,
  `status` enum('pending','failed','ready','complete') default NULL,
  `lastPolled` datetime default NULL,
  `inserted` timestamp NULL default CURRENT_TIMESTAMP,
  `polledAttempts` int(11) unsigned default '0',
  `polledSuccess` int(11) unsigned default '0',
  PRIMARY KEY  (`tempID`),
  UNIQUE KEY `tempurl_url` (`URL`(255))
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

/*Table structure for table `users` */

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `email` varchar(128) NOT NULL,
  `password` varchar(255) default NULL,
  `preference` text,
  `fullName` varchar(60) default NULL,
  `isPublic` tinyint(1) unsigned default '0',
  `countryCode` varchar(3) default NULL,
  `probeHMAC` varchar(32) default NULL,
  `status` enum('pending','ok','suspended','banned') default 'pending',
  `pgpKey` text,
  `yubiKey` varchar(12) default NULL,
  `publicKey` text,
  `secret` varchar(128),
  `createdAt` timestamp NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `results`;

CREATE TABLE `results` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `urlID` int(11) NOT NULL,
  `probeID` int(11) NOT NULL,
  `config` int(11) NOT NULL,
  `ip_network` varchar(16) DEFAULT NULL,
  `status` varchar(8) DEFAULT NULL,
  `http_status` int(11) DEFAULT NULL,
  `network_name` varchar(32) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `requests`;

CREATE TABLE `requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `urlID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `submission_info` text,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
