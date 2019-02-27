<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path . 'sys/lib.payment.adapter.php';

$paymentAdapterManagement = PaymentAdapterManagement::getInstance($db);

$perpage = 20; // Elemente pro Seite
$limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);

$param = array();

$paymentAdapters = $paymentAdapterManagement->fetchAllByParam(array_merge($param, array(
    'LIMIT' => $perpage,
    'OFFSET' => $limit
)));
$numberOfPaymentAdapters = $paymentAdapterManagement->countByParam($param);


$tpl_content->addvar("pager", htm_browse($numberOfPaymentAdapters, $_REQUEST['npage'], "index.php?page=".$tpl_content->vars['curpage']."&".http_build_query($param)."&npage=", $perpage));
$tpl_content->addlist('liste', $paymentAdapters, 'tpl/de/payment_adapter.row.htm');
$tpl_content->addvar("allinvoices", $numberOfPaymentAdapters);
$tpl_content->addvars($_GET);

?>