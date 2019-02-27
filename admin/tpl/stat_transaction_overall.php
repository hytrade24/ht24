<?php

/* ###VERSIONSBLOCKINLCUDE### */


$cacheHash = sha1("stat|".__FILE__);
$cacheStorage = Api_DatabaseCacheStorage::getInstance();

if ($cacheStorage->checkContentValidByHash($cacheHash) && !array_key_exists("recache", $_REQUEST)) {
    // Cache available!
    $tpl_content->addvar("CHART_JSON", $cacheStorage->getContentByHash($cacheHash));
} else {
    // Chart erstellen
    $chart = new ChartJs_Chart("doughnut");

    // Data-Set erstellen
    $dataSet = $chart->createDataSet("#000000", "#FFFFFF");
    
    $arColorsBackground = array();

    $query = "select count(*) as anzahl, STATUS_CONFIRMATION from ad_order group by STATUS_CONFIRMATION";
    $arData = $db->fetch_table($query);
    $arLabels = array();
    foreach ($arData as $index => $arValue) {
        // Anzahl zum Chart hinzufügen    
        $dataSet->addData($arValue["anzahl"]);
        // Titel erzeugen
        switch ($arValue["STATUS_CONFIRMATION"]) {
            case 0:
                $arColorsBackground[] = 'rgba(0,0,255,0.4)';
                $gtitel = 'Unbestätigt';
                break;
            case 1:
                $arColorsBackground[] = 'rgba(0,255,0,0.4)';
                $gtitel = 'Verkauft';
                break;
            case 2:
                $arColorsBackground[] = 'rgba(255,0,0,0.4)';
                $gtitel = 'Abgelehnt';
                break;

        }
        $arLabels[] = $gtitel;
    }

    // Farben definieren
    $dataSet->setBackgroundColor($arColorsBackground);
    // Labels definieren
    $chart->setLabels($arLabels);

    // Encode as json
    $chartJson = json_encode($chart);
    
    // Update cache (valid for 1 day)
    $cacheStorage->addContent($cacheHash, $chartJson, time() + Api_DatabaseCacheStorage::INTERVAL_DAY, array("STATISTIC" => 1));

    // Add to template
    $tpl_content->addvar("CHART_JSON", $chartJson);
}

?>