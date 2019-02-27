<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $GLOBALS["ab_path"]."sys/lib.user_media.php";

$table = $tpl_content->vars['TABLE'];
$id = (int)$tpl_content->vars['ID'];
$template = (!empty($tpl_content->vars['TEMPLATE']) ? $tpl_content->vars['TEMPLATE'] : "default");
$userMedia = new UserMediaManagement($db, $table, $uid);
$userMedia->loadFromDatabase($id);

if ($_REQUEST["mode"] == "ajax") {
    switch ($_REQUEST['do']) {
        case 'download':
            $id = (int)$_REQUEST['ID_MEDIA_UPLOAD'];
            if (isset($_REQUEST['INDEX'])) {
                $index = (int)$_REQUEST['INDEX'];
                $arUpload = $_SESSION['EBIZ_TRADER_USER_MEDIA']["adData"]["uploads"][$index];
                if (is_array($arUpload)) {
                    $id = (int)$arUpload['ID_MEDIA_UPLOAD'];
                    if ($id > 0) {
                        $filePath = $ab_path.ltrim($arUpload['SRC'], "/");
                        header( 'Content-type: application/octet-stream' );
                        header( 'Content-Length: ' . filesize( $filePath ) );
                        header( 'Content-Disposition: attachment; filename="'.$arUpload['FILENAME'].'.'.$arUpload['EXT'].'"' );
                        echo file_get_contents($filePath);
                        die();
                    } else {
                        header( 'Content-type: application/octet-stream' );
                        header( 'Content-Length: ' . filesize( $ar['TMP'] ) );
                        header( 'Content-Disposition: attachment; filename="'.$arUpload['FILENAME'].'.'.$arUpload['EXT'].'"' );
                        echo file_get_contents($arUpload['TMP']);
                        die();
                    }
                }
            } else {
                $ar = $db->fetch1("
                    SELECT
                        *
                    FROM
                        media_upload
                    WHERE
                        ID_MEDIA_UPLOAD=".$id);
                if(!empty($ar)) {
                    $filePath = $ab_path.ltrim($ar['SRC'], "/");
                    header( 'Content-type: application/octet-stream' );
                    header( 'Content-Length: ' . filesize( $filePath ) );
                    header( 'Content-Disposition: attachment; filename="'.$ar['FILENAME'].'.'.$ar['EXT'].'"' );
                    echo file_get_contents($filePath);
                    die();
                }
            }
        default:
            break;
    }
}

$tpl_content->addvar("media", $userMedia->renderMediaView($template));