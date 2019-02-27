<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.nav.url.php";
$navUrlMan = NavUrlManagement::getInstance($db);

function NavUrlConvertMapping(&$arNavUrl) {
    $strMapping = str_replace(array("\r\n", "\r"), "\n", $arNavUrl["URL_MAPPING"]);
    $arMapping = array();
    if (!empty($strMapping)) {
        $arMappingPairs = explode("\n", $strMapping);
        foreach ($arMappingPairs as $mappingRow => $mappingPair) {
            list($mappingIndex, $mappingValue) = explode("=", $mappingPair);
            $arMapping[$mappingIndex] = $mappingValue;
        }
    }
    $arNavUrl["URL_MAPPING"] = serialize($arMapping);
}

$idNav = (int)$_REQUEST["id"];
$isExtended = (int)$_REQUEST['extended'];

if (!empty($_REQUEST['done'])) {
    $tpl_content->addvar("Done", 1);
    $tpl_content->addvar("Done".$_REQUEST['done'], 1);
}

if (!empty($_REQUEST['do'])) {
    switch ($_REQUEST['do']) {
        case 'delete':
            $idNavUrl = (int)$_REQUEST['target'];
            $navUrlMan->deleteById($idNavUrl);
            die(forward("index.php?page=nav_url_edit&id=".$idNav."&done=Delete"));
    }
}

if (!empty($_POST)) {
    if (!empty($_POST["EDIT"])) {
        $arEdited = $_POST["EDIT"];
        foreach ($arEdited as $idNavUrl => $arNavUrl) {
            $arNavUrl["ID_NAV_URL"] = $idNavUrl;
            $arNavUrl["FK_NAV"] = $idNav;
            if ($arNavUrl["URL_MANUAL"]) {
                NavUrlConvertMapping($arNavUrl);
            } else {
                $navUrlMan->generateRegexp($arNavUrl);
            }
            $query = "
            UPDATE `nav_url` SET PRIORITY=".(int)$arNavUrl["PRIORITY"].", URL_MANUAL=".(int)$arNavUrl["URL_MANUAL"].",
                URL_PATTERN='".mysql_real_escape_string($arNavUrl["URL_PATTERN"])."',
                URL_REGEXP='".mysql_real_escape_string($arNavUrl["URL_REGEXP"])."',
                URL_MAPPING='".mysql_real_escape_string($arNavUrl["URL_MAPPING"])."'
            WHERE ID_NAV_URL=".(int)$idNavUrl;
            $result = $db->querynow($query);
        }

    }
    if (!empty($_POST["NEW"]) && !empty($_POST["NEW"]["URL_PATTERN"])) {
        $arNew = $_POST["NEW"];
        $arNew["FK_LANG"] = $GLOBALS['lang_list'][ $GLOBALS['s_lang'] ]['ID_LANG'];
        $arNew["FK_NAV"] = $idNav;
        if ($arNew["URL_MANUAL"]) {
            NavUrlConvertMapping($arNew);
        } else {
            $navUrlMan->generateRegexp($arNew);
        }
        $idNavUrl = $db->update("nav_url", $arNew);
    }
    $navUrlMan->updateCache();
    die(forward("index.php?page=nav_url_edit&id=".$idNav."&extended=".$isExtended."&done=Save"));
}

require_once 'sys/lib.nestedsets.php'; // Nested Sets

$nest = new nestedsets('nav', 1, true);
$arNode = $nest->getNode($idNav);
$arList = $navUrlMan->fetchUrlsByNav($idNav);
foreach ($arList as $listIndex => $listRow) {
    $mappingArray = unserialize($listRow["URL_MAPPING"]);
    $mappingText = array();
    foreach ($mappingArray as $mappingIndex => $mappingValue) {
        $mappingText[] = $mappingIndex."=".$mappingValue;
    }
    $arList[$listIndex]["URL_MAPPING_TEXT"] = implode("\n", $mappingText);
}


$tpl_content->addvar("EXTENDED", $isExtended);
$tpl_content->addvars($arNode);
$tpl_content->addlist("liste", $arList, "tpl/de/nav_url_edit.row.htm");