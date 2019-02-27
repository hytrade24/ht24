<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once dirname(__FILE__).'/lib/lib.paypal.php';

class Payment_Adapter_Paypal_PaypalAdapter extends Payment_Adapter_AbstractPaymentAdapter {
    public $adapterName = 'paypal';

    protected $api = NULL;

    public function __construct($param) {
        parent::__construct($param);
        
        $configuration = array();
        if(isset($param['CONFIG']) && is_array($param['CONFIG'])) {
            $configuration = $param['CONFIG'];
        }

        if (!isset($this->configuration['ApiUser']) || $this->configuration['ApiUser'] == '') {
            throw new Exception('Paypal Payment Adapter needs ApiUser');
        }
        if (!isset($this->configuration['ApiPassword']) || $this->configuration['ApiPassword'] == '') {
            throw new Exception('Paypal Payment Adapter needs ApiPassword');
        }
        if (!isset($this->configuration['ApiSignature']) || $this->configuration['ApiSignature'] == '') {
            throw new Exception('Paypal Payment Adapter needs ApiSignature');
        }

        if (!isset($this->configuration['SandboxMode']) || $this->configuration['SandboxMode'] == '') {
            $configuration['SandboxMode'] = false;
        } else {
            $this->configuration['SandboxMode'] = true;
        }

        if (!isset($configuration['DefaultCurrency']) || $this->configuration['DefaultCurrency'] == '') {
            $this->configuration['DefaultCurrency'] = "EUR";
        }

        $this->api = new PayPal($this->configuration['ApiSignature'], $this->configuration['ApiUser'], $this->configuration['ApiPassword'], $this->configuration['DefaultCurrency'], $this->configuration['SandboxMode']);
    }

    public function prepareOrder() {
    	$result = $this->doPayment();
    	if ($result !== false) {
    		die(forward($result));
    	}
    	return "";
    }

    public function doPayment() {
        if(isset($this->paymentObject['CURRENCY'])) {
            $this->api->setPaypalCurrency($this->paymentObject['CURRENCY']);
        }
        // Get seller configuration
        $configurationSeller = $this->getSellerConfiguration($this->adapterName);

		$items = array();
		if($this->paymentObject['ITEMS'] != NULL) {
			foreach($this->paymentObject['ITEMS'] as $key => $item) {
				if (!isset($item['TOTAL_PRICE_NET'])) {
					$item['TOTAL_PRICE_NET'] = $item['TOTAL_PRICE'];
				}
				/*$items[] = array(
					'TITLE' => $item['DESCRIPTION'],
					'PRODUCT_NETTO' => $item['TOTAL_PRICE_NET']/$item['QUANTITY'],
					'PRODUCT_BRUTTO' => $item['TOTAL_PRICE']/$item['QUANTITY'],
					'COUNT' => $item['QUANTITY'],
				);*/
			}
		}
		$totalPrice = $this->getTotalInvoicePrice();

		$items[] = array(
			'TITLE' => $this->paymentObject['DESCRIPTION'],
			'PRODUCT_NETTO' => $totalPrice,
			'PRODUCT_BRUTTO' => $totalPrice,
			'COUNT' => 1,
		);

        $result = $this->api->SetExpressCheckoutSingle(
			$totalPrice,
            0,
            $this->paymentObject['DESCRIPTION'],
            $this->paymentObject['RETURNURL'],
            $this->paymentObject['CANCELURL'],
            $items,
        	($configurationSeller === NULL ? false : $configurationSeller["Account"])
        );

		#var_dump($this->api->paypal_response); die();

        if($result === TRUE) {
            return "https://www.".($this->configuration['SandboxMode'] ? "sandbox." : "")."paypal.com/".
            				"cgi-bin/webscr?cmd=_express-checkout&token=".urlencode($this->api->getPaypalToken());
        } else {
            eventlog("error", "Fehler bei Zahlung mit PayPal!", var_export($this->api->paypal_response, true));
            return false;
        }
    }

    public function handleIPN() {
    	return $this->api->handleIPN();
    }

    public function verifyPayment() {
    	global $nar_systemsettings;
        $token = $_GET['token'];
        $payerId = $_REQUEST['PayerID'];
    	$noticeUrl = $nar_systemsettings['SITE']['SITEURL'].$nar_systemsettings['SITE']['BASE_URL']."paypal.htm";
        // Get seller configuration
        $configurationSeller = $this->getSellerConfiguration($this->adapterName);
        $sellerId = ($configurationSeller === NULL ? false : $configurationSeller["Account"]);

		$totalPrice = $this->getTotalInvoicePrice();

        $result = $this->api->DoExpressCheckout($token, $totalPrice, $payerId, $noticeUrl, $sellerId);
        if ($result === TRUE) {
            $response = $this->api->getPaypalResponse();

			eventlog("info", "Paypal respone: ", var_export($response, true));

            $b_paid = ($response['PAYMENTINFO_0_PAYMENTSTATUS'] == "Completed" ? true : false);
            $this->paymentObject["TRANSACTION_ID"] = $response['PAYMENTINFO_0_TRANSACTIONID'];

            if ($b_paid === TRUE) {
                return self::PAYMENT_RESULT_SUCCESS;
            } else {
            	// Zahlung erfolgreich aber noch nicht bestätigt
            	return self::PAYMENT_RESULT_PENDING;
            }
        }

        eventlog("error", "PayPalAdapter->verifyPayment() failed!", var_export($this->api->getPaypalResponse(), true));
        return self::PAYMENT_RESULT_FAILED;
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
