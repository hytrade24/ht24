<?php

/**
 * ebiz-trader
 *
 * @copyright Copyright (c) 2012 ebiz-consult e.K.
 * @version 6.5.4i
 */

$actionSub = (!empty($ar_params[2]) ? $ar_params[2] : "confirm");

if ($actionSub == "cancel") {
    $successUrl = $_SESSION["USER_ADS_ACTION_SUCCESS"];
    unset($_SESSION["USER_ADS_ACTION_LIST"]);
    unset($_SESSION["USER_ADS_ACTION_LAUFZEIT"]);   
    unset($_SESSION["USER_ADS_ACTION_COUNT"]);
    unset($_SESSION["USER_ADS_ACTION_SUCCESS"]);
    die(forward($successUrl));
}
if ($actionSub == "process") {
    $done = false;
    $countPerCall = 500;
    list($countProducts, $countProductsDone) = $_SESSION["USER_ADS_ACTION_COUNT"];
    $arAdIds = array();
    if (count($_SESSION["USER_ADS_ACTION_LIST"]) > $countPerCall) {
        $arAdIds = array_splice($_SESSION["USER_ADS_ACTION_LIST"], 0, $countPerCall);
    } else {
        $arAdIds = $_SESSION["USER_ADS_ACTION_LIST"];
        $done = true;
    }
    $countProductsDone += count($arAdIds);
    // -----------------------------------
    require_once $ab_path."sys/lib.ads.php";
    $query = "SELECT ID_AD_MASTER, AD_TABLE FROM `ad_master` WHERE ID_AD_MASTER IN (".implode(", ", $arAdIds).") AND FK_USER=".$uid;
    $arSelectedAds = $db->fetch_nar($query);
    $arLaufzeit = Api_LookupManagement::getInstance($db)->readById($_SESSION["USER_ADS_ACTION_LAUFZEIT"]);
    $extendDays = $arLaufzeit["VALUE"];
    foreach ($arSelectedAds as $adId => $adTable) {
        AdManagment::ExtendRuntime($adId, $adTable, $extendDays);
    }
    // -----------------------------------
    if ($done) {
        $successUrl = $_SESSION["USER_ADS_ACTION_SUCCESS"];
        unset($_SESSION["USER_ADS_ACTION_LIST"]);
        unset($_SESSION["USER_ADS_ACTION_LAUFZEIT"]);
        unset($_SESSION["USER_ADS_ACTION_COUNT"]);
        unset($_SESSION["USER_ADS_ACTION_SUCCESS"]);
        die(forward($successUrl));
    } else {
        $_SESSION["USER_ADS_ACTION_COUNT"] = array($countProducts, $countProductsDone);
        $tpl_content->addvar("PROCESSING", 1);
        $tpl_content->addvar("PROCESS_COUNT", $countProducts);
        $tpl_content->addvar("PROCESS_DONE", $countProductsDone);
        $tpl_content->addvar("PROCESS_PERCENT", round(($countProductsDone / $countProducts) * 100));
        return;
    }
}

if (array_key_exists("confirm", $_POST)) {
    $successUrl = $tpl_content->tpl_uri_action("my-marktplatz,active,,,extended");
    $_SESSION["USER_ADS_ACTION_LIST"] = $arSelected;
    $_SESSION["USER_ADS_ACTION_LAUFZEIT"] = $_POST["LU_LAUFZEIT"];
    $_SESSION["USER_ADS_ACTION_COUNT"] = array(count($arSelected), 0);
    $_SESSION["USER_ADS_ACTION_SUCCESS"] = $successUrl;
    $_SESSION["USER_ADS_SELECTED"] = array();
    die(forward( $tpl_content->tpl_uri_action("my-marktplatz,extend,process") ));
}

$errorList = array();
$errorAds = array();
$packetRequirements = array("ads" => 0, "images" => 0, "videos" => 0, "downloads" => 0);

// Read articles from db
$articleCount = $db->fetch_atom("
  SELECT COUNT(*)
  FROM `ad_master`
  WHERE ID_AD_MASTER IN (".implode(", ", $arSelected).") AND FK_USER=".$uid);

// Calculate required contents for non-abo packets
require_once $ab_path."sys/packet_management.php";
$packets = PacketManagement::getInstance($db);
$articlePackets = $db->fetch_table("
  SELECT
    COUNT(*) AS COUNT, FK_PACKET_ORDER,
    IF((SELECT BILLING_CYCLE FROM `packet_order` WHERE ID_PACKET_ORDER=FK_PACKET_ORDER)='ONCE',0,1) AS IS_ABO
  FROM `ad_master`
  WHERE ID_AD_MASTER IN (".implode(", ", $arSelected).") AND FK_USER=".$uid."
  GROUP BY FK_PACKET_ORDER");
foreach ($articlePackets as $articlePacketIndex => $articlePacketDetails) {
    if ($articlePacketDetails["IS_ABO"] == 0) {
        $articlePacketAds = $db->fetch_nar("
          SELECT ID_AD_MASTER, PRODUKTNAME FROM `ad_master` 
          WHERE FK_PACKET_ORDER=".(int)$articlePacketDetails["FK_PACKET_ORDER"]." AND ID_AD_MASTER IN (".implode(", ", $arSelected).")");
        /** @var PacketOrderCollectionOnce $packetOrder */
        $packetOrder = $packets->order_get($articlePacketDetails["FK_PACKET_ORDER"]);
        $packetOrderContents = $packetOrder->getPacketUsageEx(array_keys($articlePacketAds), true);
        $packetRequirements["ads"] += $packetOrderContents["ads_required"];
        $packetRequirements["images"] += $packetOrderContents["images_required"];
        $packetRequirements["videos"] += $packetOrderContents["videos_required"];
        $packetRequirements["downloads"] += $packetOrderContents["downloads_required"];
        $hasError = false;
        if ($packetOrderContents["ads_available"] < 0) {
            $hasError = true;
            $errorList["insufficient_ads"] = Translation::readTranslation("marketplace", "ads.extend.insufficient.ads", null, array(), "Sie haben nicht genügend Anzeigen verfügbar um die gewählten Artikel zu verlängern.");
        }
        if ($packetOrderContents["images_available"] < 0) {
            $hasError = true;
            $errorList["insufficient_images"] = Translation::readTranslation("marketplace", "ads.extend.insufficient.images", null, array(), "Sie haben nicht genügend Bilder verfügbar um die gewählten Artikel zu verlängern.");
        }
        if ($packetOrderContents["videos_available"] < 0) {
            $hasError = true;
            $errorList["insufficient_videos"] = Translation::readTranslation("marketplace", "ads.extend.insufficient.videos", null, array(), "Sie haben nicht genügend Videos verfügbar um die gewählten Artikel zu verlängern.");
        }
        if ($packetOrderContents["downloads_available"] < 0) {
            $hasError = true;
            $errorList["insufficient_downloads"] = Translation::readTranslation("marketplace", "ads.extend.insufficient.downloads", null, array(), "Sie haben nicht genügend Downloads verfügbar um die gewählten Artikel zu verlängern.");
        }
        if ($hasError) {
            foreach ($articlePacketAds as $adId => $adTitle) {
                $errorAds[] = $adTitle." (#".$adId.")";
            }

        }
        #var_dump($errorList, $errorAds);
        #die(var_dump($arAdIds, $packetOrderContents, $packetOrder));
    }
}

$error = (empty($errorList) ? false : "<li>".implode("</li><li>", $errorList)."</li>");
$errorAdsCount = count($errorAds);
$errorAds = (empty($errorAds) || ($errorAdsCount > 5) ? false : "<li>".implode("</li><li>", $errorAds)."</li>");

$requirementList = array();
foreach ($packetRequirements as $requirementType => $requirementCount) {
    if ($requirementCount > 0) {
        $requirementList[strtoupper($requirementType)] = $requirementCount;
    }
}
$requirements = (!empty($requirementList));

// Add articles and variables to template
$tpl_content->addvar("ERROR", $error);
$tpl_content->addvar("ERROR_ADS", $errorAds);
$tpl_content->addvar("ERROR_ADS_COUNT", $errorAdsCount);
$tpl_content->addvar("REQUIREMENTS", $requirements);
$tpl_content->addvars($requirementList, "REQUIREMENT_");
$tpl_content->addvar("count", $articleCount);