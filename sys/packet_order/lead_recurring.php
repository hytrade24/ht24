<?php

require_once $ab_path."sys/packet_order_recurring.php";
require_once $ab_path."sys/packet_order/targets/lead.php";

class PacketOrderLeadRecurring extends PacketOrderRecurring {

	/**
	 * Feature aktivieren
	 *
	 * @see PacketOrderInterface::activate()
	 */
	public function activate() {
		parent::activate();
		return PacketTargetLead::activate($this->database, $this->id);
	}

	/**
	 * Feature deaktivieren
	 *
	 * @see PacketOrderInterface::deactivate()
	 */
	public function deactivate() {
		parent::deactivate();
		return PacketTargetLead::deactivate($this->database, $this->id);
	}
	
	/**
	 * Inhalte in anderes Paket verschieben
	 * @see PacketOrderBase::moveContent()
	 */
	public function moveContent($id_packet_order, &$ar_failed = array()) {
		$adOrderOld = $this->getRoot();
		$adOrderOld->itemRemContent("lead");
		return true;
	}

}

?>