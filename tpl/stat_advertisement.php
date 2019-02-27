<?php
/* ###VERSIONSBLOCKINLCUDE### */



$id_ad_user = ($_REQUEST["id_ad_user"] ? $_REQUEST["id_ad_user"] : $ar_params[1]);
$num_days = 30;



$tpl_content->addvar("ID_ADVERTISEMENT_USER", $id_ad_user);
/*

$ar_stats = $db->fetch_nar("
	SELECT
		DATEDIFF(CURDATE(), `STAMP`),
		`COUNT`
	FROM
		`advertisement_view`
	WHERE
		`STAMP` >= (CURDATE() - INTERVAL ".$num_days." DAY) AND
		FK_ADVERTISEMENT_USER=".$id_ad_user);
  */      
  /*
  $ar_stats = $db->fetch_nar("
	select DATEDIFF(CURDATE(), `STAMP`),sum(`COUNT`) as `COUNT` from `advertisement_stat`
		where `STAMP` >= (CURDATE() - INTERVAL ".$num_days." DAY) AND FK_ADVERTISEMENT_USER=".$id_ad_user." group by `STAMP`");
  */

$cacheHash = sha1("stat|".__FILE__);
$range = "day";
$arGroup = array();
$arWhere = array("FK_ADVERTISEMENT_USER = ".(int)$id_ad_user);
$timeStart = date($dateFormatPhp);
$dateFormatPhp = "Y-m-d";
$dateFormatMySQL = "%Y-%m-%d";
$backgroundColor = "";

if (array_key_exists('bg', $_REQUEST)) {
  $backgroundColor = $_REQUEST['bg'];
}
$tpl_content->addvar("params", "id_ad_user=".$id_ad_user."&bg=".$backgroundColor);

switch ($_REQUEST['range']) {
    default:
    case 'day':
        // Per day
        $range = "day";
        $arWhere[] = "`STAMP` >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-%d'), INTERVAL 30 DAY)";
        $arGroup[] = "YEAR(`STAMP`), MONTH(`STAMP`), DAY(`STAMP`)";
        $dateFormatPhp = "Y-m-d";
        $dateFormatMySQL = "%Y-%m-%d";
        $dateFormatOutput = "d-m-Y";
        $timeStart = mktime(0, 0, 0, date("m"), date("d")-30, date('Y'));
        break;
    case 'month':
        // Per month
        $range = "month";
        $arWhere[] = "`STAMP` >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'), INTERVAL 13 MONTH)";
        $arGroup[] = "YEAR(`STAMP`), MONTH(`STAMP`)";
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
        SUM(`COUNT`) as anzahl, 
        DATE_FORMAT(`STAMP`, '".$dateFormatMySQL."') as datum
      FROM `advertisement_stat`
      WHERE ".implode(" AND ", $arWhere)."
      GROUP BY ".implode(", ", $arGroup)." 
      ORDER BY `STAMP`";
    
    // Translations
    /* $strTitle ="Translation::readTranslation("marketplace", "statistic.invoices.title", null, array(), "Rechnungsstatus");*/
    $strCountViews = Translation::readTranslation("marketplace", "statistic.count.advertisement.views", null, array(), "Views");
  
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
            "labelString" => $strCountViews,
            "fontColor"   => "rgba(255, 0, 0, 0.4)"
          ),
          "ticks"       => array(
            "min"         => 0,
            "fontColor"   => "rgba(255, 0, 0, 0.4)"
          )
        )
      ]
    ));
    
    // Data-Set erstellen
    $dataSetViews = $chart->createDataSet("rgba(255,0,0,0.4)", "rgba(255,0,0,0.5)", $strCountViews);
    $dataSetViews->setOption("yAxisID", "A");
    $dataSetViews->setOption("fill", true);
    $dataSetViews->addData(0, $dateStart);
    $dataSetViews->addData(0, $dateEnd);
    
    // Anzahl der Aufrufe abfragen
    $arData = $db->fetch_table($query);
    
    foreach ($arData as $index => $arValue) {
        // Betrag und Anzahl zum Chart hinzufügen
        $dataSetViews->addData($arValue["anzahl"], $arValue["datum"]);
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
  
/*
$stamp_today = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
$interval_day = (3600 * 24);

$value_max = 0;
$ar_dates = array();
$ar_values = array();
for ($day = $num_days; $day >= 0; $day--) {
	$stamp = $stamp_today - ($day * $interval_day);
	$ar_dates[] = date("d.m.Y", $stamp);
	if (!empty($ar_stats[$day])) {
		$ar_values[] = $ar_stats[$day];
		if ($ar_stats[$day] > $value_max)
			$value_max = $ar_stats[$day];
	} else {
		$ar_values[] = 0;		
	}
}

$tpl_content->addvar("DATES", implode(",", $ar_dates));
$tpl_content->addvar("VIEWS", implode(",", $ar_values));
$tpl_content->addvar("VIEWS_MAX", $value_max);

?>
  */