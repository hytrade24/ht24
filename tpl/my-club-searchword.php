<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.club.php";

$clubManagement = ClubManagement::getInstance($db);
$clubId = (isset($ar_params[1]) ? $ar_params[1] : 0);
$clubAction = (isset($ar_params[2]) ? $ar_params[2] : "GET");
$ar_club = array();

if (isset($_REQUEST["do"]) && isset($_REQUEST["ID_CLUB"])) {
	// Form abgeschickt
	$clubId = $_REQUEST["ID_CLUB"];
	$clubAction = $_REQUEST["do"];
}

if (!$clubId) {
	$url = $tpl_content->tpl_uri_action("my-club");
	die(forward($url));
}

switch ($clubAction) {
	case 'ADD':
	    $result = $clubManagement->addSearchWord($clubId, $_POST['SEARCHWORD'], $_POST['LANG']);
    	die(json_encode(array("result" => true)));
	case 'DELETE':
	    $result = $clubManagement->deleteSearchWord($clubId, $_POST['SEARCHWORD'], $_POST['LANG']);
    	die(json_encode(array("result" => true)));
	case 'GET':
		// Sprachrelevante Felder
		$languageSelection = $db->fetch_table("SELECT * FROM lang WHERE B_PUBLIC = 1");
		// Club-Infos
		if ($clubId > 0) {
			$ar_club = $clubManagement->getClubById($clubId);
			$ar_club["ADMIN"] = ($ar_club["FK_USER"] == $uid);
			$ar_club["MODERATOR"] = ($ar_club["ADMIN"] || $clubManagement->isClubModerator($clubId, $uid));
		}
	    // Zeige die Schlagworte
	    $clubSearchWords = $clubManagement->getSearchWordsByClubId($clubId, $langval);
		$tpl_content->addvars($ar_club, "CLUB_");
	    $tpl_content->addlist("searchwords", $clubSearchWords, $ab_path.'tpl/'.$s_lang.'/my-club-searchword.row.htm');
		$tpl_content->addlist("searchWordLanguageHeader", $languageSelection, $ab_path.'tpl/'.$s_lang.'/my-club-searchword.lang.header.htm');
		$tpl_content->addlist("searchWordLanguageBody", $languageSelection, $ab_path.'tpl/'.$s_lang.'/my-club-searchword.lang.body.htm');
	    break;
	case 'GET_AJAX':
		// Club-Infos
		if ($clubId > 0) {
			$ar_club = $clubManagement->getClubById($clubId);
			$ar_club["ADMIN"] = ($ar_club["FK_USER"] == $uid);
			$ar_club["MODERATOR"] = ($ar_club["ADMIN"] || $clubManagement->isClubModerator($clubId, $uid));
		}
	    // Zeige die Schlagworte
	    $clubSearchWords = $clubManagement->getSearchWordsByClubId($clubId, (isset($_POST['LANG']) ? $_POST['LANG'] : $langval));
	    $tpl_content->loadText($ab_path.'tpl/'.$s_lang.'/my-club-searchword-list.htm');
		$tpl_content->addvars($ar_club, "CLUB_");
	    $tpl_content->addlist("searchwords", $clubSearchWords, $ab_path.'tpl/'.$s_lang.'/my-club-searchword.row.htm');
	    die($tpl_content->process(true));
}