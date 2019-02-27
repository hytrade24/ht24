<?php
/* ###VERSIONSBLOCKINLCUDE### */



global $db, $ab_path;
require_once $ab_path."sys/lib.geoip.php";

$tpl_content->addvar("ip", $_SERVER["REMOTE_ADDR"]);
switch ($_REQUEST['do']) {
	case 'status':
		$file_lock = $ab_path."cache/geo_update.lock";
		$status = $db->fetch_atom("SELECT value FROM `geo_status` WHERE title='Status'");
		if ($status == "Import der Datenbank erfolgreich.") {
			die();	
		} else {
			if (empty($status)) $status = "Datenbank nicht gefüllt!";
			die($status);
		}
	case 'update':
		todo("GeoIP Datenbank importieren", "cron/geoip_update.php");
		GeoIP::UpdateGeoStatus("Status", "Import wird in kürze gestartet...");
		break;
	case 'resolve':
		$ar_loc = GeoIP::GetIPLoc($_REQUEST['ip']);
		$tpl_content->addvar("ip", $_REQUEST['ip']);
		$tpl_content->addvar("resolve_response", var_export($ar_loc, true));
		break;
}

$tpl_content->addvar("status", $db->fetch_atom("SELECT value FROM `geo_status` WHERE title='Status'"));
$tpl_content->addvar("last_update", $db->fetch_atom("SELECT value FROM `geo_status` WHERE title='Letzter Import'"));

?>