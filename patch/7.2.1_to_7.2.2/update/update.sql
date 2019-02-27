INSERT INTO `option` (`plugin`, `typ`, `value`, `default_value`, `beschreibung`, `format`) 
	VALUES ('MARKTPLATZ', 'CHAT_SHOW_CONTACT', '0', '0', 'Kontaktdaten des Absenders bei Nachrichten darstellen', 'check');

ALTER TABLE `chat`
	ADD COLUMN `FK_CHAT_USER` INT(11) NULL DEFAULT NULL AFTER `SUBJECT`;
	
UPDATE `chat` SET 
	FK_CHAT_USER=(SELECT SENDER FROM `chat_message` WHERE FK_CHAT=ID_CHAT ORDER BY ID_CHAT_MESSAGE ASC LIMIT 1)
WHERE FK_CHAT_USER IS NULL;