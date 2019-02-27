<?php

require_once $ab_path."sys/lib.ads.php";

class PacketTargetAd {

	/**
	 * Anzeigen aktivieren
	 *
	 * @param int     $id_packet_order
	 */
	public static function activate($db, $id_packet_order) {
		$ar_ads = $db->fetch_nar("
			SELECT ID_AD_MASTER, AD_TABLE
			FROM `packet_order_usage` u
				LEFT JOIN `ad_master` a ON a.ID_AD_MASTER=u.FK
			WHERE u.ID_PACKET_ORDER=".(int)$id_packet_order);
		if (!empty($ar_ads)) {
			foreach ($ar_ads as $id_ad => $ad_table) {
				AdManagment::Enable($id_ad, $ad_table);
			}
		}
		return true;
	}

	/**
	 * Anzeigen deaktivieren
	 *
	 * @param int     $id_packet_order
	 */
	public function deactivate($db, $id_packet_order) {
		$ar_ads = $db->fetch_nar("
			SELECT ID_AD_MASTER, AD_TABLE
			FROM `packet_order_usage` u
				LEFT JOIN `ad_master` a ON a.ID_AD_MASTER=u.FK
			WHERE u.ID_PACKET_ORDER=".(int)$id_packet_order);
		if (!empty($ar_ads)) {
			foreach ($ar_ads as $id_ad => $ad_table) {
				AdManagment::Disable($id_ad, $ad_table);
			}
		}
		return true;
	}
}

?>