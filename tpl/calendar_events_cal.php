<?php
/* ###VERSIONSBLOCKINLCUDE### */

// Default parameters
$tpl_content->vars["VIEW"] = ($tpl_content->vars["VIEW"] ? $tpl_content->vars["VIEW"] : "month");

require_once $ab_path."sys/lib.calendar_event.php";
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

if(isset($_REQUEST['start']) && $_REQUEST['start'] != 0) {
	$searchParameter['STAMP_START_GT'] = date("Y-m-d H:i:s", $_REQUEST['start']);
}
if(isset($_REQUEST['end']) && $_REQUEST['end'] != 0) {
	$searchParameter['STAMP_END_LT'] = date("Y-m-d H:i:s", $_REQUEST['end']);
}

$tpl_content->addvars($searchParameter, "SEARCH_");