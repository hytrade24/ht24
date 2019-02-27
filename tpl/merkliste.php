<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once 'sys/lib.watchlist.php';
require_once 'sys/lib.watchlist_user.php';

$translationListe = Translation::readTranslation("marketplace", "watchlist.list", null, array(), "Liste");
// Watchlist
$watchlistManagement = WatchlistManagement::getInstance($db);
$watchlistUserManagement = WatchlistUserManagement::getInstance($db);
$watchlistUserManagement->initWatchlistsForUser($uid);

$perpage = 15;
$npage = ($ar_params[1])?(int)$ar_params[1]:1;

$searchParameters = array();

if(isset($_POST) && $_POST['DO'] == 'save_list') {
	$watchlistUserManagement->updateListForUser($uid, $_POST['LISTNAME']);
	$tpl_content->addvar("save_list", 1);
	$tpl_content->addvar("active_tab_listform", 1);
}


if(isset($_REQUEST['FK_WATCHLIST_USER']) && (int)$_REQUEST['FK_WATCHLIST_USER'] > 0) {
	$searchParameters['FK_WATCHLIST_USER'] = $_REQUEST['FK_WATCHLIST_USER'];
}
if(isset($_REQUEST['SEARCHTEXT']) && trim($_REQUEST['SEARCHTEXT']) != "") {
	$searchParameters['SEARCHTEXT'] = $_REQUEST['SEARCHTEXT'];
}

$watchLists = $watchlistUserManagement->fetchAllByParam(array(
	'FK_USER' => $uid
));

foreach($watchLists as $key => $watchList) {
	if($_REQUEST['FK_WATCHLIST_USER'] == $watchList['ID_WATCHLIST_USER']) {
		$watchLists[$key]['SELECTED'] = 1;
	}
}

$tpl_content->addlist("watchlist_list", $watchLists, "tpl/".$s_lang."/merkliste-ajax.row_list.htm");
$tpl_content->addlist("watchlist_list_form", $watchLists, "tpl/".$s_lang."/merkliste.row_list_form.htm");


$watchlistItems = $watchlistManagement->fetchAllByParam(array_merge($searchParameters, array(
	'FK_USER' => $uid,
	'LIMIT' => $perpage,
	'OFFSET' => ($npage-1)*$perpage
)));
$numberOfWatchlistItems = $watchlistManagement->getLastFetchByParamCount();


$tpl_content->addlist("liste", $watchlistItems, "tpl/".$s_lang."/merkliste.row.htm");
$tpl_content->addvar("pager", htm_browse_extended($numberOfWatchlistItems, $npage, "merkliste,{PAGE}", $perpage));
$tpl_content->addvar("all", $numberOfWatchlistItems);

$tpl_content->addvars($_REQUEST);

?>