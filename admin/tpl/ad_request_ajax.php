<?php
/* ###VERSIONSBLOCKINLCUDE### */



global $ab_path, $langval;
require_once $ab_path.'sys/lib.ad_request.php';

$adRequestManagement = AdRequestManagement::getInstance($db);
$adRequestManagement->setLangval($langval);

if ($_REQUEST['saved'] == 1) {
	$tpl_content->addvar('saved', $_REQUEST['saved']);
}

if(isset($_GET['DO'])) {
    if($_GET['DO'] == 'load' && isset($_REQUEST['ID_AD_REQUEST'])) {
		$tpl_content->addvars( $adRequestManagement->find($_REQUEST['ID_AD_REQUEST']) );
    }
    if ($_GET['DO'] == 'save') {
    	if (isset($_REQUEST['ID_AD_REQUEST'])) {
			$adRequestManagement->updateById($_REQUEST['ID_AD_REQUEST'], $_REQUEST);
			header('Content-type: application/json');
			die(json_encode(array("success" => true)));
    	} else {
			die(json_encode(array("success" => false)));
    	}
    }
}

?>