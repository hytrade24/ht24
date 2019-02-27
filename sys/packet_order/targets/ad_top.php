<?php

require_once $ab_path."sys/lib.ads.php";

class PacketTargetAdTop {

	/**
	 * Anzeigen aktivieren
	 *
	 * @param int     $id_packet_order
	 */
	public static function activate($db, $id_packet_order, $bitmask = 7) {
		$ar_ads = $db->fetch_nar("
			SELECT ID_AD_MASTER, AD_TABLE
			FROM `packet_order_usage` u
				LEFT JOIN `ad_master` a ON a.ID_AD_MASTER=u.FK
			WHERE u.ID_PACKET_ORDER=".(int)$id_packet_order);
		if (!empty($ar_ads)) {
            $db->querynow("UPDATE `ad_master` SET B_TOP=B_TOP|".(int)$bitmask." WHERE ID_AD_MASTER IN (".implode(",", array_keys($ar_ads)).")");
            $db->querynow("UPDATE `ad_master` SET B_TOP_LIST=".Rest_MarketplaceAds::getTopValueDatabaseUpdate("B_TOP")." WHERE ID_AD_MASTER IN (".implode(",", array_keys($ar_ads)).")");
		}
		return true;
	}

	/**
	 * Anzeigen deaktivieren
	 *
	 * @param int     $id_packet_order
	 */
	public function deactivate($db, $id_packet_order, $bitmask = 7) {
		$ar_ads = $db->fetch_nar("
			SELECT ID_AD_MASTER, AD_TABLE
			FROM `packet_order_usage` u
				LEFT JOIN `ad_master` a ON a.ID_AD_MASTER=u.FK
			WHERE u.ID_PACKET_ORDER=".(int)$id_packet_order);
		if (!empty($ar_ads)) {
            $db->querynow("UPDATE `ad_master` SET B_TOP=B_TOP-(B_TOP&".(int)$bitmask.") WHERE ID_AD_MASTER IN (".implode(",", array_keys($ar_ads)).")");
            $db->querynow("UPDATE `ad_master` SET B_TOP_LIST=".Rest_MarketplaceAds::getTopValueDatabaseUpdate("B_TOP")." WHERE ID_AD_MASTER IN (".implode(",", array_keys($ar_ads)).")");
		}
		return true;
	}
}

?>