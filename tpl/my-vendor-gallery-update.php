<?php
/* ###VERSIONSBLOCKINLCUDE### */


/**
 *
 * @changed 2011-12-16 Danny Rosifka, Hinzufügen von Videos
 */

require_once 'sys/lib.vendor.php';
require_once 'sys/lib.vendor.gallery.php';

$userId = $uid;
$vendorGalleryId = ((int)$ar_params[1] ? (int)$ar_params[1] : null);
/** @var string|null $doAction  */
$doAction = ((string)$ar_params[2] ? (string)$ar_params[2] : null);


$vendorManagement = VendorManagement::getInstance($db);
$vendorGalleryManagement = VendorGalleryManagement::getInstance($db);

$vendor = $vendorManagement->fetchByUserId($userId);
if($vendor != null) {
    $vendorId = $vendor['ID_VENDOR'];
}
if(isset($_POST) && $_POST['DO'] == 'ADD' && !empty($_FILES['FILENAME']['tmp_name']) && $_POST['gallery_type'] == 'image') {
    // Neues Bild hinzufügen

    if(preg_match("/(\.gif|\.jpg|\.jpeg|\.png)$/", strtolower($_FILES['FILENAME']['name']))) {

        $galleryFilename = md5_file($_FILES['FILENAME']['tmp_name']).'_'.$_FILES['FILENAME']['name'];
        $galleryFile = $ab_path.'cache/vendor/gallery/'.$galleryFilename;

        move_uploaded_file($_FILES['FILENAME']['tmp_name'], $galleryFile);
        chmod($galleryFile, 0777);

        $result = $vendorGalleryManagement->insertFile("", $galleryFilename, $vendorId);
    }
} elseif($doAction !== null && $doAction == "delete" && $vendorGalleryId !== null) {
    $result = $vendorGalleryManagement->deleteById($vendorGalleryId, $userId);
} elseif(isset($_POST) && $_POST['DO'] == 'ADD' && ($_POST['youtubelink'] != "") && $_POST['gallery_type'] == 'video') {
    // Neues Video hinzufügen
    // @todo Youtube Link extract
    require_once 'sys/lib.youtube.php';
    $youtube = new Youtube();


    $youtubeId = $youtube->ExtractCodeFromURL($_POST['youtubelink']);

    if($youtubeId != null) {
        $result = $vendorGalleryManagement->insertVideo("", $youtubeId, $vendorId);
    }

} elseif($doAction !== null && $doAction == "delete_video" && $vendorGalleryId !== null) {
    $result = $vendorGalleryManagement->deleteVideoById($vendorGalleryId, $userId);
}

die(forward('/my-pages/my-vendor-gallery.htm'));

