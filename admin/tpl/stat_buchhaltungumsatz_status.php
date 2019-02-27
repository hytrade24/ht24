<?php

/* ###VERSIONSBLOCKINLCUDE### */

$cacheHash = sha1("stat|".__FILE__);
$range = "day";
$arGroup = array("STATUS");
$arWhere = array();
$timeStart = date($dateFormatPhp);
$dateFormatPhp = "Y-m-d";
$dateFormatMySQL = "%Y-%m-%d";
$colorIndex = 0;
$title = "Rechnungsstatus";
if ($_REQUEST['STATUS']>0) {
    $arWhere[] = 'i.STATUS = '.$_REQUEST['STATUS'];
    $tpl_content->addvar("params", "STATUS=".$_REQUEST['STATUS']);
    switch ($_REQUEST['STATUS']) {
        case 0:
            $colorIndex = 0;
            $title = "Rechnungsstatus unbezahlt";
            break;
        case 1:
            $colorIndex = 2;
            $title = "Rechnungsstatus bezahlt";
            break;
        case 2:
            $colorIndex = 1;
            $title = "Rechnungsstatus storniert";
            break;
    }
}

$colorIndex = (int)$_REQUEST['STATUS'];

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
    'rgba(0,0,220,0.7)',
    'rgba(0,220,0,0.7)',
    'rgba(220,220,0,0.7)',
    'rgba(220,0,0,0.7)',
    'rgba(44,142,143,0.4)',
    'rgba(0,255,255,0.7)',
    'rgba(255,255,255,0.7)',
    'rgba(0,0,0,0.7)',
    'rgba(128,0,0,0.7)',
    'rgba(0,0,128,0.7)'
);



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
        COUNT(DISTINCT i.ID_BILLING_INVOICE) as anzahl, 
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
    $chart->setTitle($title);
    $chart->setOption("scales", array(
      "yAxes" => [
        array(
          "id"          => "A",
          "type"        => "linear",
          "position"    => "left",
          "gridLines"   => array(
            "color"       => $arColorsBorder[$colorIndex]
          ),
          "scaleLabel"  => array(
            "display"     => true,
            "labelString" => "Betrag",
            "fontColor"   => $arColorsBorder[$colorIndex]
          ),
          "ticks"       => array(
            "min"         => 0,
            "fontColor"   => $arColorsBorder[$colorIndex]
          )
        ),
        array(
          "id"        => "B",
          "type"      => "linear",
          "position"  => "right",
          "gridLines" => array(
            "color"     => $arColorsBorder[7]
          ),
          "scaleLabel"  => array(
            "display"     => true,
            "labelString" => "Anzahl Rechnungen",
            "fontColor"   => $arColorsBorder[7]
          ),
          "ticks"       => array(
            "min"         => 0,
            "fontColor"   => $arColorsBorder[7]
          )
        )
      ]
    ));
    
    // Data-Set erstellen
    $dataSetAmount = $chart->createDataSet($arColorsBackground[$colorIndex], $arColorsBorder[$colorIndex], "Betrag");
    $dataSetAmount->setOption("yAxisID", "A");
    $dataSetAmount->setOption("fill", true);
    $dataSetAmount->addData(0, $dateStart);
    $dataSetAmount->addData(0, $dateEnd);
  
    $dataSetCount = $chart->createDataSet($arColorsBackground[7], $arColorsBorder[7], "Anzahl Rechnungen");
    $dataSetCount->setOption("yAxisID", "B");
    $dataSetCount->setOption("fill", true);
    $dataSetCount->addData(0, $dateStart);
    $dataSetCount->addData(0, $dateEnd);
    
    // Anzahl der Aufrufe abfragen
    $arData = $db->fetch_table($query);
    
    foreach ($arData as $index => $arValue) {
        // Betrag und Anzahl zum Chart hinzufügen
        $dataSetAmount->addData($arValue["betrag"], $arValue["datum"]);
        $dataSetCount->addData($arValue["anzahl"], $arValue["datum"]);
    }
    
    // Fehlende Daten auffüllen
    $chart->fillGaps();
    
    // Datums-Format der Labels anpassen
    $chart->setLabelsByDate($dateFormatOutput);
    
    // Encode as json
    $chartJson = json_encode($chart);
    
    // Update cache (valid for 1 day)
    $cacheStorage->addContent($cacheHash, $chartJson, time() + Api_DatabaseCacheStorage::INTERVAL_CACHE_DEFAULT, array("STATISTIC" => 1));
    
    // Add to template
    $tpl_content->addvar("CHART_JSON", $chartJson);
}

?>