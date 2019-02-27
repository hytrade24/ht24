<?php

$pagerLimit = 15;
$pagerIndex = ($_REQUEST['npage'] > 0 ? (int)$_REQUEST['npage'] : 1);
$pagerOffset = ($_REQUEST['npage'] > 0 ? ($pagerIndex - 1) * $pagerLimit : 0);

$configFile = $ab_path."conf/billing_sales_import.php";
$configFileExists = file_exists($configFile);

if ($configFileExists && !empty($_POST['action'])) {
    require_once $ab_path."sys/lib.billing.sales.import.php";
    include $ab_path."conf/billing_sales_import.php";
    $billingImport = new BillingSalesImport($db, $ar_config);
    $arResult = array('success' => false);
    switch ($_POST['action']) {
        case 'delete':
            $arResult['success'] = $billingImport->delete($_POST["SELECTED"]);
            break;
        case 'important':
            $arResult['success'] = $billingImport->setImportant($_POST['id'], $_POST['value']);
            break;
    }
    header("Content-Type: application/json");
    die(json_encode($arResult));
}

if (!empty($_REQUEST["done"])) {
    if (!empty($_SESSION["notices"])) {
        $tpl_content->addvar("NOTICES", implode("<br />\n", $_SESSION["notices"]));
        unset($_SESSION["notices"]);
    }
    if (!empty($_SESSION['processed'])) {
        $tpl_content->addvars($_SESSION['processed'], "PROCESS_COUNT_");
    }
    $tpl_content->addvar("DONE", 1);
    $tpl_content->addvar("DONE_".strtoupper($_REQUEST["done"]), 1);
}
$tpl_content->addvar("CONFIGURED", $configFileExists);

if (!is_array($_POST["SEARCH"]) && (!empty($_POST) || !empty($_FILES))) {
    if (!$configFileExists) {
        // Keine Konfiguration vorhanden
        if (!is_array($_POST["CONFIG"])) {
            // Schritt 1 (Datei upload)
            $ar_file = file($_FILES['FILE']['tmp_name']);
            if (is_array($ar_file)) {
                $ar_fields = array();
                $ar_file_headers = false;
                if (isset($_POST["CSV_HEADERS"])) {
                    $ar_file_headers = str_getcsv($ar_file[0], $_POST["CSV_DELIMITER"]);
                }
                $ar_file_first = str_getcsv($ar_file[(isset($_POST["CSV_HEADERS"]) ? 1 : 0)], $_POST["CSV_DELIMITER"]);
                $ar_file_last = str_getcsv(array_pop($ar_file), $_POST["CSV_DELIMITER"]);
                foreach ($ar_file_first as $colIndex => $colValue) {
                    $ar_fields[] = array(
                        "LABEL"     => ($ar_file_headers !== false ? $ar_file_headers[$colIndex] : false),
                        "EXAMPLE1"  => $colValue,
                        "EXAMPLE2"  => $ar_file_last[$colIndex]
                    );
                }
                $tpl_content->addlist("CONFIG_FIELDS", $ar_fields, "tpl/".$s_lang."/billing_sales_import.row_field.htm");
            }
            $tpl_content->addvars($_POST);
        } else {
            // Schritt 2 (Feldzuordnung)
            if ($_POST["CSV_DELIMITER"] == "\\t") {
                $_POST["CSV_DELIMITER"] = "\t";
            }
            $ar_config = array(
                "CSV_HEADERS"   => $_POST["CSV_HEADERS"],
                "CSV_DELIMITER" => $_POST["CSV_DELIMITER"]
            );
            foreach ($_POST["CONFIG"] as $colIndex => $colType) {
                if (!empty($colType)) {
                    $configIndex = "FIELDS_".$colType;
                    if (!is_array($ar_config[$configIndex])) {
                        $ar_config[$configIndex] = array();
                    }
                    $ar_config[$configIndex][] = $colIndex;
                }
            }
            $config = "<?php\n".
                '$ar_config = '.var_export($ar_config, true).';';
            file_put_contents($configFile, $config);
            die(forward("index.php?page=billing_sales_import&done=config"));
        }
    } else {
        if (isset($_POST['RESET_CONFIG'])) {
            unlink($configFile);
            die(forward("index.php?page=billing_sales_import&done=config_reset"));
        }
        // Konfiguration vorhanden
        require_once $ab_path."sys/lib.billing.sales.import.php";
        include $ab_path."conf/billing_sales_import.php";
        $billingImport = new BillingSalesImport($db, $ar_config);
        if ($billingImport->processFile($_FILES['FILE']['tmp_name'])) {
            $arProcessed = $billingImport->getProcessed();
            $_SESSION['processed'] = array(
                "SUCCESS"   => count($arProcessed["SUCCESS"]),
                "NOTICE"    => count($arProcessed["NOTICE"]),
                "SKIP"      => count($arProcessed["SKIP"]),
                "ERROR"     => count($arProcessed["ERROR"])
            );
            die(forward("index.php?page=billing_sales_import&done=import"));
        } else {
            $tpl_content->addvar("errors", implode("<br />\n", $billingImport->getErrors()));
        }
    }
} else {
    if ($configFileExists) {
        // Konfiguration vorhanden
        require_once $ab_path."sys/lib.billing.sales.import.php";
        include $ab_path."conf/billing_sales_import.php";
        $billingImport = new BillingSalesImport($db, $ar_config);

        // Zählen nach wichtigkeit
        $tpl_content->addvar("COUNT_NEW_ALL", $billingImport->countByParam( array("IMPORTANT" => 3) ));
        $tpl_content->addvar("COUNT_NEW_SUCCESS", $billingImport->countByParam( array("IMPORTANT" => 3, "TYPE" => "SUCCESS") ));
        $tpl_content->addvar("COUNT_NEW_NOTICE", $billingImport->countByParam( array("IMPORTANT" => 3, "TYPE" => "NOTICE") ));
        $tpl_content->addvar("COUNT_NEW_SKIP", $billingImport->countByParam( array("IMPORTANT" => 3, "TYPE" => "SKIP") ));
        $tpl_content->addvar("COUNT_NEW_ERROR", $billingImport->countByParam( array("IMPORTANT" => 3, "TYPE" => "ERROR") ));
        // Importierte Umsätze ausgeben
        $processedCount = 0;
        $arSearch = (is_array($_REQUEST["SEARCH"]) ? $_REQUEST["SEARCH"] : array());
        $arSearch["IMPORTANT"] = ($arSearch["IMPORTANT"] !== null ? $arSearch["IMPORTANT"] : "show_all");
        $searchParams = (!empty($arSearch) ? "&".http_build_query(array("SEARCH" => $arSearch)) : "");
        $tpl_content->addvar("SEARCH_TYPE_".$arSearch["TYPE"], 1);
        $tpl_content->addvars($arSearch, "SEARCH_");
        if (preg_match("/^[0-9]{1,2}\.[0-9]{1,2}\.[0-9]{2,4}$/", $arSearch["DATE_FROM"], $arMatches)) {
            $arSearch["DATE_FROM"] = $arMatches[3]."-".$arMatches[2]."-".$arMatches[1];
        }
        if (preg_match("/^[0-9]{1,2}\.[0-9]{1,2}\.[0-9]{2,4}$/", $arSearch["DATE_UNTIL"], $arMatches)) {
            $arSearch["DATE_UNTIL"] = $arMatches[3]."-".$arMatches[2]."-".$arMatches[1];
        }
        $arProcessed = $billingImport->fetchByParam($arSearch, $pagerLimit, $pagerOffset, $processedCount);
        foreach ($arProcessed as $processedIndex => &$processedRow) {
            $processedRow["TYPE_".$processedRow["TYPE"]] = 1;
        }
        $tpl_content->addlist("processed", $arProcessed, "tpl/".$s_lang."/billing_sales_import.row_processed.htm");
        $tpl_content->addvar("pager", htm_browse($processedCount, $pagerIndex, "index.php?page=".$tpl_content->vars['curpage'].$searchParams."&npage=", $pagerLimit));

        // Konfiguration ausgeben
        $ar_config_fields = array();
        foreach ($ar_config as $configProperty => $configValue) {
            if (strpos($configProperty, "FIELDS_") === 0) {
                $fieldName = substr($configProperty, 7);
                $fieldColumns = array();
                foreach ($configValue as $columnNumber => $columnIndex) {
                    $fieldColumns[] = $columnIndex + 1;
                }
                $ar_config_fields[] = array(
                    "COLUMNS"               => implode(", ", $fieldColumns),
                    "CONTENT"               => $fieldName,
                    "CONTENT_".$fieldName   => 1
                );
            }
        }
        $tpl_content->addlist("config_fields", $ar_config_fields, "tpl/".$s_lang."/billing_sales_import.row.htm");
    }
}

?>