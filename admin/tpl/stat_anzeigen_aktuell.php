<?php

/* ###VERSIONSBLOCKINLCUDE### */


$cacheHash = sha1("stat|".__FILE__);
$cacheStorage = Api_DatabaseCacheStorage::getInstance();

if ($cacheStorage->checkContentValidByHash($cacheHash) && !array_key_exists("recache", $_REQUEST)) {
    // Cache available!
    $tpl_content->addvar("CHART_JSON", $cacheStorage->getContentByHash($cacheHash));
} else {
    $range = 13;  //Tage
    $dateStart = date("Y-m-d",mktime(0, 0, 0, date("m"), date('d')-$range, date('Y')));
    $dateEnd = date("Y-m-d");
    
    /*
     * PIE CHART
     */
    $colorsBackground = ["#FFA0A0", "#FFFFA0", "#A0A0FF"];
    $colorsBorder = ["#FFA0A0", "#FFFFA0", "#A0A0FF"];
    $chart = new ChartJs_Chart("doughnut");
    $chart->setTitle("Anzeigenverteilung");
    $chart->setLabels(["Aktiv", "Deaktiviert", "Verkauft","Ausgelaufen"]);
    
    
    $chartData = $chart->createDataSet($colorsBackground, $colorsBorder);
    
    $Summe=$db->fetch_atom("SELECT count(*) FROM `ad_master` a");
    $SummeAktiv=$db->fetch_atom("SELECT count(*) FROM `ad_master` a WHERE (a.STATUS&3)=1 AND a.DELETED=0");
    $SummeAusgelaufen=$db->fetch_atom("SELECT count(*)FROM `ad_master` a WHERE (a.STATUS&3)=2 AND a.DELETED=0 AND a.STAMP_DEACTIVATE IS NULL");
    $SummeVerkauft=$db->fetch_atom("SELECT count(*)FROM `ad_master` a WHERE (a.STATUS&3)=0 AND a.DELETED=0 AND a.STAMP_DEACTIVATE IS NULL");
    $SummeDeaktiviert = $Summe-$SummeAktiv-$SummeAusgelaufen-$SummeVerkauft;
    
    $chartData->addData($SummeAktiv);
    $chartData->addData($SummeDeaktiviert);
    $chartData->addData($SummeVerkauft);
    $chartData->addData($SummeAusgelaufen);

    // Encode as json
    $chartJson = json_encode($chart);
    
    // Update cache (valid for 1 day)
    $cacheStorage->addContent($cacheHash, $chartJson, time() + Api_DatabaseCacheStorage::INTERVAL_DAY, array("STATISTIC" => 1));

    // Add to template
    $tpl_content->addvar("CHART_JSON", $chartJson);
}

?>