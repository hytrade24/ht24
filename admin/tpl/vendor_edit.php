<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.vendor.php";

$vendorManagement = VendorManagement::getInstance($db);
$id = $_REQUEST['ID_VENDOR'];
$action = (isset($_REQUEST['ajax']) ? $_REQUEST['ajax'] : $_REQUEST['do']);
$ar_vendor = array();
$err = array();

switch ($action) {
    case 'disable':
        if ($vendorManagement->disableById($id)) {
            die(forward("index.php?page=vendor&disabled=".$id));
        } else {
            $err[] = "NOT_FOUND";
            break;
        }
    case 'enable':
        if ($vendorManagement->enableById($id)) {
            die(forward("index.php?page=vendor&enabled=".$id));
        } else {
            $err[] = "NOT_FOUND";
            break;
        }
    case 'save':
        if(isset($_FILES) && $_FILES['LOGO']['tmp_name'] != "") {
            // Logo uploaded
            $galleryFilename = md5_file($_FILES['LOGO']['tmp_name']).'_'.$_FILES['LOGO']['name'];
            $galleryFile = $ab_path.'cache/vendor/logo/'.$galleryFilename;

            move_uploaded_file($_FILES['LOGO']['tmp_name'], $galleryFile);
            chmod($galleryFile, 0777);

            $_POST['LOGO'] = $galleryFilename;
        }

        if(isset($_POST['DELETE_LOGO']) && $_POST['DELETE_LOGO'] == 1) {
            $_POST['LOGO'] = "";
        }

        if (!isset($_POST["STATUS"])) {
            $_POST["STATUS"] = 0;
        }

        if ($id > 0) {
            $ar_vendor = array_merge($vendorManagement->fetchByVendorId($id), $_POST);
            if ($vendorManagement->updateCheckFields($ar_vendor, $err)) {
                $vendorManagement->saveVendorByUserId($ar_vendor, $ar_vendor['FK_USER']);
            }
        }
        else {
            $ar_vendor = $_POST;
            if ($vendorManagement->updateCheckFields($ar_vendor, $err)) {
                $id = $vendorManagement->saveVendorByUserId($ar_vendor, $ar_vendor['FK_USER']);
            }
        }

        if (empty($err)) {
            // Success
            die(forward("index.php?page=vendor_edit&ID_VENDOR=".$id));
        }

        // Error!
        $ar_vendor["NEW"] = ($id > 0 ? 0 : 1);
        $ar_vendor["EDITABLE"] = 1;

        break;
    default:
        if ($id > 0) {
            $ar_vendor = $vendorManagement->fetchByVendorId($id);
        }

        $ar_vendor["NEW"] = 0;
        $ar_vendor["EDITABLE"] = 1;

        break;
}

$ar_vendor['LOGO'] = ($ar_vendor['LOGO'] != "" ? 'cache/vendor/logo/'.$ar_vendor['LOGO'] : null);

$tpl_content->addvars($ar_vendor, "VENDOR_");

?>