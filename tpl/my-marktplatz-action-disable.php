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
    foreach ($arSelectedAds as $adId => $adTable) {
        AdManagment::Disable($adId, $adTable);
    }
    // -----------------------------------
    if ($done) {
        $successUrl = $_SESSION["USER_ADS_ACTION_SUCCESS"];
        unset($_SESSION["USER_ADS_ACTION_LIST"]);
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
    $successUrl = $_POST["SUCCESS"];
    $_SESSION["USER_ADS_ACTION_LIST"] = $arSelected;
    $_SESSION["USER_ADS_ACTION_COUNT"] = array(count($arSelected), 0);
    $_SESSION["USER_ADS_ACTION_SUCCESS"] = $successUrl;
    $_SESSION["USER_ADS_SELECTED"] = array();
    die(forward( $tpl_content->tpl_uri_action("my-marktplatz,disable,process") ));
} else {
    $successUrl = $_SERVER["HTTP_REFERER"];
    if (strpos($successUrl, "?") !== false) {
        $successUrl .= "&hinweis=sel_akt";
    } else {
        $successUrl .= "?hinweis=sel_akt";
    }
}

// Read articles from db
$articleCountEnabled = $db->fetch_atom("
  SELECT COUNT(*)
  FROM `ad_master`
  WHERE ID_AD_MASTER IN (".implode(", ", $arSelected).") AND FK_USER=".$uid." AND STATUS IN (1,3,5,7)");
$articleCountDisabled = $db->fetch_atom("
  SELECT COUNT(*)
  FROM `ad_master`
  WHERE ID_AD_MASTER IN (".implode(", ", $arSelected).") AND FK_USER=".$uid." AND STATUS NOT IN (1,3,5,7)");

// Add articles and variables to template
$tpl_content->addvar("count", ($articleCountEnabled + $articleCountDisabled));
$tpl_content->addvar("countEnabled", $articleCountEnabled);
$tpl_content->addvar("countDisabled", $articleCountDisabled);