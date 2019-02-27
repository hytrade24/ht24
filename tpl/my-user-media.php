<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.user_media.php";

$table = $tpl_content->vars['TABLE'];
$id = (int)$tpl_content->vars['ID'];
$packet = (int)$tpl_content->vars['PACKET'];
$allowImages = (array_key_exists("allowImages", $tpl_content->vars) ? $tpl_content->vars["allowImages"] : true);
$allowUploads = (array_key_exists("allowUploads", $tpl_content->vars) ? $tpl_content->vars["allowUploads"] : true);
$allowVideos = (array_key_exists("allowVideos", $tpl_content->vars) ? $tpl_content->vars["allowVideos"] : true);
$freeImages = (array_key_exists("freeImages", $tpl_content->vars) ? $tpl_content->vars["freeImages"] : null);
$freeUploads = (array_key_exists("freeUploads", $tpl_content->vars) ? $tpl_content->vars["freeUploads"] : null);
$freeVideos = (array_key_exists("freeVideos", $tpl_content->vars) ? $tpl_content->vars["freeVideos"] : null);
$imageFormatId = (array_key_exists("imageFormat", $tpl_content->vars) ? $tpl_content->vars["imageFormat"] : UserMediaManagement::IMAGE_DEFAULT_FORMAT);
// Extended free counts
if (($packet == 0) && array_key_exists("EXTEND_FREE", $tpl_content->vars)) {
  $freeImages = UserMediaManagement::MEDIA_MAX_IMAGES;
  $freeUploads = UserMediaManagement::MEDIA_MAX_UPLOADS;
  $freeVideos = UserMediaManagement::MEDIA_MAX_VIDEOS;
}
// Templates
$tplImages = (array_key_exists("tplImages", $tpl_content->vars) ? $tpl_content->vars["tplImages"] : null);
$tplImagesRow = (array_key_exists("tplImagesRow", $tpl_content->vars) ? $tpl_content->vars["tplImagesRow"] : null);
$tplUploads = (array_key_exists("tplUploads", $tpl_content->vars) ? $tpl_content->vars["tplUploads"] : null);
$tplUploadsRow = (array_key_exists("tplUploadsRow", $tpl_content->vars) ? $tpl_content->vars["tplUploadsRow"] : null);
$tplVideos = (array_key_exists("tplVideos", $tpl_content->vars) ? $tpl_content->vars["tplVideos"] : null);
$tplVideosRow = (array_key_exists("tplVideosRow", $tpl_content->vars) ? $tpl_content->vars["tplVideosRow"] : null);
// Add settings to template
$tpl_content->addvar("allowImages", $allowImages);
$tpl_content->addvar("allowUploads", $allowUploads);
$tpl_content->addvar("allowVideos", $allowVideos);
$tpl_content->addvar("imageFormat", $imageFormatId);
$tpl_content->addvar('UPLOAD_MAX_FILESIZE', Tools_Utility::getUploadMaxFilsize());
$userMedia = new UserMediaManagement($db, $table, $uid);
// Free amounts
if ($freeImages !== null) {
    $userMedia->setFreeImages((int)$freeImages);
}
if ($freeUploads !== null) {
    $userMedia->setFreeUploads((int)$freeUploads);
}
if ($freeVideos !== null) {
    $userMedia->setFreeVideos((int)$freeVideos);
}
// Templates
if ($tplImages !== null) {
    $userMedia->setTplImages($tplImages);
}
$tpl_content->addvar("tplImages", $userMedia->getTplImages());
if ($tplImagesRow !== null) {
    $userMedia->setTplImagesRow($tplImagesRow);
}
$tpl_content->addvar("tplImagesRow", $userMedia->getTplImagesRow());
if ($tplUploads !== null) {
    $userMedia->setTplUploads($tplUploads);
}
if ($tplUploadsRow !== null) {
    $userMedia->setTplUploadsRow($tplUploadsRow);
}
if ($tplVideos !== null) {
    $userMedia->setTplVideos($tplVideos);
}
if ($tplVideosRow !== null) {
    $userMedia->setTplVideosRow($tplVideosRow);
}
if ($_REQUEST["mode"] != "ajax") {
    $userMedia->loadFromDatabase($id);
    if ($packet > 0) {
        $userMedia->setPacket($packet);
    }

    $arMediaUsage = $userMedia->getMediaUsage();
    if ($allowImages) {
        $arMediaUsage["images"] = $userMedia->renderMediaImages();
    }
    if ($allowUploads) {
        $arMediaUsage["downloads"] = $userMedia->renderMediaDownloads();
    }
    if ($allowVideos) {
        $arMediaUsage["videos"] = $userMedia->renderMediaVideos();
    }
    $tpl_content->addvars($arMediaUsage);
}

if ($_REQUEST["mode"] == "ajax") {
    require_once $ab_path."sys/lib.user_media.php";
    switch ($_REQUEST['do']) {
        case 'getMediaUsage':
            header('Content-type: application/json');
            die(json_encode(
                $userMedia->getMediaUsage()
            ));
        case 'upload':
            $success = false;
            $errors = array();
            if (isset($_REQUEST['action'])) {
                switch ($_REQUEST['action']) {
                    case 'image_default':
                        // Set default image
                        $userMedia->setImageDefault((int)$_REQUEST["id"]);
                        $_REQUEST['show'] = 'images';
                        break;
                    case 'image_delete':
                        // Delete image
                        $userMedia->deleteImage((int)$_REQUEST["id"]);
                        $_REQUEST['show'] = 'images';
                        break;
                    case 'document_delete':
                        // Delete image
                        $userMedia->deleteFile((int)$_REQUEST["id"]);
                        $_REQUEST['show'] = 'documents';
                        break;
                    case 'video_delete':
                        // Delete video
                        $userMedia->deleteVideo((int)$_REQUEST["id"]);
                        $_REQUEST['show'] = 'videos';
                        break;
                }
            }
            if (isset($_FILES["image"])) {
                $arImage = array();
                $success = $userMedia->handleImageUpload($_FILES["image"], $errors, $arImage, $imageFormatId);
                $arResult = array('files' => array(), 'success' => false);
                if ($success) {
                    $arResult['success'] = true;
                    $arResult['files'][] = array(
                        'IMAGE_INDEX'   => $arImage['INDEX'],
                        'IMAGE_TYPE'    => $arImage['TYPE'],
                        'IMAGE_DATA'    => ($arImage['FK_AD'] == 0 ?
                                base64_encode( file_get_contents($arImage['TMP_THUMB']) ) :
                                base64_encode( file_get_contents($ab_path.$arImage['SRC_THUMB']) )
                            ),
                        'IMAGE_DEFAULT' => $arImage['IS_DEFAULT']
                    );
                } else {
                    $arResult['files'][] = array(
                        'ERRORS'        => $errors,
                        'IMAGE_INDEX'   => -1,
                        'IMAGE_TYPE'    => $_FILES["image"]['TYPE']
                    );
                }
                header('Content-type: application/json');
                die(json_encode($arResult));
            }
            if (isset($_FILES["document"])) {
                $arImage = array();
                $success = $userMedia->handleFileUpload($_FILES["document"], $errors, $arDocument);
                $arResult = array('files' => array(), 'success' => false);
                if ($success) {
                    $arResult['success'] = true;
                    $arResult['files'][] = array(
                        'UPLOAD_INDEX'  => $arDocument['INDEX'],
                        'UPLOAD_TYPE'   => $arDocument['EXT'],
                        'UPLOAD_FILE'   => $arDocument['FILENAME']
                    );
                } else {
                    $arResult['files'][] = array(
                        'ERRORS'        => $errors,
                        'UPLOAD_INDEX'  => -1,
                        'UPLOAD_TYPE'   => $_FILES["image"]['TYPE']
                    );
                }
                header('Content-type: application/json');
                die(json_encode($arResult));
            }
            if (isset($_FILES["UPLOAD_FILE"])) {
                $success = $userMedia->handleFileUpload($_FILES["UPLOAD_FILE"], $errors);
                $_REQUEST['show'] = 'documents';
            }
            if (isset($_POST["youtube_url"])) {
                $success = $userMedia->handleVideoUpload($_POST["youtube_url"]);
                $_REQUEST['show'] = 'videos';
            }
            if (isset($_REQUEST['show'])) {
                switch ($_REQUEST['show']) {
                    case 'images':
                        die($userMedia->renderMediaImages());
                    case 'documents':
                        die($userMedia->renderMediaDownloads());
                    case 'videos':
                        die($userMedia->renderMediaVideos());
                }
            }
            die();
        default:
            break;
    }
}