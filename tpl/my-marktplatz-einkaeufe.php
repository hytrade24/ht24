<?php
/* ###VERSIONSBLOCKINLCUDE### */

$npage = ($ar_params[1] ? (int)$ar_params[1] : 1);
$perpage = 10;
$limit = ($perpage*$npage)-$perpage;

$id = (int)$ar_params[2];
$act = $ar_params[3];
$show = $ar_params[4];


require_once $ab_path.'sys/lib.ad_order.php';
require_once $ab_path.'sys/lib.payment.adapter.php';
require_once $ab_path.'sys/payment/PaymentFactory.php';

$adOrderManagement = AdOrderManagement::getInstance($db);
$paymentAdapterManagement = PaymentAdapterManagement::getInstance($db);


$param = array(
	'USER_BUYER' => $uid,
	'LIMIT' => $perpage,
	'OFFSET' => $limit
);


if (isset($_REQUEST['ID_AD_ORDER']) && $_REQUEST['ID_AD_ORDER'] != "") {
	$param['ID_AD_ORDER'] = $_REQUEST['ID_AD_ORDER'];
}
if (isset($_REQUEST['NAMESELLER']) && $_REQUEST['NAMESELLER'] != "") {
	$param['SELLER_NAME'] = $_REQUEST['NAMESELLER'];
}
if (isset($_REQUEST['STAMP_CREATE_FROM']) && $_REQUEST['STAMP_CREATE_FROM'] != "") {
	$param['STAMP_CREATE_FROM'] = $_REQUEST['STAMP_CREATE_FROM'];
}
if (isset($_REQUEST['STAMP_CREATE_TO']) && $_REQUEST['STAMP_CREATE_TO'] != "") {
	$param['STAMP_CREATE_TO'] = $_REQUEST['STAMP_CREATE_TO'];
}
if (isset($_REQUEST['SEARCH_ORDER_CONFIRMATION_STATUS']) && $_REQUEST['SEARCH_ORDER_CONFIRMATION_STATUS'] != "-1") {
	$param['STATUS_CONFIRMATION'] = $_REQUEST['SEARCH_ORDER_CONFIRMATION_STATUS'];
} else {
	$_REQUEST['SEARCH_ORDER_CONFIRMATION_STATUS'] = -1;
}
if (isset($_REQUEST['SEARCH_ORDER_PAYMENT_STATUS']) && $_REQUEST['SEARCH_ORDER_PAYMENT_STATUS'] != "-1") {
	$param['STATUS_PAYMENT'] = $_REQUEST['SEARCH_ORDER_PAYMENT_STATUS'];
} else {
	$_REQUEST['SEARCH_ORDER_PAYMENT_STATUS'] = -1;
}
if (isset($_REQUEST['SEARCH_ORDER_SHIPPING_STATUS']) && $_REQUEST['SEARCH_ORDER_SHIPPING_STATUS'] != "-1") {
	$param['STATUS_SHIPPING'] = $_REQUEST['SEARCH_ORDER_SHIPPING_STATUS'];
} else {
	$_REQUEST['SEARCH_ORDER_SHIPPING_STATUS'] = -1;
}
if (isset($_REQUEST['RENOUNCE_REFUND_RIGHT']) && !empty($_REQUEST['RENOUNCE_REFUND_RIGHT'])) {
    foreach($_REQUEST['RENOUNCE_REFUND_RIGHT'] as $ID_AD_SOLD => $value) {
        if($value == 1) {
            $db->querynow("UPDATE `ad_sold` SET `RENOUNCE_REFUND_RIGHT`='".date('Y-m-d H:i:s')."' WHERE `ID_AD_SOLD`='".mysql_real_escape_string($ID_AD_SOLD)."' LIMIT 1");
        }
    }
    die(forward($tpl_content->tpl_uri_action("my-marktplatz-einkaeufe,,,,show_digital_downloads")));
}
$param['PAID_DOWNLOADS'] = 0;

if($show == 'show_done') {
	$param['ARCHIVE'] = 1;
	$tpl_content->addvar("show_done", 1);
} else if ($show == 'show_digital_downloads') {
	$param['PAID_DOWNLOADS'] = 1;
	$tpl_content->addvar("show_digital_downloads", 1);
} else {
	$param['ARCHIVE'] = 0;
	$tpl_content->addvar("show_open", 1);
}

$userOrders = $adOrderManagement->fetchAllByParam($param);
$countOrder = $adOrderManagement->countByParam($param);

foreach($userOrders as $key => $order) {

	if($order['SHIPPING_TRACKING_SERVICE'] > 0) {
		$userOrders[$key]['SHIPPING_TRACKING_URL'] = $adOrderManagement->getTrackingUrlByOrder($order);
	}

	foreach ( $order["items"] as $index => $item ) {
		//*, LEFT(FILENAME, 20) as FILENAME_SHORT
		$paid_files = $db->fetch_atom(
			"SELECT count(1) as count
			FROM `ad_upload` 
			WHERE FK_AD=".$item["FK_AD"]."
			AND IS_PAID = 1"
		);
		if ( !is_null( $paid_files ) ) {
			$userOrders[$key]["items"][$index]["HAVE_PAID_FILES"] = $paid_files;
		}
	}
}

$countUnpaidOrders =  $adOrderManagement->countByParam(array(
	'STATUS_PAYMENT' => 0,
	'USER_BUYER' => $uid,
	'ARCHIVE' => 0
));

$additionalParams = $_GET;
unset($additionalParams['page']);

$tpl_content->addlist("orders", $userOrders, "tpl/".$s_lang."/my-marktplatz-einkaeufe.row.htm", "callback_order_addOrderItemsBuyer");
$tpl_content->addvar("CURRENCY_DEFAULT", $nar_systemsettings['MARKTPLATZ']['CURRENCY']);
$tpl_content->addvar("pager", htm_browse_extended($countOrder, $npage, "my-marktplatz-einkaeufe,{PAGE},".$id.",".$act.",".$show, $perpage, 5, '?'.http_build_query($additionalParams)));
$tpl_content->addvar('COUNT_UNPAID_ORDERS', $countUnpaidOrders);
$tpl_content->addvars($_REQUEST);

// Settings
$tpl_content->addvar("MARKTPLATZ_HIDE_CONTACT_INFO", $nar_systemsettings["MARKTPLATZ"]["HIDE_CONTACT_INFO"]);

?>