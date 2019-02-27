<?php
/* ###VERSIONSBLOCKINLCUDE### */

class Payment_Adapter_CashPayment_CashPaymentAdapter extends Payment_Adapter_AbstractPaymentAdapter {

    public function __construct($param) {
        parent::__construct($param);
    }

    public function prepare() {
        global $s_lang;

		$filename =

        $tpl = new Template($this->getTemplateFilename('prepare.htm'));
        $tpl->addvars($this->configuration, 'CONFIG_');
        $tpl->addvar('INVOICE_TOTAL_PRICE', $this->paymentObject['TOTAL_PRICE']);
        $tpl->addvar('INVOICE_REMAINING_PRICE', $this->paymentObject['REMAINING_PRICE']);
        $tpl->addvar('CURRENCY', $this->paymentObject['CURRENCY']);

        return $tpl->process();
    }

	public function prepareOrder() {
		global $s_lang;

		$sellerConfiguration = $this->getSellerConfiguration($this->getAdapterName());

		$tpl = new Template($this->getTemplateFilename('prepareorder.htm'));
		$tpl->addvar('ORDER_TOTAL_PRICE', $this->paymentObject['TOTAL_PRICE']);
		$tpl->addvar('CURRENCY', $this->paymentObject['CURRENCY']);
		$tpl->addvars($sellerConfiguration, 'SELLER_CONFIG_');

		return $tpl->process();
	}

    public function doPayment() {
		return false;
    }

    public function verifyPayment() {
        return self::PAYMENT_RESULT_SUCCESS;
    }


}