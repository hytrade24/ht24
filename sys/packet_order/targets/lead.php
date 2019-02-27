<?php

class PacketTargetLead {

	/**
	 * Anzeigen aktivieren
	 *
	 * @param int     $id_packet_order
	 */
	public static function activate($db, $id_packet_order) {
		// TODO: Enable leads
		return true;
	}

	/**
	 * Anzeigen deaktivieren
	 *
	 * @param int     $id_packet_order
	 */
	public function deactivate($db, $id_packet_order) {
		// TODO: Disable leads
		return true;
	}
}

?>