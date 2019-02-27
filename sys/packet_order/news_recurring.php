<?php

require_once $ab_path."sys/packet_order_recurring.php";
require_once $ab_path."sys/packet_order/targets/news.php";

class PacketOrderNewsRecurring extends PacketOrderRecurring {

	/**
	 * Feature aktivieren
	 *
	 * @see PacketOrderInterface::activate()
	 */
	public function activate() {
		parent::activate();
		return PacketTargetNews::activate($this->database, $this->id);
	}

	/**
	 * Feature deaktivieren
	 *
	 * @see PacketOrderInterface::deactivate()
	 */
	public function deactivate() {
		parent::deactivate();
		return PacketTargetNews::deactivate($this->database, $this->id);
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
			if ($adOrderNew->isAvailable("news", 1)) {
				$adOrderOld->itemRemContent("news", $ar_pair["FK"]);
				$adOrderNew->itemAddContent("news", $ar_pair["FK"]);
			} else {
				if (!is_array($ar_failed["news"])) {
					$ar_failed["news"] = array();
				}
				$ar_failed["news"][] = $ar_pair["FK"];
				$result = false;
			}
		}
		return true;
	}

}

?>