<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.vendor.php';
require_once 'sys/lib.vendor.place.php';

$userId = $uid;
$vendorPlaceId = ((int)$ar_params[1] ? (int)$ar_params[1] : null);
/** @var string|null $doAction  */
$doAction = ((string)$ar_params[2] ? (string)$ar_params[2] : null);
$inputLanguage = ((string)$ar_params[3] ? (string)$ar_params[3] : null);

$vendorManagement = VendorManagement::getInstance($db);
$vendorPlaceManagement = VendorPlaceManagement::getInstance($db);

$vendor = $vendorManagement->fetchByUserId($userId);


if(isset($_POST) && $_POST['DO'] == "SAVE") {
    /**
     * Standort speichern
     */

    if(!isset($_POST['ID_VENDOR_PLACE']) || $_POST['ID_VENDOR_PLACE'] == "") {
       $result =  $vendorPlaceManagement->insertVendorPlace($_POST, $vendor['ID_VENDOR']);
    } else {
        $result = $vendorPlaceManagement->updateByIdAndUserId($_POST, $_POST['ID_VENDOR_PLACE'], $userId);
    }

    if($result == true) {
       die(forward('/my-pages/my-vendor-place.htm'));
    }
} elseif($doAction !== null && $doAction == "delete" && $vendorPlaceId !== null) {
    /**
     * Standort löschen
     */

    $result = $vendorPlaceManagement->deleteVendorPlaceById($vendorPlaceId, $userId);

    if($result == true) {
       die(forward('/my-pages/my-vendor-place.htm'));
    }
}

if($vendorPlaceId !== null) {
    $vendorPlace = $vendorPlaceManagement->fetchById($vendorPlaceId, $userId);

    if($vendorPlace != null) {
        $vendorPlaceTemplate = array();
        foreach($vendorPlace as $key => $value) {
            // für Template aufbereiten
            if(!in_array($key, array("ID_VENDOR_PLACE", "FK_VENDOR", "FK_COUNTRY"))) {
                $vendorPlaceTemplate["VENDOR_PLACE_".$key] = $value;
            } else {
                $vendorPlaceTemplate[$key] = $value;
            }
        }

        $tpl_content->addvars($vendorPlaceTemplate);
    }
}


// Sprache
$languageSelection = $db->fetch_table("SELECT * FROM lang WHERE B_PUBLIC = 1");

foreach($languageSelection as $key=>$value) {
    $languageSelection[$key]['VENDOR_PLACE_DESCRIPTION'] = $vendorPlaceManagement->fetchVendorPlaceDescriptionByLanguage($vendorPlace['ID_VENDOR_PLACE'], $value['BITVAL']);
}

$tpl_content->addlist("languageHeader", $languageSelection, $ab_path.'tpl/'.$s_lang.'/my-vendor-place-update.lang.header.htm');
$tpl_content->addlist("languageBody", $languageSelection, $ab_path.'tpl/'.$s_lang.'/my-vendor-place-update.lang.body.htm');
