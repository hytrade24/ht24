<?php
/* ###VERSIONSBLOCKINLCUDE### */



global $db;

// Alte Statistiken löschen
$db->querynow("
	DELETE FROM
		`advertisement_stat`
    WHERE STAMP < DATE_SUB(CURDATE(),INTERVAL 40 DAY)");


?>