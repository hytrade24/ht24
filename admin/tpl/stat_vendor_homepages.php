<?php

/* ###VERSIONSBLOCKINLCUDE### */

$cacheHash = sha1("stat|".__FILE__);
$range = "day";
$arGroup = array("ACTIVE");
$arWhere = array();
$timeStart = date($dateFormatPhp);
$dateFormatPhp = "Y-m-d";
$dateFormatMySQL = "%Y-%m-%d";
if ($_REQUEST['ID_USER']>0) {
    $arWhere[] = 'FK_USER = '.$_REQUEST['ID_USER'];
    $tpl_content->addvar("params", "ID_USER=".$_REQUEST['ID_USER']);
}

switch ($_REQUEST['range']) {
    default:
    case 'day':
        // Per day
        $range = "day";
        $arWhere[] = "STAMP_CREATE >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-%d'), INTERVAL 30 DAY)";
        $arGroup[] = "YEAR(STAMP_CREATE), MONTH(STAMP_CREATE), DAY(STAMP_CREATE)";
        $dateFormatPhp = "Y-m-d";
        $dateFormatMySQL = "%Y-%m-%d";
        $dateFormatOutput = "d-m-Y";
        $timeStart = mktime(0, 0, 0, date("m"), date("d")-30, date('Y'));
        break;
    case 'month':
        // Per month
        $range = "month";
        $arWhere[] = "STAMP_CREATE >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'), INTERVAL 13 MONTH)";
        $arGroup[] = "YEAR(STAMP_CREATE), MONTH(STAMP_CREATE)";
        $dateFormatPhp = "Y-m";
        $dateFormatMySQL = "%Y-%m";
        $dateFormatOutput = "m-Y";
        $timeStart = mktime(0, 0, 0, date("m")-13, date("d"), date('Y'));
        break;
}

$cacheHash = sha1("stat|".__FILE__."|".implode(" AND ", $arWhere)."|".implode(" AND ", $arGroup));
$cacheStorage = Api_DatabaseCacheStorage::getInstance();

$tpl_content->addvar("range", $range);
$tpl_content->addvar("range_".$range, 1);

if ($cacheStorage->checkContentValidByHash($cacheHash) && !array_key_exists("recache", $_REQUEST)) {
    // Cache available!
    $tpl_content->addvar("CHART_JSON", $cacheStorage->getContentByHash($cacheHash));
} else {
    // Read data live
    $dateStart = date($dateFormatPhp, $timeStart);
    $dateEnd = date($dateFormatPhp);
    $query = "
      SELECT 
        COUNT(*) as anzahl, 
        DATE_FORMAT(STAMP_CREATE, '".$dateFormatMySQL."') as datum,
        ACTIVE as aktiv
      FROM vendor_homepage
      WHERE ".implode(" AND ", $arWhere)."
      GROUP BY ".implode(", ", $arGroup)." 
      ORDER BY STAMP_CREATE";
    
    // Chart erstellen
    $chart = new ChartJs_Chart("line");
    #$chart->setTitle("Neuanmeldungen pro Monat");
    
    // Data-Set erstellen
    $chartDataRejected = $chart->createDataSet("rgba(255,0,0,0.4)", "rgba(255,0,0,0.5)", "Abgelehnt");
    $chartDataRejected->setOption("fill", true);
    $chartDataRejected->addData(0, $dateStart);
    $chartDataRejected->addData(0, $dateEnd);
    
    $chartDataActive = $chart->createDataSet("rgba(0,255,0,0.4)", "rgba(0,255,0,0.5)", "Aktiv");
    $chartDataActive->setOption("fill", true);
    $chartDataActive->addData(0, $dateStart);
    $chartDataActive->addData(0, $dateEnd);
    
    $chartDataInactive = $chart->createDataSet("rgba(0,0,255,0.4)", "rgba(0,0,255,0.5)", "In Bearbeitung");
    $chartDataInactive->setOption("fill", true);
    $chartDataInactive->addData(0, $dateStart);
    $chartDataInactive->addData(0, $dateEnd);
    
    
    
    
    // Anzahl der Aufrufe abfragen
    $arData = $db->fetch_table($query);
    
    foreach ($arData as $index => $arValue) {
        // Betrag zum Chart hinzufügen
        switch ($arValue["aktiv"]) {
            case 2:
                // abgelehnt
                $chartDataRejected->addData($arValue["anzahl"], $arValue["datum"]);
                break;
            case 1:
                // bestaetigt
                $chartDataActive->addData($arValue["anzahl"], $arValue["datum"]);
                break;
            case 0:
                // unbestaetigt
                $chartDataInactive->addData($arValue["anzahl"], $arValue["datum"]);
                break;
        }
    }
    
    // Fehlende Daten auffüllen
    $chart->fillGaps();

// Anzahl aufaddieren
    $arDataRejected = $chartDataRejected->getData();
    $arDataActive = $chartDataActive->getData();
    $arDataInactive = $chartDataInactive->getData();
    
    
    $sumRejected = 0;
    $sumActive =0;
    $sumInactive=0;
    
    
    foreach ($arDataRejected as $dataIndex => $dataValue) {
        $sumRejected += $dataValue;
        $chartDataRejected->addData($sumRejected, $dataIndex);
    }
	foreach ($arDataActive as $dataIndex => $dataValue) {
        $sumActive += $dataValue;
        $chartDataActive->addData($sumActive, $dataIndex);
    }
    foreach ($arDataInactive as $dataIndex => $dataValue) {
        $sumInactive += $dataValue;
        $chartDataRejected->addData($sumInactive, $dataIndex);
    }


    // Datums-Format der Labels anpassen
    $chart->setLabelsByDate($dateFormatOutput);
    
    // Encode as json
    $chartJson = json_encode($chart);
    
    // Update cache (valid for 1 day)
    $cacheStorage->addContent($cacheHash, $chartJson, time() + Api_DatabaseCacheStorage::INTERVAL_DAY, array("STATISTIC" => 1));
    
    // Add to template
    $tpl_content->addvar("CHART_JSON", $chartJson);
}

?>
