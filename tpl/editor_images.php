<?php

require_once $ab_path."/sys/lib.user_upload.php";
$userUploadMan = UserUploadManagement::getInstance($db);

if (!empty($_POST['delete'])) {
    header("Content-Type: application/json");
    die(json_encode(array("success" => $userUploadMan->deleteUpload($_POST['delete']))));
}
if (($uid > 0) && !empty($_FILES)) {
    if ($userUploadMan->uploadFile($_FILES["UPLOAD_FILE"], $ajaxResponse)) {
        // Success
        //$uploadFile = $userUploadMan->getLastUpload();
    }
    header("Content-Type: application/json");
    die(json_encode($ajaxResponse));
}

$arList = $userUploadMan->getUploads();
$tpl_list = new Template("tpl/".$s_lang."/editor_images.list.htm");
$tpl_list->addlist("liste", $arList, "tpl/".$s_lang."/editor_images.list.row.htm");

if ($_REQUEST['mode'] == "ajax") {
    die($tpl_list->process());
} else {
    $tpl_content->addvar("liste", $tpl_list);
}