<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.vendor.php';
require_once 'sys/lib.vendor.category.php';

$userId = $uid;
$vendorManagement = VendorManagement::getInstance($db);
$vendorCategoryManagement = VendorCategoryManagement::getInstance($db);

$vendor = $vendorManagement->fetchByUserId($userId);
$vendorId = $vendor['ID_VENDOR'];

if (!empty($ar_params[1])) {
    $tpl_content->addvar("NOTICE_".strtoupper($ar_params[1]), 1);
}

if(isset($_POST) && $_POST['DO'] == 'SAVE') {
    $_POST['STATUS'] = $vendor['STATUS'];
    $saveResult = $vendorManagement->saveVendorByUserId($_POST, $userId);
    if ($saveResult) {
        die(forward($tpl_content->tpl_uri_action("my-vendor-description,success")));
    }
    /*
    $tpl_content->addvar("DO_SAVE", true);
    $tpl_content->addvar("SAVE_RESULT", $saveResult);
    */
    $vendor = $vendorManagement->fetchByUserId($userId);
}

$vendorTemplate = array();

foreach($vendor as $key => $value) {
    // für Template aufbereiten
    if(!in_array($key, array("ID_VENDOR", "FK_USER"))) {
        $vendorTemplate["VENDOR_".$key] = $value;
    } else {
        $vendorTemplate[$key] = $value;
    }
}
$vendorTemplate['VENDOR_LOGO'] = ($vendorTemplate['VENDOR_LOGO'] != "")?'cache/vendor/logo/'.$vendorTemplate['VENDOR_LOGO']:null;

// Kategorien
$selectedCategories = $vendorCategoryManagement->fetchAllVendorCategoriesByVendorId($vendorId);
$preSelectedNodes = array();
foreach($selectedCategories as $key => $selectedCategory) {
    $preSelectedNodes[] = $selectedCategory['FK_KAT'];
}

$categoryJSONTree = $vendorCategoryManagement->getVendorCategoryJSONTree($preSelectedNodes);
$tpl_content->addvar("CATEGORY_JSON_TREE", $categoryJSONTree);
$tpl_content->addvar("CATEGORY_TREE_MAX_SELECTS", VendorCategoryManagement::MAX_CATEGORY_PER_USER);



// zusätzliche Informationen über den User
$user = $db->fetch1("SELECT * FROM user WHERE ID_USER = '".mysql_real_escape_string($userId)."'");


// Sprachrelevante Felder
$languageSelection = $db->fetch_table("SELECT * FROM lang WHERE B_PUBLIC = 1");

foreach($languageSelection as $key=>$value) {
    $languageSelection[$key]['VENDOR_DESCRIPTION'] = $vendorManagement->fetchVendorDescriptionByLanguage($vendor['ID_VENDOR'], $value['BITVAL']);
}

$tpl_content->addlist("languageHeader", $languageSelection, $ab_path.'tpl/'.$s_lang.'/my-vendor.lang.header.htm');
$tpl_content->addlist("languageBody", $languageSelection, $ab_path.'tpl/'.$s_lang.'/my-vendor.lang.body.htm');
$tpl_content->addlist("searchWordLanguageHeader", $languageSelection, $ab_path.'tpl/'.$s_lang.'/my-vendor-searchword.lang.header.htm');
$tpl_content->addlist("searchWordLanguageBody", $languageSelection, $ab_path.'tpl/'.$s_lang.'/my-vendor-searchword.lang.body.htm');

// Template Ausgabe
$tpl_content->addvars($vendorTemplate);
$tpl_content->addvars($user);