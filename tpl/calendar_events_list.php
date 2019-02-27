<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.calendar_event.php";
require_once $ab_path."sys/lib.user_media.php";
$calendarEventManagement = CalendarEventManagement::getInstance($db);
$searchParameter = array();

$searchParameterKeys = CalendarEventManagement::getParameterKeys();
foreach($searchParameterKeys as $searchParameterKey) {
	if((isset($_REQUEST[$searchParameterKey]) && $_REQUEST[$searchParameterKey] != NULL)) {
		$searchParameter[$searchParameterKey] = $_REQUEST[$searchParameterKey];
	} elseif (!empty($tpl_content->vars["SEARCH_".$searchParameterKey])) {
		$searchParameter[$searchParameterKey] = $tpl_content->vars["SEARCH_".$searchParameterKey];
	}
}
if (!isset($searchParameter['TYPE'])) {
    $searchParameter['TYPE'] = 'DEFAULT';
}
if(isset($_REQUEST['start']) && $_REQUEST['start'] != 0) {
	$searchParameter['STAMP_START_GT'] = date("Y-m-d H:i:s", $_REQUEST['start']);
}
if(isset($_REQUEST['end']) && $_REQUEST['end'] != 0) {
	$searchParameter['STAMP_START_LT'] = date("Y-m-d H:i:s", $_REQUEST['end']);
}
if (!empty($searchParameter['STAMP_END_GT'])) {
	$searchParameter['UNIX_DATE_START'] = strtotime($searchParameter['STAMP_END_GT']) * 1000;
}
if (!empty($searchParameter['STAMP_START_LT'])) {
	$searchParameter['UNIX_DATE_END'] = strtotime($searchParameter['STAMP_START_LT']) * 1000;
}
$tpl_content->addvars($searchParameter, "SEARCH_");

if (array_key_exists('STAMP_END_GT', $searchParameter)) {
	$searchStart = strtotime($searchParameter['STAMP_END_GT']);
	$searchStartMonth = (int)date("n", $searchStart);
	$searchStartYear = (int)date("Y", $searchStart);
	$prevMonth = ($searchStartMonth > 1 ? $searchStartMonth - 1 : 12);
	$prevYear = ($searchStartMonth > 1 ? $searchStartYear : $searchStartYear - 1);
	$nextMonth = ($searchStartMonth < 12 ? $searchStartMonth + 1 : 1);
	$nextYear = ($searchStartMonth < 12 ? $searchStartYear : $searchStartYear + 1);
	$searchPrevMonth = $calendarEventManagement->getSearchHashSimple($prevMonth, $prevYear);
	$searchNextMonth = $calendarEventManagement->getSearchHashSimple($nextMonth, $nextYear);
	if ($searchPrevMonth['COUNT'] > 0) {
		$tpl_content->addvar("linkPrev", $tpl_content->tpl_uri_action('calendar_events,,'.chtrans($searchPrevMonth['HASH'])));
	}
	if ($searchNextMonth['COUNT'] > 0) {
		$tpl_content->addvar("linkNext", $tpl_content->tpl_uri_action('calendar_events,,'.chtrans($searchNextMonth['HASH'])));
	}
}

$all = 0;
$calendarEvents = $calendarEventManagement->fetchAllByParam($searchParameter, $all);
$prevMonth = true;
foreach ( $calendarEvents as $key => $calendarEvent ) {
	$currentMonth = explode(" ",$calendarEvent["STAMP_START"]);
	$currentMonth = explode("-",$currentMonth[0]);
	$currentMonth = $currentMonth[0] . "-" . $currentMonth[1];
	if ( $key == 0 || $currentMonth != $prevMonth ) {
		$calendarEvents[$key]["NEW_MONTH"] = 1;
	}
	$prevMonth = $currentMonth;
	$arDefaultImage = UserMediaManagement::getDefaultImage($db, "calendar_event", $calendarEvent["ID_CALENDAR_EVENT"]);
	if ($arDefaultImage !== false) {
		$calendarEvents[$key] = array_merge(
			$calendarEvents[$key],
			array_flatten(
				$arDefaultImage,
				true,
				"_",
				"IMAGE_"
			)
		);
	}
}
/*echo '<pre>';
var_dump( $calendarEvents[$key] );
echo '</pre>';*/
$tpl_content->addlist("events_list",$calendarEvents,"tpl/".$s_lang."/calendar_events_list.row.htm");
/*echo '<pre>';
var_dump( $calendarEvents );
echo '</pre>';*/