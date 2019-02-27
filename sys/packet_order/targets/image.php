<?php

require_once $ab_path."sys/lib.ads.php";

class PacketTargetImage {

	/**
	 * Bilder aktivieren
	 *
	 * @param int     $id_packet_order
	 */
	public static function activate($db, $id_packet_order) {
		// TODO: Activate image?
		return true;
	}

	/**
	 * Bilder deaktivieren
	 *
	 * @param int     $id_packet_order
	 */
	public function deactivate($db, $id_packet_order) {
		// TODO: Deactivate image?
		return true;
	}
}

?>