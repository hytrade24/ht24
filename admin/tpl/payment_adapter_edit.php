<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path . 'sys/lib.payment.adapter.php';
$paymentAdapterManagement = PaymentAdapterManagement::getInstance($db);

$paymentAdapterId = (int) $_REQUEST['ID_PAYMENT_ADAPTER'];
$action = $_REQUEST['action'];


if($action == 'save') {
    $_POST['V1'] = $_POST['NAME'];
    if($_POST['V1'] != "") {
        $db->update('payment_adapter', $_POST);
        $tpl_content->addvar('ok', 1);
    } else {
        $tpl_content->addvar('err', 1);
    }
} elseif($action == 'hide') {
    $db->update('payment_adapter', array('ID_PAYMENT_ADAPTER' => $paymentAdapterId, 'STATUS' => 0));
    forward("index.php?page=payment_adapter");
} elseif($action == 'visible') {
    $db->update('payment_adapter', array('ID_PAYMENT_ADAPTER' => $paymentAdapterId, 'STATUS' => 1));
    forward("index.php?page=payment_adapter");
} elseif($action == 'delete') {
    $db->delete('payment_adapter', $paymentAdapterId);
    forward("index.php?page=payment_adapter");
}

if($paymentAdapterId != 0) {
	$paymentAdapter = $paymentAdapterManagement->fetchById($paymentAdapterId);
	$tpl_content->addvars($paymentAdapter);
} else {
	$tpl_content->addvar("ID_PAYMENT_ADAPTER", 0);
	$tpl_content->addvar("NAME", "");
}

?>