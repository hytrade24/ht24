<?php

class PacketTargetNews {

	/**
	 * Anzeigen aktivieren
	 *
	 * @param int     $id_packet_order
	 */
	public static function activate($db, $id_packet_order) {
		$ar_news = $db->fetch_nar("
			SELECT ID_NEWS, OK
			FROM `packet_order_usage` u
				LEFT JOIN `news` a ON a.ID_NEWS=u.FK
			WHERE u.ID_PACKET_ORDER=".(int)$id_packet_order);
		if (!empty($ar_news)) {
			foreach ($ar_news as $id_news => $freigabe) {
				$db->querynow("UPDATE `news` SET OK=(OK|2) WHERE ID_NEWS=".(int)$id_news);
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
		$ar_news = $db->fetch_nar("
			SELECT ID_NEWS, OK
			FROM `packet_order_usage` u
				LEFT JOIN `news` a ON a.ID_NEWS=u.FK
			WHERE u.ID_PACKET_ORDER=".(int)$id_packet_order);
		if (!empty($ar_news)) {
			foreach ($ar_news as $id_news => $freigabe) {
				$db->querynow("UPDATE `news` SET OK=(OK&1) WHERE ID_NEWS=".(int)$id_news);
			}
		}
		return true;
	}
}

?>