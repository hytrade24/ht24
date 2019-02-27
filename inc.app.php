<?php
/* ###VERSIONSBLOCKINLCUDE### */

// Disable notices and warnings
#error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED & ~E_WARNING);


// APPLICATION SETTINGS
session_name('ebiztrader');

define ('LOG_REDIRECTS', false);
define ('ERRCLASS', ' class="error"');
define ('DEV_DEBUG', is_dir(__DIR__."/dev"));

// DB
$err_dbconnect = 'Keine Verbindung zum Datenbank-Server.';
$err_dbselect = 'Datenbank nicht gefunden.';
// page_edit
$err_identsyntax = 'Unzul&auml;ssiges Zeichen im Bezeichner.';
$err_require_label = 'Kein Label angegeben.';
$err_require_trg = 'Kein Eltern Objekt gewählt';
// file_edit
$err_pagenotfound = 'Datei nicht gefunden.';
$err_noext = 'Keine Datei-Erweiterung angegeben!';
$err_fopen = 'Fehler beim &Ouml;ffnen der Datei.';
// systemsettings
$err_notfound = 'unbekannt';
// users
$err_allrequired = 'Bitte f&uuml;llen Sie alle Felder aus!';
$err_uniquemail = 'Diese E-Mail-Adresse wird schon von einem anderen User verwendet.';

define('EBIZ_TRADER_VERSION', '7.5.1');

?>