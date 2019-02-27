<?php

require_once $ab_path."sys/packet_order_once.php";
require_once $ab_path."sys/packet_order/targets/job.php";

class PacketOrderJobOnce extends PacketOrderOnce {

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

}

?>