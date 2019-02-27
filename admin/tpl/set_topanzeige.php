<?php
/* ###VERSIONSBLOCKINLCUDE### */



$res = $db->querynow("
	UPDATE
		`ad_master`
	SET
		B_TOP=".(int)$_REQUEST['SET'].",
		B_TOP_LIST=".Rest_MarketplaceAds::getTopValueByFlags($_REQUEST['SET'])."
	WHERE
		ID_AD_MASTER=".(int)$_REQUEST['ID_AD']);

die(dump($res));

?>