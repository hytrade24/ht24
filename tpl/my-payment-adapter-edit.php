<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path . 'sys/lib.payment.adapter.php';
require_once $ab_path . 'sys/lib.payment.adapter.user.php';
require_once $ab_path . 'sys/payment/PaymentFactory.php';

$paymentAdapterManagement = PaymentAdapterManagement::getInstance($db);
$paymentAdapterUserManagement = PaymentAdapterUserManagement::getInstance($db);

$userPaymentAdapterId = (int)$_POST['ID_PAYMENT_ADAPTER'];
$userPaymentAdapter = $paymentAdapterManagement->fetchById($userPaymentAdapterId);

if($userPaymentAdapterId != 0 && $userPaymentAdapter != NULL) {

	$paymentAdapterConfiguration = array(
		'CONFIG' => $paymentAdapterManagement->fetchConfigurationById($userPaymentAdapter['ID_PAYMENT_ADAPTER'])
	);

	/** @var Payment_Adapter_PaymentAdapterInterface $paymentAdapter  */
	$paymentAdapter = Payment_PaymentFactory::factory($userPaymentAdapter['ADAPTER_NAME'], $paymentAdapterConfiguration);
	$paymentAdapter->init(array(
		'FK_SELLER' => $uid
	));

	if (count($_POST) && $_POST['do'] == 'save') {
		$saveResult = $paymentAdapter->configurationSaveSellerConfiguration($_POST['PAYMENT_ADAPTER_SELLER_CONFIG']);
		if($saveResult == TRUE) {

			$db->querynow("DELETE FROM user2payment_adapter WHERE FK_USER = '".(int)$uid."' AND FK_PAYMENT_ADAPTER = '".(int)$userPaymentAdapterId."' ");

			$adapterStatus = ($_POST['USER_STATUS'] == 1) ? PaymentAdapterUserManagement::STATUS_ENABLED : PaymentAdapterUserManagement::STATUS_DISABLED;
			$adapterAutocheck = ($_POST['USER_AUTOCHECK'] == 1) ? PaymentAdapterUserManagement::AUTOCHECK_ENABLED : PaymentAdapterUserManagement::AUTOCHECK_DISABLED;
			$paymentAdapterUserManagement->setPaymentAdapterConfigurationStatusForUser($userPaymentAdapterId, $uid, $adapterStatus);
			$paymentAdapterUserManagement->setPaymentAdapterConfigurationAutocheckForUser($userPaymentAdapterId, $uid, $adapterAutocheck);
			$paymentAdapterUserManagement->setPaymentAdapterConfigurationValidityForUser($userPaymentAdapterId, $uid, PaymentAdapterUserManagement::CONFIG_VALID);

			$tpl_content->addvar('ok',1);
		} else {
			$paymentAdapterUserManagement->setPaymentAdapterConfigurationStatusForUser($userPaymentAdapterId, $uid, PaymentAdapterUserManagement::STATUS_DISABLED);
			$paymentAdapterUserManagement->setPaymentAdapterConfigurationAutocheckForUser($userPaymentAdapterId, $uid, PaymentAdapterUserManagement::AUTOCHECK_DISABLED);
			$paymentAdapterUserManagement->setPaymentAdapterConfigurationValidityForUser($userPaymentAdapterId, $uid, PaymentAdapterUserManagement::CONFIG_INVALID);


			$tpl_content->addvar('err', implode('<br>',get_messages('PAYMENT', implode(',', $paymentAdapter->getErrorList()))));
		}
	}

	$userConfigOutput = $paymentAdapter->configurationEditSellerConfiguration(array_key_exists("PAYMENT_ADAPTER_SELLER_CONFIG", $_POST) ? $_POST["PAYMENT_ADAPTER_SELLER_CONFIG"] : null);
	$paymentAdapterUser = $paymentAdapterUserManagement->getPaymentAdapterConfigurationForUser($userPaymentAdapterId, $uid);

	$tpl_content->addvar('PAYMENT_ADAPTER_SELLER_CONFIG', $userConfigOutput);
	$tpl_content->addvars($userPaymentAdapter, 'USER_PAYMENT_ADAPTER_');
	$tpl_content->addvar('ID_PAYMENT_ADAPTER', $userPaymentAdapterId);
	$tpl_content->addvar('USER_STATUS', $paymentAdapterUser['STATUS']);
	$tpl_content->addvar('USER_AUTOCHECK', $paymentAdapterUser['AUTOCHECK']);

}
