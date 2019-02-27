<?php

require_once $ab_path."sys/packet_order_recurring.php";
require_once $ab_path."sys/packet_order/targets/ad_top.php";

class PacketOrderAdTopColorRecurring extends PacketOrderRecurring {

	/**
	* Feature aktivieren
	*
	* @see PacketOrderInterface::activate()
	*/
	public function activate() {
		parent::activate();
		$result = PacketTargetAdTop::activate($this->database, $this->id, 4);
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
		return PacketTargetAdTop::deactivate($this->database, $this->id, 4);
	}

	/**
	 * Inhalte in anderes Paket verschieben
	 * @see PacketOrderBase::moveContent()
	 */
	public function moveContent($id_packet_order, &$ar_failed = array()) {
        echo "move contet ad top recurring $id_packet_order <br>";

		$packets = PacketManagement::getInstance($this->database);
		$adOrderOld = $this->getRoot();
		$adOrderNew = $packets->order_get($id_packet_order);
		$ar_usage = $this->database->fetch_table("SELECT FK, ID_PACKET_ORDER FROM `packet_order_usage` WHERE ID_PACKET_ORDER=".$this->id);
		$result = true;
		foreach ($ar_usage as $index => $ar_pair) {
			if ($adOrderNew->isAvailable("ad_top_color", 1)) {
				$adOrderOld->itemRemContent("ad_top_color", $ar_pair["FK"]);
				$adOrderNew->itemAddContent("ad_top_color", $ar_pair["FK"]);
			} else {
				if (!is_array($ar_failed["ad_top_color"])) {
					$ar_failed["ad_top_color"] = array();
				}
				$ar_failed["ad_top_color"][] = $ar_pair["FK"];
				$result = false;
			}
		}
		return $result;
	}

}

?>