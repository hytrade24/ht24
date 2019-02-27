<?php
/* ###VERSIONSBLOCKINLCUDE### */


class Payment_Adapter_IPaymentCC_IPaymentCCAdapter extends Payment_Adapter_AbstractPaymentAdapter {
    protected $api = NULL;

    public function __construct($param) {
        parent::__construct($param);

        if (!isset($this->configuration['ApiAccountId']) || $this->configuration['ApiAccountId'] == '') {
            throw new Exception('iPayment Adapter needs ApiAccountId');
        }
        if (!isset($this->configuration['ApiApplicationId']) || $this->configuration['ApiApplicationId'] == '') {
            throw new Exception('iPayment Adapter needs ApiApplicationId');
        }
        if (!isset($this->configuration['ApiApplicationPassword']) || $this->configuration['ApiApplicationPassword'] == '') {
            throw new Exception('iPayment Adapter needs ApiApplicationPassword');
        }

        if (!isset($this->configuration['ApiSecurityKey']) || $this->configuration['ApiSecurityKey'] == '') {
            $this->configuration['ApiSecurityKey'] = null;
        }

        if (!isset($this->configuration['Currency']) || $this->configuration['Currency'] == '') {
            $this->configuration['Currency'] = "EUR";
        }

        if (!isset($this->configuration['DefaultCurrencyMultiplier']) || $this->configuration['DefaultCurrencyMultiplier'] == '') {
            $this->configuration['DefaultCurrencyMultiplier'] = 1;
        } else {
            $this->configuration['DefaultCurrencyMultiplier'] = (int)$this->configuration['DefaultCurrencyMultiplier'];
        }

        if (!isset($this->configuration['SandboxMode']) || $this->configuration['SandboxMode'] == '') {
            $this->configuration['SandboxMode'] = false;
        } else {
            $this->configuration['SandboxMode'] = true;
        }

        if (!isset($this->configuration['Currency']) || $this->configuration['Currency'] == '') {
            $this->configuration['Currency'] = "EUR";
        }

    }

    public function prepare() {
        global $s_lang, $nar_systemsettings;

        $tpl = new Template($this->getTemplateFilename('prepare.htm'));
        $tpl->addvars($this->configuration, 'CONFIG_');

		$totalPrice = $this->getTotalInvoicePrice();
        $tpl->addvar('INVOICE_TOTAL_PRICE', $totalPrice);
        $tpl->addvar('CURRENCY', $this->configuration['Currency']);
        $totalPriceInCurrency = $totalPrice * $this->configuration['DefaultCurrencyMultiplier'];
        $tpl->addvar('TOTAL_PRICE_IN_CURRENCY', $totalPriceInCurrency);


        $tpl->addvar('INVOICE_ID', $this->paymentObject['DATA']['INVOICE']['ID_BILLING_INVOICE']);
        $tpl->addvar('INVOICE_DESCRIPTION', $this->paymentObject['DESCRIPTION']);

        $tpl->addvar('URL_SUCCESS', $this->paymentObject['SUCCESSURL']);
        $tpl->addvar('URL_CANCEL', $this->paymentObject['CANCELURL']);
        $tpl->addvar('URL_HIDDENTRIGGER', $nar_systemsettings['SITE']['SITEURL'].$nar_systemsettings['SITE']['BASE_URL'].'sys/payment/adapter/ipaymentcc/lib/verify.php');

        $tpl->addvar('API_ACCOUNT_ID', $this->configuration['ApiAccountId']);
        $tpl->addvar('API_APPLICATION_ID', $this->configuration['ApiApplicationId']);
        $tpl->addvar('API_APPLICATION_PASSWORD', $this->configuration['ApiApplicationPassword']);

        if($this->configuration['ApiSecurityKey']) {
            $securityHash = md5($this->configuration['ApiApplicationId'].$totalPriceInCurrency.$this->configuration['Currency'].$this->configuration['ApiApplicationPassword'].$this->configuration['ApiSecurityKey']);
            $tpl->addvar('API_SECURITY_HASH', $securityHash);
        }

        return $tpl->process();
    }


    public function doPayment() {
    	return false;
    }

    public function verifyPayment() {
        $paramInvoiceId = $_POST['shopper_id'];
        $paramPaymentAmount = $_POST['trx_amount'];
        $paramPaymentTransactionNumber = $_POST['ret_trx_number'];

		$totalPrice = $this->getTotalInvoicePrice();
        $totalPriceInCurrency = $totalPrice * $this->configuration['DefaultCurrencyMultiplier'];

        if (!preg_match('/\.ipayment\.de$/', gethostbyaddr($_SERVER["REMOTE_ADDR"]))) {
            return self::PAYMENT_RESULT_FAILED;
        }

        $checksum = md5($_POST["trxuser_id"].$_POST["trx_amount"].$_POST["trx_currency"].$_POST["ret_authcode"].$_POST["ret_trx_number"].$this->configuration['ApiSecurityKey']);
        if($checksum !== $_POST['ret_param_checksum']) {
            return self::PAYMENT_RESULT_FAILED;
        }

        if($_POST['ret_status'] === 'SUCCESS' && $_POST['ret_errorcode'] == 0) {
            if($totalPriceInCurrency == $paramPaymentAmount) {
                return self::PAYMENT_RESULT_SUCCESS;
            }
        }

        return self::PAYMENT_RESULT_FAILED;
    }

    public function cancelPayment() {
        global $s_lang;

        $tpl = new Template($this->getTemplateFilename('cancel.htm'));
        $tpl->addvars($this->paymentObject);
        $tpl->addvar('INVOICE_ID', $this->paymentObject['DATA']['INVOICE']['ID_BILLING_INVOICE']);

        if($_GET['ret_errorcode'] > 0) {
            $tpl->addvar('errcode', $_GET['ret_errorcode']);
            $tpl->addvar('errmsg', $_GET['ret_errormsg']);
        }

        return $tpl->process();
    }

}