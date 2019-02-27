<?php
/* ###VERSIONSBLOCKINLCUDE### */

global $ab_path, $langval, $nar_systemsettings;
require_once $ab_path . 'sys/lib.vendor.php';

$isSearch = false;

$vendorManagement = VendorManagement::getInstance($db);
$vendorManagement->setLangval($langval);


// Aktion: Anzeige freischalten/bestätigen
if (isset($_REQUEST["confirm_user"])) {
    $id_vendor = (int)$_REQUEST["confirm"];
    $id_user = (int)$_REQUEST["confirm_user"];
    header('Content-type: application/json');
    die(json_encode(array(
        "success"	=> $vendorManagement->adminAccept($id_vendor) &&
            $vendorManagement->adminAcceptUser($id_user)
    )));
}
if (isset($_REQUEST["confirm"])) {
    $id_vendor = (int)$_REQUEST["confirm"];
    header('Content-type: application/json');
    die(json_encode(array(
        "success"	=> $vendorManagement->adminAccept($id_vendor)
    )));
}
// Aktion: Anzeige "ablehnen"
if (isset($_REQUEST["decline_user"])) {
    $id_vendor = (int)$_REQUEST["decline"];
    header('Content-type: application/json');
    die(json_encode(array(
        "success"	=> $vendorManagement->adminDecline($id_vendor, $_REQUEST["REASON"]) &&
            $vendorManagement->adminDeclineUser((int)$_REQUEST["decline_user"], $_REQUEST["REASON"])
    )));
}
if (isset($_REQUEST["decline"])) {
    $id_vendor = (int)$_REQUEST["decline"];
    header('Content-type: application/json');
    die(json_encode(array(
        "success"	=> $vendorManagement->adminDecline($id_vendor, $_REQUEST["REASON"])
    )));
}

// pager
$perpage = 20; // Elemente pro Seite
$limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage'] = 1) -1) * $perpage);

$ar_search = array(
    'LIMIT' => $perpage,
    'OFFSET' => $limit
);
if (isset($_REQUEST['SEARCH'])) {
    if (!empty($_REQUEST['ID_VENDOR'])) {
        $ar_search['ID_VENDOR'] = $_REQUEST['ID_VENDOR'];
    }
    if (!empty($_REQUEST['NAME_'])) {
        $ar_search['NAME_'] = $_REQUEST['NAME_'];
    }
    if (!empty($_REQUEST['FK_USER'])) {
        $ar_search['FK_USER'] = $_REQUEST['FK_USER'];
    }
    if (!empty($_REQUEST['TEXT'])) {
        $ar_search['SEARCHVENDOR'] = $_REQUEST['TEXT'];
        $ar_search['TEXT'] = $_REQUEST['TEXT'];
    }
    if (isset($_REQUEST['MODERATED']) && ($_REQUEST['MODERATED'] != "")) {
        $ar_search['MODERATED'] = $_REQUEST['MODERATED'];
        $tpl_content->addvar("SEARCH_MODERATED_SET", 1);
    }
    if (isset($_REQUEST['STATUS']) && ($_REQUEST['STATUS'] != "")) {
        $ar_search['STATUS'] = $_REQUEST['STATUS'];
        $tpl_content->addvar("SEARCH_STATUS_SET", 1);
    }
    $tpl_content->addvars($ar_search, "SEARCH_");
    $isSearch = true;
}

$vendorList = $vendorManagement->fetchAllByParam($ar_search, null);
$numberOfVendors = $vendorManagement->countByParam($ar_search, null);

foreach ($vendorList as $vendorIndex => $vendorDetail) {
    $vendorList[$vendorIndex]['LOGO'] = ($vendorDetail['LOGO'] != "" ? 'cache/vendor/logo/'.$vendorDetail['LOGO'] : null);
}

if ($isSearch) {
	$tpl_content->addvar("SEARCH_RESULT", 1);
}

$tpl_content->addvar("pager", htm_browse($numberOfVendors, $_REQUEST['npage'], "index.php?page=".$tpl_content->vars['curpage']."&npage=", $perpage));
$tpl_content->addlist('liste', $vendorList, 'tpl/' . $s_lang .'/vendor.row.htm');
$tpl_content->addvar("all", $numberOfVendors);

$tpl_content->addvar("MODERATE_VENDORS", $nar_systemsettings["MARKTPLATZ"]["MODERATE_VENDORS"]);