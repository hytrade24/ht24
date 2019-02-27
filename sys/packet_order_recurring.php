<?php

require_once $ab_path."sys/packet_order_base.php";

abstract class PacketOrderRecurring extends PacketOrderBase {

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

	private function updateUsage() {
		$count_used = (int)$this->database->fetch_atom("SELECT count(*) FROM `packet_order_usage` WHERE ID_PACKET_ORDER=".$this->id);
		$ret = $this->database->querynow("UPDATE `packet_order` SET COUNT_USED=".$count_used." WHERE ID_PACKET_ORDER=".$this->id);
		if (!$ret['rsrc']) {
			// Fehler!
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Kündigt das Abo.
	 *
	 * @return Wahr wenn das abo gekündigt wurde
	 */
	public function cancel() {
		$ret = $this->database->querynow("
		  			UPDATE
		 	  			`packet_order`
		  	  		SET
		  	  			STAMP_NEXT=NULL,
		  	  			STAMP_CANCEL_UNTIL=NOW()
		  	  		WHERE
		  	  			ID_PACKET_ORDER=".$this->id);
		if (!$ret['rsrc']) {
			// Fehler!
			return false;
		} else {
			return true;
		}
	}

    /**
     * Kündigt das Abo.
     *
     * @return Wahr wenn das abo gekündigt wurde
     */
    public function cancelNow($isUpgrade = false) {
        $ret = $this->database->querynow("
                    UPDATE
                        `packet_order`
                    SET
                        STAMP_NEXT=NULL,
                        STAMP_CANCEL_UNTIL=NOW(),
                        STAMP_END=NOW()
                    WHERE
                        ID_PACKET_ORDER=".$this->id);

        if (!$ret['rsrc']) {
            // Fehler!
            return false;
        } else {
        	$this->deactivate();
            return true;
        }
    }

	/**
	 * Gibt true zurück wenn der aktuelle Vertrag noch vor Verlängerung gekündigt werden kann.
	 *
	 * @return Wahr wenn der aktuelle Vertrag noch vor Verlängerung gekündigt werden kann.
	 */
	public function isCancelable() {
		$date_cancel = $this->getPaymentDateCancel();
		if ($date_cancel != null) {
			$time_cancel = strtotime($date_cancel);
			return ($time_cancel > time());
		}
		return false;
	}

	/**
	 * (non-PHPdoc)
	 * @see PacketOrderBase::isRecurring()
	 */
	public function isRecurring() {
		return true;
	}

}

?>