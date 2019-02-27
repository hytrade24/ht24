<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path . 'sys/lib.billing.invoice.php';
require_once $ab_path . 'sys/lib.user.php';
require_once $ab_path . 'sys/lib.payment.adapter.php';
require_once $ab_path . 'sys/payment/PaymentFactory.php';

$billingInvoiceManagement = BillingInvoiceManagement::getInstance($db);
$userManagement = UserManagement::getInstance($db);
$paymentAdapterManagement = PaymentAdapterManagement::getInstance($db);

$isSearch = false;
$perpage = 20; // Elemente pro Seite
$limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);

$param = array();
if(isset($_GET['ID_BILLING_INVOICE']) && $_GET['ID_BILLING_INVOICE'] !== "") {
    $isSearch = true;
    $param['ID_BILLING_INVOICE'] = $_GET['ID_BILLING_INVOICE'];
} 
if(isset($_GET['OLDER_AS']) && $_GET['OLDER_AS'] !== "") {
    $isSearch = true;
    $param['STAMP_CREATE_AFTER'] = $_GET['OLDER_AS'];
    $tpl_content->addvar('OLDER_AS_'.str_replace(" ", "_", $_GET['OLDER_AS']), true);
}
if(isset($_GET['STAMP_CREATE_FROM']) && $_GET['STAMP_CREATE_FROM'] !== "") {
    $isSearch = true;
    $param['STAMP_CREATE_FROM'] = $_GET['STAMP_CREATE_FROM'];
} 
if(isset($_GET['STAMP_CREATE_TO']) && $_GET['STAMP_CREATE_TO'] !== "") {
    $isSearch = true;
    $param['STAMP_CREATE_TO'] = $_GET['STAMP_CREATE_TO'];
} 
if(isset($_GET['FK_USER']) && $_GET['FK_USER'] !== "") {
    $isSearch = true;
    $param['FK_USER'] = $_GET['FK_USER'];
} 
if(isset($_GET['FK_PAYMENT_ADAPTER']) && $_GET['FK_PAYMENT_ADAPTER'] !== "") {
    $isSearch = true;
    $param['FK_PAYMENT_ADAPTER'] = $_GET['FK_PAYMENT_ADAPTER'];
}
if(isset($_GET['STATUS']) && $_GET['STATUS'] !== "") {
    $isSearch = true;
    $param['STATUS'] = $_GET['STATUS'];
    $tmpStatusArray = array('OPEN', 'PAID', 'CANCELED');
    $tpl_content->addvar('STATUS_'.$tmpStatusArray[$param['STATUS']], true);
}
if (!empty($_REQUEST["FK_BILLING_INVOICE_EXPORT"])) {
  $param["FK_BILLING_INVOICE_EXPORT"] = $_REQUEST["FK_BILLING_INVOICE_EXPORT"];
}

$param['SORT'] = 'STAMP_CREATE';
$param['SORT_DIR'] = 'DESC';
switch($_GET['SORT']) {
    case 'SORT_STAMP_CREATE_DESC': $param['SORT'] = 'STAMP_CREATE'; $param['SORT_DIR'] = 'DESC'; break;
    case 'SORT_STAMP_CREATE_ASC': $param['SORT'] = 'STAMP_CREATE'; $param['SORT_DIR'] = 'ASC'; break;
    case 'SORT_ID_BILLING_INVOICE_DESC': $param['SORT'] = 'ID_BILLING_INVOICE'; $param['SORT_DIR'] = 'DESC'; break;
    case 'SORT_ID_BILLING_INVOICE_ASC': $param['SORT'] = 'ID_BILLING_INVOICE'; $param['SORT_DIR'] = 'ASC'; break;
}
$tpl_content->addvar('SORT_'.$param['SORT'].'_'.$param['SORT_DIR'], true);

$param['SORT_DIR'] .= ', i.ID_BILLING_INVOICE '.$param['SORT_DIR'];

if(isset($_GET['print'])) {
    $invoiceIds = $billingInvoiceManagement->fetchAllInvoiceIdsByParam($param);
    die($billingInvoiceManagement->streamAsPdf(array_values($invoiceIds), null, false, false));
}
if(isset($_GET['download'])) {
    $invoiceIds = array_values($billingInvoiceManagement->fetchAllInvoiceIdsByParam($param));
    #die(var_dump($invoiceIds));
    die($billingInvoiceManagement->streamAsZippedPdfs($invoiceIds, "invoices.zip", false, false));
}
if ( isset($_GET['csv']) ) {
    $arPaymentAdapters = $db->fetch_nar("SELECT ID_PAYMENT_ADAPTER, ADAPTER_NAME FROM `payment_adapter`");
    $invoices = $billingInvoiceManagement->fetchAllByParam($param);
    foreach($invoices as $key => $invoice) {
        $invoiceUser = $userManagement->fetchById($invoice['FK_USER']);
        $invoices[$key]['USER_NAME'] = $invoiceUser['NAME'];
        if ($arPaymentAdapters[$invoice["FK_PAYMENT_ADAPTER"]] == "DirectDebit") {
            // TODO Read user config
            $payment_adapter_config = unserialize($invoiceUser['PAYMENT_ADAPTER_CONFIG']);
            $invoices[$key]['IBAN'] = $payment_adapter_config["directdebit"]["iban"];
            $invoices[$key]['BIC'] = $payment_adapter_config["directdebit"]["bic"];
        }
        else {
            $invoices[$key]['IBAN'] = "";
            $invoices[$key]['BIC'] = "";
        }
    }
    die($billingInvoiceManagement->streamAsCSV( $invoices ));
}

$invoices = $billingInvoiceManagement->fetchAllByParam(array_merge($param, array(
	'BILLING_CANCEL_CHECK'  =>  '1',
    'LIMIT' => $perpage,
    'OFFSET' => $limit
)));
$numberOfInvoices = $billingInvoiceManagement->countByParam($param);
$tplInvoices = array();

foreach($invoices as $key => $invoice) {
    $invoiceUser = $userManagement->fetchById($invoice['FK_USER']);
    $invoice['USER_NAME'] = $invoiceUser['NAME'];
    $tplInvoices[] = $invoice;
}

if ($isSearch) {
	$tpl_content->addvar("SEARCH_RESULT", 1);
}

// Payment Adapter
$paymentAdapters = $paymentAdapterManagement->fetchAllByParam(array('STATUS' => PaymentAdapterManagement::STATUS_ENABLED));
foreach($paymentAdapters as $key => $tplPaymentAdapter) {
    $paymentAdapters[$key]['CURRENT_PAYMENT_ADAPTER'] = $param['FK_PAYMENT_ADAPTER'];
}
$tpl_content->addlist('PAYMENT_ADAPTER', $paymentAdapters, "tpl/".$s_lang."/buchhaltung.payment_adapter_row.htm");


$tpl_content->addvar("pager", htm_browse($numberOfInvoices, $_REQUEST['npage'], "index.php?page=".$tpl_content->vars['curpage']."&".http_build_query($param)."&npage=", $perpage));
$tpl_content->addlist('liste', $tplInvoices, 'tpl/de/buchhaltung.row.htm');
$tpl_content->addvar("allinvoices", $numberOfInvoices);
$tpl_content->addvars($_GET);

?>