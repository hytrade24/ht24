<?php
/* ###VERSIONSBLOCKINLCUDE### */
require_once $ab_path."sys/lib.watchlist.php";
require_once $ab_path."sys/lib.watchlist_user.php";

$watchlistManagement = WatchlistManagement::getInstance($db);
$watchlistUserManagement = WatchlistUserManagement::getInstance($db);

$SILENCE=false;

if($_GET['DO'] == 'TOGGLE') {

	if ( $_GET['FK_REF_TYPE'] == "normal" || $_GET['type'] == "normal" ) {
		$url = $_GET['URL'];
		$str = "normal";
		$status = $watchlistManagement->existItemURLForUser($uid, $url, $str );

		if($status) {
			$watchlistManagement->removeItemURLByFk($uid, $url, $str);
		}
	}
	else {
		$status = $watchlistManagement->existItemForUser($uid, $_GET['ID_AD'], 'ad_master');

		if($status) {
			$watchlistManagement->removeItemByFk($uid, $_GET['ID_AD'], 'ad_master');
		}
	}
	echo json_encode(array('success' => true, 'status' => !($status > 0)));
	die();

} elseif($_GET['DO'] == 'LOAD') {
	if ( $_GET['type'] == "normal" ) {
		$url = $_GET['URL'];
		$status = $watchlistManagement->existItemURLForUser($uid, $url, $_GET['type'] );
	}
	else {
		$status = $watchlistManagement->existItemForUser($uid, $_GET['ID_AD'], 'ad_master');
	}
	echo json_encode(array('success' => true, 'status' => ($status > 0)));
	die();
}
die();
?>