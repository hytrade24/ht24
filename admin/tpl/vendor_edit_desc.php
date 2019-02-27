<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.vendor.php";

$vendorManagement = VendorManagement::getInstance($db);
$id = $_REQUEST['ID_VENDOR'];
$action = (isset($_REQUEST['ajax']) ? $_REQUEST['ajax'] :
          (isset($_REQUEST['do']) ? $_REQUEST['do'] : 'view') );
$ar_vendor = array();

if (isset($_REQUEST['saved'])) {
    $tpl_content->addvar("saved", 1);
}

switch ($action) {
    case 'saved':
        $tpl_content->addvar("saved", 1);
    case 'view':
        if ($id > 0) {
            $ar_vendor = $vendorManagement->fetchByVendorId($id);
            $ar_vendor['T1'] = $vendorManagement->fetchVendorDescriptionByLanguage($ar_vendor['ID_VENDOR'], $langval);
        }
        break;
    case 'save':
        $err = array();
        $ar_vendor = array_merge($vendorManagement->fetchByVendorId($id), $_POST);

        $vendorManagement->saveVendorByUserId($ar_vendor, $ar_vendor['FK_USER']);
        die(forward("index.php?page=vendor_edit_desc&ID_VENDOR=".$id."&saved=1"));

        break;
}

$ar_vendor['LOGO'] = ($ar_vendor['LOGO'] != "" ? 'cache/vendor/logo/'.$ar_vendor['LOGO'] : null);
$tpl_content->addvars($ar_vendor, "VENDOR_");

?>