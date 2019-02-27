<?php


require_once $ab_path."sys/lib.calendar_event.php";
$calendarEventManagement = CalendarEventManagement::getInstance($db);

$npage = 1;
$perpage = 20;
$isSearch = false;

if (!empty($_REQUEST['done'])) {
    $tpl_content->addvar("done", 1);
    $tpl_content->addvar("done_".$_REQUEST['done'], 1);
}

if (isset($_REQUEST["ajax"])) {
	$result = false;
	switch ($_REQUEST["ajax"]) {
		case 'delete':
			$id = (int)$_REQUEST["id"];
			if ($id > 0) {
				if ($calendarEventManagement->deleteById($id)) {
					$result = true;
				}
			}
			break;
		default:
			break;
	}
	header('Content-type: application/json');
	die(json_encode(array(
		"success"	=> $result
	)));
}
// Aktion: Anzeige freischalten/bestätigen
if (isset($_REQUEST["confirm_user"])) {
    $id_event = (int)$_REQUEST["confirm"];
    $id_user = (int)$_REQUEST["confirm_user"];
    header('Content-type: application/json');
    die(json_encode(array(
        "success"	=> $calendarEventManagement->adminAccept($id_event) && $calendarEventManagement->adminAcceptUser($id_user)
    )));
}
if (isset($_REQUEST["confirm"])) {
    $id_event = (int)$_REQUEST["confirm"];
    header('Content-type: application/json');
    die(json_encode(array(
        "success"	=> $calendarEventManagement->adminAccept($id_event)
    )));
}
// Aktion: Anzeige "ablehnen"
if (isset($_REQUEST["decline_user"])) {
    $id_event = (int)$_REQUEST["decline"];
    header('Content-type: application/json');
    die(json_encode(array(
        "success"	=> $calendarEventManagement->adminDecline($id_event, $_REQUEST["REASON"]) &&
            $calendarEventManagement->adminDeclineUser((int)$_REQUEST["decline_user"], $_REQUEST["REASON"])
    )));
}
if (isset($_REQUEST["decline"])) {
    $id_event = (int)$_REQUEST["decline"];
    header('Content-type: application/json');
    die(json_encode(array(
        "success"	=> $calendarEventManagement->adminDecline($id_event, $_REQUEST["REASON"])
    )));
}

if (!empty($_POST)) {
	$searchParameter = $_POST["SEARCH"];
	// Pagination
	if (isset($_POST['search_'])) {
		// New search, reset page
		unset($_POST["npage"]);
	} else {
		$npage = (int)$_POST["npage"];
		$searchParameter['OFFSET'] = $perpage * ($npage-1);
	}
	$searchParameter['LIMIT'] = $perpage;
	// Sort order
	list($searchParameter["SORT_BY"], $searchParameter["SORT_DIR"]) = explode(",", $searchParameter["SORT"]);
	// User search
	if ($_POST["FK_USER"] > 0) {
		$searchParameter["FK_USER"] = (int)$_POST["FK_USER"];
		$searchParameter["NAME_"] = $db->fetch_atom("
			SELECT NAME FROM `user`
				WHERE ID_USER=".(int)$_POST["FK_USER"]);
	} else if (!empty($_POST["NAME_"])) {
		$searchParameter["FK_USER"] = array_keys($db->fetch_nar("
			SELECT ID_USER, NAME FROM `user`
				WHERE NAME LIKE '%".mysql_real_escape_string($_POST["NAME_"])."%'"));
		$searchParameter["NAME_"] = $_POST["NAME_"];
	}
	// Date range (from)
	if (preg_match("/([0-3][0-9])\.([0-1][0-9])\.([0-9]{2,4})/", $searchParameter["STAMP_END_GT"], $matches)) {
		$searchParameter["STAMP_END_GT"] = $matches[3]."-".$matches[2]."-".$matches[1];
	} else {
		unset($searchParameter["STAMP_END_GT"]);
	}
	// Date range (from)
	if (preg_match("/([0-3][0-9])\.([0-1][0-9])\.([0-9]{2,4})/", $searchParameter["STAMP_START_LT"], $matches)) {
		$searchParameter["STAMP_START_LT"] = $matches[3]."-".$matches[2]."-".$matches[1];
	} else {
		unset($searchParameter["STAMP_START_LT"]);
	}
	$isSearch = true;
} else {
	$searchParameter["STAMP_END_GT"] = date("Y-m-d");
	$searchParameter['SORT'] = 'STAMP_START';
	$searchParameter['SORT_DIR'] = 'DESC';
	$searchParameter['LIMIT'] = $perpage;
    if (isset($_REQUEST["MODERATED"])) {
        $searchParameter["MODERATED"] = (int)$_REQUEST["MODERATED"];
    }
}

$all = 0;
$eventliste = $calendarEventManagement->fetchAllByParam($searchParameter, $all);
foreach ($eventliste as $index => $event) {
	$eventliste[$index]["FK_REF_TYPE_".strtoupper($event["FK_REF_TYPE"])] = 1;
}


$tpl_content->addlist('liste', $eventliste, 'tpl/' . $s_lang .'/veranstaltung.row.htm');
// Search parameter
if (is_array($searchParameter["FK_USER"])) {
	unset($searchParameter["FK_USER"]);
}
if (isset($searchParameter["FK_REF_TYPE"])) {
	$tpl_content->addvar("FK_REF_TYPE_".strtoupper($searchParameter["FK_REF_TYPE"]), 1);
}
$tpl_content->addvars($searchParameter, "SEARCH_");
foreach ($searchParameter as $paramName => $paramValue) {
    $tpl_content->addvar("SEARCH_".$paramName."_".$paramValue, 1);
}

if ($isSearch) {
	$tpl_content->addvar("SEARCH_RESULT", 1);
}

// Sort order
$tpl_content->addvar("SORT_BY_".$searchParameter['SORT_BY']."_".$searchParameter['SORT_DIR'], 1);
// Pager
$tpl_content->addvar("pager", htm_browse($all, $npage, "index.php?page=veranstaltung&npage=", $perpage));
$tpl_content->addvar("npage", $npage);

$tpl_content->addvar("MODERATE_EVENTS", $nar_systemsettings["MARKTPLATZ"]["MODERATE_EVENTS"]);