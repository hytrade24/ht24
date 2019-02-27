<?php

require_once $ab_path."sys/packet_order_once.php";
require_once $ab_path."sys/packet_order/targets/lead.php";

class PacketOrderLeadOnce extends PacketOrderOnce {

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

}

?>