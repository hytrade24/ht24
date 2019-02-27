<?php

/* ###VERSIONSBLOCKINLCUDE### */

$cacheHash = sha1("stat|".__FILE__);
$title = "Blogansichten";
$range = "day";
$arGroup = array();
$arWhere = array();
$timeStart = time();
$dateFormatPhp = "Y-m-d";
$dateFormatMySQL = "%Y-%m-%d";

$arColorsBackground = array(
    'rgba(0,0,255,0.4)',
    'rgba(0,255,0,0.4)',
    'rgba(255,255,0,0.4)',
    'rgba(255,0,0,0.4)',
    'rgba(44,142,143,0.4)',
    'rgba(0,255,255,0.4)',
    'rgba(255,255,255,0.4)',
    'rgba(0,0,0,0.4)',
    'rgba(128,0,0,0.4)',
    'rgba(0,0,128,0.4)'
);
$arColorsBorder = array(
    'rgba(0,0,255,0.7)',
    'rgba(0,255,0,0.7)',
    'rgba(255,255,0,0.7)',
    'rgba(255,0,0,0.7)',
    'rgba(44,142,143,0.4)',
    'rgba(0,255,255,0.7)',
    'rgba(255,255,255,0.7)',
    'rgba(0,0,0,0.7)',
    'rgba(128,0,0,0.7)',
    'rgba(0,0,128,0.7)'
);

if (array_key_exists("ID_NEWS", $_REQUEST)) {
    $arWhere[] = "FK_NEWS=".$_GET['ID_NEWS'];
    $tpl_content->addvar("params", "ID_NEWS=".$_REQUEST['ID_NEWS']);
}
$colorBackground = $arColorsBackground[4];
$colorBorder = $arColorsBorder[4];

switch ($_REQUEST['range']) {
    default:
    case 'day':
        // Per day
        $title = "Blogansicht pro Tag"; // Zeitraum 30 Tage
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
        $title = "Blogansicht pro Monat"; // Zeitraum 13 Monate
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
        sum(VIEWS) as anzahl,
        DATE_FORMAT(STAMP, '".$dateFormatMySQL."') as datum
      FROM newsview
      WHERE ".implode(" AND ", $arWhere)."
      GROUP BY ".implode(", ", $arGroup)." 
      ORDER BY STAMP";
    
    // Chart erstellen
    $chart = new ChartJs_Chart("line");
    #$chart->setTitle("Neuanmeldungen pro Monat");
    
    // Data-Set erstellen
    $chartData = $chart->createDataSet($colorBackground, $colorBorder, $title);
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