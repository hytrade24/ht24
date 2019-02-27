UPDATE `option` SET `format`='list sendmail smtp' 
	WHERE  `plugin`='EMAIL' AND `typ`='TRANSPORT_TYPE';

INSERT INTO `option` (`plugin`, `typ`, `value`, `default_value`, `beschreibung`, `format`) 
	VALUES ('EMAIL', 'SMTP_HOST', 'localhost', 'localhost', 'Host-Name für den SMTP-Versand\r\nHost name for SMTP mailing', 'text');
	
INSERT INTO `option` (`plugin`, `typ`, `value`, `default_value`, `beschreibung`, `format`) 
	VALUES ('EMAIL', 'SMTP_PORT', '25', '25', 'Port für den SMTP-Versand\r\nPort for SMTP mailing', 'text');
	
INSERT INTO `option` (`plugin`, `typ`, `value`, `default_value`, `beschreibung`, `format`) 
	VALUES ('EMAIL', 'SMTP_ENCRYPTION', 'ssl', 'ssl', 'Verschlüsselung für den SMTP-Versand\r\nEncryption for SMTP mailing', 'list ssl tls none');
	
INSERT INTO `option` (`plugin`, `typ`, `value`, `default_value`, `beschreibung`, `format`) 
	VALUES ('EMAIL', 'SMTP_USER', '', '', 'Benutzername für den SMTP-Versand\r\nUsername for SMTP mailing', 'text');
	
INSERT INTO `option` (`plugin`, `typ`, `value`, `default_value`, `beschreibung`, `format`) 
	VALUES ('EMAIL', 'SMTP_PASS', '', '', 'Passwort für den SMTP-Versand\r\nPassword for SMTP mailing', 'text');