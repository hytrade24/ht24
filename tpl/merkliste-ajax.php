<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.watchlist.php";
require_once $ab_path."sys/lib.watchlist_user.php";

$watchlistManagement = WatchlistManagement::getInstance($db);
$watchlistUserManagement = WatchlistUserManagement::getInstance($db);

$error = FALSE;
if(isset($_POST['FK_REF']) && $_POST['FK_REF'] != "" && isset($_POST['FK_REF_TYPE'])  && $_POST['FK_REF_TYPE'] != "" && $watchlistManagement->existItemForUser($uid, $_POST['FK_REF'], $_POST['FK_REF_TYPE'])) {

	$tpl_content->addvar("err_already_in_list", 1);
	$tpl_content->addvar("err", 1);
	$error = TRUE;
}

if($_POST['do'] == 'add_watchlist') {
	if(trim($_POST['ITEMNAME']) == "") {
		$error = TRUE;
		$tpl_content->addvar("err_title", 1);
	}

	if(trim($_POST['URL']) == "" || $_POST['FK_WATCHLIST_USER'] == NULL) {
		$error = TRUE;
		$tpl_content->addvar("err_other", 1);
	}

	if(!$error) {
		$fk_ref_type = $_POST['FK_REF_TYPE'];
		if ( empty($_POST['FK_REF_TYPE']) || is_null($_POST['FK_REF_TYPE']) ) {
			$fk_ref_type = "normal";
		}
		$watchlistManagement->addItem($uid, $_POST['FK_WATCHLIST_USER'], $fk_ref_type, $_POST['FK_REF'], $_POST['ITEMNAME'], $_POST['DESCRIPTION'], $_POST['URL']);

		$tpl_content->addvar("saved", 1);

		header("Content-Type:application/json");
		echo json_encode(
			array(
				"success"   => true,
				"redirect"  => true
			)
		);
		die();
	} else {
		$tpl_content->addvar("err", 1);
	}
} elseif($_POST['do'] == 'remove_watchlist') {

	$watchlistManagement->removeItemById($_REQUEST['ID_WATCHLIST'], $uid);

	header("Content-Type:application/json");
	echo json_encode(array("success" => true)); die();
}



$watchlistUserManagement->initWatchlistsForUser($uid);
$watchLists = $watchlistUserManagement->fetchAllByParam(array(
	'FK_USER' => $uid
));

foreach($watchLists as $key => $watchList) {
	if($_POST['FK_WATCHLIST_USER'] == $watchList['ID_WATCHLIST_USER']) {
		$watchLists[$key]['SELECTED'] = 1;
	}
}

$tpl_content->addlist("watchlist_list", $watchLists, "tpl/".$s_lang."/merkliste-ajax.row_list.htm");
$tpl_content->addvars($_POST);
$tpl_content->addvar("LINK", $tpl_content->tpl_uri_action_full($_POST['URL']));

if ( isset($_POST["redirect"]) && $_POST["redirect"] == "true" ) {
	$tpl_content->addvar("redirect",$_POST["redirect"]);
}

?>