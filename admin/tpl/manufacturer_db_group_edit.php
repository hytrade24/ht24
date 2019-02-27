<?php

include $ab_path."sys/lib.nestedsets.php";
include $ab_path."sys/lib.shop_kategorien.php";
include $ab_path."sys/lib.pub_kategorien.php";

$kat = new TreeCategories("kat", 1);
$kat_cache = new CategoriesCache();

if (array_key_exists("ajax", $_REQUEST)) {
    switch ($_REQUEST["ajax"]) {
        case 'manufacturersSearch':
            $arSelectedRaw = explode(",", $_POST["selected"]);
            $arSelectedIds = array();
            foreach ($arSelectedRaw as $selectedIndex => $selectedId) {
                $arSelectedIds[] = (int)$selectedId;
            }
            $arWhere = array();
            if (array_key_exists("SEARCH_NAME", $_POST)) {
                $arWhere[] = 'NAME LIKE "%'.mysql_real_escape_string($_POST["SEARCH_NAME"]).'%"';
            }
            if (array_key_exists("SEARCH_STATUS", $_POST)) {
                if ($_POST["SEARCH_STATUS"]) {
                    $arWhere[] = 'ID_MAN IN ('.implode(", ", $arSelectedIds).')';
                } else {
                    $arWhere[] = 'ID_MAN NOT IN ('.implode(", ", $arSelectedIds).')';
                }
            }
            $manufacturerList = $db->fetch_table("
                SELECT * FROM `manufacturers`
                ".(!empty($arWhere) ? "WHERE ".implode(" AND ", $arWhere) : "")."
                ORDER BY `NAME` ASC");
            $tplList = array();
            foreach ($manufacturerList as $manufacturerIndex => $manufacturerDetails) {
                $tplListRow = new Template("tpl/de/manufacturer_db_group_edit.row.htm");
                $tplListRow->addvar("selected", in_array($manufacturerDetails["ID_MAN"], $arSelectedIds));
                $tplListRow->addvars($manufacturerDetails);
                $tplList[] = $tplListRow->process(true);
            }
            die(implode("\n", $tplList));
            break;
    }
    die("Unknown ajax request!");
}

if (!empty($_POST)) {
    $idResult = $db->update("man_group", $_POST);
    if ($idResult > 0) {
        $arSelectedRaw = explode(",", $_POST["MANUFACTURERS"]);
        $arSelectedIds = array();
        $arMappingInsert = array();
        foreach ($arSelectedRaw as $selectedIndex => $selectedId) {
            $arSelectedIds[] = (int)$selectedId;
            $arMappingInsert[] = "(".(int)$idResult.", ".(int)$selectedId.")";
        }
        $db->querynow("DELETE FROM `man_group_mapping` WHERE FK_MAN_GROUP=".(int)$idResult." AND FK_MAN NOT IN (".implode(", ", $arSelectedIds).")");
        $db->querynow("INSERT IGNORE INTO `man_group_mapping` (FK_MAN_GROUP, FK_MAN) VALUES ".implode(", ", $arMappingInsert));
        // Clear caches
        require_once $GLOBALS['ab_path']."sys/lib.cache.admin.php";
        $cacheAdmin = new CacheAdmin();
        $cacheAdmin->emptyCache("subtpl_ads_search");
    }
    die(forward("index.php?page=manufacturer_db_group&added=".$idResult));
} else {
    if ($_REQUEST["ID_MAN_GROUP"] > 0) {
        $arManGroup = Api_Entities_ManufacturerGroup::getById($_REQUEST["ID_MAN_GROUP"]);
        $tpl_content->addvars($arManGroup);
    }
}