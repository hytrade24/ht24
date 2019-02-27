<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.vendor.php';
require_once 'sys/lib.vendor.place.php';
 
$userId = $uid;
$vendorManagement = VendorManagement::getInstance($db);
$vendorPlaceManagement = VendorPlaceManagement::getInstance($db);

$tmp = $vendorManagement->fetchByUserId($userId);
$vendor = $vendorManagement->fetchByVendorId($tmp['ID_VENDOR']);

$vendorPlaces = $vendorPlaceManagement->fetchAllByUserId($userId);

foreach($vendorPlaces as $key => $vendorPlace) {
    $vendorPlaces[$key]['COUNTRY'] = $db->fetch_atom("SELECT V1 FROM string WHERE S_TABLE='country' AND BF_LANG=".$langval." AND FK=".(int)$vendorPlace["FK_COUNTRY"]);
}
$tpl_content->addlist("liste", $vendorPlaces, $ab_path.'tpl/'.$s_lang.'/my-vendor-place.row.htm');


$vendorTemplate = array();
foreach($vendor as $key => $value) {
    // für Template aufbereiten
    if(!in_array($key, array("ID_VENDOR", "FK_USER"))) {
        $vendorTemplate["VENDOR_".$key] = $value;
    } else {
        $vendorTemplate[$key] = $value;
    }
}
$tpl_content->addvars($vendorTemplate);
