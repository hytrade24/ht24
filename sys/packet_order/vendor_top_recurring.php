<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/packet_order_recurring.php";
require_once $ab_path."sys/packet_order/targets/vendor_top.php";

class PacketOrderVendorTopRecurring extends PacketOrderRecurring {

	/**
	* Feature aktivieren
	*
	* @see PacketOrderInterface::activate()
	*/
	public function activate() {
		parent::activate();
		$result = PacketTargetVendorTop::activate($this->database, $this->id);
		$this->cancel();
		return $result;
	}

	/**
	 * Feature deaktivieren
	 *
	 * @see PacketOrderInterface::deactivate()
	 */
	public function deactivate() {
		parent::deactivate();
		return PacketTargetVendorTop::deactivate($this->database, $this->id);
	}

	/**
	 * Inhalte in anderes Paket verschieben
	 * @see PacketOrderBase::moveContent()
	 */
	public function moveContent($id_packet_order, &$ar_failed = array()) {
		$packets = PacketManagement::getInstance($this->database);
		$adOrderOld = $this->getRoot();
		$adOrderNew = $packets->order_get($id_packet_order);
		$ar_usage = $this->database->fetch_table("SELECT FK, ID_PACKET_ORDER FROM `packet_order_usage` WHERE ID_PACKET_ORDER=".$this->id);
		$result = true;
		foreach ($ar_usage as $index => $ar_pair) {
			if ($adOrderNew->isAvailable("vendor_top", 1)) {
				$adOrderOld->itemRemContent("vendor_top", $ar_pair["FK"]);
				$adOrderNew->itemAddContent("vendor_top", $ar_pair["FK"]);
			} else {
				if (!is_array($ar_failed["vendor_top"])) {
					$ar_failed["vendor_top"] = array();
				}
				$ar_failed["vendor_top"][] = $ar_pair["FK"];
				$result = false;
			}
		}
		return true;
	}

}

?>