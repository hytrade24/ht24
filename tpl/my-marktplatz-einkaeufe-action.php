<?php
/* ###VERSIONSBLOCKINLCUDE### */

function getOrderPaymentAdapter($idOrder) {
	global $db, $adOrderManagement, $tpl_content, $uid, $user, $nar_systemsettings;
	$order = $adOrderManagement->fetchById($idOrder);

	$paymentAdapterManagement = PaymentAdapterManagement::getInstance($db);
	$orderPaymentAdapter = $paymentAdapterManagement->fetchById($order['FK_PAYMENT_ADAPTER']);
	if($orderPaymentAdapter != null && $orderPaymentAdapter['STATUS_USER'] == PaymentAdapterManagement::STATUS_ENABLED) {
		$paymentAdapterConfiguration = array(
				'CONFIG' => $paymentAdapterManagement->fetchConfigurationById($orderPaymentAdapter['ID_PAYMENT_ADAPTER'])
		);

		/** @var Payment_Adapter_PaymentAdapterInterface $paymentAdapter  */
		$paymentAdapter = Payment_PaymentFactory::factory($orderPaymentAdapter['ADAPTER_NAME'], $paymentAdapterConfiguration);

		$paymentObject = array(
				'TYPE' => 'AD_ORDER',
				'FK_USER' => $uid,
				'FK_SELLER' => $order['FK_USER_VK'],
				'DATA' => array(
						'AD_ORDER' => $order,
						'USER' => $user
				),
				'DESCRIPTION' => ''.$nar_systemsettings['SITE']['SITENAME'].' - Bestellung Nr. '.$idOrder,
				'TOTAL_PRICE' => $order['TOTAL_PRICE'],
				'SHIPPING_PRICE' => $order['SHIPPING_PRICE'],
				//'CURRENCY' => 'EUR',
				'ITEMS' => null,
				'PAYURL' => $tpl_content->tpl_uri_action_full('my-marktplatz-einkaeufe-action,PAY,'.$idOrder),
				'RETURNURL' => $tpl_content->tpl_uri_action_full('my-marktplatz-einkaeufe-action,VERIFY,'.$idOrder),
				'SUCCESSURL' => $tpl_content->tpl_uri_action_full('my-marktplatz-einkaeufe-action,SUCCESS,'.$idOrder),
				'CANCELURL' => $tpl_content->tpl_uri_action_full('my-marktplatz-einkaeufe-action,CANCEL,'.$idOrder)
		);

		if($order['items'] != null) {
			$paymentObjectItems = array();
			foreach($order['items'] as $key => $orderItem) {
				$paymentObjectItems[] = array(
						'DESCRIPTION' => $orderItem['PRODUKTNAME'],
						'QUANTITY'    => $orderItem['MENGE'],
						'TOTAL_PRICE' => $orderItem['PREIS']
				);
			}
			$paymentObject['ITEMS'] = $paymentObjectItems;
		}

		$paymentAdapter->init($paymentObject);
		return $paymentAdapter;
	}
	return false;
}

global $adOrderManagement, $tpl_content;

require_once $ab_path.'sys/lib.ad_order.php';
require_once $ab_path . 'sys/lib.payment.adapter.php';
require_once $ab_path . 'sys/payment/PaymentFactory.php';

$adOrderManagement = AdOrderManagement::getInstance($db);

if (count($ar_params) > 1) {
	// Non-Post URL z.B. zum bezahlen via paypal
	$_REQUEST["DO"] = $ar_params[1];
	$_REQUEST["ID_AD_ORDER"] = $ar_params[2];
}

if(isset($_REQUEST['DO'])) {
	if($_REQUEST['DO'] == 'PAY' && $_REQUEST['ID_AD_ORDER'] !== 0 && $adOrderManagement->existOrderForUserId($_REQUEST['ID_AD_ORDER'], $uid)) {

		$paymentAdapter = getOrderPaymentAdapter($_REQUEST['ID_AD_ORDER']);
		if ($paymentAdapter !== false) {
			$prepareOutput = $paymentAdapter->prepareOrder();
			echo $prepareOutput;
			die();
		}
	} elseif($_REQUEST['DO'] == 'VERIFY' && $adOrderManagement->existOrderForUserId($_REQUEST['ID_AD_ORDER'], $uid))  {
		$paymentAdapter = getOrderPaymentAdapter($_REQUEST['ID_AD_ORDER']);
		$result = $paymentAdapter->verifyPayment();
		eventlog("info", "debug: ".$result);
		if ($result == Payment_Adapter_AbstractPaymentAdapter::PAYMENT_RESULT_SUCCESS) {
			$paymentObject = $paymentAdapter->getPaymentObject();
			$adOrderManagement->markOrderAsPaid($_REQUEST['ID_AD_ORDER'], $paymentObject['TRANSACTION_ID']);
			die(forward($tpl_content->tpl_uri_action('my-marktplatz-einkaeufe-action,SUCCESS,'.$_REQUEST['ID_AD_ORDER'])));
		} else if ($result == Payment_Adapter_AbstractPaymentAdapter::PAYMENT_RESULT_PENDING) {
			$paymentObject = $paymentAdapter->getPaymentObject();
			$adOrderManagement->setTransactionId($_REQUEST['ID_AD_ORDER'], $paymentObject['TRANSACTION_ID']);
			$adOrderManagement->setPaymentStatus($_REQUEST['ID_AD_ORDER'], AdOrderManagement::STATUS_PAYMENT_PENDING);

			die(forward($tpl_content->tpl_uri_action('my-marktplatz-einkaeufe-action,PENDING,'.$_REQUEST['ID_AD_ORDER'])));
		} else {
			$tpl_content->addvar('ERROR', true);
		}
	} elseif($_REQUEST['DO'] == 'SPLIT' && $_REQUEST['ID_AD_ORDER'] !== 0 && $adOrderManagement->existOrderForUserId($_REQUEST['ID_AD_ORDER'], $uid)) {
		$adOrderManagement->splitOrder($_REQUEST['ID_AD_ORDER']);

		echo json_encode(array('success' => TRUE)); die();
	} elseif($_REQUEST['DO'] == 'ARCHIVE' && $_REQUEST['ID_AD_ORDER'] !== 0 && $adOrderManagement->existOrderForUserId($_REQUEST['ID_AD_ORDER'], $uid)) {
		$adOrderManagement->markOrderAsArchived($_REQUEST['ID_AD_ORDER']);

		forward($tpl_content->tpl_uri_action("my-marktplatz-einkaeufe"));
		die();
	} elseif($_REQUEST['DO'] == 'UNARCHIVE' && $_REQUEST['ID_AD_ORDER'] !== 0 && $adOrderManagement->existOrderForUserId($_REQUEST['ID_AD_ORDER'], $uid)) {
		$adOrderManagement->markOrderAsArchived($_REQUEST['ID_AD_ORDER'], NULL, 0);

		forward($tpl_content->tpl_uri_action("my-marktplatz-einkaeufe,,,,show_done"));
		die();
	} elseif($_REQUEST['DO'] == 'SHOW_AGB') {
		$id_ad_sold = (int)$_REQUEST["ID_AD_SOLD"];
		if ($id_ad_sold > 0) {
			$id_ad = (int)$db->fetch_atom("SELECT FK_AD FROM `ad_sold` WHERE ID_AD_SOLD=".$id_ad_sold);
			$table = $db->fetch_atom("SELECT AD_TABLE FROM `ad_master` WHERE ID_AD_MASTER=".$id_ad);
			$result = $db->fetch_atom("SELECT AD_AGB FROM `".mysql_real_escape_string($table)."` WHERE ID_".strtoupper($table)."=".$id_ad);
			die($result);
		}
	} elseif($_REQUEST['DO'] == 'SHOW_WIDERRUF') {
		$id_ad_sold = (int)$_REQUEST["ID_AD_SOLD"];
		if ($id_ad_sold > 0) {
			$id_ad = (int)$db->fetch_atom("SELECT FK_AD FROM `ad_sold` WHERE ID_AD_SOLD=".$id_ad_sold);
			$table = $db->fetch_atom("SELECT AD_TABLE FROM `ad_master` WHERE ID_AD_MASTER=".$id_ad);
			$result = $db->fetch_atom("SELECT AD_WIDERRUF FROM `".mysql_real_escape_string($table)."` WHERE ID_".strtoupper($table)."=".$id_ad);
			die($result);
		}
	} else {
		$tpl_content->addvar($_REQUEST['DO'], true);
	}

}

?>

