<?php
/* ###VERSIONSBLOCKINLCUDE### */

class Payment_Adapter_BankTransfer_BankTransferAdapter extends Payment_Adapter_AbstractPaymentAdapter {

    public function __construct($param) {
        parent::__construct($param);
    }

    public function prepare() {
        global $s_lang;

        $tpl = new Template($this->getTemplateFilename('prepare.htm'));
        $tpl->addvars($this->configuration, 'CONFIG_');
        $tpl->addvar('INVOICE_ID', $this->paymentObject['DATA']['INVOICE']['ID_BILLING_INVOICE']);
        $tpl->addvar('INVOICE_FK_USER', $this->paymentObject['DATA']['INVOICE']['FK_USER']);
        $tpl->addvar('INVOICE_TOTAL_PRICE', $this->paymentObject['TOTAL_PRICE']);
        $tpl->addvar('INVOICE_REMAINING_PRICE', $this->paymentObject['REMAINING_PRICE']);
        $tpl->addvar('CURRENCY', $this->paymentObject['CURRENCY']);

        return $tpl->process();
    }

	public function prepareOrder() {
		global $s_lang;

		$sellerConfiguration = $this->getSellerConfiguration($this->getAdapterName());

		$tpl = new Template($this->getTemplateFilename('prepareorder.htm'));
		$tpl->addvars($this->paymentObject, 'PAYMENT_OBJECT_');
		$tpl->addvars($this->paymentObject['DATA']['AD_ORDER'], 'ORDER_');
		$tpl->addvar('CURRENCY', $this->paymentObject['CURRENCY']);
		$tpl->addvars($sellerConfiguration, 'SELLER_CONFIG_');

		return $tpl->process();
	}

    public function doPayment() {
		return false;
    }

    public function verifyConfigForRequiredFields($config) {
        $state = true;

        if (empty($config['Recipient'])) {
            $this->addError('BANKTRANSFER_NO_RECIPIENT');
            $state = false;
        }

        if (empty($config['Iban'])) {
            $this->addError('BANKTRANSFER_NO_IBAN_NUMBER');
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

    public function verifyPayment() {
        return self::PAYMENT_RESULT_SUCCESS;
    }


}
