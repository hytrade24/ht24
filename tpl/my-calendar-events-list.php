<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.calendar_event.php";
require_once $ab_path."sys/lib.club.php";
$calendarEventManagement = CalendarEventManagement::getInstance($db);
$clubManagement = ClubManagement::getInstance($db);
$searchParameter = array();
$sortFields = array("STAMP_START", "TITLE", "COMMENTS");

$perpage = 20; // Elemente pro Seite
$npage =  (isset($tpl_content->vars['PAGE_OFFSET']) ? $tpl_content->vars['PAGE_OFFSET'] : 1);
$viewType = (isset($tpl_content->vars['VIEW_TYPE']) ? $tpl_content->vars['VIEW_TYPE'] : "LIST");
$sortBy = (isset($tpl_content->vars['SORT_BY']) && in_array($tpl_content->vars['SORT_BY'], $sortFields) ? $tpl_content->vars['SORT_BY'] : "STAMP_START");
$sortDir = ($tpl_content->vars['SORT_DIR'] == "DESC" ? "DESC" : "ASC");

$limit = ($npage-1)*$perpage;

$ar_param_raw = array_merge($tpl_content->vars, $_POST);
$ar_param = array();

if (isset($ar_param_raw['VIEW'])) {
	switch ($ar_param_raw['VIEW']) {
		case 'MINIMAL':
			$tpl_content->addvar("HIDE_SORT", 1);
			$tpl_content->addvar("HIDE_SEARCH", 1);
			$tpl_content->addvar("HIDE_LEGEND", 1);
			break;
	}
}
if (!isset($ar_param_raw['TYPE'])) {
    $ar_param_raw['TYPE'] = 'ALL';
}
// Typ
if ($ar_param_raw['TYPE'] == 'ALL') {
    $ar_param['FK_USER_MOD'] = $uid;
    $tpl_content->addvar("TYPE_ALL", 1);
} elseif ($ar_param_raw['TYPE'] == 'ALL_SIGNUP') {
    $ar_param['IS_SIGNED_UP'] = 1;
    $tpl_content->addvar("TYPE_ALL_SIGNUP", 1);
} elseif ($ar_param_raw['TYPE'] == 'VENDOR') {
    $ar_param['FK_REF'] = $uid;
    $ar_param['FK_REF_TYPE'] = 'user';
    $tpl_content->addvar("TYPE_VENDOR", 1);
} elseif ($ar_param_raw['TYPE'] == 'GROUPS') {
    // Show all clubs where the user is moderator or higher
    $ar_param['FK_REF'] = $clubManagement->getUserModClubIds($uid);
    $ar_param['FK_REF_TYPE'] = 'club';
    $tpl_content->addvar("TYPE_GROUPS", 1);
} elseif (preg_match("/^GROUP_([0-9]+)$/", $ar_param_raw['TYPE'], $matches)) {
    $userClubId = (int)$matches[1];
    if ($clubManagement->isClubOwner($userClubId, $uid) || $clubManagement->isClubModerator($userClubId, $uid)) {
        // Show selected club
        $ar_param['FK_REF'] = $userClubId;
        $tpl_content->addvar("TYPE_GROUP", $ar_param['FK_REF']);
    } else {
        // Show all clubs where the user is moderator
        $ar_param['FK_REF'] = $clubManagement->getUserModClubIds($uid);
        $tpl_content->addvar("TYPE_GROUPS", 1);
    }
    $ar_param['FK_REF_TYPE'] = 'club';
}
if (isset($ar_param_raw["SEARCHCALENDAREVENT"])) {
	$ar_param["SEARCHCALENDAREVENT"] = $ar_param_raw["SEARCHCALENDAREVENT"];
}
if (isset($ar_param_raw["IS_CONFIRMED"])) {
	$ar_param["IS_CONFIRMED"] = $ar_param_raw["IS_CONFIRMED"];
}
if (!empty($ar_param_raw["STAMP_END_GT"])) {
	// Date range (from)
    if (preg_match("/([0-3][0-9])\.([0-1][0-9])\.([0-9]{2,4})/", $ar_param_raw["STAMP_END_GT"], $matches)) {
        $ar_param["STAMP_END_GT"] = $matches[3]."-".$matches[2]."-".$matches[1];
    }
    if (preg_match("/([0-9]{2,4})\-([0-1][0-9])\-([0-3][0-9])/", $ar_param_raw["STAMP_END_GT"], $matches)) {
        $ar_param["STAMP_END_GT"] = $matches[1]."-".$matches[2]."-".$matches[3];
    }
}
if (!empty($ar_param_raw["STAMP_START_LT"])) {
	// Date range (to)
    if (preg_match("/([0-3][0-9])\.([0-1][0-9])\.([0-9]{2,4})/", $ar_param_raw["STAMP_START_LT"], $matches)) {
        $ar_param["STAMP_START_LT"] = $matches[3]."-".$matches[2]."-".$matches[1];
    }
    if (preg_match("/([0-9]{2,4})\-([0-1][0-9])\-([0-3][0-9])/", $ar_param_raw["STAMP_START_LT"], $matches)) {
        $ar_param["STAMP_START_LT"] = $matches[1]."-".$matches[2]."-".$matches[3];
    }
	if (strtotime($ar_param["STAMP_START_LT"]) < strtotime($ar_param["STAMP_END_GT"])) {
		// Prevent invalid range
		unset($ar_param["STAMP_START_LT"]);
	}
}
if (isset($ar_param_raw["SORT_BY"]) && in_array($ar_param_raw['SORT_BY'], $sortFields)) {
	// Sort target
	$sortBy = $ar_param_raw["SORT_BY"];
}
if (isset($ar_param_raw["SORT_DIR"])) {
	// Sort direction
	$sortDir = $ar_param_raw["SORT_DIR"];
}
// Additional parameters
if ($ar_param_raw["TYPE_LOCKED"] == 1) {
	$tpl_content->addvar("TYPE_LOCKED", 1);
}

$ar_param = array_merge($ar_param, array(
    'LIMIT' => $perpage,
    'OFFSET' => $limit,
	'SORT_BY' => $sortBy,
	'SORT_DIR' => $sortDir
));

$userClubs = $clubManagement->getClubsByUser($uid, $langval, 1, null, $all, array('IS_ADMIN' => 'DESC', 'MODERATOR' => 'DESC'));
for ($i=count($userClubs)-1; $i >= 0; $i--) {
	if (!$userClubs[$i]["IS_ADMIN"] && !$userClubs[$i]["IS_MODERATOR"]) {
		unset($userClubs[$i]);
	}
}

$numberOfCalendarEvents = 0;
$calendarEvents = $calendarEventManagement->fetchAllByParam($ar_param, $numberOfCalendarEvents);

foreach($calendarEvents as $key => $calendarEvent) {
	$calendarEvents[$key]['FK_REF_TYPE_'.strtoupper($calendarEvent['FK_REF_TYPE'])] = 1;
}

// Settings
$tpl_content->addvar("ALLOW_COMMENTS_EVENT", $nar_systemsettings["MARKTPLATZ"]["ALLOW_COMMENTS_EVENT"]);
$tpl_content->addvar("ID_USER", $uid);
// Available groups
$tpl_content->addlist('options_groups', $userClubs, 'tpl/'.$s_lang.'/my-calendar-events-list.option.htm');
// Results
$tpl_content->addlist('liste', $calendarEvents, 'tpl/'.$s_lang.'/my-calendar-events-list.row.htm');
$tpl_content->addvar("ALL_CALENDAR_EVENTS", $numberOfCalendarEvents);
// Search params
$tpl_content->addvars($ar_param);
$tpl_content->addvar('VIEW_TYPE_'.$viewType, 1);
$tpl_content->addvar('SORT_BY_'.$sortBy.'_'.$sortDir, 1);
// Pager
$tpl_content->addvar("pager", htm_browse_extended($numberOfCalendarEvents, $npage, "my-calendar-events,{PAGE},AJAX_LIST", $perpage));
$tpl_content->addvar("npage", $npage);
