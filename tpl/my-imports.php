<?php
/* ###VERSIONSBLOCKINLCUDE### */

global $ab_path;

require_once 'sys/lib.ad_import.php';


$adImportManagement = AdImportManagement::getInstance($db);

if(isset($_POST['DO'])) {
    if($_POST['DO'] == 'remove' && isset($_POST['ID_IMPORT_FILE'])) {
        $result = $adImportManagement->removeImportFile($_POST['ID_IMPORT_FILE'], $uid);

        echo json_encode(array("success" => $result));
        die();
    }
}


$importFiles = $adImportManagement->fetchAllImportFilesByUserId($uid);

foreach($importFiles as $key=>$importFile) {
	$importFiles[$key]['COUNT_IMPORTS_VALIDATION'] = $adImportManagement->countImportsByImportFileIdAndUserId($importFile['ID_IMPORT_FILE'], $uid, array('status' => AdImportManagement::IMPORT_STATUS_VALIDATION));
	$importFiles[$key]['COUNT_IMPORTS_VALIDATION_SUCCESS'] = $adImportManagement->countImportsByImportFileIdAndUserId($importFile['ID_IMPORT_FILE'], $uid, array('status' => AdImportManagement::IMPORT_STATUS_VALIDATION_SUCCESS));
	$importFiles[$key]['COUNT_IMPORTS_VALIDATION_FAILED'] = $adImportManagement->countImportsByImportFileIdAndUserId($importFile['ID_IMPORT_FILE'], $uid, array('status' => AdImportManagement::IMPORT_STATUS_VALIDATION_FAILED));
	$importFiles[$key]['COUNT_IMPORTS_IMPORTED'] = $adImportManagement->countImportsByImportFileIdAndUserId($importFile['ID_IMPORT_FILE'], $uid, array('status' => AdImportManagement::IMPORT_STATUS_IMPORTED));
	$importFiles[$key]['COUNT_IMPORTS'] = $adImportManagement->countImportsByImportFileIdAndUserId($importFile['ID_IMPORT_FILE'], $uid);
}

$log_hash = md5("import_hash_user_".$uid);
if (file_exists($ab_path."cache/log_import_".$log_hash.".log")) {
	$tpl_content->addvar("IMPORT_LOG", 1);
	$tpl_content->addvar("IMPORT_LOG_HASH", $log_hash);
}
$tpl_content->addlist("liste", $importFiles, "tpl/".$s_lang."/my-imports.row.htm");


