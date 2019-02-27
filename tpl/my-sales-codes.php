<?php

require_once $ab_path."sys/lib.sales.php";
$salesManagment = SalesManagement::getInstance();

if (!empty($ar_params[1])) {
    switch ($ar_params[1]) {
        case 'added':
            $tpl_content->addvar("ADDED", 1);
            break;
        case 'deleted':
            $tpl_content->addvar("DELETED", 1);
            break;
        case 'del':
            $salesManagment->deleteRegisterCode((int)$ar_params[2]);
            die(forward($tpl_content->tpl_uri_action("my-sales-codes,deleted")));
        case 'qrcode':
            $idCode = (int)$ar_params[2];
            $arCode = $db->fetch1("SELECT * FROM `sales_code` WHERE ID_SALES_CODE=".$idCode);
            $tpl_content->LoadText("tpl/".$s_lang."/my-sales.qrcode.htm");
            if (is_array($arCode)) {
                $codeUrl = $tpl_content->tpl_uri_action_full("register_code,".$arCode["CODE"]);
                if (!is_dir($ab_path."cache/sales")) {
                    mkdir($ab_path."cache/sales", 0777, true);
                }
                $qrFileRelative = "cache/sales/qr_".$idCode.".png";
                $qrFileAbsolute = $ab_path.$qrFileRelative;
                if (!file_exists($qrFileAbsolute)) {
                    require_once $ab_path."lib/phpqrcode/qrlib.php";
                    QRcode::png($codeUrl, $qrFileAbsolute, QR_ECLEVEL_L);
                }
                if ($ar_params[3] == "download") {
                    $downloadFilename = "qrcode_".preg_replace("/[^a-z0-9]+/i", "_", $arCode["CODE"]).".png";
                    header("Content-Type: image/png");
                    header("Content-Length: " . filesize($qrFileAbsolute));
                    header("Content-Disposition: attachment; filename=\"".$downloadFilename."\"");
                    flush();
                    session_write_close();
                    readfile($qrFileAbsolute);
                    die();
                }
                $tpl_content->addvar("ID_SALES_CODE", $idCode);
                $tpl_content->addvar("QRCODE", $tpl_content->tpl_uri_baseurl_full($qrFileRelative));
                $tpl_content->addvar("URL_TARGET", $codeUrl);
            }
            die($tpl_content->process());
    }
}

if (!empty($_POST)) {
    $tpl_content->addvars($_POST);
    if (empty($_POST["CODE"])) {
        $tpl_content->addvar("ERROR", 1);
        $tpl_content->addvar("ERROR_CODE_EMPTY", 1);
    } else if (!preg_match("/^[a-z0-9-_]+$/i", $_POST["CODE"])) {
        $tpl_content->addvar("ERROR", 1);
        $tpl_content->addvar("ERROR_CODE_INVALID", 1);
    } else if (!$salesManagment->createRegisterCode($_POST["CODE"], $_POST["DESCRIPTION"])) {
        $tpl_content->addvar("ERROR", 1);
        $tpl_content->addvar("ERROR_CODE_EXISTS", 1);
    } else {
        die(forward($tpl_content->tpl_uri_action("my-sales-codes,added")));
    }
}

$codes = $salesManagment->getRegisterCodesByUser();
$tpl_content->addlist("liste", $codes, "tpl/".$s_lang."/my-sales-codes.row.htm");

?>