<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.nav.url.php";
$navUrlMan = NavUrlManagement::getInstance($db);

if (!empty($_REQUEST['done'])) {
    $tpl_content->addvar("Done", 1);
    $tpl_content->addvar("Done".$_REQUEST['done'], 1);
}

$baseurl = $GLOBALS['lang_list'][ $GLOBALS['s_lang'] ]['BASE_URL'];
if (empty($baseurl)) {
    $baseurl = $GLOBALS['nar_systemsettings']['SITE']['BASE_URL'];
}
$baseurl = rtrim($baseurl, "/");
$tpl_content->addvar("BASEURL", $baseurl);

if (!empty($_REQUEST['do'])) {
    switch ($_REQUEST['do']) {
        case 'SortSave':
            foreach ($_POST["URL_PRIORITY"] as $idNav => $priority) {
                $db->querynow("UPDATE `nav` SET URL_PRIORITY=".(int)$priority." WHERE ID_NAV=".(int)$idNav);
            }
            $navUrlMan->updateCache();
            die(forward("index.php?page=nav_url&done=SortSave"));
        case 'SortRepair':
            $priorityCur = 1;
            $arPages = array_reverse( $navUrlMan->fetchAllPages() );
            foreach ($arPages as $pageIndex => $pageDetails) {
                $db->querynow("UPDATE `nav` SET URL_PRIORITY=".(int)$priorityCur++." WHERE ID_NAV=".$pageDetails["ID_NAV"]);
            }
            die(forward("index.php?page=nav_url&done=SortRepair"));
        case 'GetAllUrls':
            $arUrlList = $navUrlMan->fetchUrlPatternsByNav($_REQUEST['id']);
            if (!empty($arUrlList)) {
                die($baseurl.implode("<br />\n".$baseurl, $arUrlList));
            } else {
                die("");
            }
    }
}

$tpl_content->addlist("liste", $navUrlMan->fetchAllPages(), "tpl/de/nav_url.row.htm");