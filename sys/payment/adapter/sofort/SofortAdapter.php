<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once(dirname(__FILE__).'/lib/payment/sofortLibSofortueberweisung.inc.php');
require_once(dirname(__FILE__).'/lib/core/sofortLibTransactionData.inc.php');

class Payment_Adapter_Sofort_SofortAdapter extends Payment_Adapter_AbstractPaymentAdapter {
    public $adapterName = 'sofort';

    protected $api = NULL;

    public function __construct($param) {
        parent::__construct($param);

        if (!isset($this->configuration['ApiKey']) || $this->configuration['ApiKey'] == '') {
            throw new Exception('Sofort Adapter needs ApiKey');
        }
        if (!isset($configuration['DefaultCurrency']) || $this->configuration['DefaultCurrency'] == '') {
            $this->configuration['DefaultCurrency'] = "EUR";
        }

        $this->api = new Sofortueberweisung($this->configuration['ApiKey']);
    }

    public function prepareOrder() {
    	$result = $this->doPayment();
    	if ($result !== false) {
    		die(forward($result));
    	}
    	return "";
    }

    public function doPayment() {
        // Get seller configuration
        $configurationSeller = $this->getSellerConfiguration($this->adapterName);
        if ($configurationSeller === null) {
            // TODO: Zahlung gegenüber dem Händler ermöglichen
            // $this->api = new Sofortueberweisung($configurationSeller['ApiKey']);
        }        
		$totalPrice = $this->getTotalInvoicePrice();
        $paymentSubject = $this->paymentObject['DATA']['INVOICE']['ID_BILLING_INVOICE']."-".$this->paymentObject['DATA']['INVOICE']['FK_USER'];
        // Prepare request
        $this->api->setAmount( round($totalPrice, 2) );
        if(isset($this->paymentObject['CURRENCY'])) {
            $this->api->setCurrencyCode($this->paymentObject['CURRENCY']);
        } else {
            $this->api->setCurrencyCode($this->configuration['DefaultCurrency']);            
        }
        #$this->api->setSenderSepaAccount('88888888', '12345678', 'Max Mustermann');
        #$this->api->setSenderCountryCode('DE');
        $this->api->setReason($this->paymentObject['DESCRIPTION'], $paymentSubject);
        $this->api->setSuccessUrl($this->paymentObject['RETURNURL'], true);
        $this->api->setAbortUrl($this->paymentObject['CANCELURL']);
        $this->api->setNotificationUrl( $GLOBALS["tpl_main"]->tpl_uri_action_full("system-payment,sofort"), "pending,received,refunded" );
        $this->api->setCustomerprotection(true);
        // Send request
        $this->api->sendRequest();        
        if($this->api->isError()) {
            // SOFORT-API didn't accept the data
            eventlog("error", "Fehler bei Zahlung mit Sofortüberweisung!", $this->api->getError());
            return false;
        } else {
            $this->paymentObject['TRANSACTION_ID'] = $this->api->getTransactionId();
            return $this->api->getPaymentUrl();
        }
    }

    public function handleIPN() {        
        $arPost = array();
        $postText = file_get_contents('php://input');
        $postXml = simplexml_load_string($postText);
        foreach ($postXml as $postXmlTag) {
            $tagName = $postXmlTag->getName();
            $tagValue = (string)$postXmlTag;
            $arPost[$tagName] = $tagValue;
        }
        if (array_key_exists("transaction", $arPost)) {
            $apiTransaction = new SofortLibTransactionData($this->configuration['ApiKey']);
            $apiTransaction->addTransaction($arPost["transaction"]);
            $apiTransaction->sendRequest();
            if ($apiTransaction->isError()) {
                eventlog("error", "Sofortüberweisung", "Fehler beim Auslesen der Transaktion '".$arPost["transaction"]."':".$apiTransaction->getError());
            } else {
                $arResponse = $apiTransaction->getResponse();
                if (count($arResponse) > 0) {
                    $this->handleIPN_updateTransaction($arPost["transaction"], $arResponse[0], true);
                }
            }
        }
    }
    
    private function handleIPN_updateTransaction($transactionId, $response, $updateInvoice = false) {
        $result = self::PAYMENT_RESULT_FAILED;
        if ($transactionId != $response["transaction"]["@data"]) {
            return $result;
        }
        $transactionAmount = false;
        $transactionStatus = false;
        $transactionStatusReason = false;
        if (array_key_exists("amount", $response)) {
            $transactionAmount = (float)$response["amount"]["@data"];
        }
        if (array_key_exists("status", $response)) {
            $transactionStatus = $response["status"]["@data"];
        }
        if (array_key_exists("status_reason", $response)) {
            $transactionStatusReason = $response["status_reason"]["@data"];
        }
        switch ($transactionStatus) {
            default:
            case 'pending':
                $result = self::PAYMENT_RESULT_SUCCESS;
                break;
        }
        if ($updateInvoice) {
            if ($result == self::PAYMENT_RESULT_SUCCESS) {
                // Payment confirmed
                require_once $GLOBALS['ab_path'] . 'sys/lib.billing.invoice.php';
                $billingInvoiceManagement = BillingInvoiceManagement::getInstance($GLOBALS['db']);
                $invoice = $billingInvoiceManagement->fetchByTransactionId($transactionId);
                if ($invoice !== false) {
                    require_once $GLOBALS['ab_path'] . 'sys/lib.billing.invoice.transaction.php';
                    $billingInvoiceTransactionManagement = BillingInvoiceTransactionManagement::getInstance($GLOBALS['db']);
                    $transCount = (int)$billingInvoiceTransactionManagement->countByParam(array("TRANSACTION_ID" => $transactionId));
                    if ($transCount === 0) {
                        // New transaction!
                        $billingInvoiceTransactionManagement->createInvoiceTransaction(array(
                            'FK_BILLING_INVOICE' => $invoice["ID_BILLING_INVOICE"],
                            'TYPE' => BillingInvoiceTransactionManagement::TYPE_DEFAULT,
                            'DESCRIPTION' => "Sofortüberweisung".': '.$this->paymentObject['DESCRIPTION'],
                            'TRANSACTION_ID' => $transactionId,
                            'PRICE' => $transactionAmount
                        ));
                    }
                }
            }
        }
        return $result;
    }

    public function verifyPayment() {        
        $transactionId = null;
        if (array_key_exists('TRANSACTION_ID', $this->paymentObject)) {
            $transactionId = $this->paymentObject['TRANSACTION_ID'];
        } else if (array_key_exists('INVOICE', $this->paymentObject['DATA']) && array_key_exists('TRANSACTION_ID', $this->paymentObject['DATA']['INVOICE'])) {
            $transactionId = $this->paymentObject['DATA']['INVOICE']['TRANSACTION_ID'];
        }
        if ($transactionId === null) {
            eventlog("error", "Sofortüberweisung", "Transaktion nicht gefunden!", var_export($this->paymentObject['DATA'], true));
            return self::PAYMENT_RESULT_FAILED;
        }
        $this->paymentObject['TRANSACTION_ID'] = $transactionId;
        $apiTransaction = new SofortLibTransactionData($this->configuration['ApiKey']);
        $apiTransaction->addTransaction($transactionId);
        $apiTransaction->sendRequest();
        if ($apiTransaction->isError()) {
            eventlog("error", "Sofortüberweisung", "Fehler beim Auslesen der Transaktion '".$transactionId."':".$apiTransaction->getError(), var_export($this->paymentObject, true));
            return self::PAYMENT_RESULT_FAILED;
        } else {
            $arResponse = $apiTransaction->getResponse();
            return $this->handleIPN_updateTransaction($transactionId, $arResponse[0]);
        }
    }

    public function verifyConfigForRequiredFields($config) {
        $state = true;

        if (empty($config['Account'])) {
            $this->addError('PAYPAL_NO_ACCOUNT');
            $state = false;
        }

        return $state;
    }

    public function configurationSaveSellerConfiguration($config) {
        if (!$this->verifyConfigForRequiredFields($config)) {
            return false;
        }

        return parent::configurationSaveSellerConfiguration($config);
    }

}
