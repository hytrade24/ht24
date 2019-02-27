<?php

if ($_REQUEST["DELETE"] > 0) {
    $idGroup = (int)$_REQUEST["DELETE"];
    if (Api_Entities_ManufacturerGroup::deleteById($idGroup)) {
        // Clear caches
        require_once $GLOBALS['ab_path']."sys/lib.cache.admin.php";
        $cacheAdmin = new CacheAdmin();
        $cacheAdmin->emptyCache("subtpl_ads_search");
        die(forward("index.php?page=manufacturer_db_group&deleted=".$idGroup));
    }
}

$arListe = Api_Entities_ManufacturerGroup::getByParam();

$tpl_content->addvar("added", ($_REQUEST["added"] > 0 ? 1 : 0));
$tpl_content->addvar("deleted", ($_REQUEST["deleted"] > 0 ? 1 : 0));
$tpl_content->addlist("liste", $arListe, "tpl/".$s_lang."/manufacturer_db_group.row.htm");