<?php

/* ###VERSIONSBLOCKINLCUDE### */

$cacheHash = sha1("stat|".__FILE__);
$range = "day";
$arGroup = array("STATUS");
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
        ROUND(SUM(it.QUANTITY*it.PRICE*(1+(IFNULL(tax.TAX_VALUE, 0)/100))),2) as betrag,
        COUNT(*) as anzahl, 
        DATE_FORMAT(STAMP_CREATE, '".$dateFormatMySQL."') as datum,
        STATUS
      FROM billing_invoice i
      LEFT JOIN billing_invoice_item it ON it.FK_BILLING_INVOICE = i.ID_BILLING_INVOICE
      LEFT JOIN tax tax ON tax.ID_TAX = it.FK_TAX
      WHERE ".implode(" AND ", $arWhere)."
      GROUP BY ".implode(", ", $arGroup)." 
      ORDER BY STAMP_CREATE";
    
    // Chart erstellen
    $chart = new ChartJs_Chart("line");
    #$chart->setTitle("Neuanmeldungen pro Monat");
    
    // Data-Set erstellen
    $chartDataOpen = $chart->createDataSet("rgba(0,0,255,0.4)", "rgba(0,0,255,0.5)", "Offen");
    $chartDataOpen->setOption("fill", true);
    $chartDataOpen->addData(0, $dateStart);
    $chartDataOpen->addData(0, $dateEnd);
    
    $chartDataPaid = $chart->createDataSet("rgba(0,255,0,0.4)", "rgba(0,255,0,0.5)", "Bezahlt");
    $chartDataPaid->setOption("fill", true);
    $chartDataPaid->addData(0, $dateStart);
    $chartDataPaid->addData(0, $dateEnd);
    
    $chartDataStorno = $chart->createDataSet("rgba(255,0,0,0.4)", "rgba(255,0,0,0.5)", "Storniert");
    $chartDataStorno->setOption("fill", true);
    $chartDataStorno->addData(0, $dateStart);
    $chartDataStorno->addData(0, $dateEnd);
    
    // Anzahl der Aufrufe abfragen
    $arData = $db->fetch_table($query);
    
    foreach ($arData as $index => $arValue) {
        // Betrag zum Chart hinzufügen
        switch ($arValue['STATUS']) {
            case 0:
                // Offen
                $chartDataOpen->addData($arValue["betrag"], $arValue["datum"]);
                break;
            case 1:
                // Bezahlt
                $chartDataPaid->addData($arValue["betrag"], $arValue["datum"]);
                break;
            case 2:
                // Storniert
                $chartDataStorno->addData($arValue["betrag"], $arValue["datum"]);
                break;
        }
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