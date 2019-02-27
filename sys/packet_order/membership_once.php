<?php

require_once $ab_path."sys/packet_order/collection_once.php";

class PacketOrderMembershipOnce extends PacketOrderCollectionOnce {

	/**
	* Initialisieren eines neuen Objekts anhand der ID/des Primärschlüssels
	*
	* @param int $id_packet_order		ID des Datensatzes in der 'packet_order'-Tabelle
	*/
	function __construct(ebiz_db $db, $id_packet_order) {
		parent::__construct($db, $id_packet_order);
	}

	/**
	 * Mitgliedschaft aktivieren
	 *
	 * @see PacketOrderInterface::activate()
	 */
	public function activate() {
		global $uid;
		$packets = PacketManagement::getInstance($this->database);
		$membership_cur = $packets->getActiveMembershipByUserId($this->getUserId());
		if (($membership_cur != null) && ($membership_cur->getPacketId() != $this->getPacketId())) {
			PacketTargetMembership::prepareUpgrade($this->database, $membership_cur, $this);
		}
		parent::activate();
		$eventParameters = new Api_Entities_EventParamContainer(array(
			"user"	=> $this->getUserId(),
			"from"	=> $membership_cur,
			"to"	=> $this
		));
		Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MEMBERSHIP_CHANGED, $eventParameters);
		return true;
	}

    /**
     * @see PacketOrderBase::cancelNow()
     */
    public function cancelNow($isUpgrade = false) {
        // Auf Standard-Mitgliedschaft wechseln?
        if (!$isUpgrade) {
            $arUsergroup = $this->database->fetch1("SELECT g.*, u.NAME as USERNAME FROM `usergroup` g JOIN `user` u ON u.FK_USERGROUP=g.ID_USERGROUP WHERE u.ID_USER=".$this->getUserId());
            if ($arUsergroup['FK_PACKET_RUNTIME_DEFAULT'] > 0) {
                $packets = PacketManagement::getInstance($this->database);
                $packetIds = $packets->order($arUsergroup['FK_PACKET_RUNTIME_DEFAULT'], $this->getUserId(), 1, NULL);
                $membershipNew = $packets->order_get(array_pop($packetIds));
                PacketTargetMembership::moveContent($this->database, $this, $membershipNew->getOrderId());
                eventlog('info', 'Benutzer "'.$arUsergroup['USERNAME'].'" erhält Standard-Mitgliedschaft.');
            }
        } else {
			$eventParameters = new Api_Entities_EventParamContainer(array(
				"user" 	=> $this->getUserId(),
				"from" 	=> $this,
				"to" 	=> null
			));
			Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MEMBERSHIP_CHANGED, $eventParameters);
		}
        parent::cancelNow($isUpgrade);
    }

}

?>