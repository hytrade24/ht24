<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path . 'sys/lib.billing.creditnote.php';
require_once $ab_path . 'sys/lib.user.php';

$billingCreditnoteManagement = BillingCreditnoteManagement::getInstance($db);
$userManagement = UserManagement::getInstance($db);

$id_user = (int)$_REQUEST['ID_USER'];
$tpl_content->addvar("ID_USER", $id_user);
$name_=$db->fetch_atom("
	SELECT
		NAME
	FROM
		user
	WHERE
		ID_USER=".$id_user);


$tpl_content_links->addvar("NAME_", $name_);
$tpl_content_links->addvar("FK_USER", $id_user);
$tpl_content->addvar("NAME", $name_);


if(isset($_REQUEST['do']) && $_REQUEST['do'] == "add") {
    $result = $billingCreditnoteManagement->createCreditnote(array(
        'FK_USER' => $id_user,
        'DESCRIPTION' => $_POST['DESCRIPTION'],
        'PRICE' => (float)str_replace(',', '.', $_POST['PRICE']),
        'FK_TAX' => $_POST['FK_TAX'],
        'STATUS' => BillingCreditnoteManagement::STATUS_ACTIVE
    ));
    if($result) {
        $tpl_content->addvar('new', 1);
    } else {
        $tpl_content->addvar('err', 1);
        $tpl_content->addvars($_POST);
    }
} elseif(isset($_REQUEST['do']) && $_REQUEST['do'] == "delete") {
    $result = $billingCreditnoteManagement->deleteById($_REQUEST['ID_BILLING_CREDITNOTE']);

    if($result) {
        $tpl_content->addvar('del', 1);
    } else {
        $tpl_content->addvar('err', 1);
    }
}

$perpage = 20; // Elemente pro Seite
$limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);

$param = array(
    'FK_USER' => $id_user,
    'ID_USER' => $id_user
);
$creditnotes = $billingCreditnoteManagement->fetchAllByParam(array_merge($param, array(
    'LIMIT' => $perpage,
    'OFFSET' => $limit
)));
$numberOCreditnotes = $billingCreditnoteManagement->countByParam($param);
$tplCreditnotes = array();


$tpl_content->addvar("pager", htm_browse($numberOCreditnotes, $_REQUEST['npage'], "index.php?page=".$tpl_content->vars['curpage']."&".http_build_query($param)."&npage=", $perpage));
$tpl_content->addlist('liste', $creditnotes, 'tpl/de/user_billing_creditnote.row.htm');
$tpl_content->addvar("all", $numberOCreditnotes);
$tpl_content->addvar("FK_TAX", $nar_systemsettings["MARKTPLATZ"]["TAX_DEFAULT"]);
$tpl_content->addvars($_GET);

?>