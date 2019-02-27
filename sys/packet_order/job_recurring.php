<?php

require_once $ab_path."sys/packet_order_recurring.php";
require_once $ab_path."sys/packet_order/targets/job.php";

class PacketOrderJobRecurring extends PacketOrderRecurring {

	/**
	 * Feature aktivieren
	 *
	 * @see PacketOrderInterface::activate()
	 */
	public function activate() {
		parent::activate();
		return PacketTargetJob::activate($this->database, $this->id);
	}

	/**
	 * Feature deaktivieren
	 *
	 * @see PacketOrderInterface::deactivate()
	 */
	public function deactivate() {
		parent::deactivate();
		return PacketTargetJob::deactivate($this->database, $this->id);
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
			if ($adOrderNew->isAvailable("job", 1)) {
				$adOrderOld->itemRemContent("job", $ar_pair["FK"]);
				$adOrderNew->itemAddContent("job", $ar_pair["FK"]);
			} else {
				if (!is_array($ar_failed["job"])) {
					$ar_failed["job"] = array();
				}
				$ar_failed["job"][] = $ar_pair["FK"];
				$result = false;
			}
		}
		return true;
	}

}

?>