<?php
/* ###VERSIONSBLOCKINLCUDE### */




global $ab_path, $langval;
require_once $ab_path.'sys/lib.ad_request.php';

$doAction =  (isset($ar_params[2]) ? $ar_params[2] : null);
$adRequestId =  (isset($ar_params[3]) ? (int)$ar_params[3] : null);


$adRequestManagement = AdRequestManagement::getInstance($db);
$adRequestManagement->setLangval($langval);

$perpage = 20; // Elemente pro Seite
$npage =  (isset($ar_params[1]) ? $ar_params[1] : 1);

$limit = ($npage-1)*$perpage;

if(isset($doAction)) {
    if($doAction == 'delete' && isset($adRequestId)) {
        $adRequest = $adRequestManagement->find($adRequestId);
        if(($adRequest != null) && ($adRequest['FK_USER'] == $uid)) {

            $adRequestManagement->deleteById($adRequestId);

            die(forward("/my-pages/my-ad-request,".$npage.",deleted.htm"));

        }
    }
	if ($doAction == 'deleted') {
		$tpl_content->addvar("deleted" ,1);
	}
}


$adRequests = $adRequestManagement->fetchAllByParam(array(
    'LIMIT' => $perpage,
    'OFFSET' => $limit,
    'FK_USER' => $uid
));
$numberOfAdRequests = $adRequestManagement->countByParam(array('FK_USER' => $uid));


$tpl_content->addvar("pager", htm_browse($numberOfAdRequests, $npage, "/my-pages/my-ad-request,", $perpage));
$tpl_content->addvar("npage", $npage);
$tpl_content->addlist('liste', $adRequests, 'tpl/'.$s_lang.'/my-ad-request.row.htm');
$tpl_content->addvar("all", $numberOfAdRequests);
