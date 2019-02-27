<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path . 'sys/lib.payment.adapter.php';
require_once $ab_path . 'sys/payment/PaymentFactory.php';

$paymentAdapterManagement = PaymentAdapterManagement::getInstance($db);
$paymentAdapter = $paymentAdapterManagement->fetchByAdapterName("PayPal");
if($paymentAdapter != null && $paymentAdapter['STATUS'] == PaymentAdapterManagement::STATUS_ENABLED) {
	$paymentAdapterConfiguration = array(
			'CONFIG' => $paymentAdapterManagement->fetchConfigurationById($paymentAdapter['ID_PAYMENT_ADAPTER'])
	);

	eventlog("info", "DEBUG - PayPal IPN post received", var_export($_REQUEST, true));
	/** @var Payment_Adapter_Paypal_PaypalAdapter $paymentAdapter  */
	$paymentAdapter = Payment_PaymentFactory::factory($paymentAdapter['ADAPTER_NAME'], $paymentAdapterConfiguration);
	$paymentAdapter->handleIPN();
	die();
}

?>