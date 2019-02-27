<?php
/* ###VERSIONSBLOCKINLCUDE### */



class GeoIP {
	
	/**
	 * 
	 * Convert a dotted ip into a numeric one.
	 * @param string $ip_dot	IP-Adress in format x.x.x.x
	 */
	static public function GetIPNum($ip_dot) {
		$ar_segments = explode(".", $ip_dot);
		if ($ar_segments < 4) {
			return false;
		} else {
			$ip_num = 16777216 * (int)$ar_segments[0];
			$ip_num += 65536 * (int)$ar_segments[1];
			$ip_num += 256 * (int)$ar_segments[2];
			$ip_num += (int)$ar_segments[3];
			return $ip_num;
		}
	}
	
	static public function GetIPLoc($ip_dot = false) {
		global $db;
		if ($ip_dot == false) {
			$ip_dot = $_SERVER["REMOTE_ADDR"];
		}
		$ip_num = GeoIP::GetIPNum($ip_dot);
		if ($ip_num == false) {
			return array();
		} else {
			return $db->fetch1("SELECT b.*, l.* FROM `geo_blocks` b ".
				"LEFT JOIN `geo_location` l ON l.locId=b.locId ".
				"WHERE ".$ip_num." BETWEEN b.startIpNum AND b.endIpNum");
		}
	}
	
	/**
	 * 
	 * Update a status field of the GeoDB
	 * @param string $title		Name of the status field
	 * @param string $status	Value of that field
	 */
	static public function UpdateGeoStatus($title, $status) {
		$query = "INSERT INTO `geo_status` (`title`, `value`) VALUES ".
			"('".mysql_escape_string($title)."', '".mysql_escape_string($status)."') ".
			"ON DUPLICATE KEY UPDATE `value`='".mysql_escape_string($status)."'";
		mysql_query($query);
	}
	
	/**
	 * 
	 * Imports the GeoIP database from CSV
	 * @param boolean $lite		Whether this is the lite or full database
	 */
	static public function UpdateGeoDB($lite = true) {
		global $db, $ab_path;
		$file_lock = $ab_path."cache/geo_update.lock";
		if ($lite) {
			$file_blocks = $ab_path."geoip/GeoLiteCity-Blocks.csv";
			$file_location = $ab_path."geoip/GeoLiteCity-Location.csv";	
		} else {
			$file_blocks = $ab_path."geoip/GeoCity-Blocks.csv";
			$file_location = $ab_path."geoip/GeoCity-Location.csv";
		}
		if (!file_exists($file_lock)) {
			touch($file_lock);
			if (file_exists($file_blocks)) {
				// Import IP-Blocks
				GeoIP::UpdateGeoStatus("Status", "Datenbank wird importiert. (IP-Ranges)");
				$size_blocks = filesize($file_blocks);
				$fp_blocks = fopen($file_blocks, "r");
				if ($fp_blocks !== false) {
					$query = $db->querynow("TRUNCATE `geo_blocks`");
					$next_status_update = 10000;
					// Get first (copyright) and second row (column labels)
					$ar_row = fgetcsv($fp_blocks);
					$ar_row = fgetcsv($fp_blocks);
					while ($ar_row = fgetcsv($fp_blocks)) {
						$query = "INSERT INTO `geo_blocks` (`startIpNum`, `endIpNum`, `locId`) VALUES ".
							"('".mysql_escape_string($ar_row[0])."', '".mysql_escape_string($ar_row[1]).
								"', '".mysql_escape_string($ar_row[2])."')";
						mysql_query($query);
						if ($next_status_update-- <= 0) {
							$next_status_update = 10000;
							$percent = round(ftell($fp_blocks) * 100 / $size_blocks, 1);
							GeoIP::UpdateGeoStatus("Status", "Datenbank wird importiert. (IP-Ranges: ".$percent."%)");
							sleep(1);
						}
					}
					fclose($fp_blocks);
				}
				GeoIP::UpdateGeoStatus("Letzter Import", date("d.m.Y H:i:s"));
				GeoIP::UpdateGeoStatus("Status", "Import der Datenbank erfolgreich.");
			}
			if (file_exists($file_location)) {
				// Import Locations
				GeoIP::UpdateGeoStatus("Status", "Datenbank wird importiert. (Länder/Orte)");
				$size_location = filesize($file_location);
				$fp_location = fopen($file_location, "r");
				if ($fp_location !== false) {
					$query = $db->querynow("TRUNCATE `geo_location`");
					$next_status_update = 5000;
					// Get first (copyright) and second row (column labels)
					$ar_row = fgetcsv($fp_location);
					$ar_row = fgetcsv($fp_location);
					while ($ar_row = fgetcsv($fp_location)) {
						$query = "INSERT INTO `geo_location` (`locId`, `country`, `region`, `city`, `postalCode`, `latitude`, `longitude`, `metroCode`, `areaCode`) VALUES ".
							"('".mysql_escape_string($ar_row[0])."', '".mysql_escape_string($ar_row[1])."', '".mysql_escape_string($ar_row[2]).
								"', '".mysql_escape_string($ar_row[3])."', '".mysql_escape_string($ar_row[4])."', '".mysql_escape_string($ar_row[5]).
								"', '".mysql_escape_string($ar_row[6])."', '".mysql_escape_string($ar_row[7])."', '".mysql_escape_string($ar_row[8])."')";
						mysql_query($query);
						if ($next_status_update-- <= 0) {
							$next_status_update = 5000;
							$percent = round(ftell($fp_location) * 100 / $size_location, 1);
							GeoIP::UpdateGeoStatus("Status", "Datenbank wird importiert. (Länder/Orte: ".$percent."%)");
							sleep(1);
						}
					}
					fclose($fp_location);
				}
				GeoIP::UpdateGeoStatus("Letzter Import", date("d.m.Y H:i:s"));
				GeoIP::UpdateGeoStatus("Status", "Import der Datenbank erfolgreich.");
			}
			unlink($file_lock);
		}
	}
}

?>