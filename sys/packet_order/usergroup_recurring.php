<?php

require_once $ab_path."sys/packet_order_recurring.php";
require_once $ab_path."sys/packet_order/targets/usergroup.php";

class PacketOrderUserGroupRecurring extends PacketOrderRecurring {

	/**
	* Feature aktivieren
	*
	* @see PacketOrderInterface::activate()
	*/
	public function activate() {
		parent::activate();
		return PacketTargetUsergroup::activate($this->database, $this->id, $this->getUserId());
	}

	/**
	 * Feature deaktivieren
	 *
	 * @see PacketOrderInterface::deactivate()
	 */
	public function deactivate() {
		parent::deactivate();
		return PacketTargetUsergroup::deactivate($this->database, $this->id, $this->getUserId());
	}

}

?>