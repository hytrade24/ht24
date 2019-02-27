<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.ads.php";

/**
 * Ereignisse bezüglich der Marktplatz-Kategorien sollten künftig hier Zentral ausgelöst werden um Erweiterungen
 * der Software zu vereinfachen
 *
 * @author Jens
 */
class EventCategory {
	static function onDelete($id_kat) {
		global $ab_path, $db;
		$id_kat = (int)$id_kat;
		if ($id_kat > 0) {
			// Feldzuordnungen löschen
			$query = "DELETE FROM `kat2field` WHERE FK_KAT=".$id_kat;
			$db->querynow($query);
			// Anzeigen löschen
			$query = "SELECT ID_AD_MASTER, AD_TABLE FROM `ad_master` WHERE FK_KAT=".$id_kat;
			$ar_ads = $db->fetch_nar($query);
			if (!empty($ar_ads)) {
				require_once $ab_path."sys/lib.ads.php";
				foreach ($ar_ads as $ad_id => $ad_table) {
					if (($ad_id > 0) && !empty($ad_table)) {
                        Ad_Marketplace::deleteAd($ad_id, $ad_table);
					}
				}
			}
			// Imports korrigieren
			$query = "SELECT ID_IMPORT_FILTER, IDENT FROM `import_filter`";
			$ar_imports = $db->fetch_nar($query);
			if (!empty($ar_imports)) {
				foreach ($ar_imports as $import_id => $import_ident) {
					if (($import_id > 0) && !empty($import_ident)) {
						$query = "UPDATE `import_tmp_".strtolower($import_ident)."` SET FK_KAT=NULL WHERE FK_KAT=".$id_kat;
						$db->querynow($query);
					}
				}
			}
			// Anzeigen-Agenten löschen
			$query = "DELETE FROM `ad_agent` WHERE SEARCH_KAT=".$id_kat;
			$db->querynow($query);
			// Werbung aus dieser Kategorie löschen
			$query = "DELETE FROM `advertisement_kat` WHERE FK_KAT=".$id_kat;
			$db->querynow($query);
		}
	}
}

?>