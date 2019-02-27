<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path . 'sys/lib.billing.invoice.php';
require_once $ab_path . 'sys/lib.billing.invoice.item.php';
require_once $ab_path . 'sys/lib.billing.invoice.transaction.php';
require_once $ab_path . 'sys/lib.billing.creditnote.php';
require_once $ab_path . 'sys/lib.user.php';
require_once $ab_path . 'sys/lib.payment.adapter.php';
require_once $ab_path . 'sys/payment/PaymentFactory.php';

$billingInvoiceManagement = BillingInvoiceManagement::getInstance($db);
$billingInvoiceItemManagement = BillingInvoiceItemManagement::getInstance($db);
$billingInvoiceTransactionManagement = BillingInvoiceTransactionManagement::getInstance($db);
$billingCreditnoteManagement = BillingCreditnoteManagement::getInstance($db);
$paymentAdapterManagement = PaymentAdapterManagement::getInstance($db);
$userManagement = UserManagement::getInstance($db);

$invoiceId = ((int)$ar_params[1] > 0 ? (int)$ar_params[1] : $_REQUEST["ID_BILLING_INVOICE"]);
$action = (!empty($ar_params[2]) ? $ar_params[2] : $_REQUEST["action"]);
$mode = (array_key_exists("mode", $_REQUEST) ? $_REQUEST["mode"] : "view");

$invoice = $billingInvoiceManagement->fetchById($invoiceId);
if($invoice == null) {
	die("invoice not found..");
}
if (($mode == "edit") && ($invoice['STATUS'] == BillingInvoiceManagement::STATUS_PAID)) {
	die("Bezahlte Rechnungen können nicht bearbeitet werden!");
}

if ($nar_systemsettings['MARKTPLATZ']['INVOICE_SAVE_PDF']) {
    $tpl_content_links->addvar("INVOICE_ID", $invoiceId);
    $tpl_content_links->addvar("INVOICE_SAVE_PDF", 1);
    $invoiceFile = $billingInvoiceManagement->getCachePdfFilename($invoiceId, true, ($invoice["STAMP_CORRECTION"] !== null), ($invoice["STATUS"] == 2));
    $invoiceFilename = pathinfo($invoiceFile, PATHINFO_BASENAME);
    if (file_exists($invoiceFile)) {
        $tpl_content_links->addvar("INVOICE_PDF_FILENAME", $invoiceFilename);
        $tpl_content_links->addvar("INVOICE_PDF_CHANGED", date("Y-m-d H:i:s", filemtime($invoiceFile)));
    }
}

$invoiceItems = $billingInvoiceItemManagement->fetchAllByParam(
	array(
		'FK_BILLING_INVOICE' => $invoiceId,
		'BILLING_CANCEL_CHECK'  =>  '1'
	)
);
$invoicePaymentAdapter = $paymentAdapterManagement->fetchById($invoice['FK_PAYMENT_ADAPTER']);
$invoiceUser = $userManagement->fetchById($invoice['FK_USER']);
if (!$invoiceUser) {
    $invoiceUser = array(
        "ID_USER"   => $invoice['FK_USER'],
        "NAME"      => "???"
    );
}


if($action == 'setstatus' && isset($_REQUEST['status'])) {
    if($_REQUEST['status'] == BillingInvoiceManagement::STATUS_PAID) {
        $priceRemaining = round($invoice["REMAINING_PRICE"] * 100) / 100;
        if ($priceRemaining > 0) {
            $result = $billingInvoiceTransactionManagement->createInvoiceTransaction(array(
                'FK_BILLING_INVOICE' => $invoiceId,
                'TYPE' => BillingInvoiceTransactionManagement::TYPE_DEFAULT,
                'STAMP_CREATE' => date("Y-m-d"),
                'DESCRIPTION' => "",
                'TRANSACTION_ID' => "",
                'PRICE' => $priceRemaining
            ));
        } else {
            $billingInvoiceManagement->setStatus($invoice['ID_BILLING_INVOICE'], (int)$_REQUEST['status'], $invoice);
        }
    } else if($invoice['STATUS'] != BillingInvoiceManagement::STATUS_CANCELED) {
        $arData = $invoice;
        if (array_key_exists("performances", $_REQUEST)) {
            $arData["KEEP_PERFORMANCES"] = (int)$_REQUEST["performances"];
        }
        if (array_key_exists("creditnote", $_REQUEST)) {
            $arData["CREATE_CREDITNOTE"] = (int)$_REQUEST["creditnote"];
        }
        $billingInvoiceManagement->setStatus($invoice['ID_BILLING_INVOICE'], (int)$_REQUEST['status'], $arData);
    }
    die(forward("index.php?page=invoice_view&ID_BILLING_INVOICE=".$invoice['ID_BILLING_INVOICE']));
} elseif($action == 'addpayment') {
    date_implode($_POST, 'STAMP_CREATE');

    $result = $billingInvoiceTransactionManagement->createInvoiceTransaction(array(
        'FK_BILLING_INVOICE' => $invoiceId,
        'TYPE' => BillingInvoiceTransactionManagement::TYPE_DEFAULT,
        'STAMP_CREATE' => $_POST['STAMP_CREATE'],
        'DESCRIPTION' => $_POST['DESCRIPTION'],
        'TRANSACTION_ID' => $_POST['TRANSACTION_ID'],
        'PRICE' => ((float)str_replace(',','.', $_POST['PRICE']))
    ));

    die(forward("index.php?page=invoice_view&ID_BILLING_INVOICE=".$invoice['ID_BILLING_INVOICE']));
} elseif($action == 'applycreditnote') {

    $result = $billingInvoiceTransactionManagement->applyCreditNote(array(
        'FK_BILLING_INVOICE' => $invoiceId,
        'FK_BILLING_CREDITNOTE' => $_POST['FK_BILLING_CREDITNOTE']
    ));

    if($result) {
        die(forward("index.php?page=invoice_view&ID_BILLING_INVOICE=".$invoice['ID_BILLING_INVOICE']));
    } else {
        die(forward("index.php?page=invoice_view&ID_BILLING_INVOICE=".$invoice['ID_BILLING_INVOICE']));

    }
} elseif($action == 'deletetransaction') {
    $result = $billingInvoiceTransactionManagement->deleteById($_REQUEST['ID_BILLING_INVOICE_TRANSACTION']);
    die(forward("index.php?page=invoice_view&ID_BILLING_INVOICE=".$invoice['ID_BILLING_INVOICE']));
} elseif($action == 'editinvoiceaddress') {
	$result = $billingInvoiceManagement->update($invoiceId, array(
		'ADDRESS' => $_POST['ADDRESS']
	));

    die(forward("index.php?page=invoice_view&ID_BILLING_INVOICE=".$invoice['ID_BILLING_INVOICE']));
} elseif($action == "downloadPdf") {
    $billingInvoiceManagement->streamAsPdf($invoiceId, null, false, false);
    die();
} elseif($action == "createPdf") {
    $billingInvoiceManagement->saveAsPdf($invoiceId, null, true, -1);
    die(forward("index.php?page=invoice_view&ID_BILLING_INVOICE=".$invoice['ID_BILLING_INVOICE']));
} elseif($action == "edit") {
    $mode = "edit";
    $invoiceUpdated = false;
    $arNewItems = array();
    $arRemItems = array();
    foreach ($_POST["NEW"]["NAME"] as $itemIndex => $itemName) {
        $itemQuantity = (int)$_POST["NEW"]["QUANTITY"][$itemIndex];
        $itemPrice = (float)str_replace(",", ".", $_POST["NEW"]["PRICE"][$itemIndex]);
        if (!empty($itemName) && ($itemQuantity > 0) && ($itemPrice > 0)) {
            $arNewItems[] = array(
                "DESCRIPTION"   => $itemName,
                "QUANTITY"      => $itemQuantity,
                "PRICE"         => $itemPrice
            );
        }
    }
    foreach ($invoiceItems as $itemIndex => $itemData) {
        $itemPos = $itemIndex + 1;
        // Apply changes from post parameters
        $itemUpdated = false;
        if (array_key_exists("STORNO_FULL", $_POST) && array_key_exists($itemPos, $_POST["STORNO_FULL"])) {
            $arRemItems[ $itemData["ID_BILLING_INVOICE_ITEM"] ] = false;
            $invoiceItems[$itemIndex]["STORNO_FULL"] = true;
            continue;
        }
        if (array_key_exists("STORNO_KEEP", $_POST) && array_key_exists($itemPos, $_POST["STORNO_KEEP"])) {
            $arRemItems[ $itemData["ID_BILLING_INVOICE_ITEM"] ] = true;
            $invoiceItems[$itemIndex]["STORNO_KEEP"] = true;
            continue;
        }
        if (array_key_exists("PRICE", $_POST) && array_key_exists($itemPos, $_POST["PRICE"])) {
            $invoiceItems[$itemIndex]["PRICE"] = $_POST["PRICE"][$itemPos];
            $itemUpdated = true;
        }
        if (array_key_exists("DESCRIPTION", $_POST) && array_key_exists($itemPos, $_POST["DESCRIPTION"])) {
            $invoiceItems[$itemIndex]["DESCRIPTION"] = $_POST["DESCRIPTION"][$itemPos];
            $itemUpdated = true;
        }
        if (array_key_exists("QUANTITY", $_POST) && array_key_exists($itemPos, $_POST["QUANTITY"])) {
            $invoiceItems[$itemIndex]["QUANTITY"] = $_POST["QUANTITY"][$itemPos];
            $itemUpdated = true;
        }
        // Update item
        if ($itemUpdated) {
            $billingInvoiceManagement->updateItem($itemData["ID_BILLING_INVOICE_ITEM"], $invoiceItems[$itemIndex]);
            $invoiceUpdated = true;
        }
    }
    if ((count($arRemItems) >= count($invoiceItems)) && empty($arNewItems)) {
        // No items remain! Error!
        $tpl_content->addvar("ERROR", 1);
        $tpl_content->addvar("ERROR_NO_ITEMS", 1);
    } else {
        // Valid invoice! Remove/Add items.
        if (!empty($arRemItems)) {
            foreach ($arRemItems as $invoiceItemId => $invoiceItemKeepService) {
                $billingInvoiceManagement->deleteItem($invoiceItemId, $invoiceItemKeepService);
            }
            $itemUpdated = true;
        }
        if (!empty($arNewItems)) {
            $taxId = ($invoice["TAX_EXEMPT"] ? $nar_systemsettings['MARKTPLATZ']['INVOICE_TAX_EXEMPT_TAX_ID'] : $nar_systemsettings["MARKTPLATZ"]["TAX_DEFAULT"]);
            foreach ($arNewItems as $itemIndex => $itemData) {
                $billingInvoiceManagement->addItem($invoiceId, $itemData["DESCRIPTION"], $itemData["PRICE"], $itemData["QUANTITY"], $taxId);
            }
            $itemUpdated = true;
        }
        if ($invoiceUpdated) {
            $billingInvoiceManagement->setCorrection($invoiceId);
        }

        die(forward("index.php?page=invoice_view&ID_BILLING_INVOICE=".$invoice['ID_BILLING_INVOICE']));
    }
}


$taxes = array();
foreach($invoiceItems as $key => $invoiceItem) {
    $invoiceItems[$key]['POS'] = ($key + 1);

    if(array_key_exists($invoiceItem['TAX_VALUE'], $taxes)) {
        $taxes[$invoiceItem['TAX_VALUE']]['TAX_AMOUNT'] += ($invoiceItem['TOTAL_PRICE'] - $invoiceItem['TOTAL_PRICE_NET']);
    } else {
        $taxes[$invoiceItem['TAX_VALUE']] = array();
        $taxes[$invoiceItem['TAX_VALUE']]['TAX_AMOUNT'] = ($invoiceItem['TOTAL_PRICE'] - $invoiceItem['TOTAL_PRICE_NET']);
        $taxes[$invoiceItem['TAX_VALUE']]['TAX_VALUE'] = $invoiceItem['TAX_VALUE'];
    }
}

$tranactions = $billingInvoiceTransactionManagement->fetchAllByParam(array(
    'FK_BILLING_INVOICE' => $invoiceId
));

$creditnotes =$billingCreditnoteManagement->fetchAllByParam(array(
    'FK_USER' => $invoice['FK_USER'],
    'STATUS' => BillingCreditnoteManagement::STATUS_ACTIVE
));

$tpl_content->addvar("mode", strtoupper($mode));
$tpl_content->addvar("MODE_".strtoupper($mode), 1);

$tpl_content->addvars($invoice, 'INVOICE_');
$tpl_content->addvars($invoiceUser, 'INVOICE_USER_');
$tpl_content->addlist('INVOICE_ITEMS', $invoiceItems, "tpl/de/invoice_view.item.row.htm");
$tpl_content->addlist('INVOICE_TAXES', $taxes, "tpl/de/invoice_view.tax.row.htm");
$tpl_content->addlist('TRANSACTIONS', $tranactions, "tpl/de/invoice_view.transactions.row.htm");
$tpl_content->addlist('creditnotes', $creditnotes, "tpl/de/invoice_view.creditnote_select.row.htm");
$tpl_content->addvars($invoicePaymentAdapter, 'INVOICE_PAYMENT_ADAPTER_');


$taxId = ($invoice["TAX_EXEMPT"] ? $nar_systemsettings['MARKTPLATZ']['INVOICE_TAX_EXEMPT_TAX_ID'] : $nar_systemsettings["MARKTPLATZ"]["TAX_DEFAULT"]);
$taxDefault = $db->fetch_atom("SELECT TAX_VALUE FROM `tax` WHERE ID_TAX=".(int)$taxId);
$tpl_content->addvar("TAX_DEFAULT", $taxDefault);