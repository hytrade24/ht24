<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path . 'sys/lib.billing.invoice.php';
require_once $ab_path . 'sys/lib.billing.invoice.item.php';
require_once $ab_path . 'sys/lib.billing.invoice.transaction.php';
require_once $ab_path . 'sys/lib.user.php';
require_once $ab_path . 'sys/lib.payment.adapter.php';
require_once $ab_path . 'sys/payment/PaymentFactory.php';

$billingInvoiceManagement = BillingInvoiceManagement::getInstance($db);
$billingInvoiceItemManagement = BillingInvoiceItemManagement::getInstance($db);
$billingInvoiceTransactionManagement = BillingInvoiceTransactionManagement::getInstance($db);
$paymentAdapterManagement = PaymentAdapterManagement::getInstance($db);
$userManagement = UserManagement::getInstance($db);

$invoiceId = ((int)$ar_params[1] > 0 ? (int)$ar_params[1] : $_REQUEST["ID_BILLING_INVOICE"]);
$action = (!empty($ar_params[2]) ? $ar_params[2] : $_REQUEST["action"]);

$invoice = $billingInvoiceManagement->fetchById($invoiceId);
$invoiceItems = $billingInvoiceItemManagement->fetchAllByParam(
	array(
		'FK_BILLING_INVOICE' => $invoiceId,
		'BILLING_CANCEL_CHECK' => '1'
	)
);

if($invoice['FK_USER'] != $uid) {
    die("error: user not allowed");
}

$paymentAdapters = $paymentAdapterManagement->fetchAllByParam(array('STATUS' => PaymentAdapterManagement::STATUS_ENABLED));
$invoicePaymentAdapter = $paymentAdapterManagement->fetchById($invoice['FK_PAYMENT_ADAPTER']);
if (($invoicePaymentAdapter === null) && !empty($paymentAdapters)) {
    $invoicePaymentAdapter = $paymentAdapters[0];
}

// use default adapter
if ($invoicePaymentAdapter['STATUS'] == PaymentAdapterManagement::STATUS_DISABLED) {
    $invoicePaymentAdapter = $paymentAdapterManagement->fetchById($nar_systemsettings['MARKTPLATZ']['INVOICE_STD_PAYMENT_ADAPTER']);
}

if($invoicePaymentAdapter != null && $invoicePaymentAdapter['STATUS'] == PaymentAdapterManagement::STATUS_ENABLED) {
    $paymentAdapterConfiguration = array(
        'CONFIG' => $paymentAdapterManagement->fetchConfigurationById($invoicePaymentAdapter['ID_PAYMENT_ADAPTER'])
    );

    /** @var Payment_Adapter_PaymentAdapterInterface $paymentAdapter  */
    $paymentAdapter = Payment_PaymentFactory::factory($invoicePaymentAdapter['ADAPTER_NAME'], $paymentAdapterConfiguration);

    $paymentObject = array(
        'TYPE' => 'INVOICE',
        'FK_USER' => $uid,
        'DATA' => array(
            'INVOICE' => $invoice,
            'USER' => $user
        ),
        'DESCRIPTION' => 'Rechnung Nr. '.$invoice['ID_BILLING_INVOICE'],
        'TOTAL_PRICE' => $invoice['TOTAL_PRICE'],
        'TOTAL_PRICE_NET' => $invoice['TOTAL_PRICE_NET'],
        'REMAINING_PRICE' => $invoice['REMAINING_PRICE'],
        'PAID_PRICE' => $invoice['PAID_PRICE'],
        //'CURRENCY' => 'EUR',
        'ITEMS' => null,
        'PAYURL' => $tpl_content->tpl_uri_action_full('invoice,'.$invoiceId.',pay'),
        'RETURNURL' => $tpl_content->tpl_uri_action_full('invoice,'.$invoiceId.',verify'),
        'SUCCESSURL' => $tpl_content->tpl_uri_action_full('invoice,'.$invoiceId.',success'),
        'CANCELURL' => $tpl_content->tpl_uri_action_full('invoice,'.$invoiceId.',cancel')

    );

    if($invoiceItems != null) {
        $paymentObjectItems = array();
        foreach($invoiceItems as $key => $invoiceItem) {
            $paymentObjectItems[] = array(
                'DESCRIPTION' => $invoiceItem['DESCRIPTION'],
                'QUANTITY' => $invoiceItem['QUANTITY'],
                'TAX_VALUE' => $invoiceItem['TAX_VALUE'],
                'TOTAL_PRICE' => $billingInvoiceItemManagement->getInvoiceItemTotalPrice($invoiceItem['ID_BILLING_INVOICE_ITEM']),
                'TOTAL_PRICE_NET' => $billingInvoiceItemManagement->getInvoiceItemTotalPrice($invoiceItem['ID_BILLING_INVOICE_ITEM'], true)
            );
        }
        $paymentObject['ITEMS'] = $paymentObjectItems;
    }

    $paymentAdapter->init($paymentObject);
} else {
    $validPaymentAdapterIsSelected = false;
    $tpl_content->addvar("INVALID_PAYMENT_ADAPTER", true);
}

if($action == 'pay') {
    if($paymentAdapter == null) {
        die(forward('/my-pages/invoice,'.$invoiceId.'.htm'));
    }

    $url = $paymentAdapter->doPayment();
    if ($url !== false) {
        $paymentObject = $paymentAdapter->getPaymentObject();
        if (!empty($paymentObject['TRANSACTION_ID'])) {
            // Update transaction ID
            $billingInvoiceManagement->update($invoiceId, array("TRANSACTION_ID" => $paymentObject['TRANSACTION_ID']));
        }
    	die(forward($url));
    } else {
    	// TODO: Fehler!
        $prepareOutput = $paymentAdapter->prepare();
        $tpl_content->addvar('PAYMENT_ADAPTER_PREPARE_OUTPUT', $prepareOutput);
    }
} elseif($action == 'verify') {
	$result = $paymentAdapter->verifyPayment();
	eventlog("info", "debug: ".$result);
    if ($result == Payment_Adapter_AbstractPaymentAdapter::PAYMENT_RESULT_SUCCESS) {
    	$paymentObject = $paymentAdapter->getPaymentObject();
        $transCount = (int)$billingInvoiceTransactionManagement->countByParam(array("TRANSACTION_ID" => $paymentObject['TRANSACTION_ID']));
        if ($transCount === 0) {
            $billingInvoiceTransactionManagement->createInvoiceTransaction(array(
                'FK_BILLING_INVOICE' => $invoiceId,
                'TYPE' => BillingInvoiceTransactionManagement::TYPE_DEFAULT,
                'DESCRIPTION' => $invoicePaymentAdapter['NAME']. ': '. $paymentObject['DESCRIPTION'],
                'TRANSACTION_ID' => $paymentObject['TRANSACTION_ID'],
                'PRICE' => $paymentObject['TOTAL_PRICE']
            ));
        }
        die(forward('/my-pages/invoice,'.$invoiceId.',success.htm'));
    } else if ($result == Payment_Adapter_AbstractPaymentAdapter::PAYMENT_RESULT_PENDING) {
    	$paymentObject = $paymentAdapter->getPaymentObject();
        $billingInvoiceManagement->update($invoiceId, array("TRANSACTION_ID" => $paymentObject['TRANSACTION_ID']));
        die(forward('/my-pages/invoice,'.$invoiceId.',pending.htm'));
    } else {
        $billingInvoiceManagement->update($invoiceId, array("TRANSACTION_ID" => null));
        $tpl_content->addvar('ERROR', true);
    }
} elseif($action == 'success') {
    $successOutput = $paymentAdapter->successPayment();
    $tpl_content->addvar('PAYMENT_ADAPTER_SUCCESS_OUTPUT', $successOutput);
} elseif($action == 'pending') {
    $pendingOutput = $paymentAdapter->pendingPayment();
    $tpl_content->addvar('PAYMENT_ADAPTER_PENDING_OUTPUT', $pendingOutput);
} elseif($action == 'cancel') {
    $cancelOutput = $paymentAdapter->cancelPayment();
    $tpl_content->addvar('PAYMENT_ADAPTER_CANCEL_OUTPUT', $cancelOutput);
} elseif($action == 'setadapter') {
    if(isset($_POST['PAYMENT_ADAPTER']) && $_POST['PAYMENT_ADAPTER'] != "") {
        if($invoice['STATUS'] == 0) {
            $billingInvoiceManagement->update($invoiceId, array(
                'FK_PAYMENT_ADAPTER' => $_POST['PAYMENT_ADAPTER']
            ));
        }
        die();
    }
} else {
    if ($paymentAdapter) {
        $prepareOutput = $paymentAdapter->prepare();
        $tpl_content->addvar('PAYMENT_ADAPTER_PREPARE_OUTPUT', $prepareOutput);
    }
}


// Payment Adapter
foreach($paymentAdapters as $key => $tplPaymentAdapter) {
    $paymentAdapters[$key]['CURRENT_PAYMENT_ADAPTER'] = $invoice['FK_PAYMENT_ADAPTER'];
}
$tpl_content->addlist('PAYMENT_ADAPTER', $paymentAdapters, "tpl/".$s_lang."/invoice.payment_adapter_row.htm");

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

$tpl_content->addvars($invoice, 'INVOICE_');
$tpl_content->addvars($userManagement->fetchById($invoice['FK_USER']), 'INVOICE_USER_');
$tpl_content->addvars($invoicePaymentAdapter, 'INVOICE_PAYMENT_ADAPTER_');
$tpl_content->addlist('INVOICE_ITEMS', $invoiceItems, "tpl/".$s_lang."/invoice.invoice_item.row.htm");
$tpl_content->addlist('INVOICE_TAXES', $taxes, "tpl/".$s_lang."/invoice.invoice_tax.row.htm");
$tpl_content->addlist('TRANSACTIONS', $tranactions, "tpl/".$s_lang."/invoice.transactions.row.htm");




