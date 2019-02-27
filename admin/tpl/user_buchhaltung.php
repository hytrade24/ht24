<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path . 'sys/lib.billing.invoice.php';
require_once $ab_path . 'sys/lib.billing.billableitem.php';
require_once $ab_path . 'sys/lib.user.php';

$billingInvoiceManagement = BillingInvoiceManagement::getInstance($db);
$userManagement = UserManagement::getInstance($db);

$id_user = (int)$_REQUEST['ID_USER'];
$tpl_content->addvar("ID_USER", $id_user);
$nameuser= $db->fetch_atom("
	SELECT
		NAME
	FROM
		user
	WHERE
		ID_USER=".$id_user);
$tpl_content->addvar("NAME", $nameuser);

$tpl_content_links->addvar("FK_USER", $id_user);
$tpl_content_links->addvar("NAME_", $nameuser);

$perpage = 20; // Elemente pro Seite
$limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);

$param = array(
    'FK_USER' => $id_user,
    'ID_USER' => $id_user
);
$invoices = $billingInvoiceManagement->fetchAllByParam(array_merge($param, array(
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


$tpl_content->addvar("pager", htm_browse($numberOfInvoices, $_REQUEST['npage'], "index.php?page=".$tpl_content->vars['curpage']."&".http_build_query($param)."&npage=", $perpage));
$tpl_content->addlist('liste', $tplInvoices, 'tpl/de/user_edit.inv.htm');
$tpl_content->addvar("allinvoices", $numberOfInvoices);
$tpl_content->addvars($_GET);


$umsatz['UMSATZ_THIS'] = $db->fetch_atom("
    SELECT
        SUM(it.QUANTITY*it.PRICE*(1+(IFNULL(tax.TAX_VALUE, 0)/100)))
    FROM
      billing_invoice i
    LEFT JOIN billing_invoice_item it ON it.FK_BILLING_INVOICE = i.ID_BILLING_INVOICE
    LEFT JOIN tax tax ON tax.ID_TAX = it.FK_TAX
    WHERE
        MONTH(STAMP_CREATE) = MONTH(CURDATE()) AND i.STATUS != 2 AND i.FK_USER=".$id_user."
");
$umsatz['UMSATZ_LAST'] = $db->fetch_atom("
    SELECT
        SUM(it.QUANTITY*it.PRICE*(1+(IFNULL(tax.TAX_VALUE, 0)/100)))
    FROM
      billing_invoice i
    LEFT JOIN billing_invoice_item it ON it.FK_BILLING_INVOICE = i.ID_BILLING_INVOICE
        LEFT JOIN tax tax ON tax.ID_TAX = it.FK_TAX
    WHERE
        MONTH(STAMP_CREATE) = MONTH(DATE_SUB(CURDATE(), interval 1 MONTH)) AND i.STATUS != 2 AND i.FK_USER=".$id_user."
");

$umsatz['UMSATZ_GESAMT'] = $db->fetch_atom("
    SELECT
        SUM(it.QUANTITY*it.PRICE*(1+(IFNULL(tax.TAX_VALUE, 0)/100)))
    FROM
      billing_invoice i
    LEFT JOIN billing_invoice_item it ON it.FK_BILLING_INVOICE = i.ID_BILLING_INVOICE
        LEFT JOIN tax tax ON tax.ID_TAX = it.FK_TAX
    WHERE
        i.STATUS != 2 AND i.FK_USER=".$id_user."
");
$tpl_content->addvars($umsatz);

$billingBillableItemManagement = BillingBillableItemManagement::getInstance($db);
$billableItems = $billingBillableItemManagement->fetchAllByParam(
	array(
		"FK_USER"   =>  $id_user
	)
);
$tplBillableItems = array();
foreach($billableItems as $key => $billableItem) {
	$invoiceUser = $userManagement->fetchById($billableItem['FK_USER']);

	$billableItem['USER_NAME'] = $invoiceUser['NAME'];

	$tplBillableItems[] = $billableItem;
}
if ( count($tplBillableItems) > 0 ) {
	$tpl_content->addlist('liste_rechnungsposition', $tplBillableItems, 'tpl/'.$s_lang.'/user_buchhaltung.row.position.htm');
}
?>