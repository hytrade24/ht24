<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.club.php";
require_once $ab_path."sys/lib.calendar_event.php";
$clubManagement = ClubManagement::getInstance($db);
$calendarEventManagement = CalendarEventManagement::getInstance($db);

$clubId = ($ar_params[2] ? (int)$ar_params[2] : null);
$ar_club = $clubManagement->getClubById($clubId);
if (!empty($ar_club) && (($ar_club['STATUS'] == 1) || ($ar_club['FK_USER'] == $uid))) {
	$searchParameter = array();
	$searchHash = ($ar_params[6] ? $ar_params[6] : false);

	if($searchHash !== false) {
		$tmp = $db->fetch_atom("SELECT S_STRING FROM `searchstring` WHERE `QUERY`='".mysql_real_escape_string($searchHash)."'");
		if ($tmp != "N;") {
			$searchParameter = unserialize($tmp);
		}
	}
	
	$viewTypeList = array(
		'LIST' => array(
			'TEMPLATE'	=> 'tpl/'.$s_lang.'/calendar_events.list_row.htm'
		),
		'BOX' => array()
	);
	$viewType = (($ar_params[3] && array_key_exists($ar_params[3], $viewTypeList)) ? $ar_params[3] : "LIST");
	if ($viewType == "LIST") {
		if(empty($searchParameter) && empty($_POST)) {
			if (($_SESSION["SEARCH_CACHE"]["CLUB_CALENDAR"]["FK_REF_TYPE"] == "club")
				&& ($_SESSION["SEARCH_CACHE"]["CLUB_CALENDAR"]["FK_REF"] == $clubId)) {
				$searchParameter['STAMP_START_GT'] = date("Y-m-d", $_SESSION["SEARCH_CACHE"]["CLUB_CALENDAR"]["start"]);
				$searchParameter['UNIX_DATE_START'] = strtotime($searchParameter['STAMP_START_GT']) * 1000;
			} else {
				unset($_SESSION["SEARCH_CACHE"]["CLUB_CALENDAR"]);
				$searchParameter['STAMP_START_GT'] = date("Y-m-d");
			}
		}
	}
	$searchParameter["TYPE"] = 'GROUP_'.$clubId;
	$searchParameter["SORT_BY"] = (!empty($ar_params[4]) ? $ar_params[4] : "STAMP_START");
	$searchParameter["SORT_DIR"] = (!empty($ar_params[5]) ? $ar_params[5] : "ASC");

	$ar_club['LOGO'] = ($ar_club['LOGO'] != "" ? 'cache/club/logo/'.$ar_club['LOGO'] : null);

	$tpl_content->addvar('CUR_SORT_'.$searchParameter["SORT_BY"]."_".$searchParameter["SORT_DIR"], 1);

	$tpl_content->addvar('VIEW_TYPE', $viewType);
	$tpl_content->addvar('VIEW_TYPE_'.$viewType, 1);

	$tpl_content->addvars($ar_club, "CLUB_");
	$tpl_content->addvars($searchParameter, "SEARCH_");
	$tpl_content->addvar("SEARCH_HASH", $searchHash);
}

$tpl_content->addvar("active_club_calendar", 1);

?>