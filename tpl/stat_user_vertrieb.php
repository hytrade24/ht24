<?php

/* ###VERSIONSBLOCKINLCUDE### */

$cacheHash = sha1("stat|".__FILE__);
$range = "day";
$arGroup = array();
$arWhere = array("bi.FK_USER_SALES = ".(int)$uid);
$timeStart = date($dateFormatPhp);
$dateFormatPhp = "Y-m-d";
$dateFormatMySQL = "%Y-%m-%d";
$backgroundColor = "";

if (array_key_exists('bg', $_REQUEST)) {
  $backgroundColor = $_REQUEST['bg'];
  $tpl_content->addvar("params", "bg=".$backgroundColor);
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

$cacheHash = sha1("stat|".__FILE__."|".implode(" AND ", $arWhere)."|".implode(" AND ", $arGroup)."|".$backgroundColor);
$cacheStorage = Api_DatabaseCacheStorage::getInstance();

$tpl_content->addvar("hash", $cacheHash);
$tpl_content->addvar("range", $range);
$tpl_content->addvar("range_".$range, 1);

if ($cacheStorage->checkContentValidByHash($cacheHash)) {
    // Cache available!
    $tpl_content->addvar("CHART_JSON", $cacheStorage->getContentByHash($cacheHash));
} else {
    // Read data live
    $dateStart = date($dateFormatPhp, $timeStart);
    $dateEnd = date($dateFormatPhp); 
    $query = "
      SELECT
        ROUND(SUM(bs.AMOUNT_PROV),2) as betrag,
        COUNT(*) as anzahl, 
        DATE_FORMAT(STAMP_CREATE, '".$dateFormatMySQL."') as datum
      FROM `billing_sales` bs
      LEFT JOIN `billing_invoice` bi ON bs.FK_BILLING_INVOICE = bi.ID_BILLING_INVOICE
      WHERE ".implode(" AND ", $arWhere)."
      GROUP BY ".implode(", ", $arGroup)." 
      ORDER BY STAMP_CREATE";
    
    // Translations
    /* $strTitle ="Translation::readTranslation("marketplace", "statistic.invoices.title", null, array(), "Rechnungsstatus");*/
    $strCountTransactions = Translation::readTranslation("marketplace", "statistic.count.transactions", null, array(), "Transaktionen");
    $strAmount = Translation::readTranslation("marketplace", "statistic.amount.provision", null, array(), "Provision");
  
    // Chart erstellen
    $chart = new ChartJs_Chart("line");
    /*$chart->setTitle($strTitle);*/
    $chart->setOption("scales", array(
      "yAxes" => [
        array(
          "id"          => "A",
          "type"        => "linear",
          "position"    => "left",
          "gridLines"   => array(
            "color"       => "rgba(255, 0, 0, 0.4)"
          ),
          "scaleLabel"  => array(
            "display"     => true,
            "labelString" => $strAmount,
            "fontColor"   => "rgba(255, 0, 0, 0.4)"
          ),
          "ticks"       => array(
            "min"         => 0,
            "fontColor"   => "rgba(255, 0, 0, 0.4)"
          )
        ),
        array(
          "id"        => "B",
          "type"      => "linear",
          "position"  => "right",
          "gridLines" => array(
            "color"     => "rgba(0, 0, 255, 0.4)"
          ),
          "scaleLabel"  => array(
            "display"     => true,
            "labelString" => $strCountTransactions,
            "fontColor"   => "rgba(0, 0, 255, 0.4)"
          ),
          "ticks"       => array(
            "min"         => 0,
            "fontColor"   => "rgba(0, 0, 255, 0.4)"
          )
        )
      ]
    ));
    
    // Data-Set erstellen
    $dataSetAmount = $chart->createDataSet("rgba(255,0,0,0.4)", "rgba(255,0,0,0.5)", $strAmount);
    $dataSetAmount->setOption("yAxisID", "A");
    $dataSetAmount->setOption("fill", true);
    $dataSetAmount->addData(0, $dateStart);
    $dataSetAmount->addData(0, $dateEnd);
  
    $dataSetCount = $chart->createDataSet("rgba(0,0,255,0.4)", "rgba(0,0,255,0.5)", $strCountTransactions);
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
    $cacheStorage->addContent($cacheHash, $chartJson, time() + Api_DatabaseCacheStorage::INTERVAL_DAY, array("STATISTIC" => 1));
    
    // Add to template
    $tpl_content->addvar("CHART_JSON", $chartJson);
}

?>