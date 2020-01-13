USE `app_bragi`;

DROP TABLE IF EXISTS `profile_persist`;

CREATE TABLE IF NOT EXISTS `profile_persist` (
	`persistID` bigint(20) UNSIGNED NOT NULL,
	`profileID` mediumint(8) UNSIGNED NOT NULL,
	PRIMARY KEY (`persistID`),
	KEY `profileID` (`profileID`)
	) ENGINE=TokuDB DEFAULT CHARSET=utf8;