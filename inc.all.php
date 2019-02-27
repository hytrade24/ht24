<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once dirname(__FILE__).'/inc.legacy.php'; // PHP7 Kompatibilität
include dirname(__FILE__).'/inc.server.php'; // Server-abh. Einstellungen

if (file_exists(dirname(__FILE__).'/cache/option.php')) {
	include dirname(__FILE__).'/cache/option.php'; // System-Einstellungen
}

require_once dirname(__FILE__).'/sys/lib.debug.php'; // Server-abh. Einstellungen

include dirname(__FILE__).'/inc.app.php'; // Anwendungs-abh. Einstellungen

require_once dirname(__FILE__).'/sys/lib.misc.php'; // sonstige Funktionen
require_once dirname(__FILE__).'/sys/lib.string.php'; // sonstige Funktionen

require_once dirname(__FILE__).'/sys/lib.db.mysql.php'; // DB Wrapper class db
require_once dirname(__FILE__).'/sys/lib.template.php'; // Template-Klassen

?>