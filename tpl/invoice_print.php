<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once $ab_path . 'sys/lib.billing.invoice.php';
require_once $ab_path . 'sys/lib.billing.invoice.item.php';
require_once $ab_path . 'sys/lib.user.php';
require_once $ab_path . 'sys/lib.payment.adapter.php';
require_once $ab_path . 'sys/payment/PaymentFactory.php';

$billingInvoiceManagement = BillingInvoiceManagement::getInstance($db);

$invoiceIds = ((int)$ar_params[1] > 0 ? (int)$ar_params[1] : $_REQUEST["selected_invoices"]);

if (is_array($invoiceIds)) {
    #die(var_dump($invoiceIds));
    if ($_REQUEST['do'] == "download") {
        $billingInvoiceManagement->streamAsZippedPdfs($invoiceIds);
    } else {
        $billingInvoiceManagement->streamAsPdf($invoiceIds);
    }
} else {
    $billingInvoiceManagement->streamAsPdf($invoiceIds);
}