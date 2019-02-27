<?php

/* ###VERSIONSBLOCKINLCUDE### */

$cacheHash = sha1("stat|".__FILE__);
$title = "Anmeldungen";
$range = "day";
$arGroup = array();
$arWhere = array("STATUS=1");
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
        $arWhere[] = "CREATED >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-%d'), INTERVAL 30 DAY)";
        $arGroup[] = "YEAR(CREATED), MONTH(CREATED), DAY(CREATED)";
        $dateFormatPhp = "Y-m-d";
        $dateFormatMySQL = "%Y-%m-%d";
        $dateFormatOutput = "d-m-Y";
        $timeStart = mktime(0, 0, 0, date("m"), date("d")-30, date('Y'));
        break;
    case 'month':
        // Per month
        $title = "Aufrufe pro Monat"; // Zeitraum 13 Monate
        $range = "month";
        $arWhere[] = "CREATED >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'), INTERVAL 13 MONTH)";
        $arGroup[] = "YEAR(CREATED), MONTH(CREATED)";
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
        DATE_FORMAT(CREATED, '".$dateFormatMySQL."') as datum
      FROM vendor
      WHERE ".implode(" AND ", $arWhere)."
      GROUP BY ".implode(", ", $arGroup)." 
      ORDER BY CREATED";
    
    // Chart erstellen
    $chart = new ChartJs_Chart("line");
    #$chart->setTitle("Neuanmeldungen pro Monat");
    
    // Data-Set erstellen
    $chartData = $chart->createDataSet("rgba(7,52,254,0.4)", "rgba(41,41,100,0.7)", "Branchenbuch - Anmeldungen");
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
    // Anzahl aufaddieren
    $arData = $chartData->getData();
    $sum = 0;
    foreach ($arData as $dataIndex => $dataValue) {
        $sum += $dataValue;
        $chartData->addData($sum, $dataIndex);
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