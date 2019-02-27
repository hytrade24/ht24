<?php
/* ###VERSIONSBLOCKINLCUDE### */

class Payment_Adapter_Dummy_DummyAdapter extends Payment_Adapter_AbstractPaymentAdapter {

    public function __construct($param) {   }

    public function prepare() {
        return "<p>Dummy Implementation...</p>";
    }
    public function doPayment() {
		return false;
    }

    public function verifyPayment() {
        return self::PAYMENT_RESULT_SUCCESS;
    }


}