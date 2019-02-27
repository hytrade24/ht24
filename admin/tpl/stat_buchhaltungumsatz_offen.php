<?php

/* ###VERSIONSBLOCKINLCUDE### */

if (array_key_exists("ID_USER", $_REQUEST)) {
    $arWhere = " and FK_USER=".$_GET['ID_USER'];
    $tpl_content->addvar("params", "FK_USER=".$_REQUEST['ID_USER']);
}

$cacheHash = sha1("stat|".__FILE__.$arWhere);
$cacheStorage = Api_DatabaseCacheStorage::getInstance();

if ($cacheStorage->checkContentValidByHash($cacheHash) && !array_key_exists("recache", $_REQUEST)) {
    // Cache available!
    $tpl_content->addvar("CHART_JSON", $cacheStorage->getContentByHash($cacheHash));
} else {
    $range = 12;  //Monate
    $dateStart = date("Y-m-d",mktime(0, 0, 0, date("m"), date('d')-$range, date('Y')));
    $dateEnd = date("Y-m-d");
    
    /*
     * PIE CHART
     */
    $colorsBackground = ["rgba(255,0,0,1)", "rgba(0,255,0,1)", "rgba(0,0,255,1)"];
    $colorsBorder = ["#FFFFFF", "#FFFFFF", "#FFFFFF"];
    $chart = new ChartJs_Chart("doughnut");
    $chart->setTitle("Umsatzverteilung in EUR in ".$range." Monat");
    $chart->setLabels(["Unbezahlt", "Bezahlt", "Storno"]);
    
    
    $chartData = $chart->createDataSet($colorsBackground, $colorsBorder);
    
    $SummeUnbezahlt=$db->fetch_atom("SELECT
            SUM(it.QUANTITY*it.PRICE*(1+(IFNULL(tax.TAX_VALUE, 0)/100)))as ANZAHL,STATUS ,count(STATUS) as ANZAHL_INVOICE
        FROM
          billing_invoice i
        LEFT JOIN billing_invoice_item it ON it.FK_BILLING_INVOICE = i.ID_BILLING_INVOICE
            LEFT JOIN tax tax ON tax.ID_TAX = it.FK_TAX
            and STAMP_CREATE >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'),INTERVAL ".$range." MONTH)
            where i.STATUS=0 ".$arWhere."
            group by STATUS");
    $SummeBezahlt=$db->fetch_atom("SELECT
            SUM(it.QUANTITY*it.PRICE*(1+(IFNULL(tax.TAX_VALUE, 0)/100)))as ANZAHL,STATUS ,count(STATUS) as ANZAHL_INVOICE
        FROM
          billing_invoice i
        LEFT JOIN billing_invoice_item it ON it.FK_BILLING_INVOICE = i.ID_BILLING_INVOICE
            LEFT JOIN tax tax ON tax.ID_TAX = it.FK_TAX
            and STAMP_CREATE >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'),INTERVAL ".$range." MONTH)
            where i.STATUS=1 ".$arWhere."
            group by STATUS");
    $SummeStorno=$db->fetch_atom("SELECT
            SUM(it.QUANTITY*it.PRICE*(1+(IFNULL(tax.TAX_VALUE, 0)/100)))as ANZAHL,STATUS ,count(STATUS) as ANZAHL_INVOICE
        FROM
          billing_invoice i
        LEFT JOIN billing_invoice_item it ON it.FK_BILLING_INVOICE = i.ID_BILLING_INVOICE
            LEFT JOIN tax tax ON tax.ID_TAX = it.FK_TAX
            and STAMP_CREATE >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'),INTERVAL ".$range." MONTH)
            where i.STATUS=2 ".$arWhere."
            group by STATUS");
    
    
    $chartData->addData($SummeUnbezahlt);
    $chartData->addData($SummeBezahlt);
    $chartData->addData($SummeStorno);

    // Encode as json
    $chartJson = json_encode($chart);
    
    // Update cache (valid for 1 day)
    $cacheStorage->addContent($cacheHash, $chartJson, time() + Api_DatabaseCacheStorage::INTERVAL_DAY, array("STATISTIC" => 1));

    // Add to template
    $tpl_content->addvar("CHART_JSON", $chartJson);
}

?>