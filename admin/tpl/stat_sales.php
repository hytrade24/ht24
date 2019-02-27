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

$arColorsBackground = array(
    'rgba(0,0,255,0.4)',
    'rgba(0,255,0,0.4)',
    'rgba(255,255,0,0.4)',
    'rgba(255,0,0,0.4)',
    'rgba(255,0,255,0.4)',
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
    'rgba(255,0,255,0.7)',
    'rgba(0,255,255,0.7)',
    'rgba(255,255,255,0.7)',
    'rgba(0,0,0,0.7)',
    'rgba(128,0,0,0.7)',
    'rgba(0,0,128,0.7)'
);

$title = 'Umsatzübersicht für den Vertrieb';

if (array_key_exists("ID_USER", $_REQUEST)) {
    $arWhere[] = "bi.FK_USER_SALES=".(int)$_REQUEST['ID_USER'];
    $tpl_content->addvar("params", "ID_USER=".$_REQUEST['ID_USER']);
    $userName = $db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".(int)$_REQUEST['ID_USER']);
    $title = 'Umsatz des Vertrieblers "'.$userName.'"';
} else {
    $arWhere[] = "bi.FK_USER_SALES>0";
}

switch ($_REQUEST['range']) {
    default:
    case 'day':
        // Per day
        $title .= " pro Tag"; // Zeitraum 30 Tage
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
        $title .= " pro Monat"; // Zeitraum 13 Monate
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
        ROUND(SUM(bit.QUANTITY*bit.PRICE),2) as betrag,
        DATE_FORMAT(STAMP_CREATE, '".$dateFormatMySQL."') as datum
    FROM `billing_invoice` bi
    LEFT JOIN billing_invoice_item bit ON bit.FK_BILLING_INVOICE = bi.ID_BILLING_INVOICE
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
            "color"       => $arColorsBorder[3]
          ),
          "scaleLabel"  => array(
            "display"     => true,
            "labelString" => "Betrag",
            "fontColor"   => $arColorsBorder[3]
          ),
          "ticks"       => array(
            "min"         => 0,
            "fontColor"   => $arColorsBorder[3]
          )
        ),
        array(
          "id"        => "B",
          "type"      => "linear",
          "position"  => "right",
          "gridLines" => array(
            "color"     => $arColorsBorder[0]
          ),
          "scaleLabel"  => array(
            "display"     => true,
            "labelString" => "Transaktionen",
            "fontColor"   => $arColorsBorder[0]
          ),
          "ticks"       => array(
            "min"         => 0,
            "fontColor"   => $arColorsBorder[0]
          )
        )
      ]
    ));
    
    // Data-Set erstellen (Betrag)
    $chartDataAmount = $chart->createDataSet($arColorsBackground[3], $arColorsBorder[3], "Betrag");
    $chartDataAmount->setOption("yAxisID", "A");
    $chartDataAmount->setOption("fill", true);
    $chartDataAmount->addData(0, $dateStart);
    $chartDataAmount->addData(0, $dateEnd);
    
    // Data-Set erstellen (Transaktionen)
    $chartDataCount = $chart->createDataSet($arColorsBackground[0], $arColorsBorder[0], "Transaktionen");
    $chartDataCount->setOption("yAxisID", "B");
    $chartDataCount->setOption("fill", true);
    $chartDataCount->addData(0, $dateStart);
    $chartDataCount->addData(0, $dateEnd);
    
    // Anzahl der Aufrufe abfragen
    $arData = $db->fetch_table($query);
    foreach ($arData as $index => $arValue) {
        // Betrag zum Chart hinzufügen
        $chartDataAmount->addData($arValue["betrag"], $arValue["datum"]);
        $chartDataCount->addData($arValue["anzahl"], $arValue["datum"]);
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