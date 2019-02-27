<?php

require_once $ab_path."sys/packet_order_base.php";

abstract class PacketOrderOnce extends PacketOrderBase {

	/**
	 * Feature aktivieren
	 *
	 * @see PacketOrderInterface::activate()
	 */
	public function activate() {
		return parent::activate();
	}

	/**
	 * Feature deaktivieren
	 *
	 * @see PacketOrderInterface::deactivate()
	 */
	public function deactivate() {
		return parent::deactivate();
	}

	public function isRecurring() {
		return false;
	}

	public function cancel() { }

}

?>