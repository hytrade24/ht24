<?php
/* ###VERSIONSBLOCKINLCUDE### */


/**
 * Schlagwort Anzeige des Anbieterverzeichnisses
 */
require_once 'sys/lib.vendor.php';
require_once 'sys/lib.nestedsets.php';

$userId = $uid;
$vendorManagement = VendorManagement::getInstance($db);

if(isset($_POST['LANG'])) {
    $defaultLanguage = $_POST['LANG'];
} else {
    $t = get_language();
    $defaultLanguage = $t['0'];
}

if(isset($_POST) && $_POST['DO'] == "GET") {
    // Zeige die Schlagworte

    $vendorSearchWords = $vendorManagement->fetchAllSearchWordsByUserIdAndLanguage($userId, $defaultLanguage);

    foreach($vendorSearchWords as $key=>$vendorSearchWord) {
        $vendorSearchWords[$key]['wort'] = $vendorSearchWord['wort'];
    }
    $tpl_content->addlist("searchwords", $vendorSearchWords, $ab_path.'tpl/'.$s_lang.'/my-vendor-searchword.row.htm');

} elseif($_POST['DO'] == "ADD" && $_POST['SEARCHWORD'] != "") {
    $result = $vendorManagement->addVendorSearchWordByUserId($_POST['SEARCHWORD'], $userId, $defaultLanguage);
    /*echo '<pre>';
    var_dump( $result );
    echo '</pre>';*/

    echo json_encode(array("result" => true)); die();
} elseif($_POST['DO'] == "DELETE" && $_POST['SEARCHWORD'] != "") {
    $result = $vendorManagement->deleteVendorSearchWordByUserId($_POST['SEARCHWORD'], $userId, $defaultLanguage);

    echo json_encode(array("result" => true)); die();
}
