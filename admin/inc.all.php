<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once '../inc.legacy.php'; // PHP7 Kompatibilität
require_once '../inc.server.php'; // Server-abh. Einstellungen
require_once '../inc.app.php'; // Anwendungs-abh. Einstellungen


chdir ( 'sys' );
require_once 'lib.debug.php'; // Server-abh. Einstellungen


#  require_once 'inc.messages.php';  // Meldungen und Mitteilungen


#  require_once 'lib.error.php';    // Fehlerbehandlung
#  require_once 'lib.string.php';   // String-Funktionen:
//          validate_email, magic_unquote_gpc, magic_unquote_runtime, iso2date
#  require_once 'lib.forms.php';    // Formular-Template-Klassen
require_once 'lib.misc.php'; // sonstige Funktionen
require_once 'lib.string.php'; // sonstige Funktionen


require_once 'lib.db.mysql.php'; // DB Wrapper class db
require_once 'lib.template.php'; // Template-Klassen
require_once '../../cache/option.php';

#  require_once '';


#  lang.xx.php        Bezeichner
chdir ( '..' );
?>