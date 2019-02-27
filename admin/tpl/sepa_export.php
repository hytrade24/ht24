<?php

require_once $ab_path . 'sys/lib.billing.invoice.php';
require_once $ab_path . 'sys/lib.user.php';
require_once $ab_path . 'sys/lib.payment.adapter.php';
require_once $ab_path . 'sys/payment/PaymentFactory.php';

require_once $ab_path.'lib/sepa/Ebiz_SEPA.php';
require_once $ab_path.'lib/sepa/Util/GermanHolidays.php';

$error = 0;
$error_msg = array();
$billingInvoiceManagement = BillingInvoiceManagement::getInstance($db);
$userManagement = UserManagement::getInstance($db);
$arPaymentAdapters = $db->fetch_nar("SELECT ID_PAYMENT_ADAPTER, ADAPTER_NAME FROM `payment_adapter`");

$query = 'SELECT * 
					FROM `option` 
					WHERE `plugin` = "MARKTPLATZ"
					AND `typ` = "LASTSCHRIFT_VERIFICATION"';

$option_result = $db->fetch_table( $query );

$tpl_content->addvar('LASTSCHRIFT_VERIFICATION_VALUE', $option_result[0]['value'] );

if ( isset($_GET["download"]) ) {
	if ( $_GET["download"] == "true" ) {

		$folder_path = dirname(__FILE__)."/../../filestorage";
		$sepa_exports = "/sepa_exports";
		$file_path = $folder_path.$sepa_exports."/".$_GET["export"].".xml";

		if ( file_exists($file_path) ) {
			header('Content-Type: text/xml; charset=utf-8');
			header('Content-Disposition: attachment; filename='.$_GET['export'].'.xml');
			echo file_get_contents($file_path);
			die();
		}
	}
}
if ( isset($_GET["export_id"]) && $_GET['mark_invoice_as_paid'] == "1" ) {
	$export_id = intval( $_GET["export_id"] );
	$mark_invoice_as_paid = $_GET["mark_invoice_as_paid"];
	$stamp_pay = null;
	if ( $mark_invoice_as_paid == "1" ) {
		$stamp_pay = date("Y-m-d");
	}

	$sql = 'SELECT *
    FROM billing_invoice_export a
    INNER JOIN billing_invoice b
    ON a.EXPORT_NO = '.$export_id.'
    AND b.FK_BILLING_INVOICE_EXPORT = a.ID_BILLING_INVOICE_EXPORT';

	$result = $db->fetch_table( $sql );

	$id_bill_inv_exp = '';

	foreach ( $result as $row ) {
		$id_bill_inv_exp = $row["ID_BILLING_INVOICE_EXPORT"];
		$invoice = $billingInvoiceManagement->fetchById( $row["ID_BILLING_INVOICE"] );
		$billingInvoiceManagement->setStatus(
			$row["ID_BILLING_INVOICE"],
			1
		);
	}

	$arr_billing_invoice_export = array(
		"ID_BILLING_INVOICE_EXPORT"     =>  $id_bill_inv_exp,
		"EXPORT_NO"                     =>  $export_id,
		"MARK_AS_PAID"                  =>  1,
		"STAMP_MARK_AS_PAID"            =>  date("Y-m-d H:i:s")
	);

	$db->update("billing_invoice_export", $arr_billing_invoice_export);

}

$param['STATUS'] = 0;
$param['FK_BILLING_INVOICE_EXPORT'] = 0;

$sepaday_option = $db->fetch_atom("SELECT value
    FROM `option`
    WHERE `plugin` = 'MARKTPLATZ' AND `typ` = 'SEPADAY'"
);

$paymentAdapterManagement = PaymentAdapterManagement::getInstance($db);
$invoicePaymentAdapter = $paymentAdapterManagement->fetchByAdapterName("DirectDebit");
$paymentAdapterConfiguration2 = array(
	'CONFIG' => $paymentAdapterManagement->fetchConfigurationById(
		$invoicePaymentAdapter['ID_PAYMENT_ADAPTER']
	)
);


$payment_adapter = $paymentAdapterManagement->fetchByAdapterName("DirectDebit");
$paymentAdapterConfiguration = array(
	'CONFIG' => $paymentAdapterManagement->fetchConfigurationById($payment_adapter["ID_PAYMENT_ADAPTER"])
);
$paymentAdapter = Payment_PaymentFactory::factory(
	"DirectDebit",
	$paymentAdapterConfiguration
);


if ( isset($_GET['xml']) ) {
	$invoices = $billingInvoiceManagement->fetchAllByParam($param);
	$total = 0;
	$invoice_count = 0;

	$exportedInvoicesId = array();
	$xmlArr = array();
	$ebiz_sepa = new Ebiz_SEPA();
	$msgId = time();

	$invoicePaymentAdapter = $paymentAdapterManagement->fetchByAdapterName("DirectDebit");
	$paymentAdapterConfiguration = array(
		'CONFIG' => $paymentAdapterManagement->fetchConfigurationById(
			$invoicePaymentAdapter['ID_PAYMENT_ADAPTER']
		)
	);

	$currentDate = new dateTime();
	$germanHolidays = new \Scs\Common\BillingBundle\Util\GermanHolidays();
	$currentDate->add(new DateInterval('P'.$sepaday_option.'D'));

	while ( $germanHolidays->isWeekend( $currentDate ) ||
	        $germanHolidays->isHoliday( $currentDate ) ) {
		$currentDate->add(new DateInterval('P1D'));
	}

	try {
		$ebiz_sepa->initiatorDetails(
			$paymentAdapterConfiguration2['CONFIG']['CREDITORID'],
			$currentDate->format("Y-m-d"),
			$paymentAdapterConfiguration2['CONFIG']['Recipient'],
			$paymentAdapterConfiguration2['CONFIG']['BIC'],
			$paymentAdapterConfiguration2['CONFIG']['IBAN'],
			$msgId
		);
		$ebiz_sepa->setInitiator();
	}
	catch (Exception $e) {
		$error = 1;
		array_push(
			$error_msg,
			'Marktplatz: ' . $e->getMessage()
		);
	}

	$paymentAdapterManagement = PaymentAdapterManagement::getInstance($db);

	$last_date= null;
	$first_date = null;
	foreach($invoices as $key => $invoice) {
		$invoiceUser = $userManagement->fetchById($invoice['FK_USER']);
		$invoices[$key]['USER_NAME'] = $invoiceUser['NAME'];

		if ($arPaymentAdapters[$invoice["FK_PAYMENT_ADAPTER"]] == "DirectDebit") {

			//$payment_adapter_config = unserialize($invoiceUser['PAYMENT_ADAPTER_CONFIG']);
			$paymentAdapter->init(array(
				'FK_USER'   =>  $invoice['FK_USER']
			));
			$payment_adapter_config = $paymentAdapter->getUserConfiguration("directdebit");

			//checking is account is verified or not
			if ( isset($payment_adapter_config["Verified"]) ) {

				$is_block = false;
				if ( isset($payment_adapter_config["Block_Account"]) ) {
					if ( $payment_adapter_config["Block_Account"] == true ) {
						$is_block = true;
					}
				}

				if ( $payment_adapter_config["Verified"] == "1" && $is_block == false ) {
					$invoices[$key]['IBAN'] = $payment_adapter_config["iban"];
					$invoices[$key]['BIC'] = $payment_adapter_config["bic"];

					$dateOfSign = $payment_adapter_config['VerifiedDate'];
					try {
						$ebiz_sepa->paymentDetails(
							$invoices[$key]['BIC'],
							$invoices[$key]['IBAN'],
							$invoices[$key]['USER_NAME'],
							$paymentAdapterConfiguration['CONFIG']['PREFIX']."-".$invoices[$key]['FK_USER']."-".$invoices[$key]['ID_BILLING_INVOICE'],
							sprintf("%0.2f",(double)$invoice["REMAINING_PRICE"]),
							$invoices[$key]['ID_BILLING_INVOICE'],
							$invoices[$key]['FK_USER'],
							$dateOfSign//$payment_adapter_config['directdebit']['VerifiedDate']
						);
						$ebiz_sepa->addPayment();

						$invoice_count++;
						$total += sprintf("%0.2f",(double)$invoice["REMAINING_PRICE"]);
						if ( is_null($last_date) ) {
							$last_date = $invoices[$key]["STAMP_CREATE"];
						}
						$first_date = $invoices[$key]["STAMP_CREATE"];
						$exportedInvoicesId[] = $invoices[$key]['ID_BILLING_INVOICE'];

					}
					catch (Exception $e) {
						$error = 1;
						array_push(
							$error_msg,
							"<a href='index.php?page=invoice_view&ID_BILLING_INVOICE=".$invoice["ID_BILLING_INVOICE"]."'>Rechnung # ".$invoice["ID_BILLING_INVOICE"]."</a> nicht enthalten : error \"" . $e->getMessage() . "\""
						);
					}
				}
			}
		}
	}
	if ( count($exportedInvoicesId) != 0 ) {

		$stamp_date = date("Y-m-d H:i:s", $msgId);
		$id_billing_invoice_export= false;

		$data = array(
			"EXPORT_NO"         =>  $msgId,
			"TOTAL_INVOICES"    =>  $invoice_count,
			"TOTAL_MONEY"       =>  $total,
			"FIRST_DATE"        =>  $first_date,
			"LAST_DATE"         =>  $last_date,
			"MARK_AS_PAID"      =>  $_GET["MARK_AS_PAID"],
			"STAMP"             =>  $stamp_date
		);
		$folder_path = dirname(__FILE__)."/../../filestorage";
		$sepa_exports = "/sepa_exports";
		$id_billing_invoice_export = $db->update('billing_invoice_export',$data);
		if ( $id_billing_invoice_export ) {
			foreach ( $exportedInvoicesId as $invoice_id ) {
				$data = array(
					'ID_BILLING_INVOICE'        => $invoice_id,
					'FK_BILLING_INVOICE_EXPORT' => $id_billing_invoice_export
				);
				if ( isset($_GET["MARK_AS_PAID"]) ) {
					if ( $_GET["MARK_AS_PAID"] == "1" ) {
						$data["STATUS"] = 1;
						$data["STAMP_PAY"] = date("Y-m-d", $msgId);
					}
				}
				$db->update('billing_invoice', $data);
			}
		}

		if ( !file_exists($folder_path . $sepa_exports) ) {
			mkdir($folder_path . $sepa_exports, 0777, true);
		}

		$file_name = $folder_path.$sepa_exports."/".$msgId.".xml";
		$output = fopen($file_name, 'w');
		$xml = $ebiz_sepa->execute();
		fwrite($output,$xml);
		fclose( $output );

		$tpl_content->addvar("download_button_click",$msgId);
		$tpl_content->addvar("download_button_click_check",true);
	}
}
$invoices = $billingInvoiceManagement->fetchAllByParam($param);

$total = 0;
$invoice_count = 0;
$possible_first_date= null;
$possible_last_date= null;

foreach ( $invoices as $key => $invoice ) {
	$invoiceUser = $userManagement->fetchById($invoice['FK_USER']);
	$invoices[$key]['USER_NAME'] = $invoiceUser['NAME'];

	if ($arPaymentAdapters[$invoice["FK_PAYMENT_ADAPTER"]] == "DirectDebit") {

		$paymentAdapter->init(array(
			'FK_USER'   =>  $invoice['FK_USER']
		));

		$payment_adapter_config = $paymentAdapter->getUserConfiguration("directdebit");

		//checking is account is verified or not
		if ( isset($payment_adapter_config["Verified"]) ) {

			$is_block = false;
			if ( isset($payment_adapter_config["Block_Account"]) ) {
				if ( $payment_adapter_config["Block_Account"] == true ) {
					$is_block = true;
				}
			}

			if ( $payment_adapter_config["Verified"] == "1" ) {
				if ( is_null($possible_last_date) ) {
					$possible_last_date = $invoice["STAMP_CREATE"];
				}
				$possible_first_date = $invoice["STAMP_CREATE"];
				$total += sprintf("%0.2f",(double)$invoice["REMAINING_PRICE"]);
				$invoice_count++;
			}
		}
	}

}

$prev_exported_xmls = $billingInvoiceManagement->exportedSEPAXml();

if ( $_GET["MARK_AS_PAID"] ) {
	$tpl_content->addvar("MARK_AS_PAID_".$_GET["MARK_AS_PAID"],true);
}
else {
	$tpl_content->addvar("MARK_AS_PAID_0",true);
}

//$tpl_content->addvar("PREV_EXPORTED_SEPA_XML", $prev_exported_xml);
$tpl_content->addvar("POSSIBILITY_FIRST_DATE",$possible_first_date);
$tpl_content->addvar("POSSIBILITY_LAST_DATE",$possible_last_date);
$tpl_content->addvar("SEPADAY", $sepaday_option);
$tpl_content_links->addvar("SEPADAY", $sepaday_option);
$tpl_content->addvar("SEPA_CREDITOR_ID", $paymentAdapterConfiguration2['CONFIG']['CREDITORID']);
$tpl_content_links->addvar("SEPA_CREDITOR_ID", $paymentAdapterConfiguration2['CONFIG']['CREDITORID']);
$tpl_content->addlist('liste', $prev_exported_xmls, 'tpl/'.$s_lang.'/sepa_export.row.htm');
$tpl_content->addvar("TOTAL_NEW_EXPORT", $total);
$tpl_content->addvar("INVOICE_COUNT", $invoice_count);

$tpl_content->addvar("error",$error);
$tpl_content->addvar("error_msg",implode("<br />",$error_msg));