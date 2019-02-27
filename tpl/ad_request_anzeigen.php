<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.ad_request.php';


$adRequestManagement = AdRequestManagement::getInstance($db);
$adRequestManagement->setLangval($langval);

$adRequestId = ($ar_params[1] ? (int)$ar_params[1] : null);

$adRequest = $adRequestManagement->find($adRequestId);

if ($adRequest === false) {
	die(forward($tpl_content->tpl_uri_baseurl('404.htm')));
}

$tpl_content->addvar("isuser", ($uid > 0)?1:0);
$tpl_content->addvars($adRequest);
$nar_tplglobals['newstitle'] = $adRequest['PRODUKTNAME'];


$categoryTree = $adRequestManagement->getAdRequestCategoryJSONTree(array());

$tpl_content->addvar("CATEGORY_JSON_TREE", $categoryTree);

// User Info
$userdata = $db->fetch1("select VORNAME as USER_VORNAME, NACHNAME as USER_NACHNAME, NAME as USER_NAME, FIRMA as USER_FIRMA, CACHE as USER_CACHE, STAMP_REG as USER_STAMP_REG, LASTACTIV as USER_LASTACTIV, URL as USER_URL, STRASSE as USER_STRASSE , PLZ as USER_PLZ, ORT as USER_ORT, ID_USER as USER_ID_USER, UEBER as USER_UEBER, ROUND(RATING) as USER_lastrate,TIMESTAMPDIFF(YEAR,GEBDAT,CURDATE()) as USER_age, TEL as USER_TEL,RATING,TOP_USER as USER_TOP_USER,
                 TOP_SELLER AS USER_TOP_SELLER,
                 PROOFED AS USER_PROOFED from user where ID_USER=". $adRequest["FK_USER"]); // Userdaten lesen
$tpl_content->addvars($userdata);

require_once 'sys/lib.vendor.php';
require_once 'sys/lib.vendor.category.php';

$vendorManagement = VendorManagement::getInstance($db);
$vendorManagement->setLangval($langval);

$isUserVendor = $vendorManagement->isUserVendorByUserId($adRequest["FK_USER"]);
$tpl_content->addvar("USER_IS_VENDOR", $isUserVendor);

if($isUserVendor) {
    $tmp = $vendorManagement->fetchByUserId($adRequest["FK_USER"]);


    $vendor = $vendorManagement->fetchByVendorId($tmp['ID_VENDOR']);
    $vendorTemplate = array();
    foreach($vendor as $key=>$value) { $vendorTemplate['VENDOR_'.$key] = $value; }

    // Kategorie Liste
    $vendorTemplate['VENDOR_LOGO'] = ($vendorTemplate['VENDOR_LOGO'] != "")?'cache/vendor/logo/'.$vendorTemplate['VENDOR_LOGO']:null;
    $vendorTemplate['USER_ID_USER'] = $vendor['FK_USER'];

    $tpl_content->addvars($vendorTemplate);
}


$tpl_content->addvar("USER_HAS_JOBS", $db->fetch_atom("SELECT count(*) FROM `job` WHERE FK_AUTOR=".$adRequest["FK_USER"]));
$tpl_content->addvar("USER_HAS_NEWS", $db->fetch_atom("SELECT count(*) FROM `news` WHERE FK_AUTOR=".$adRequest["FK_USER"]." AND OK=3"));

include_once ($GLOBALS['nar_systemsettings']['SITE']['USER_PATH'].$userdata['USER_CACHE']."/".$adRequest["FK_USER"]."/useroptions.php");
$tpl_content->addvar("showcontact", perm_checkview($useroptions['LU_SHOWCONTAC']));
