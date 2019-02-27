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
if ($_REQUEST['FK_AD'] > 0) {
  $arWhere[] = 'FK_AD='.$_REQUEST['FK_AD'];
  $tpl_content->addvar("params", "FK_AD=".$_REQUEST['FK_AD']);
}

switch ($_REQUEST['range']) {
    default:
    case 'day':
        // Per day
        $title = "Aufrufe pro Tag"; // Zeitraum 30 Tage
        $range = "day";
        $arWhere[] = "DATUM >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-%d'), INTERVAL 30 DAY)";
        $arGroup[] = "YEAR(DATUM), MONTH(DATUM), DAY(DATUM)";
        $dateFormatPhp = "Y-m-d";
        $dateFormatMySQL = "%Y-%m-%d";
        $dateFormatOutput = "d-m-Y";
        $timeStart = mktime(0, 0, 0, date("m"), date("d")-30, date('Y'));
        break;
    case 'month':
        // Per month
        $title = "Aufrufe pro Monat"; // Zeitraum 13 Monate
        $range = "month";
        $arWhere[] = "DATUM >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'), INTERVAL 13 MONTH)";
        $arGroup[] = "YEAR(DATUM), MONTH(DATUM)";
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
        DATE_FORMAT(DATUM, '".$dateFormatMySQL."') as datum
      FROM article_stats
      WHERE ".implode(" AND ", $arWhere)."
      GROUP BY ".implode(", ", $arGroup)." 
      ORDER BY DATUM";
    
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