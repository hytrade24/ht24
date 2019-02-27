<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.ad_export.php';
require_once 'sys/lib.ad_export.filter.php';
include "admin/sys/tabledef.php";

$adExportFilterManagement = AdExportFilterManagement::getInstance();

$table = new tabledef();
$table->getTables(0, 1);

$tables = array();
foreach($table->tables as $key=>$t) {
	if($t['T_NAME'] != "artikel_master") { 
		$tables[] = $t; 
	}
}

$tpl_content->addlist("TABLES", $tables, "tpl/de/my-exports-kat.table.row.htm");
$tpl_content->addlist("FILTER", $adExportFilterManagement->getFilters(), "tpl/de/my-exports.filter.row.htm");
if(isset($_POST) && isset($_POST['DO']) && $_POST['DO'] == "EXPORT") {
    // Anzeigen Exportieren

    $filter = array();
    if(isset($_POST['KAT_TABLE'])) $filter['KAT_TABLE'] = $_POST['KAT_TABLE'];
    if(isset($_POST['STATUS']) && $_POST['STATUS'] !== "") $filter['STATUS'] = $_POST['STATUS'];
    if(isset($_POST['FILTER']) && $_POST['FILTER'] !== "") $filter['FILTER'] = $_POST['FILTER'];
    if(isset($_POST['FIRST_LINE_COLUMN_NAMES']) && $_POST['FIRST_LINE_COLUMN_NAMES'] !== "") $filter['FIRST_LINE_COLUMN_NAMES'] = true;
    $filter['FK_USER'] = $uid;

    $adExportManagement = AdExportManagement::getInstance($db);
    $csv = $adExportManagement->getExportAsCsvByFilter($filter);

    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=".date("Ymd_His")."_export.csv");

    echo $csv;

    die();
}