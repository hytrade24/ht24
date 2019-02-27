<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once dirname(__FILE__) . '/lib.billing.invoice.php';

class BillingNotificationPacketManagement {
    private static $db;
    private static $instance = null;

    /**
     * Singleton
     *
     * @param ebiz_db $db
     * @return BillingNotificationPacketManagement
     */
    public static function getInstance(ebiz_db $db) {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        self::setDb($db);

        return self::$instance;
    }


    public function _eventInvoiceCreate($invoiceId, $data) {

    }

    public function _eventInvoicePay($invoiceId, $data) {
        
        global $ab_path;
        require_once $ab_path . "sys/packet_management.php";
        $packets = PacketManagement::getInstance(self::$db);
        $packets->invoiceActivate($invoiceId);
    }

    public function _eventInvoiceUnpay($invoiceId, $data) {
        if (!array_key_exists("KEEP_PERFORMANCES", $data) || ($data["KEEP_PERFORMANCES"] == 0)) {
            global $ab_path;
            require_once $ab_path . "sys/packet_management.php";
            $packets = PacketManagement::getInstance(self::$db);
            $packets->invoiceDeactivate($invoiceId);
        }
    }

    public function _eventInvoiceCancel($invoiceId, $data) {
        if (!array_key_exists("KEEP_PERFORMANCES", $data) || ($data["KEEP_PERFORMANCES"] == 0)) {
            global $ab_path;
            require_once $ab_path . "sys/packet_management.php";
            $packets = PacketManagement::getInstance(self::$db);
            $packets->invoiceDeactivate($invoiceId);
        }
    }

    public function _eventInvoiceItemCancel($invoiceItemId, $data) {
        $db = $this->getDb();
        $invoiceItemManagement = BillingInvoiceItemManagement::getInstance($db);
        $arInvoiceItem = $invoiceItemManagement->fetchById($invoiceItemId);
        if (($arInvoiceItem["REF_TYPE"] == BillingInvoiceItemManagement::REF_TYPE_PACKET) && ($arInvoiceItem["REF_FK"] !== null)) {
            if (!array_key_exists("KEEP_PERFORMANCES", $data) || ($data["KEEP_PERFORMANCES"] == 0)) {
                global $ab_path;
                require_once $ab_path . "sys/packet_management.php";
                $packets = PacketManagement::getInstance(self::$db);
                $packets->invoiceItemDeactivate($arInvoiceItem["REF_FK"]);
            }
        }
    }

    public function _eventBillableItemCancel($billableItemId, $data) {
	    $db = $this->getDb();
	    $billableItemManagement = BillingBillableItemManagement::getInstance($db);
	    $arBillableItem = $billableItemManagement->fetchById($billableItemId);
	    if (($arBillableItem["REF_TYPE"] == BillingBillableItemManagement::REF_TYPE_PACKET) && ($arBillableItem["REF_FK"] !== null)) {
		    if (!array_key_exists("KEEP_PERFORMANCES", $data) || ($data["KEEP_PERFORMANCES"] == 0)) {
			    global $ab_path;
			    require_once $ab_path . "sys/packet_management.php";
			    $packets = PacketManagement::getInstance(self::$db);
			    $packets->billableItemDeactivate($arBillableItem["REF_FK"]);
		    }
	    }
    }

    public function _eventInvoiceDunning($invoiceId, $level, $data) {
        if ($level >= 3) {
            global $ab_path;
            require_once $ab_path . "sys/packet_management.php";
            $packets = PacketManagement::getInstance(self::$db);
            $packets->invoiceDeactivate($invoiceId);
        }
    }

    public function _eventAutomaticBillingRunCreateInvoice($invoiceId, $touchedBillableItemIds) {
        $db = $this->getDb();
        if (count($touchedBillableItemIds) > 0) {
            $packetOrderIds = $db->fetch_nar("SELECT FK_BILLING_BILLABLEITEM, FK_PACKET_ORDER FROM packet_order_billableitem WHERE FK_BILLING_BILLABLEITEM IN (" . implode(',', $touchedBillableItemIds) . ")");
            $insertValues = array();
            foreach ($packetOrderIds as $key => $packetOrderId) {
                $insertValues[] = "(" . $packetOrderId . ", " . $invoiceId . ")";
            }
            $query = "INSERT INTO packet_order_invoice (FK_PACKET_ORDER, FK_INVOICE) VALUES " . implode(',', $insertValues) . " ";
            $db->querynow($query);
            $db->querynow("DELETE FROM packet_order_billableitem WHERE FK_BILLING_BILLABLEITEM IN (" . implode(',', $touchedBillableItemIds) . ")");
        }
    }


    /**
     * @return ebiz_db $db
     */
    public function getDb() {
        return self::$db;
    }

    /**
     * @param ebiz_db $db
     */
    public function setDb(ebiz_db $db) {
        self::$db = $db;
    }

    private function __construct() {
    }

    private function __clone() {
    }
}
