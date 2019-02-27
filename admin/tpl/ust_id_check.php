<?php

include_once $ab_path . 'sys/lib.billing.invoice.taxexempt.php';

$billingInvoiceTaxExemptManagement = BillingInvoiceTaxExemptManagement::getInstance($db);

$id_user = (int)($_REQUEST['ID_USER'] ? $_REQUEST['ID_USER'] : 0);

if ($id_user) {

	$result = $billingInvoiceTaxExemptManagement->updateVatNumberValidationForUser($id_user);
	$userdata = $db->fetch1("SELECT * FROM user WHERE ID_USER = '".(int)$id_user."' ");

    if($result === false) {
        $tpl_content->addvar('error', 1);
    } else {
		$tpl_content->addvar('RESULT', $result);
        $tpl_content->addvar('ok', 1);
    }

	$tpl_content->addvar('CHECKDATE', date("Y-m-d H:i:s"));
	$tpl_content->addvar('OWN_USTID', $nar_systemsettings['MARKTPLATZ']['INVOICE_TAX_EXEMPT_USTID']);
	$tpl_content->addvars($userdata, "USERDATA_");

}
