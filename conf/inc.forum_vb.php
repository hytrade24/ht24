<?php
/* ###VERSIONSBLOCKINLCUDE### */



global $ar_vb;
### Config File für die Kommunikation mit dem vBulletin
$ar_vb = array(

	// URL der Foren-Schnittstelle
   'API_URL'		=> 'http://dev.ebiz-trader.de/forum/api.php',

	// API-Schlüssel des Forums
	//   vBulletin-Einstellungen -> Einstellungen -> vBulletin-API und mobile Anwendungen
   'API_KEY' 		=> 'g5FRgKJr',

	// Verwendete Version der Mobile-API
   'API_VERSION'	=> 4,

	// Prefix der vBulletin-Datenbanken
   'DB_PREFIX'		=> 'vb_',

	// Für das Cookie verwendete Salt
	//   Zu finden in: /forum/includes/functions.php
	//   define('COOKIE_SALT', '??????????????????');
   'COOKIE_SALT'	=> 'L4j8W0zbO4JhXTV7g6kEYTsTIenQX'

);

?>