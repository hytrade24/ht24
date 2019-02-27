<?php

/* ###VERSIONSBLOCKINLCUDE### */

$cacheHash = sha1("stat|".__FILE__);
$title = "Anmeldungen";
$range = "day";
$arGroup = array();
$arWhere = array();
$timeStart = time();
$dateFormatPhp = "Y-m-d";
$dateFormatMySQL = "%Y-%m-%d";

if ( isset($_REQUEST["ID_USER"]) && $_REQUEST["ID_USER"] != "" ) {
    $arWhere[] = "FK_USER=".$_REQUEST['ID_USER'];
    $tpl_content->addvar("params", "ID_USER=".$_REQUEST['ID_USER']);
}

switch ($_REQUEST['range']) {
    default:
    case 'day':
        // Per day
        $title = "Aufrufe pro Tag"; // Zeitraum 30 Tage
        $range = "day";
        $arWhere[] = "STAMP >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-%d'), INTERVAL 30 DAY)";
        $arGroup[] = "YEAR(STAMP), MONTH(STAMP), DAY(STAMP)";
        $dateFormatPhp = "Y-m-d";
        $dateFormatMySQL = "%Y-%m-%d";
        $dateFormatOutput = "d-m-Y";
        $timeStart = mktime(0, 0, 0, date("m"), date("d")-30, date('Y'));
        break;
    case 'month':
        // Per month
        $title = "Aufrufe pro Monat"; // Zeitraum 13 Monate
        $range = "month";
        $arWhere[] = "STAMP >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'), INTERVAL 13 MONTH)";
        $arGroup[] = "YEAR(STAMP), MONTH(STAMP)";
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
        SUM(VIEWS) as anzahl, 
        DATE_FORMAT(STAMP, '".$dateFormatMySQL."') as datum
      FROM user_views
      WHERE ".implode(" AND ", $arWhere)."
      GROUP BY ".implode(", ", $arGroup)." 
      ORDER BY STAMP";
    
    // Chart erstellen
    $chart = new ChartJs_Chart("line");
    #$chart->setTitle("Neuanmeldungen pro Monat");
    
    // Data-Set erstellen
    $chartData = $chart->createDataSet("rgba(7,52,254,0.4)", "rgba(7,52,254,0.5)", $title);
    $chartData->setOption("fill", true);
    $chartData->addData(0, $dateStart);
    $chartData->addData(0, $dateEnd);
    
    // Anzahl der Aufrufe abfragen
    $arData = $db->fetch_table($query);
    foreach ($arData as $index => $arValue) {
        // Betrag zum Chart hinzufügen
        $chartData->addData($arValue["anzahl"], $arValue["datum"]);
    }
        
    // Fehlende Daten auffüllen
    $chart->fillGaps();
    
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