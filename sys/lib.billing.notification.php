<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once dirname(__FILE__) . '/lib.billing.invoice.item.php';
require_once dirname(__FILE__) . '/lib.billing.notification.email.php';
require_once dirname(__FILE__) . '/lib.billing.notification.advertisement.php';
require_once dirname(__FILE__) . '/lib.billing.notification.packet.php';

class BillingNotificationManagement {
    private static $db;
    private static $instance = null;

    const EVENT_INVOICE_CREATE = 1;
    const EVENT_INVOICE_PAY = 2;
    const EVENT_INVOICE_CANCEL = 3;
    const EVENT_INVOICE_DUE = 4;
    const EVENT_INVOICE_UNPAY = 5;
    const EVENT_INVOICE_OVERDUE = 6;
    const EVENT_INVOICE_DUNNING_LEVEL_1 = 7;
    const EVENT_INVOICE_DUNNING_LEVEL_2 = 8;
    const EVENT_INVOICE_DUNNING_LEVEL_3 = 9;
    const EVENT_INVOICE_TRANSACTION_CREATE = 10;
    const EVENT_INVOICE_TRANSACTION_APPLY_CREDIT = 11;
    
    const EVENT_INVOICE_CORRECTION = 13;
    const EVENT_INVOICE_ITEM_CANCEL = 14;
    const EVENT_BILLABLEITEM_CANCEL = 15;
    
    const EVENT_AUTOMATICBILLING_RUN_CREATE_INVOICE = 12;


    /**
     * Singleton
     *
     * @param ebiz_db $db
     * @return BillingNotificationManagement
     */
    public static function getInstance(ebiz_db $db) {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        self::setDb($db);

        return self::$instance;
    }

    public function notify($event, $invoiceId, $data = array()) {
        $apiHandler = Api_TraderApiHandler::getInstance( $this->getDb() );
        switch ($event) {
            case self::EVENT_INVOICE_CREATE:
                self::_eventInvoiceCreate($invoiceId, $data);
                $apiHandler->triggerEvent(Api_TraderApiEvents::INVOICE_CREATE, array("id" => $invoiceId, "data" => $data));
                break;
            case self::EVENT_INVOICE_PAY:
                self::_eventInvoicePay($invoiceId, $data);
                $apiHandler->triggerEvent(Api_TraderApiEvents::INVOICE_PAY, array("id" => $invoiceId, "data" => $data));
                break;
            case self::EVENT_INVOICE_UNPAY:
                self::_eventInvoiceUnpay($invoiceId, $data);
                $apiHandler->triggerEvent(Api_TraderApiEvents::INVOICE_UNPAY, array("id" => $invoiceId, "data" => $data));
                break;
            case self::EVENT_INVOICE_CANCEL:
                self::_eventInvoiceCancel($invoiceId, $data);
                $apiHandler->triggerEvent(Api_TraderApiEvents::INVOICE_CANCEL, array("id" => $invoiceId, "data" => $data));
                break;
            case self::EVENT_INVOICE_ITEM_CANCEL:
                self::_eventInvoiceItemCancel((int)$data["ID_BILLING_INVOICE_ITEM"], $data);
                $apiHandler->triggerEvent(Api_TraderApiEvents::INVOICE_ITEM_CANCEL, array("id" => $invoiceId, "data" => $data));
                break;
	        case self::EVENT_BILLABLEITEM_CANCEL:
		        self::_eventBillableItemCancel((int)$data["ID_BILLING_BILLABLEITEM"], $data);
		        $apiHandler->triggerEvent(
		        	Api_TraderApiEvents::EVENT_BILLABLEITEM_CANCEL,
				     array(
				     	"id" => $invoiceId,
				        "data" => $data
				     )
		        );
	        	break;
            case self::EVENT_INVOICE_OVERDUE:
                $apiHandler->triggerEvent(Api_TraderApiEvents::INVOICE_OVERDUE, array("id" => $invoiceId, "data" => $data));
                break;
            case self::EVENT_INVOICE_CORRECTION:
                self::_eventInvoiceCorrection($invoiceId, $data);
                $apiHandler->triggerEvent(Api_TraderApiEvents::INVOICE_CORRECTION, array("id" => $invoiceId, "data" => $data));
                break;
            case self::EVENT_INVOICE_DUNNING_LEVEL_1:
                self::_eventInvoiceDunning($invoiceId, 1, $data);
                $apiHandler->triggerEvent(Api_TraderApiEvents::INVOICE_DUNNING_LEVEL_1, array("id" => $invoiceId, "data" => $data));
                break;
            case self::EVENT_INVOICE_DUNNING_LEVEL_2:
                self::_eventInvoiceDunning($invoiceId, 2, $data);
                $apiHandler->triggerEvent(Api_TraderApiEvents::INVOICE_DUNNING_LEVEL_2, array("id" => $invoiceId, "data" => $data));
                break;
            case self::EVENT_INVOICE_DUNNING_LEVEL_3:
                self::_eventInvoiceDunning($invoiceId, 3, $data);
                $apiHandler->triggerEvent(Api_TraderApiEvents::INVOICE_DUNNING_LEVEL_3, array("id" => $invoiceId, "data" => $data));
                break;

            case self::EVENT_INVOICE_TRANSACTION_CREATE:
                self::_eventInvoiceTransactionCreate($invoiceId, $data);
                $apiHandler->triggerEvent(Api_TraderApiEvents::INVOICE_TRANSACTION_CREATE, array("id" => $invoiceId, "data" => $data));
                break;
            case self::EVENT_INVOICE_TRANSACTION_APPLY_CREDIT:
                $apiHandler->triggerEvent(Api_TraderApiEvents::INVOICE_TRANSACTION_APPLY_CREDIT, array("id" => $invoiceId, "data" => $data));
                break;
			      case self::EVENT_AUTOMATICBILLING_RUN_CREATE_INVOICE:
                self::_eventAutomaticBillingRunCreateInvoice($invoiceId, $data);
                $apiHandler->triggerEvent(Api_TraderApiEvents::INVOICE_AUTOMATICBILLING_RUN_CREATE_INVOICE, array("id" => $invoiceId, "data" => $data));
				break;
        }

    }

    protected function _eventInvoiceCreate($invoiceId, $data) {
        //send E-Mail to customer
        $billingNotificationEmailManagement = BillingNotificationEmailManagement::getInstance($this->getDb());
        $billingNotificationEmailManagement->sendEmailToCustomerReasonNewInvoice($invoiceId);

        $billingNotificationPacketManagement = BillingNotificationPacketManagement::getInstance($this->getDb());
        $billingNotificationPacketManagement->_eventInvoiceCreate($invoiceId, $data);
    }

    protected function _eventInvoicePay($invoiceId, $data) {
        // advertisement
        // TODO: über Packets lösen
        $billingNotificationAdvertisementManagement = BillingNotificationAdvertisementManagement::getInstance($this->getDb());
        $billingNotificationAdvertisementManagement->_eventInvoicePay($invoiceId, $data);

        $billingNotificationPacketManagement = BillingNotificationPacketManagement::getInstance($this->getDb());
        $billingNotificationPacketManagement->_eventInvoicePay($invoiceId, $data);
        // sales
        $fk_user_sales = $this->getDb()->fetch_atom("SELECT FK_USER_SALES FROM `billing_invoice` WHERE ID_BILLING_INVOICE=".(int)$invoiceId);
        if ($fk_user_sales > 0) {
            require_once $GLOBALS['ab_path']."sys/lib.sales.php";
            SalesManagement::getInstance()->onInvoicePay($invoiceId, $fk_user_sales);
        }
    }

    protected function _eventInvoiceUnpay($invoiceId, $data) {
        // advertisement
        // TODO: über Packets lösen
        $billingNotificationAdvertisementManagement = BillingNotificationAdvertisementManagement::getInstance($this->getDb());
        $billingNotificationAdvertisementManagement->_eventInvoiceUnpay($invoiceId, $data);
        
        $billingNotificationPacketManagement = BillingNotificationPacketManagement::getInstance($this->getDb());
        $billingNotificationPacketManagement->_eventInvoiceUnpay($invoiceId, $data);
        // sales
        $fk_user_sales = $this->getDb()->fetch_atom("SELECT FK_USER_SALES FROM `billing_invoice` WHERE ID_BILLING_INVOICE=".(int)$invoiceId);
        if ($fk_user_sales > 0) {
            require_once $GLOBALS['ab_path']."sys/lib.sales.php";
            SalesManagement::getInstance()->onInvoiceUnpay($invoiceId, $fk_user_sales);
        }
    }

    protected function _eventInvoiceCancel($invoiceId, $data) {
        // advertisement
        // TODO: über Packets lösen
      
        $billingNotificationAdvertisementManagement = BillingNotificationAdvertisementManagement::getInstance($this->getDb());
        $billingNotificationPacketManagement = BillingNotificationPacketManagement::getInstance($this->getDb());
        
        if (!array_key_exists("KEEP_PERFORMANCES", $data) || is_array($data["KEEP_PERFORMANCES"])) {
          // Read invoice items
          $billingInvoiceItemManagement = BillingInvoiceItemManagement::getInstance($this->getDb());
          $arBillingInvoiceItems = $billingInvoiceItemManagement->fetchAllByParam(array('FK_BILLING_INVOICE' => $invoiceId));
          // For each ...
          foreach ($arBillingInvoiceItems as $arBillingInvoiceItem) {
            // -> Call InvoiceItemCancel for idividual item
            $idItem = $arBillingInvoiceItem["ID_BILLING_INVOICE_ITEM"];
            $dataItem = array( 
              "KEEP_PERFORMANCES" => (array_key_exists($idItem, $data["KEEP_PERFORMANCES"]) ? $data["KEEP_PERFORMANCES"][$idItem] : 0) 
            );
            $billingNotificationAdvertisementManagement->_eventInvoiceItemCancel($idItem, $dataItem);
            $billingNotificationPacketManagement->_eventInvoiceItemCancel($idItem, $dataItem);
          }
          unset($data["KEEP_PERFORMANCES"]);
        }
        
        $billingNotificationAdvertisementManagement->_eventInvoiceCancel($invoiceId, $data);
        $billingNotificationPacketManagement->_eventInvoiceCancel($invoiceId, $data);
        
        //send E-Mail to customer
        $billingNotificationEmailManagement = BillingNotificationEmailManagement::getInstance($this->getDb());
        $billingNotificationEmailManagement->sendEmailToCustomerReasonInvoiceCancel($invoiceId);
    }

    protected function _eventInvoiceItemCancel($invoiceItemId, $data) {
        // advertisement
        // TODO: über Packets lösen
        $billingNotificationAdvertisementManagement = BillingNotificationAdvertisementManagement::getInstance($this->getDb());
        $billingNotificationAdvertisementManagement->_eventInvoiceItemCancel($invoiceItemId, $data);

        $billingNotificationPacketManagement = BillingNotificationPacketManagement::getInstance($this->getDb());
        $billingNotificationPacketManagement->_eventInvoiceItemCancel($invoiceItemId, $data);
    }

    protected function _eventBillableItemCancel($billableItemId, $data) {
	    // advertisement
	    // TODO: über Packets lösen
	    $billingNotificationAdvertisementManagement = BillingNotificationAdvertisementManagement::getInstance($this->getDb());
	    $billingNotificationAdvertisementManagement->_eventBillableItemCancel($billableItemId, $data);

	    $billingNotificationPacketManagement = BillingNotificationPacketManagement::getInstance($this->getDb());
	    $billingNotificationPacketManagement->_eventBillableItemCancel($billableItemId, $data);
    }

    protected function _eventInvoiceDunning($invoiceId, $level, $data) {
        //send E-Mail to customer
        $billingNotificationEmailManagement = BillingNotificationEmailManagement::getInstance($this->getDb());
        $billingNotificationEmailManagement->sendEmailToCustomerReasonDunning($invoiceId, $level);

        $billingNotificationPacketManagement = BillingNotificationPacketManagement::getInstance($this->getDb());
        $billingNotificationPacketManagement->_eventInvoiceDunning($invoiceId, $level, $data);
    }

    protected function _eventInvoiceCorrection($invoiceId, $data) {
        //send E-Mail to customer
        $billingNotificationEmailManagement = BillingNotificationEmailManagement::getInstance($this->getDb());
        $billingNotificationEmailManagement->sendEmailToCustomerReasonInvoiceCorrection($invoiceId);
    }

    protected function _eventInvoiceTransactionCreate($invoiceId, $data) {
        //send E-Mail to customer
        $billingNotificationEmailManagement = BillingNotificationEmailManagement::getInstance($this->getDb());
        $billingNotificationEmailManagement->sendEmailToCustomerReasonNewTransaction($invoiceId, $data);
    }

    protected function _eventAutomaticBillingRunCreateInvoice($invoiceId, $data) {
        $billingNotificationPacketManagement = BillingNotificationPacketManagement::getInstance($this->getDb());
        $billingNotificationAdvertisementManagement = BillingNotificationAdvertisementManagement::getInstance($this->getDb());

        $billingNotificationPacketManagement->_eventAutomaticBillingRunCreateInvoice($invoiceId, $data['touchedBillableItemIds']);
        $billingNotificationAdvertisementManagement->_eventAutomaticBillingRunCreateInvoice($invoiceId, $data['touchedBillableItemIds'], $data['invoice']);
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