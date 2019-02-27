<?php
/* ###VERSIONSBLOCKINLCUDE### */

$npage =  (isset($_POST['PAGE']) ? $_POST['PAGE'] : (isset($ar_params[1]) ? $ar_params[1] : 1));
$doAction =  (isset($ar_params[2]) ? $ar_params[2] : null);
$calendarEventId =  (isset($ar_params[3]) ? (int)$ar_params[3] : null);
$viewType = (isset($ar_params[6]) ? $ar_params[6] : "LIST");

$tpl_content->addvar("PAGE_OFFSET", $npage);
$tpl_content->addvar("VIEW_TYPE", $viewType);

if(isset($doAction)) {
    if($doAction == 'delete' && isset($calendarEventId)) {
		require_once $ab_path."sys/lib.calendar_event.php";
		$calendarEventManagement = CalendarEventManagement::getInstance($db);
    	$success = false;
        $calendarEvent = $calendarEventManagement->fetchById($calendarEventId, $uid);
        if ($calendarEvent != null) {
			$calendarEventManagement->deleteById($calendarEventId);
			$success = true;
        }
		header("Content-Type: application/json");
		die(json_encode(array('success' => true)));
    }
    if($doAction == 'confirm' && isset($calendarEventId) && isset($ar_params[4])) {
		require_once $ab_path."sys/lib.calendar_event.php";
		$calendarEventManagement = CalendarEventManagement::getInstance($db);
        $calendarEvent = $calendarEventManagement->fetchById($calendarEventId, $uid);
		$success = false;
		if (($calendarEvent != null)) {
    		$state = (int)$ar_params[4];
			$hasAccess = false;
			switch ($calendarEvent["FK_REF_TYPE"]) {
				case 'club':
					require_once $ab_path."sys/lib.club.php";
					$clubManagement = ClubManagement::getInstance($db);
					$clubId = (int)$calendarEvent["FK_REF"];
					$hasAccess = $clubManagement->isClubOwner($clubId, $uid) || $clubManagement->isClubModerator($clubId, $uid);
					break;
				default:
					$hasAccess = ($calendarEvent['FK_USER'] == $uid);
					break;
			}
			if($hasAccess) {
				$calendarEventManagement->confirmById($calendarEventId, $state);
				$success = true;
			}
		}
		header("Content-Type: application/json");
		die(json_encode(array('success' => $success)));
    }
	if ($doAction == 'deleted') {
		$tpl_content->addvar("deleted" ,1);
	}
	if ($doAction == 'AJAX_LIST') {
		$tpl_content->addvar("HIDE_SCRIPTS", 1);
		die($tpl_content->tpl_subtpl("tpl/".$s_lang."/my-calendar-events-list.htm,*"));
	}
}
