<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.vendor.php';
require_once 'sys/lib.vendor.category.php';

$userId = $uid;
$vendorManagement = VendorManagement::getInstance($db);
$vendorCategoryManagement = VendorCategoryManagement::getInstance($db);

$vendor = $vendorManagement->fetchByUserId($userId);
$vendorId = $vendor['ID_VENDOR'];

$vendorTemplate = array();

foreach($vendor as $key => $value) {
    // für Template aufbereiten
    if(!in_array($key, array("ID_VENDOR", "FK_USER"))) {
        $vendorTemplate["VENDOR_".$key] = $value;
    } else {
        $vendorTemplate[$key] = $value;
    }
}

// zusätzliche Informationen über den User
$user = $db->fetch1("SELECT * FROM user WHERE ID_USER = '".mysql_real_escape_string($userId)."'");

// Sprachrelevante Felder
$languageSelection = $db->fetch_table("SELECT * FROM lang WHERE B_PUBLIC = 1");

$tpl_content->addlist("searchWordLanguageHeader", $languageSelection, $ab_path.'tpl/'.$s_lang.'/my-vendor-searchword.lang.header.htm');
$tpl_content->addlist("searchWordLanguageBody", $languageSelection, $ab_path.'tpl/'.$s_lang.'/my-vendor-searchword.lang.body.htm');

// Template Ausgabe
$tpl_content->addvars($vendorTemplate);
$tpl_content->addvars($user);