INSERT INTO `option` (`plugin`, `typ`, `value`, `default_value`, `beschreibung`, `format`) 
VALUES ('MARKTPLATZ', 'GLOBAL_RATINGS', '1', '0', 'Erlaubt es in allen Kommentar-Bereichen eine Bewertung abzugeben\r\nAllows adding a rating to all comment areas', 'check');

ALTER TABLE `comment_stats` ADD COLUMN `RATING_JSON` TEXT NULL DEFAULT NULL AFTER `RATING_COUNT`;

ALTER TABLE `chat`
	ADD COLUMN `STAMP_CHANGED` DATETIME NULL DEFAULT NULL AFTER `STAMP_CREATE`,
	ADD COLUMN `STAMP_REPLY` DATETIME NULL DEFAULT NULL AFTER `STAMP_CHANGED`,
	ADD INDEX `STAMP_CHANGED` (`STAMP_CHANGED`);

UPDATE `chat` SET 
	FK_CHAT_USER=(SELECT SENDER FROM `chat_message` WHERE FK_CHAT=ID_CHAT ORDER BY ID_CHAT_MESSAGE ASC LIMIT 1)
WHERE FK_CHAT_USER IS NULL;

UPDATE `chat` SET
	STAMP_CHANGED=(SELECT MAX(STAMP_CREATE) FROM `chat_message` WHERE FK_CHAT=ID_CHAT),
	STAMP_REPLY=(SELECT MAX(STAMP_CREATE) FROM `chat_message` WHERE FK_CHAT=ID_CHAT AND SENDER!=FK_CHAT_USER)
WHERE (STAMP_CHANGED IS NULL) OR (STAMP_REPLY IS NULL);

ALTER TABLE `ad_upload`
ADD `IS_FREE` tinyint NOT NULL DEFAULT '1';

ALTER TABLE `ad_sold`
ADD `STAMP_PAYED` datetime NULL AFTER `PAYED`;

INSERT INTO `option` (`plugin`, `typ`, `value`, `default_value`, `beschreibung`, `orderfeld`, `format`)
VALUES ('MARKTPLATZ', 'PAID_DOWNLOAD_LIFETIME', '14', '14', 'Lebensdauer für kostenpflichtige Dateien in Tagen.\r\nLifetime for paid downloadable files in days.', '0', 'int');

ALTER TABLE `vendor`
	ADD COLUMN `BUSINESS_HOURS` TEXT NOT NULL AFTER `LOGO`;

ALTER TABLE `ad_sold`
	ALTER `FK_MAN` DROP DEFAULT;
ALTER TABLE `ad_sold`
	CHANGE COLUMN `FK_MAN` `FK_MAN` BIGINT(20) UNSIGNED NULL AFTER `SER_AVAILABILITY`;

CREATE TABLE `billing_cancel` (
  `ID_BILLING_CANCEL` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `FK_USER` bigint(20) unsigned NOT NULL,
  `CUSTOMER_REMARKS` text NOT NULL,
  `ADMIN_REMARKS` text NOT NULL,
  `FK_LOOKUP` bigint(20) unsigned NOT NULL,
  `STATUS` enum('pending','rejected','done','shelve') NOT NULL DEFAULT 'pending',
  `FK_BILLING_BILLABLEITEM` bigint(20) unsigned DEFAULT NULL,
  `FK_BILLING_INVOICE_ITEM` bigint(20) unsigned DEFAULT NULL,
  `CREATED_AT` datetime NOT NULL,
  `LAST_MODIFIED` datetime DEFAULT NULL,
  PRIMARY KEY (`ID_BILLING_CANCEL`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `billing_cancel_item` (
  `ID_BILLING_CANCEL_ITEM` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `FK_BILLING_CANCEL` bigint(20) unsigned NOT NULL,
  `FK_BILLING_INVOICE_ITEM` bigint(20) unsigned DEFAULT NULL,
  `FK_BILLING_BILLABLEITEM` bigint(20) unsigned DEFAULT NULL,
  `FK_USER` bigint(20) unsigned NOT NULL,
  `DESCRIPTION` mediumtext NOT NULL,
  `QUANTITY` float NOT NULL,
  `PRICE` decimal(10,4) NOT NULL,
  `FK_TAX` bigint(20) unsigned NOT NULL,
  `CANCEL_TIME` datetime NOT NULL,
  PRIMARY KEY (`ID_BILLING_CANCEL_ITEM`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;