<?php

/* ###VERSIONSBLOCKINLCUDE### */


$cacheHash = sha1("stat|".__FILE__);
$cacheStorage = Api_DatabaseCacheStorage::getInstance();

if ($cacheStorage->checkContentValidByHash($cacheHash) && !array_key_exists("recache", $_REQUEST)) {
  // Cache available!
  $tpl_content->addvar("CHART_JSON", $cacheStorage->getContentByHash($cacheHash));
} else {
  $range = 12;  //Monate
  $dateStart = date("Y-m-d",mktime(0, 0, 0, date("m"), date('d')-$range, date('Y')));
  $dateEnd = date("Y-m-d");
  
  /*
   * BAR CHART
   */
  $colorsBackground = ["rgba(255,0,0,1)", "rgba(0,255,0,1)", "rgba(0,0,255,1)"];
  $colorsBorder = ["#FFA000", "#FFA000", "#FFA000"];
  $chart = new ChartJs_Chart("bar");
  $chart->setTitle("Anzahl der Rechnungen in ".$range." Monat");
  
  $SummeUnbezahlt=$db->fetch_atom("SELECT
          count(STATUS) as ANZAHL_INVOICE
      FROM
        billing_invoice i
      LEFT JOIN billing_invoice_item it ON it.FK_BILLING_INVOICE = i.ID_BILLING_INVOICE
          LEFT JOIN tax tax ON tax.ID_TAX = it.FK_TAX
          and STAMP_CREATE >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'),INTERVAL ".$range." MONTH)
          where i.STATUS=0
          group by STATUS");
  $SummeBezahlt=$db->fetch_atom("SELECT
          count(STATUS) as ANZAHL_INVOICE
      FROM
        billing_invoice i
      LEFT JOIN billing_invoice_item it ON it.FK_BILLING_INVOICE = i.ID_BILLING_INVOICE
          LEFT JOIN tax tax ON tax.ID_TAX = it.FK_TAX
          and STAMP_CREATE >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'),INTERVAL ".$range." MONTH)
          where i.STATUS=1
          group by STATUS");
  $SummeStorno=$db->fetch_atom("SELECT
          count(STATUS) as ANZAHL_INVOICE
      FROM
        billing_invoice i
      LEFT JOIN billing_invoice_item it ON it.FK_BILLING_INVOICE = i.ID_BILLING_INVOICE
          LEFT JOIN tax tax ON tax.ID_TAX = it.FK_TAX
          and STAMP_CREATE >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'),INTERVAL ".$range." MONTH)
          where i.STATUS=2
          group by STATUS");
  
  $chart->createDataSet("rgba(255,0,0,1)", "#FFA000", "Unbezahlt")
      ->addData($SummeUnbezahlt);
  
  $chart->createDataSet("rgba(0,255,0,1)", "#FFA000", "Bezahlt")
      ->addData($SummeBezahlt);
  
  $chart->createDataSet("rgba(0,0,255,1)", "#FFA000", "Storno")
      ->addData($SummeStorno);

  // Encode as json
  $chartJson = json_encode($chart);
  
  // Update cache (valid for 1 day)
  $cacheStorage->addContent($cacheHash, $chartJson, time() + Api_DatabaseCacheStorage::INTERVAL_DAY, array("STATISTIC" => 1));

  // Add to template
  $tpl_content->addvar("CHART_JSON", $chartJson);
}

?>