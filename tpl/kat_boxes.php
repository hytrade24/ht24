<?php
/* ###VERSIONSBLOCKINLCUDE### */

global $s_lang;

include_once "sys/lib.nestedsets.php";
include_once "sys/lib.shop_kategorien.php";
include_once "sys/lib.pub_kategorien.php";

$rootId = (isset($tpl_content->vars["ROOT"]) ? $tpl_content->vars["ROOT"] : 1);
$katTree = new TreeCategories("kat", $rootId);
$katId = (isset($tpl_content->vars["ID_KAT"]) ? $tpl_content->vars["ID_KAT"] : $katTree->tree_get_parent());
$katColsLG = (isset($tpl_content->vars["COLUMNS"]) ? $tpl_content->vars["COLUMNS"] : 4);
$katColsLG = (isset($tpl_content->vars["COLUMNS_LG"]) ? $tpl_content->vars["COLUMNS_LG"] : $katColsLG);
$katColsMD = (isset($tpl_content->vars["COLUMNS_MD"]) ? $tpl_content->vars["COLUMNS_MD"] : round($katColsLG/2));
$katColsSM = (isset($tpl_content->vars["COLUMNS_SM"]) ? $tpl_content->vars["COLUMNS_SM"] : round($katColsLG/3));
$katColsXS = (isset($tpl_content->vars["COLUMNS_XS"]) ? $tpl_content->vars["COLUMNS_XS"] : round($katColsLG/4));
$katCols = array("XS" => $katColsXS, "SM" => $katColsSM, "MD" => $katColsMD, "LG" => $katColsLG);
$katChildColsLG = (isset($tpl_content->vars["CHILD_COLUMNS"]) ? $tpl_content->vars["CHILD_COLUMNS"] : 4);
$katChildColsLG = (isset($tpl_content->vars["CHILD_COLUMNS_LG"]) ? $tpl_content->vars["CHILD_COLUMNS_LG"] : $katChildColsLG);
$katChildColsMD = (isset($tpl_content->vars["CHILD_COLUMNS_MD"]) ? $tpl_content->vars["CHILD_COLUMNS_MD"] : round($katChildColsLG/2));
$katChildColsSM = (isset($tpl_content->vars["CHILD_COLUMNS_SM"]) ? $tpl_content->vars["CHILD_COLUMNS_SM"] : round($katChildColsLG/3));
$katChildColsXS = (isset($tpl_content->vars["CHILD_COLUMNS_XS"]) ? $tpl_content->vars["CHILD_COLUMNS_XS"] : round($katChildColsLG/4));
$katChildCols = array("XS" => $katChildColsXS, "SM" => $katChildColsSM, "MD" => $katChildColsMD, "LG" => $katChildColsLG);
$katDeepLevel = 1;
$katCache = new CategoriesCache();

$cacheFileLifeTime = $nar_systemsettings['CACHE']['LIFETIME_CATEGORY'];
$cacheFile = "cache/marktplatz/boxes_".$s_lang.".".$katId.".".$katDeepLevel.".htm";
$cacheModifyTime = @filemtime($cacheFile);
$cacheDiffTime = ((time()-$cacheModifyTime)/60);

if (($cacheDiffTime > $cacheFileLifeTime) || !file_exists($cacheFile)) {
    $katCache->cacheKatBoxes($katId, $katDeepLevel, $katCols, $katChildCols);
}

$cacheContent = @file_get_contents($cacheFile);
// Add categories to template
$tpl_content->addvar("kats", $cacheContent);

?>