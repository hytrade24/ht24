<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.ad_export.php';

if(isset($_POST) && isset($_POST['DO']) && $_POST['DO'] == "EXPORT") {
    // Anzeigen Exportieren

    $filter = array();
    if(isset($_POST['FK_KAT'])) $filter['FK_KAT'] = $_POST['FK_KAT'];
    if(isset($_POST['STATUS']) && $_POST['STATUS'] !== "") $filter['STATUS'] = $_POST['STATUS'];
    if(isset($_POST['FIRST_LINE_COLUMN_NAMES']) && $_POST['FIRST_LINE_COLUMN_NAMES'] !== "") $filter['FIRST_LINE_COLUMN_NAMES'] = true;
    $filter['FK_USER'] = $uid;

    $adExportManagement = AdExportManagement::getInstance($db);
    $csv = $adExportManagement->getExportAsCsvByFilter($filter);

    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=".date("Ymd_His")."_export.csv");

    echo $csv;

    die();
}