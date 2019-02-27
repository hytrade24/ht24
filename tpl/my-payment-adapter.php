<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path . 'sys/lib.user.php';
require_once $ab_path . 'sys/lib.payment.adapter.php';
require_once $ab_path . 'sys/lib.payment.adapter.user.php';
require_once $ab_path . 'sys/payment/PaymentFactory.php';

$paymentAdapterManagement = PaymentAdapterManagement::getInstance($db);
$paymentAdapterUserManagement = PaymentAdapterUserManagement::getInstance($db);

$perpage = 20; // Elemente pro Seite
$npage = ((int)$ar_params[1] ? $ar_params[1] : 1);
$limit = ($perpage*$npage)-$perpage;

$param = array();

$paymentAdapters = $paymentAdapterManagement->fetchAllByParam(array_merge($param, array(
	'STATUS_USER' => PaymentAdapterManagement::USER_STATUS_ENABLED,
    'LIMIT' => $perpage,
    'OFFSET' => $limit
)));
$numberOfPaymentAdapters = $paymentAdapterManagement->countByParam($param);

foreach($paymentAdapters as $key => $paymentAdapter) {
	$adapterUserData = $paymentAdapterUserManagement->getPaymentAdapterConfigurationForUser($paymentAdapter['ID_PAYMENT_ADAPTER'], $uid);
	$paymentAdapters[$key]['CONFIG_STATUS'] = $adapterUserData['STATUS'];
	$paymentAdapters[$key]['CONFIG_AUTOCHECK'] = $adapterUserData['AUTOCHECK'];
	$paymentAdapters[$key]['CONFIG_VALID'] = $adapterUserData['CONFIG_VALID'];
}


$tpl_content->addvar("pager", htm_browse_extended($numberOfPaymentAdapters, $npage, "my-payment-adapter,{PAGE}", $perpage));
$tpl_content->addlist('liste', $paymentAdapters, 'tpl/de/my-payment-adapter.row.htm');
$tpl_content->addvar("all", $numberOfPaymentAdapters);

require_once $ab_path.'sys/lib.user.authentication.php';
$userAuthenticationManagement = UserAuthenticationManagement::getInstance($db);
$tpl_content->addvar('SOCIAL_MEDIA_LOGIN_ENABLED', $userAuthenticationManagement->isSocialMediaLoginEnabled());

if ( count($_POST) ) {
	if($_POST['PAYMENT_ADAPTER'] == "") {
		$_POST['PAYMENT_ADAPTER'] = NULL;
	}

	$db->update('user', array(
		'ID_USER' => $uid,
		'FK_PAYMENT_ADAPTER' => mysql_real_escape_string($_POST['PAYMENT_ADAPTER']),

	));

	if($user['FK_PAYMENT_ADAPTER'] == $_POST['PAYMENT_ADAPTER'] && $_POST['PAYMENT_ADAPTER'] != NULL) {
		$newUserPaymentAdapter = $paymentAdapterManagement->fetchById($user['FK_PAYMENT_ADAPTER']);

		$newPaymentAdapterConfiguration = array(
			'CONFIG' => $paymentAdapterManagement->fetchConfigurationById($newUserPaymentAdapter['ID_PAYMENT_ADAPTER'])
		);

		/** @var Payment_Adapter_PaymentAdapterInterface $paymentAdapter */
		$newPaymentAdapter = Payment_PaymentFactory::factory($newUserPaymentAdapter['ADAPTER_NAME'], $newPaymentAdapterConfiguration);
		$newPaymentAdapter->init(array(
			'FK_USER' => $uid
		));

		$saveResult = $newPaymentAdapter->configurationSaveUserConfiguration($_POST['PAYMENT_ADAPTER_CONFIG']);
	} else {
		$saveResult = true;
	}

	$tpl_content->addvar('ok',1);

	$user["FK_PAYMENT_ADAPTER"] = (int)$_POST["PAYMENT_ADAPTER"];
}

$userConfigOutput = null;
// Payment Adapter
$paymentAdapters = $paymentAdapterManagement->fetchAllByParam(array('STATUS' => PaymentAdapterManagement::STATUS_ENABLED));
foreach($paymentAdapters as $key => $tplPaymentAdapter) {
	$paymentAdapters[$key]['CURRENT_PAYMENT_ADAPTER'] = $user['FK_PAYMENT_ADAPTER'];
}
$tpl_content->addlist('PAYMENT_ADAPTER', $paymentAdapters, "tpl/".$s_lang."/my-settings.payment_adapter_row.htm");
if($user['FK_PAYMENT_ADAPTER'] != NULL && $user['FK_PAYMENT_ADAPTER'] != 0) {
	//echo 'in if';
	$userPaymentAdapter = $paymentAdapterManagement->fetchById($user['FK_PAYMENT_ADAPTER']);

	$paymentAdapterConfiguration = array(
		'CONFIG' => $paymentAdapterManagement->fetchConfigurationById($userPaymentAdapter['ID_PAYMENT_ADAPTER'])
	);
	/** @var Payment_Adapter_PaymentAdapterInterface $paymentAdapter  */
	$paymentAdapter = Payment_PaymentFactory::factory($userPaymentAdapter['ADAPTER_NAME'], $paymentAdapterConfiguration);
	$paymentAdapter->init(array(
		'FK_USER' => $uid
	));

	$userConfigOutput = $paymentAdapter->configurationEditUserConfiguration();
	$tpl_content->addvar('PAYMENT_ADAPTER_CONFIG', $userConfigOutput);
} else {
	$tpl_content->addvar('PAYMENT_ADAPTER_CONFIG', "");
}
?>