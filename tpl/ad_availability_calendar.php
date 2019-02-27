<?php

require_once $ab_path."sys/lib.ad_availability.php";

$id_user = (int)$uid;

$id_ad = $tpl_content->vars['ID_AD'];
$arAds = array();
$arAdsActive = array();
if ($id_ad > 0) {
    $arAdsActive[] = $id_ad;
    $arAds = $db->fetch_table("SELECT * FROM `ad_master` WHERE ID_AD_MASTER=".$id_ad." AND (STATUS&3)=1 AND (DELETED=0)");
} else {
    $tpl_content->addvar("SHOW_FILTER", 1);
    $arAdsActive = (is_array($_POST['ads']) ? $_POST['ads'] : array());
    if (!empty($arAdsActive) || !empty($arProfilesActive)) {
        $_SESSION['calendar_settings'] = array(
            'ads' => $arAdsActive
        );
    }
    if (is_array($_SESSION['calendar_settings'])) {
        $arAdsActive = (is_array($_SESSION['calendar_settings']['ads']) ? $_SESSION['calendar_settings']['ads'] : array());
    }   
    /**
     * Ads
     */
    $arAds = $db->fetch_table("SELECT * FROM `ad_master` WHERE FK_USER=".$id_user." AND AVAILABILITY IS NOT NULL ORDER BY STAMP_START DESC");
}

$json_result = array();
$dateRange = array();
$dateStart = null;
$dateEnd = null;
$countAds = 0;
foreach ($arAds as $index => $arAd) {
    if (in_array($arAd['ID_AD_MASTER'], $arAdsActive)) {
        $arAds[$index]['ACTIVE'] = 1;
        $countAds++;
        // Check first project date
        $arAds[$index]['TIMESTAMP_START'] = $dateStartCur = strtotime($arAd['STAMP_START']);
        if (($dateStart == null) || ($dateStartCur < $dateStart)) {
            $dateStart = $dateStartCur;
        }
        // Check last project date
        $arAds[$index]['TIMESTAMP_END'] = $dateEndCur = strtotime($arAd['STAMP_END']);
        if (($dateEnd == null) || ($dateEndCur > $dateEnd)) {
            $dateEnd = $dateEndCur;
        }
    }
}
// Get availability
foreach ($arAds as $index => $arAd) {
    if (in_array($arAd['ID_AD_MASTER'], $arAdsActive)) {
	    if (($arAd['STATUS']&3) == 1) {
	        $color = '#FFC000';
	        // Prepare image
	        $adImage = "";
	        $ar_ad_image = $db->fetch1("SELECT * FROM `ad_images` WHERE FK_AD=".(int)$arAd['ID_AD_MASTER']);
	        if (is_array($ar_ad_image)) {
	        	$adImage = "<img src=\"".$ar_ad_image['SRC_THUMB']."\" />";
	        }
	        // Add blocked times
	        require_once $ab_path."sys/lib.ad_availability.php";
	        $mAvail = AdAvailabilityManagement::getInstance($arAd['ID_AD_MASTER'], $db);
	        $arBlocked = $mAvail->fetchByRange(date('Y-m-d', $dateStart), date('Y-m-d', $dateEnd), false, true);
	        foreach ($arBlocked as $index => $arBlock) {
	            $arEventJson = array(
	                'id'        => $index,
	                'start'     => date('Y-m-d H:i:s', $arBlock['BEGIN']),
	                'end'       => date('Y-m-d H:i:s', $arBlock['END']),
	                'title'     => "(".$arBlock['AMOUNT_BLOCKED']."/".$arBlock['AMOUNT'].") ".$adImage." ".$arAd['PRODUKTNAME'],
	                'allDay'    => false,
	                'editable'  => false,
	                'className' => "type-".$arBlock['TYPE']." ".($arBlock['AMOUNT_BLOCKED'] >= $arBlock['AMOUNT'] ? 'blocked-full' : 'blocked-partially'),
	                'amount'    => $arBlock['AMOUNT_BLOCKED'],
	                'amountMax' => $arBlock['AMOUNT'],
	                'eventList' => array()
	            );
	            $json_result[] = $arEventJson;
	        }
	    }
    }
}

/**
 * Push template variables
 */
if ($dateStart !== null) {
    $tpl_content->addvar("DATE_START", date('Y-m-d', $dateStart));
}
$tpl_content->addvar("count_ads", $countAds);
$tpl_content->addlist("liste_ads", $arAds, "tpl/".$s_lang."/ad_availability_calendar.ad.htm");

/**
 * Create chart
 */

$data = array();
$labels = array();
$dataProjectCount = 0;
$dataProfileCount = 0;

$tpl_content->addvar("JSON_EVENTS", json_encode($json_result));

?>