<?php

if ($_REQUEST["done"]) {
    list($countAll, $countNew, $countUpdated, $countDeleted) = explode(",", $_REQUEST["done"]);
    $tpl_content->addvar("done", 1);
    $tpl_content->addvar("count_all", $countAll);
    $tpl_content->addvar("count_new", $countNew);
    $tpl_content->addvar("count_update", $countUpdated);
    $tpl_content->addvar("count_delete", $countDeleted);
}

$fileImportCsv = $ab_path."cache/hdb/hdb_import.csv";

if (isset($_FILES['CSV']) && ($_FILES['CSV']['error'] == 0)) {
    if (file_exists($fileImportCsv)) {
        unlink($fileImportCsv);
    }
    move_uploaded_file($_FILES['CSV']['tmp_name'], $fileImportCsv);
    die(forward("index.php?page=hdb_import&action=import"));
}

if (isset($_REQUEST['action'])) {
    switch ($_REQUEST['action']) {
        case 'import':
            $tpl_content->addvar("IMPORT_START", 1);
            $tpl_content->addvar("IMPORT_SIZE", filesize($fileImportCsv));
            unset($_SESSION['hdb_import_offset']);
            unset($_SESSION["hdb_import_status"]);
            break;
        case 'ajax_import':
            if (file_exists($fileImportCsv)) {
                require_once $ab_path."sys/lib.hdb.csv.php";
                $hdbCSV = ManufacturerDatabaseCSV::getInstance($db);
                $importDone = false;
                $importOffset = $hdbCSV->importFromFile($fileImportCsv, (int)$_SESSION['hdb_import_offset']);
                $importStatus = $_SESSION["hdb_import_status"] = array(
                    "count_all"     => (int)$_SESSION["hdb_import_status"]["count_all"] + $hdbCSV->getCountAll(),
                    "count_new"     => (int)$_SESSION["hdb_import_status"]["count_new"] + $hdbCSV->getCountNew(),
                    "count_updated" => (int)$_SESSION["hdb_import_status"]["count_updated"] + $hdbCSV->getCountUpdated(),
                    "count_deleted" => (int)$_SESSION["hdb_import_status"]["count_deleted"] + $hdbCSV->getCountDeleted(),
                );
                if ($importOffset === true) {
                    $importDone = true;
                    $importOffset = filesize($fileImportCsv);
                    unset($_SESSION['hdb_import_offset']);
                    unset($_SESSION["hdb_import_status"]);
                    unlink($fileImportCsv);
                } else {
                    $_SESSION['hdb_import_offset'] = $importOffset;
                }
                $arResult = array(
                    "done"          => $importDone,
                    "offset"        => $importOffset,
                    "status"        => $importStatus
                );
                header("Content-Type: application/json");
                die(json_encode($arResult));
            }
            break;

    }
}

?>