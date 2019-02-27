ALTER TABLE `ad_agent`
ADD `STATUS` tinyint unsigned NOT NULL COMMENT '0 for pause, 1 for activate',
ADD `LIFE_CYCLE_ENDS` datetime NOT NULL AFTER `status`,
ADD `CREATED_AT` datetime NOT NULL AFTER `life_cycle_ends`;

INSERT INTO `option` (`plugin`, `typ`, `value`, `default_value`, `beschreibung`, `orderfeld`, `format`)
VALUES ('MARKTPLATZ', 'ANZEIGEN_AGENT_RUN_LIFECYCLE', '4', '4', 'Default run life cycle of anzeigen agent in months', '0', 'int');

CREATE TABLE `log_views_clicks` (
	`ID_LOG_VIEWS_CLICKS` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	`SOURCE_LINK` VARCHAR(300) NULL DEFAULT NULL,
	`CLIENT_HASH` VARCHAR(64) NOT NULL,
	`TABLE_ID` BIGINT(20) NULL DEFAULT NULL,
	`TABLE` VARCHAR(20) NOT NULL,
	`TYPE` VARCHAR(10) NOT NULL,
	`CREATED_AT` DATETIME NOT NULL,
	PRIMARY KEY (`ID_LOG_VIEWS_CLICKS`),
	UNIQUE INDEX `SOURCE_HASH_KEY_TABLE_ID_TABLE` (`CLIENT_HASH`, `TABLE_ID`, `TABLE`, `TYPE`)
)
COLLATE='utf8_general_ci'
ENGINE=MyISAM;

CREATE TABLE `log_views_clicks_deduce_result_per_day` (
	`ID_LOG_VIEWS_CLICKS_DEDUCE_RESULT_PER_DAY` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	`COUNT` BIGINT(20) UNSIGNED NOT NULL,
	`TYPE` VARCHAR(10) NOT NULL,
	`FK_TABLE` BIGINT(20) UNSIGNED NOT NULL,
	`TABLE` VARCHAR(15) NOT NULL,
	`DAY` DATE NOT NULL,
	PRIMARY KEY (`ID_LOG_VIEWS_CLICKS_DEDUCE_RESULT_PER_DAY`),
	INDEX `FK_TABLE_TABLE_DAY` (`FK_TABLE`, `TABLE`, `DAY`)
)
COLLATE='utf8_general_ci'
ENGINE=MyISAM;

ALTER TABLE `currency`
ADD `ISO_CURRENCY_FORMAT` varchar(3) NULL,
ADD `LAST_UPDATED` datetime NULL,
ADD `AUTOMATICALLY_UPDATED` tinyint unsigned NOT NULL DEFAULT '1';

ALTER TABLE `ad_master`
ADD `FK_CURRENCY` bigint unsigned NULL AFTER `PREIS`,
ADD `PREIS_IN_BASE_CURRENCY` float unsigned NULL AFTER `FK_CURRENCY`;

INSERT INTO `crontab` (`EINMALIG`, `FEHLER`, `SYSNAME`, `ERLEDIGT`, `PRIO`, `DSC`, `FIRST`, `LAST`, `EINHEIT`, `ALL_X`, `DATEI`, `FUNKTION`, `FK`, `CODE`) VALUES
(NULL,	0,	NULL,	NULL,	1,	'Update Marketplace Currencies',	'2017-08-09 14:19:04',	'2017-08-16 10:07:00',	'day',	1,	'cron/update_currencies.php',	'',	NULL,	''),
(NULL,	0,	NULL,	NULL,	1,	'Remove logs',	'2017-08-02 23:00:00',	'2017-08-16 10:05:00',	'day',	1,	'cron/remove_logs.php',	'',	NULL,	'');

INSERT INTO `option` (`plugin`, `typ`, `value`, `default_value`, `beschreibung`, `orderfeld`, `format`)
VALUES ('MARKTPLATZ', 'LASTSCHRIFT_VERIFICATION', '1', '1', 'Fragen Sie nach Überprüfung des Lastschrift bankkonto', '0', 'check');

ALTER TABLE `billing_invoice_export`
ADD `STAMP_MARK_AS_PAID` datetime NULL AFTER `MARK_AS_PAID`;

UPDATE `payment_adapter` SET
`CONFIG` = 'Recipient=Musterfirma GmbH\r\nIBAN=\r\nBIC=\r\nCREDITORID=\r\nPREFIX=EBT'
WHERE `ADAPTER_NAME` = 'DirectDebit';

ALTER TABLE `liste_values`
ADD `ORDER` int unsigned NOT NULL DEFAULT '1';