<?php

/* ###VERSIONSBLOCKINLCUDE### */


$cacheHash = sha1("stat|".__FILE__);
$cacheStorage = Api_DatabaseCacheStorage::getInstance();

if ($cacheStorage->checkContentValidByHash($cacheHash) && !array_key_exists("recache", $_REQUEST)) {
    // Cache available!
    $tpl_content->addvar("CHART_JSON", $cacheStorage->getContentByHash($cacheHash));
} else {
    $chart = new ChartJs_Chart("doughnut");
  
    // Farben
    $ar_colors = array(
        'rgba(0,0,255,0.4)',
        'rgba(0,255,0,0.4)',
        'rgba(255,255,0,0.4)',
        'rgba(255,0,0,0.4)',
        'rgba(255,0,255,0.4)',
        'rgba(0,255,255,0.4)',
        'rgba(255,255,255,0.4)',
        'rgba(0,0,0,0.4)',
        'rgba(128,0,0,0.4)',
        'rgba(0,0,128,0.4)',
    );
  
    // Chart erstellen
    $dataSet = $chart->createDataSet($ar_colors, "#FFFFFF", $arValue["V1"]);
    
    $query = "SELECT V1, (select count(*) from user where FK_USERGROUP=ID_USERGROUP AND STAT = 1) as anzahl
        FROM `usergroup` g
          LEFT JOIN `string_usergroup` s ON
            s.FK=g.ID_USERGROUP AND s.S_TABLE='usergroup' AND
            s.BF_LANG=if(g.BF_LANG_USERGROUP & ".$langval.", ".$langval.", 1 << floor(log(g.BF_LANG_USERGROUP+0.5)/log(2)))
            order by F_ORDER";
    $arData = $db->fetch_table($query);
    foreach ($arData as $index => $arValue) {
        // Anzahl zum Chart hinzufügen    
        $dataSet->addData($arValue["anzahl"]);    
        $label[]=$arValue["V1"];
    }    
    
    $chart->setLabels($label);

    // Encode as json
    $chartJson = json_encode($chart);
    
    // Update cache (valid for 1 day)
    $cacheStorage->addContent($cacheHash, $chartJson, time() + Api_DatabaseCacheStorage::INTERVAL_DAY, array("STATISTIC" => 1));

    // Add to template
    $tpl_content->addvar("CHART_JSON", $chartJson);
}

?>