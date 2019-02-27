<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path . 'sys/lib.billing.billableitem.php';
require_once $ab_path . 'sys/lib.user.php';
require_once $ab_path . 'sys/lib.billing.notification.php';

$billingBillableItemManagement = BillingBillableItemManagement::getInstance($db);
$userManagement = UserManagement::getInstance($db);

if (array_key_exists("done", $_REQUEST)) {
  $tpl_content->addvar("done_".$_REQUEST["done"], 1);
}

if ( isset($_POST["action"]) ) {
	if ( $_POST["action"] == "stornieren" ) {

		switch ($_POST["performances"]) {
			default:
			case "0"://accept_and_cancel
				$billingBillableItemManagement->deleteBillableItem(
					$_POST["ID_BILLING_BILLABLEITEM"],
					false
				);
			case "1"://accept_and_keep
				$billingBillableItemManagement->deleteBillableItem(
					$_POST["ID_BILLING_BILLABLEITEM"],
					true
				);
		}

	}
}

$perpage = 20; // Elemente pro Seite
$limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);

$param = array();
if(isset($_GET['ID_BILLING_BILLABLEITEM']) && $_GET['ID_BILLING_BILLABLEITEM'] !== "") $param['ID_BILLING_BILLABLEITEM'] = $_GET['ID_BILLING_BILLABLEITEM'];
if(isset($_GET['OLDER_AS']) && $_GET['OLDER_AS'] !== "") {
    $param['STAMP_CREATE_AFTER'] = $_GET['OLDER_AS'];
    $tpl_content->addvar('OLDER_AS_'.str_replace(" ", "_", $_GET['OLDER_AS']), true);
}
if(isset($_GET['FK_USER']) && $_GET['FK_USER'] !== "") $param['FK_USER'] = $_GET['FK_USER'];
if(isset($_GET['STATUS']) && $_GET['STATUS'] !== "") {
    $param['STATUS'] = $_GET['STATUS'];
    $tmpStatusArray = array('OPEN', 'PAID', 'CANCELED');
    $tpl_content->addvar('STATUS_'.$tmpStatusArray[$param['STATUS']], true);
}

if ( !isset($_GET['SORT']) ) {
	$param['SORT'] = 'STAMP_CREATE';
	$param['SORT_DIR'] = 'DESC';
}

switch($_GET['SORT']) {
    case 'SORT_STAMP_CREATE_DESC': $param['SORT'] = 'STAMP_CREATE'; $param['SORT_DIR'] = 'DESC'; break;
    case 'SORT_STAMP_CREATE_ASC': $param['SORT'] = 'STAMP_CREATE'; $param['SORT_DIR'] = 'ASC'; break;
    case 'SORT_ID_BILLING_BILLABLEITEM_DESC': $param['SORT'] = 'ID_BILLING_BILLABLEITEM'; $param['SORT_DIR'] = 'DESC'; break;
    case 'SORT_ID_BILLING_BILLABLEITEM_ASC': $param['SORT'] = 'ID_BILLING_BILLABLEITEM'; $param['SORT_DIR'] = 'ASC'; break;
}
$tpl_content->addvar('SORT_'.$param['SORT'].'_'.$param['SORT_DIR'], true);

$billableItems = $billingBillableItemManagement->fetchAllByParam(array_merge($param, array(
    'LIMIT' => $perpage,
    'OFFSET' => $limit
)));
$numberOfBillableItems = $billingBillableItemManagement->countByParam($param);
$tplBillableItems = array();

foreach($billableItems as $key => $billableItem) {
    $invoiceUser = $userManagement->fetchById($billableItem['FK_USER']);

    $billableItem['USER_NAME'] = $invoiceUser['NAME'];

    $tplBillableItems[] = $billableItem;
}

$tpl_main->addvar('INVOICE_DAYS_AUTOMATIC_BILLING', $nar_systemsettings['MARKTPLATZ']['INVOICE_DAYS_AUTOMATIC_BILLING']);
$tpl_content->addvar("pager", htm_browse($numberOfBillableItems, $_REQUEST['npage'], "index.php?page=".$tpl_content->vars['curpage']."&".http_build_query($param)."&npage=", $perpage));
$tpl_content->addlist('liste', $tplBillableItems, 'tpl/de/billing_billableitem.row.htm');
$tpl_content->addvar("allinvoices", $numberOfBillableItems);
$tpl_content->addvars($_GET);

?>