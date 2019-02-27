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
    $tpl_content->addvar("NOTIFICATION_".strtoupper($ar_params[1]), 1);
}

if(isset($_POST) && $_POST['DO'] == 'SAVE') {
    $vendor['BUSINESS_HOURS'] = (is_array($_POST['BUSINESS_HOURS']) ? json_encode($_POST['BUSINESS_HOURS']) : json_encode([]));
    $saveResult = $vendorManagement->saveVendorByUserId($vendor, $userId);
    if ($saveResult) {
        die(forward($tpl_content->tpl_uri_action("my-vendor-business-hours,success")));
    } else {
        $tpl_content->addvar("NOTIFICATION_ERROR", 1);
    }
}

$business_hours = @json_decode($vendor['BUSINESS_HOURS'], true);
if (!is_array($business_hours)) {
    $business_hours = [];
}
foreach ($business_hours as $weekday => $weekdayHours) {
    $tpl_content->addvar('BUSINESS_HOURS_'.$weekday, $weekdayHours);
}