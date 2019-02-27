<?php
require_once dirname(__FILE__).'/../../../../../api.php';

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

if (!preg_match('/\.ipayment\.de$/', gethostbyaddr($_SERVER["REMOTE_ADDR"]))) {
    exit();
}


if ($_POST["ret_status"] != "SUCCESS") {
    exit();
}

if (count($_POST) > 0) {
    $params = $_POST;
} else {
    $params = array();
}

$invoiceId = (int)$_POST['shopper_id'];
$invoice = $billingInvoiceManagement->fetchById($invoiceId);

$invoicePaymentAdapter = $paymentAdapterManagement->fetchById($invoice['FK_PAYMENT_ADAPTER']);
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
        //'CURRENCY' => 'EUR'
    );

    $paymentAdapter->init($paymentObject);
} else {
    $validPaymentAdapterIsSelected = false;
}

if($paymentAdapter->verifyPayment()) {
	$billingInvoiceTransactionManagement->createInvoiceTransaction(array(
		'FK_BILLING_INVOICE' => $invoiceId,
		'TYPE' => BillingInvoiceTransactionManagement::TYPE_DEFAULT,
		'DESCRIPTION' => $invoicePaymentAdapter['NAME']. ': '. $paymentObject['DESCRIPTION'],
		'TRANSACTION_ID' => $_POST["ret_trx_number"],
		'PRICE' => $paymentObject['TOTAL_PRICE']
	));
}


die();