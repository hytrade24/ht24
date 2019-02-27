<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.calendar_event.php";
require_once $ab_path."sys/lib.club.php";
$calendarEventManagement = CalendarEventManagement::getInstance($db);
$clubManagement = ClubManagement::getInstance($db);
$searchParameter = array("IS_CONFIRMED" => 1, "MODERATED" => 1);
if ($uid > 0) {
	$searchParameter["VISIBLE_FOR_USER"] = $uid;
} else {
	$searchParameter["PRIVACY"] = 1;
}

$searchParameterKeys = CalendarEventManagement::getParameterKeys();
foreach($searchParameterKeys as $searchParameterKey) {
	if((isset($_REQUEST[$searchParameterKey]) && $_REQUEST[$searchParameterKey] != NULL) || $tpl_content->vars[$searchParameterKey]) {
		$searchParameter[$searchParameterKey] = ($_REQUEST[$searchParameterKey] != NULL)?$_REQUEST[$searchParameterKey]:$tpl_content->vars[$searchParameterKey];
	}
}

$ar_param_raw = array_merge($tpl_content->vars, $_REQUEST);
if (!isset($ar_param_raw['TYPE'])) {
    $searchParameter['TYPE'] = 'DEFAULT';
}
// Typ
if ($ar_param_raw['TYPE'] == 'ALL') {
	$searchParameter['FK_USER_MOD'] = $uid;
	$tpl_content->addvar("TYPE_ALL", 1);
} elseif ($ar_param_raw['TYPE'] == 'ALL_SIGNUP') {
	$searchParameter['IS_SIGNED_UP'] = 1;
	$tpl_content->addvar("TYPE_ALL_SIGNUP", 1);
} elseif ($ar_param_raw['TYPE'] == 'VENDOR') {
	$searchParameter['FK_REF'] = $uid;
	$searchParameter['FK_REF_TYPE'] = 'user';
	$tpl_content->addvar("TYPE_VENDOR", 1);
} elseif ($ar_param_raw['TYPE'] == 'GROUPS') {
	// Show all clubs where the user is moderator or higher
	$searchParameter['FK_REF'] = $clubManagement->getUserModClubIds($uid);
	$searchParameter['FK_REF_TYPE'] = 'club';
	$tpl_content->addvar("TYPE_GROUPS", 1);
} elseif (preg_match("/^GROUP\_([0-9]+)$/", $ar_param_raw['TYPE'], $matches)) {
	$userClubId = (int)$matches[1];
	// Show selected club
	$searchParameter['FK_REF'] = $userClubId;
	$searchParameter['FK_REF_TYPE'] = 'club';
	$tpl_content->addvar("TYPE_GROUP", $ar_param['FK_REF']);
}

if(isset($_REQUEST['start']) && isset($_REQUEST['end'])) {
	$searchParameter['BETWEEN_START'] = date("Y-m-d H:i:s", $_REQUEST['start']);
	$searchParameter['BETWEEN_END'] = date("Y-m-d", $_REQUEST['end']).' 23:59:59';
} else {
	if(isset($_REQUEST['start']) && $_REQUEST['start'] != 0) {
		if (!isset($_REQUEST['end'])) {
			$_SESSION["SEARCH_CACHE"]["CLUB_CALENDAR"]["FK_REF"] = $searchParameter['FK_REF'];
			$_SESSION["SEARCH_CACHE"]["CLUB_CALENDAR"]["FK_REF_TYPE"] = $searchParameter['FK_REF_TYPE'];
			$_SESSION["SEARCH_CACHE"]["CLUB_CALENDAR"]["start"] = (int)$_REQUEST['start'];
		}
		$searchParameter['STAMP_END_GT'] = date("Y-m-d H:i:s", $_REQUEST['start']);
	}
	if(isset($_REQUEST['end']) && $_REQUEST['end'] != 0) {
		$searchParameter['STAMP_START_LT'] = date("Y-m-d H:i:s", $_REQUEST['end']);
	}
	if (isset($_REQUEST['list_start'])) {
		$searchParameter['LIMIT_DAY'] = 25;
		$searchParameter['STAMP_END_GT'] = date("Y-m-d H:i:s", $_REQUEST['list_start']);
		if (isset($_REQUEST['list_end']) && ($_REQUEST['list_end'] !== "false")) {
			$searchParameter['STAMP_START_LT'] = date("Y-m-d H:i:s", $_REQUEST['list_end']);			
		}
	}
}



if(isset($_REQUEST['DO']) && $_REQUEST['DO'] == "FETCH_EVENTS") {
	if (!empty($searchParameter['STAMP_END_GT']) && empty($searchParameter['STAMP_START_LT'])) {
		$searchParameter['LIMIT_DAY'] = 25;
	}
	$all = 0;

	require_once $ab_path."sys/lib.user_media.php";
	
	$n_current_page = (array_key_exists("PAGE_CUR", $ar_param_raw) ? $ar_param_raw["PAGE_CUR"] : 1);
	$per_page = (array_key_exists("PAGE_ITEMS", $ar_param_raw) ? $ar_param_raw["PAGE_ITEMS"] : 10);
	
	$searchParameter["OFFSET"] = ($n_current_page - 1) * $per_page;
	$searchParameter["LIMIT"] = $per_page;
	
	$calendarEvents = $calendarEventManagement->fetchAllByParam($searchParameter, $all);
	$calendarEventsAsJson = array();
	foreach($calendarEvents as $key => $calendarEvent) {
		$arDefaultImage = UserMediaManagement::getDefaultImage($db, "calendar_event", $calendarEvent["ID_CALENDAR_EVENT"]);
		if ($arDefaultImage !== false) {
			$calendarEvent = array_merge($calendarEvent, array_flatten($arDefaultImage, true, "_", "IMAGE_"));
		}
		#$calendarEvent["DESCRIPTION"] = iconv("ISO-8859-1", "UTF-8//IGNORE", strip_tags($calendarEvent["DESCRIPTION"]));
		$calendarEvent["DESCRIPTION_SHORT"] = mb_substr($calendarEvent["DESCRIPTION"], 0, 200) . (strlen($calendarEvent["DESCRIPTION"]) > 200 ? ' ...' : '');
		$calendarEvent["STAMP_START_FORMATTED"] = date("d.m.Y H:i", strtotime($calendarEvent["STAMP_START"]))."Uhr";
		$calendarEvent["STAMP_END_FORMATTED"] = date("d.m.Y H:i", strtotime($calendarEvent["STAMP_END"]))."Uhr";
		$calendarEventsAsJson[] = array_merge($calendarEvent, array(
			'title' => $calendarEvent['TITLE'],
			'start' => $calendarEvent['STAMP_START'],
			'end' => $calendarEvent['STAMP_END'],
            'EVENT_URL'     => $tpl_content->tpl_uri_action('calendar_events_view,'.chtrans($calendarEvent['TITLE']).','.$calendarEvent['ID_CALENDAR_EVENT']),
			'EVENT_URL_DL'  => $tpl_content->tpl_uri_action('calendar_events_view,'.chtrans($calendarEvent['TITLE']).','.$calendarEvent['ID_CALENDAR_EVENT'].",iCal")
		));
	}
	if ($_REQUEST['AS_OBJECT']) {
		$calendarEventsAsJson["length"] = count($calendarEventsAsJson);
		$calendarEventsAsJson["all"] = (int)$all;
		if (array_key_exists('STAMP_END_GT', $searchParameter)) {
			$searchStart = strtotime($searchParameter['STAMP_END_GT']);
			$searchStartMonth = (int)date("n", $searchStart);
			$searchStartYear = (int)date("Y", $searchStart);
			$prevMonth = ($searchStartMonth > 1 ? $searchStartMonth - 1 : 12);
			$prevYear = ($searchStartMonth > 1 ? $searchStartYear : $searchStartYear - 1);
			$nextMonth = ($searchStartMonth < 12 ? $searchStartMonth + 1 : 1);
			$nextYear = ($searchStartMonth < 12 ? $searchStartYear : $searchStartYear + 1);
			$searchPrevMonth = $calendarEventManagement->getSearchHashSimple($prevMonth, $prevYear, $searchParameter);
			$searchNextMonth = $calendarEventManagement->getSearchHashSimple($nextMonth, $nextYear, $searchParameter);
			switch ($_REQUEST['PAGE_REF']) {
				case 'club-calendar':
					require_once $ab_path."sys/lib.club.php";
					$clubManagement = ClubManagement::getInstance($db);
					$ar_club = $clubManagement->getClubById((int)$searchParameter['FK_REF']);
					if ($searchPrevMonth['COUNT'] > 0) {
						$calendarEventsAsJson["linkPrev"] = $tpl_content->tpl_uri_action('club-calendar,'.chtrans($ar_club["NAME"]).','.(int)$ar_club["ID_CLUB"].',LIST,,,'.chtrans($searchPrevMonth['HASH']));
					}
					if ($searchNextMonth['COUNT'] > 0) {
						$calendarEventsAsJson["linkNext"] = $tpl_content->tpl_uri_action('club-calendar,'.chtrans($ar_club["NAME"]).','.(int)$ar_club["ID_CLUB"].',LIST,,,'.chtrans($searchNextMonth['HASH']));
					}
					break;
				case 'view_user_events':
					$ar_user = $db->fetch1("select VORNAME,NACHNAME,NAME,CACHE,STAMP_REG,LASTACTIV,URL,STRASSE,PLZ,ORT,LU_PROFESSION,ID_USER as USER_ID_USER ,UEBER, ROUND(RATING) as lastrate,TIMESTAMPDIFF(YEAR,GEBDAT,CURDATE()) as age,TEL,RATING,UEBER
						FROM user WHERE ID_USER='". (int)$searchParameter['FK_REF']."'");
					if ($searchPrevMonth['COUNT'] > 0) {
						$calendarEventsAsJson["linkPrev"] = $tpl_content->tpl_uri_action('view_user_events,'.chtrans($ar_user["NAME"]).','.(int)$ar_user["USER_ID_USER"].',LIST,,,'.chtrans($searchPrevMonth['HASH']));
					}
					if ($searchNextMonth['COUNT'] > 0) {
						$calendarEventsAsJson["linkNext"] = $tpl_content->tpl_uri_action('view_user_events,'.chtrans($ar_user["NAME"]).','.(int)$ar_user["USER_ID_USER"].',LIST,,,'.chtrans($searchNextMonth['HASH']));
					}
					break;
				default:
					if ($searchPrevMonth['COUNT'] > 0) {
						$calendarEventsAsJson["linkPrev"] = $tpl_content->tpl_uri_action('calendar_events,,'.chtrans($searchPrevMonth['HASH']));
					}
					if ($searchNextMonth['COUNT'] > 0) {
						$calendarEventsAsJson["linkNext"] = $tpl_content->tpl_uri_action('calendar_events,,'.chtrans($searchNextMonth['HASH']));
					}
					break;
			}
		}
	}
	
	$calendarEventsAsJson["pager"] = htm_browse_extended($all, $n_current_page, "#{PAGE}", $per_page);

	#var_dump($calendarEventsAsJson);
	header("Content-Type:application/json");
	die(json_encode($calendarEventsAsJson));
}

$tpl_content->addvars($searchParameter);
