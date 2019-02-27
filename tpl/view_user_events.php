<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.club.php";
require_once $ab_path."sys/lib.calendar_event.php";
$clubManagement = ClubManagement::getInstance($db);
$calendarEventManagement = CalendarEventManagement::getInstance($db);

$userId = ($ar_params[2] ? (int)$ar_params[2] : null);
$user_ = $db->fetch1("select VORNAME,NACHNAME,NAME,CACHE,STAMP_REG,LASTACTIV,URL,STRASSE,PLZ,ORT,LU_PROFESSION,ID_USER as USER_ID_USER ,UEBER, ROUND(RATING) as lastrate,TIMESTAMPDIFF(YEAR,GEBDAT,CURDATE()) as age,TEL,RATING,UEBER from user where ID_USER='". $userId."'");
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
		if (($_SESSION["SEARCH_CACHE"]["CLUB_CALENDAR"]["FK_REF_TYPE"] == "user")
			&& ($_SESSION["SEARCH_CACHE"]["CLUB_CALENDAR"]["FK_REF"] == $userId)) {
			$searchParameter['STAMP_START_GT'] = date("Y-m-d", $_SESSION["SEARCH_CACHE"]["CLUB_CALENDAR"]["start"]);
			$searchParameter['UNIX_DATE_START'] = strtotime($searchParameter['STAMP_START_GT']) * 1000;
		} else {
			unset($_SESSION["SEARCH_CACHE"]["CLUB_CALENDAR"]);
			$searchParameter['STAMP_START_GT'] = date("Y-m-d");
		}
	}
}
$searchParameter["FK_REF_TYPE"] = 'user';
$searchParameter["FK_REF"] = (int)$userId;
$searchParameter["SORT_BY"] = (!empty($ar_params[4]) ? $ar_params[4] : "STAMP_START");
$searchParameter["SORT_DIR"] = (!empty($ar_params[5]) ? $ar_params[5] : "ASC");

$tpl_content->addvar("USER_ID_USER", $userId);
$tpl_content->addvar('CUR_SORT_'.$searchParameter["SORT_BY"]."_".$searchParameter["SORT_DIR"], 1);

$tpl_content->addvar('VIEW_TYPE', $viewType);
$tpl_content->addvar('VIEW_TYPE_'.$viewType, 1);

$tpl_content->addvars($user_, "USER_");
$tpl_content->addvars($searchParameter, "SEARCH_");
$tpl_content->addvar("SEARCH_HASH", $searchHash);

$res = $db->fetch_table("
	select
		s.V1, s.FK
	from
		`kat` t
	left join
		string_kat s on s.S_TABLE='kat' and s.FK=t.ID_KAT
		and s.BF_LANG=if(t.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_KAT+0.5)/log(2)))
	where
		t.B_VIS=1
		and ROOT=7 and LFT<>1
	order by
		s.V1");

$tpl_content->addlist("liste_category_rows",$res,"tpl/".$s_lang."/category.row.left.htm");