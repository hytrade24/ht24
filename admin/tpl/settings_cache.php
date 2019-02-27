<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.cache.admin.php";

$cacheAdmin = new CacheAdmin($db);

if (!empty($_POST['doAjax'])) {
    $ajaxResponse = array("success" => false);
    switch ($_POST['doAjax']) {
        case 'emptyCache':
            $ajaxResponse["success"] = $cacheAdmin->emptyCache($_POST['target']);
            break;
        case 'setInterval':
            $ajaxResponse["success"] = $cacheAdmin->setInterval($_POST['target'], (int)$_POST['interval']);
            break;
    }
    header("Content-Type: application/json");
    die(json_encode($ajaxResponse));
}

$arCacheCategories = $cacheAdmin->getCategories();
$arCacheCategoriesList = array();
foreach ($arCacheCategories as $cacheIndex => $cacheCategory) {
    $tplCacheCategory = new Template("tpl/".$s_lang."/settings_cache.category.htm");
    $tplCacheCategory->addvars($cacheCategory);
    $tplCacheCategory->addlist("liste", $cacheAdmin->getCacheListByCategory($cacheCategory['ident']), "tpl/".$s_lang."/settings_cache.row.htm");
    $arCacheCategoriesList[] = $tplCacheCategory;
}
$tpl_content->addvar("liste", $arCacheCategoriesList);
