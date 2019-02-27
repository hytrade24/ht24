<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once dirname(__FILE__) . '/adapter/PaymentAdapterInterface.php';
require_once dirname(__FILE__) . '/adapter/AbstractPaymentAdapter.php';

class Payment_PaymentFactory {

    /**
     * @static
     * @param $paymentAdapter
     * @param null $params
     * @return Payment_Adapter_PaymentAdapterInterface
     * @throws Exception
     */
    static public function factory($paymentAdapter, $params = null) {

        $filename = dirname(__FILE__) . '/adapter/' . strtolower($paymentAdapter) . '/' . ($paymentAdapter) . 'Adapter.php';
        $paymentAdapterName = 'Payment_Adapter_'.$paymentAdapter.'_'.$paymentAdapter.'Adapter';

        if (!file_exists($filename)) {
            throw new Exception('Payment Adapter not found');
        }
        require_once $filename;

        $obj = new $paymentAdapterName($params);
        if (!($obj instanceof Payment_Adapter_PaymentAdapterInterface)) {
            throw new Exception();
        }

		$obj->setAdapterName($paymentAdapter);

        return $obj;
    }
}