<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.ads.php";

class PacketTargetVendorTop {

	/**
	 * Anzeigen aktivieren
	 *
	 * @param int     $id_packet_order
	 */
	public static function activate($db, $id_packet_order) {
		$ar_users = $db->fetch_nar("
			SELECT v.ID_USER, v.TOP_USER
			FROM `packet_order_usage` u
				LEFT JOIN `user` v ON v.ID_USER=u.FK
			WHERE u.ID_PACKET_ORDER=".(int)$id_packet_order);
		if (!empty($ar_users)) {
			foreach ($ar_users as $id_user => $isTop) {
				$db->querynow("UPDATE `user` SET TOP_USER=1 WHERE ID_USER=".$id_user);
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
		$ar_users = $db->fetch_nar("
			SELECT v.ID_USER, v.TOP_USER
			FROM `packet_order_usage` u
				LEFT JOIN `user` v ON v.ID_USER=u.FK
			WHERE u.ID_PACKET_ORDER=".(int)$id_packet_order);
		if (!empty($ar_users)) {
			foreach ($ar_users as $id_user => $isTop) {
				$db->querynow("UPDATE `user` SET TOP_USER=0 WHERE ID_USER=".$id_user);
			}
		}
		return true;
	}
}

?>