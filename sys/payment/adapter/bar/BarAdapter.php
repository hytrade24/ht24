<?php
/* ###VERSIONSBLOCKINLCUDE### */

class Payment_Adapter_Bar_BarAdapter extends Payment_Adapter_AbstractPaymentAdapter {

    public function __construct($param) {
        parent::__construct($param);
    }

    public function prepare() {
        global $s_lang;

        $tpl = new Template($this->getTemplateFilename('prepare.htm'));
        return $tpl->process();
    }

    public function prepareOrder()
    {
        global $s_lang;

        $sellerConfiguration = $this->getSellerConfiguration($this->getAdapterName());

        $tpl = new Template($this->getTemplateFilename('prepareorder.htm'));
        return $tpl->process();
    }
    
    public function doPayment() {
		    return false;
    }

    public function verifyPayment() {
        return self::PAYMENT_RESULT_SUCCESS;
    }


}