<?php

require_once $ab_path."sys/lib.ads.php";

$id = ($_POST["ID_AD"] > 0 ? (int)$_POST["ID_AD"] : (int)$ar_params[2]);
$adCachePath = AdManagment::getAdCachePath($id, true);
$adData = $db->fetch1("SELECT * FROM `ad_master` WHERE ID_AD_MASTER=".$id);
if ($adData["FK_USER"] != $uid) {
    $tpl_content->addvar("ERROR", 1);
    $tpl_content->addvar("ERROR_NOT_FOUND", 1);
    return;
}

$adQrCodeImg = $adCachePath."/qr.png";
$adQrCodeImgRel = str_replace($ab_path, "/", $adQrCodeImg);

if ($ar_params[1] == "download") {
    $articleName = (strlen($adData["PRODUKTNAME"]) > 32 ? substr($adData["PRODUKTNAME"], 0, 32) : $adData["PRODUKTNAME"] );
    $downloadFilename = "qrcode_".preg_replace("/[^a-z0-9]+/i", "_", $articleName).".png";
    header("Content-Type: image/png");
    header("Content-Length: " . filesize($adQrCodeImg));
    header("Content-Disposition: attachment; filename=\"".$downloadFilename."\"");
    flush();
    readfile($adQrCodeImg);
    die();
}

$adUrl = $tpl_content->tpl_uri_action_full("marktplatz_anzeige,".$id.",".chtrans($adData["PRODUKTNAME"]));
if (!file_exists($adQrCodeImg)) {
    require_once $ab_path."lib/phpqrcode/qrlib.php";
    QRcode::png($adUrl, $adQrCodeImg, QR_ECLEVEL_L);
}

$tpl_content->addvar("ID_AD", $id);
$tpl_content->addvar("URL_TARGET", $adUrl);
$tpl_content->addvar("QRCODE", $tpl_content->tpl_uri_baseurl_full($adQrCodeImgRel));