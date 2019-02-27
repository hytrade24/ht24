<?php

include $ab_path."sys/lib.nestedsets.php";
include $ab_path."sys/lib.shop_kategorien.php";
include $ab_path."sys/lib.pub_kategorien.php";

$kat = new TreeCategories("kat", 1);
$kat_cache = new CategoriesCache();

if (!empty($_POST)) {
    // Update mapping
    $arCategoryIds = array();
    $arMappingInserts = array();
    foreach ($_POST["MAPPING_INDEX"] as $mappingIndex => $mappingId) {
        $arCategoryIds[] = (int)$mappingId;
        if (array_key_exists($mappingId, $_POST["MAPPING"])) {
            foreach ($_POST["MAPPING"][$mappingId] as $groupMappingIndex => $groupId) {
                $arMappingInserts[] = "(".(int)$mappingId.", ".(int)$groupId.")";
            }
        }
    }
    $db->querynow("DELETE FROM `man_group_category` WHERE FK_KAT IN (".implode(", ", $arCategoryIds).")");
    $db->querynow("INSERT INTO `man_group_category` (FK_KAT, FK_MAN_GROUP) VALUES ".implode(", ", $arMappingInserts));
    // Clear caches
    require_once $GLOBALS['ab_path']."sys/lib.cache.admin.php";
    $cacheAdmin = new CacheAdmin();
    $cacheAdmin->emptyCache("subtpl_ads_search");
    
    die(forward("index.php?page=manufacturer_db_category&done=1"));
}

$manGroups = Api_Entities_ManufacturerGroup::getByParam();

if (array_key_exists("ajax", $_REQUEST)) {
    switch ($_REQUEST["ajax"]) {
        case 'treeGet':
            $arResult = array();
            $categoryParentId = ($_REQUEST["parent"] > 0 ? (int)$_REQUEST["parent"] : $kat->tree_get_parent());
            $categoryList = $kat->element_get_childs($categoryParentId);
            $categoryListIds = array();
            foreach ($categoryList as $categoryIndex => $categoryDetails) {
                $categoryListIds[] = (int)$categoryDetails["ID_KAT"];
            }
            $categoryMapping = array();
            if (!empty($categoryListIds)) {
                $categoryMappingRaw = $db->fetch_table("SELECT FK_KAT, FK_MAN_GROUP FROM `man_group_category` WHERE FK_KAT IN (".implode(", ", $categoryListIds).")");
                foreach ($categoryMappingRaw as $categoryMappingIndex => $categoryMappingDetails) {
                    if (!array_key_exists($categoryMappingDetails["FK_KAT"], $categoryMapping)) {
                        $categoryMapping[ $categoryMappingDetails["FK_KAT"] ] = array();
                    }
                    $categoryMapping[ $categoryMappingDetails["FK_KAT"] ][] = $categoryMappingDetails["FK_MAN_GROUP"];
                }
            }
            foreach ($categoryList as $categoryIndex => $categoryDetails) {
                $manGroupsCategory = array();
                foreach ($manGroups as $manGroupIndex => $manGroupDetails) {
                    if (array_key_exists($categoryDetails["ID_KAT"], $categoryMapping)) {
                        $manGroupDetails["CHECKED"] = in_array($manGroupDetails["ID_MAN_GROUP"], $categoryMapping[ $categoryDetails["ID_KAT"] ]);
                    } else {
                        $manGroupDetails["CHECKED"] = false;
                    }
                    $manGroupsCategory[] = $manGroupDetails;
                }
                $tplCategory = new Template("tpl/".$s_lang."/manufacturer_db_category.row.htm");
                $tplCategory->addvars($categoryDetails);
                $tplCategory->addvar("HAS_CHILDREN", ($categoryDetails["RGT"] - $categoryDetails["LFT"]) > 1);
                $tplCategory->addlist("groups", $manGroupsCategory, "tpl/".$s_lang."/manufacturer_db_category.row.group.htm");
                $arResult[] = $tplCategory->process();
            }
            die(implode("\n", $arResult));
            break;
    }
}

$tpl_content->addlist("groups", $manGroups, "tpl/".$s_lang."/manufacturer_db_category.header.group.htm");