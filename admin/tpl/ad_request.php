<?php
/* ###VERSIONSBLOCKINLCUDE### */



global $ab_path, $langval, $nar_systemsettings;
require_once $ab_path.'sys/lib.ad_request.php';

$adRequestManagement = AdRequestManagement::getInstance($db);
$adRequestManagement->setLangval($langval);

$perpage = 20; // Elemente pro Seite
$limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);
$isSearch = false;

if(isset($_GET['DO'])) {
    if($_GET['DO'] == 'delete' && isset($_GET['ID_AD_REQUEST'])) {
        $adRequestManagement->deleteById($_GET['ID_AD_REQUEST']);
    }
    if($_GET['DO'] == 'unlock' && isset($_GET['ID_AD_REQUEST'])) {
        $adRequestManagement->unlockById($_GET['ID_AD_REQUEST']);
    }
    if($_GET['DO'] == 'lock' && isset($_GET['ID_AD_REQUEST'])) {
        $adRequestManagement->lockById($_GET['ID_AD_REQUEST']);
    }
    if($_GET['DO'] == 'edit' && isset($_REQUEST['ID_AD_REQUEST'])) {
		$adRequestManagement->updateById($_REQUEST['ID_AD_REQUEST'], $_REQUEST);
		header('Content-type: application/json');
		die(json_encode(array("success" => true)));
    }

    die(forward("index.php?page=ad_request&npage=".$npage));
}

if(isset($_REQUEST['id'])) {
	$tpl_content->addvar("id_show", $_REQUEST['id']);
}

$ar_search = array(
    'LIMIT' => $perpage,
    'OFFSET' => $limit
);
if (isset($_REQUEST['SEARCH'])) {
	if (!empty($_REQUEST['ID_AD_REQUEST'])) {
		$ar_search['ID_AD_REQUEST'] = $_REQUEST['ID_AD_REQUEST'];
	}
	if (!empty($_REQUEST['FK_USER'])) {
		$ar_search['FK_USER'] = $_REQUEST['FK_USER'];
		$ar_search['NAME_'] = $_REQUEST['NAME_'];
	}
	if (!empty($_REQUEST['FK_AUTOR'])) {
		$ar_search['FK_USER'] = $_REQUEST['FK_AUTOR'];
		$ar_search['NAME_'] = $_REQUEST['NAME_'];
	}
	if (!empty($_REQUEST['TEXT'])) {
		$ar_search['SEARCH_AD_REQUEST'] = $_REQUEST['TEXT'];
	}
	$tpl_content->addvars($ar_search);
	$isSearch = true;
}

$adRequests = $adRequestManagement->fetchAllByParam($ar_search);
$numberOfAdRequests = $adRequestManagement->countByParam($ar_search);

if ($isSearch) {
	$tpl_content->addvar("SEARCH_RESULT", 1);
}

$tpl_main->addvar('autoconfirm', $nar_systemsettings['MARKTPLATZ']['REQUEST_AUTO_APPROVE']);
$tpl_main->addvar('timeout_days', $nar_systemsettings['MARKTPLATZ']['REQUEST_RUNTIME_DAYS']);

$tpl_content->addvar("pager", htm_browse($numberOfAdRequests, $_REQUEST['npage'], "index.php?page=".$tpl_content->vars['curpage']."&npage=", $perpage));
$tpl_content->addlist('liste', $adRequests, 'tpl/de/ad_request.row.htm');
$tpl_content->addvar("all", $numberOfAdRequests);

?>