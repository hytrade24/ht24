<?php

$actionSub = (!empty($ar_params[2]) ? $ar_params[2] : "confirm");

if (!empty($_POST["PRODUCT_IDS"])) {
    $countProducts = 0;
    $arProductIdsGrouped = array();
    $arManufacturersRaw = explode("\n", trim($_POST["PRODUCT_IDS"]));
    foreach ($arManufacturersRaw as $rowIndex => $rowData) {
        list($tableId, $manufacturerId, $productIds) = explode(":", $rowData);
        if (array_key_exists($tableId, $arProductIdsGrouped)) {
            $arProductIdsGrouped[$tableId] = array();
        }
        // Exclude articles already present for this user
        $arProductIdsOnline = $db->fetch_col("
            SELECT DISTINCT FK_PRODUCT 
            FROM `ad_master` 
            WHERE DELETED=0 AND FK_USER=" . (int)$uid . " AND FK_MAN=" . $manufacturerId . " AND FK_PRODUCT>0");
        $arProductIdsCreate = array_diff(explode(",", $productIds), $arProductIdsOnline);
        $arProductIdsGrouped[$tableId][$manufacturerId] = $arProductIdsCreate;
        $countProducts += count($arProductIdsCreate);
    }

    $_SESSION["AD_CREATE_BULK_IDS"] = $arProductIdsGrouped;
    $_SESSION["AD_CREATE_BULK_COUNT"] = array($countProducts, 0);

    die(forward($tpl_content->tpl_uri_action("my-marktplatz-neu-bulk,process")));
}

if (!empty($ar_params[1]) && ($ar_params[1] == "cancel")) {
    unset($_SESSION["AD_CREATE_BULK_IDS"]);
    unset($_SESSION["AD_CREATE_BULK_COUNT"]);
    die(forward($tpl_content->tpl_uri_action("my-marktplatz-neu-bulk")));
}
if (!empty($ar_params[1]) && ($ar_params[1] == "process")) {
    if (empty($_SESSION["AD_CREATE_BULK_IDS"])) {
        die(forward($tpl_content->tpl_uri_action("my-marktplatz-neu-bulk")));
    }
    $arProductIdsGrouped = $_SESSION["AD_CREATE_BULK_IDS"];
    $done = true;
    $countPerCall = 1000;
    list($countProducts, $countProductsDone) = $_SESSION["AD_CREATE_BULK_COUNT"];
    // Create products
    require_once $GLOBALS["ab_path"]."sys/lib.hdb.php";
    $adBulkCreate = new Ad_Bulk_Create($db);
    $hdbManagement = ManufacturerDatabaseManagement::getInstance($db);
    foreach ($arProductIdsGrouped as $tableId => $arProductIdsByMan) {
        foreach ($arProductIdsByMan as $manufacturerId => $arProductIds) {
            if (!empty($arProductIds)) {
                if (count($arProductIds) > $countPerCall) {
                    $arProductIdsStep = array_splice($arProductIdsGrouped[$tableId][$manufacturerId], 0, $countPerCall);
                    $adBulkCreate->addProducts($tableId, $arProductIdsStep, $uid);
                    $countProductsDone += $countPerCall;
                    $done = false;
                    break 2;
                } else {
                    $arProductIdsGrouped[$tableId][$manufacturerId] = array();
                    $adBulkCreate->addProducts($tableId, $arProductIds, $uid);
                    $countProductsDone += count($arProductIds);
                    $countPerCall -= count($arProductIds);
                }
            }
        } 
    }
    $adBulkCreate->finish();
    if ($done) {
        unset($_SESSION["AD_CREATE_BULK_IDS"]);
        unset($_SESSION["AD_CREATE_BULK_COUNT"]);
        die(forward( $tpl_content->tpl_uri_action("my-marktplatz-anzeigen") ));
    } else {
        $_SESSION["AD_CREATE_BULK_IDS"] = $arProductIdsGrouped;
        $_SESSION["AD_CREATE_BULK_COUNT"] = array($countProducts, $countProductsDone);
        $tpl_content->addvar("PROCESSING", 1);
        $tpl_content->addvar("PROCESS_COUNT", $countProducts);
        $tpl_content->addvar("PROCESS_DONE", $countProductsDone);
        $tpl_content->addvar("PROCESS_PERCENT", round(($countProductsDone / $countProducts) * 100));
        return;
    }
    #die(var_dump($arProductIdsGrouped, $arProductIdsOnline));
}

if (!empty($_REQUEST["ajax"])) {
    $perpage = 20;
    $page = (array_key_exists("npage", $_REQUEST) ? $_REQUEST["npage"] : 1);
    $search = (array_key_exists("search", $_REQUEST) ? $_REQUEST["search"] : null);
    switch ($_REQUEST["ajax"]) {
        case "MANUFACTURERS":
            $tpl_content->tpl_text = "{MANUFACTURERS}";
            $tpl_content->addlist("MANUFACTURERS", Ad_Bulk_Create::GetManufacturers($db, $_REQUEST["table"]), "tpl/".$s_lang."/my-marktplatz-neu-bulk.row_manufacturer.htm");
            die($tpl_content->process());
        case "PRODUCTS":
            $resultCount = 0;
            $tpl_content->tpl_text = "{PRODUCTS}";
            $tpl_content->addlist("PRODUCTS", Ad_Bulk_Create::GetProducts($db, $_REQUEST["table"], $_REQUEST["man"], $uid, $page, $perpage, $search, $resultCount), "tpl/".$s_lang."/my-marktplatz-neu-bulk.row_product.htm");
            header("Content-Type: application/json");
            die(json_encode([ "count" => $resultCount, "list" => $tpl_content->process() ]));
        case "PRODUCTS_SELECT":
            $arProducts = Ad_Bulk_Create::GetProducts($db, $_REQUEST["table"], $_REQUEST["man"], $uid, 1, -1, $search);
            $arProductIds = array();
            foreach ($arProducts as $arProduct) {
                $arProductIds[] = $arProduct["ID_HDB_PRODUCT"];
            }
            header("Content-Type: application/json");
            die(json_encode([ "ids" => $arProductIds ]));
        case "PRODUCTS_DETAILS":
            $categoryId = (array_key_exists("category", $_REQUEST) ? $_REQUEST["category"] : null);
            $productId = (array_key_exists("product", $_REQUEST) ? $_REQUEST["product"] : null);
            die($tpl_content->tpl_subtpl("tpl/".$s_lang."/product_details.htm,ID_HDB_PRODUCT=".$productId.",ID_KAT=".$categoryId));
    }
}

$tpl_content->addlist("TABLES", Ad_Bulk_Create::GetTables($db, 64584), "tpl/".$s_lang."/my-marktplatz-neu-bulk.row_table.htm");