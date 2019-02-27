<?php

if (file_exists($GLOBALS["ab_path"]."cache/_maintenance_ad_search")) {
    require_once $GLOBALS["ab_path"]."sys/lib.ads.php";
    $adSearchOverallCount = (int)file_get_contents($GLOBALS["ab_path"]."cache/_maintenance_ad_search");
    $adSearchUpdateCount = 20;
    $listAds = $GLOBALS["db"]->fetch_table("
      SELECT a.ID_AD_MASTER, a.AD_TABLE, a.FK_USER
      FROM `ad_master` a
      WHERE a.ID_AD_MASTER NOT IN (SELECT FK_AD FROM ad_search) AND (a.STATUS&3)=1
      LIMIT ".(int)$adSearchUpdateCount);
    if (!empty($listAds)) {
        // Update search entries
        $adSearchProgressCount = 0;
		foreach ($listAds as $adIndex => $adData) {
            if (AdManagment::updateSearchDbForAd($adData['ID_AD_MASTER'], $adData['AD_TABLE'], $adData)) {
                $adSearchProgressCount++;
            } else {
                echo("Error while updating ad #".$adData['ID_AD_MASTER']."!\n");
            }
        }
        // Output status
        $adSearchRemainingCount = $GLOBALS["db"]->fetch_atom("
          SELECT COUNT(*)
          FROM `ad_master` a
          WHERE a.ID_AD_MASTER NOT IN (SELECT FK_AD FROM ad_search) AND (a.STATUS&3)=1");
        $adSearchDoneCount = $adSearchOverallCount - $adSearchRemainingCount;
        echo("Updated ".$adSearchProgressCount." ads. (".$adSearchDoneCount." / ".$adSearchOverallCount." done)\n");
    } else {
        $adSearchRemainingCount = 0;
    }
    if ($adSearchRemainingCount == 0) {
        // Update done!
        unlink($GLOBALS["ab_path"]."cache/_maintenance_ad_search");
        echo("Done updating search index.\n");
    }
}