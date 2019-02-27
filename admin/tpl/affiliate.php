<?php

require_once $ab_path.'sys/lib.affiliate.php';

$affiliateManagement = AffiliateManagement::getInstance($db);

$perpage = 20; // Elemente pro Seite
$limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);

if(isset($_GET['DO'])) {
    if($_GET['DO'] == 'delete' && isset($_GET['ID_AFFILIATE'])) {
		$affiliateManagement->deleteById($_GET['ID_AFFILIATE']);
    }

    die(forward("index.php?page=affiliate&npage=".$npage));
}

if(isset($_REQUEST['id'])) {
	$tpl_content->addvar("id_show", $_REQUEST['id']);
}

$ar_search = array(
    'LIMIT' => $perpage,
    'OFFSET' => $limit
);
if (isset($_REQUEST['SEARCH'])) {
	if (!empty($_REQUEST['ID_AFFILIATE'])) {
		$ar_search['ID_AFFILIATE'] = $_REQUEST['ID_AFFILIATE'];
	}
	if (!empty($_REQUEST['TEXT'])) {
		$ar_search['SEARCH_NAME'] = $_REQUEST['TEXT'];
	}
	$tpl_content->addvars($ar_search);
}

$affiliates = $affiliateManagement->fetchAllByParam($ar_search);
$numberOfAffiliates = $affiliateManagement->countByParam($ar_search);


$tpl_content->addvar("pager", htm_browse($numberOfAffiliates, $_REQUEST['npage'], "index.php?page=".$tpl_content->vars['curpage']."&".http_build_query($_GET)."&npage=", $perpage));
$tpl_content->addlist('liste', $affiliates, 'tpl/de/affiliate.row.htm');
$tpl_content->addvar("all", $numberOfAffiliates);
$tpl_content->addvar("SETTINGS_AFFILIATE", $nar_systemsettings['PLUGIN']['AFFILIATE']);