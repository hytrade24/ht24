<?php

require_once $ab_path.'sys/lib.ad_order.php';

$param = array(
    'VENDOR_ID' => $uid
);

if (isset($_REQUEST['STAMP_CREATE_FROM']) && $_REQUEST['STAMP_CREATE_FROM'] != "") {
    $param['STAMP_CREATE_FROM'] = $_REQUEST['STAMP_CREATE_FROM'];
} else {
    $param['STAMP_CREATE_FROM'] = "";
}
if (isset($_REQUEST['STAMP_CREATE_TO']) && $_REQUEST['STAMP_CREATE_TO'] != "") {
    $param['STAMP_CREATE_TO'] = $_REQUEST['STAMP_CREATE_TO'];
} else {
    $param['STAMP_CREATE_TO'] = "";
}
if (isset($_REQUEST['TOTAL_PAYMENT']) && $_REQUEST['TOTAL_PAYMENT'] != "") {
    $param['TOTAL_PAYMENT'] = intval($_REQUEST['TOTAL_PAYMENT']);
} else {
    $param['TOTAL_PAYMENT'] = "";
}
if (isset($_REQUEST['SEARCH_ORDER_SHIPPING_STATUS']) && $_REQUEST['SEARCH_ORDER_SHIPPING_STATUS'] != "-1") {
    $param['SEARCH_ORDER_SHIPPING_STATUS'] = $_REQUEST['SEARCH_ORDER_SHIPPING_STATUS'];
} else {
    $_REQUEST['SEARCH_ORDER_SHIPPING_STATUS'] = -1;
}


$adOrderManagement = AdOrderManagement::getInstance($db);

$customers = $adOrderManagement->getVendorCustomersOrders( $param );

$tpl_content->addlist('vendor_customers',$customers,"tpl/".$s_lang."/my_customers.row.htm");
$tpl_content->addvar('SEARCH_ORDER_SHIPPING_STATUS',$_REQUEST['SEARCH_ORDER_SHIPPING_STATUS']);
$tpl_content->addvar('STAMP_CREATE_FROM',$param['STAMP_CREATE_FROM']);
$tpl_content->addvar('STAMP_CREATE_TO',$param['STAMP_CREATE_TO']);
$tpl_content->addvar('TOTAL_PAYMENT',$param['TOTAL_PAYMENT']);