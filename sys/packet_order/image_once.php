<?php

require_once $ab_path."sys/packet_order_once.php";
require_once $ab_path."sys/packet_order/targets/image.php";

class PacketOrderImageOnce extends PacketOrderOnce {

	/**
	 * Feature aktivieren
	 *
	 * @see PacketOrderInterface::activate()
	 */
	public function activate() {
		parent::activate();
		return PacketTargetImage::activate($this->database, $this->id);
	}

	/**
	 * Feature deaktivieren
	 *
	 * @see PacketOrderInterface::deactivate()
	 */
	public function deactivate() {
		parent::deactivate();
		return PacketTargetImage::deactivate($this->database, $this->id);
	}

}

?>