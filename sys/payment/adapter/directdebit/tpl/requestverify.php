<?php
require_once dirname(__FILE__).'/../../../../../api.php';

require_once $ab_path . 'sys/lib.user.php';
require_once $ab_path . 'sys/lib.payment.adapter.php';
require_once $ab_path . 'sys/payment/PaymentFactory.php';

$paymentAdapterManagement = PaymentAdapterManagement::getInstance($db);
$userManagement = UserManagement::getInstance($db);

$user = $userManagement->fetchById($uid);

$userPaymentAdapter = $paymentAdapterManagement->fetchById($user['FK_PAYMENT_ADAPTER']);

$paymentAdapterConfiguration = array(
    'CONFIG' => $paymentAdapterManagement->fetchConfigurationById($userPaymentAdapter['ID_PAYMENT_ADAPTER'])
);

/** @var Payment_Adapter_PaymentAdapterInterface $paymentAdapter  */
$paymentAdapter = Payment_PaymentFactory::factory($userPaymentAdapter['ADAPTER_NAME'], $paymentAdapterConfiguration);
$paymentAdapter->init(array(
    'FK_USER' => $uid
));

/** @var Payment_Adapter_Directdebit_DirectDebitAdapter $paymentAdapter */
if($paymentAdapter instanceof Payment_Adapter_Directdebit_DirectDebitAdapter) {
    $paymentAdapter->configurationRequestVerify();
}

die(forward('/my-pages/my-settings.htm'));