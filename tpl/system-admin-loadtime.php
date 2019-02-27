<?php
/* ###VERSIONSBLOCKINLCUDE### */

if (!$_SESSION["USER_IS_ADMIN"]) {
    die(forward($tpl_content->tpl_uri_action("404")));
}

switch ($ar_params[2]) {
    case 'enable':
        Tools_LoadtimeStatistic::getInstance()->suppress();
        setcookie('ebizRecordLoadtime', 1, time() + 3600, '/');
        setcookie('ebizRecordLoadtimeDatabase', 1, time() + 3600, '/');
        setcookie('ebizRecordLoadtimeTemplate', 1, time() + 3600, '/');
        header("Content-Type: application/json");
        die(json_encode( true ));
    case 'disable':
        Tools_LoadtimeStatistic::getInstance()->suppress();
        unset($_COOKIE['ebizRecordLoadtime']);
        unset($_COOKIE['ebizRecordLoadtimeDatabase']);
        unset($_COOKIE['ebizRecordLoadtimeTemplate']);
        setcookie('ebizRecordLoadtime', null, -1, '/');
        setcookie('ebizRecordLoadtimeDatabase', null, -1, '/');
        setcookie('ebizRecordLoadtimeTemplate', null, -1, '/');
        header("Content-Type: application/json");
        die(json_encode( true ));
    case 'ajax':
        header("Content-Type: application/json");
        die(json_encode( Tools_LoadtimeStatistic::getInstance()->flushEvents() ));
    default:
        break;
}

$arRequests = array();
$arRequestsRaw = Tools_LoadtimeStatistic::getInstance()->flushEvents();
foreach ($arRequestsRaw as $requestIndex => $arEvents) {
    $arRows = array();
    $arRowsBlocked = array();
    $requestEvent = $arEvents[0];
    $requestStart = $requestEvent["timeStart"];
    $requestDuration = $requestEvent["duration"];
    foreach ($arEvents as $eventIndex => $eventDetails) {
        // Find target row for output
        $rowIndex = -1;
        do {
            if (!array_key_exists(++$rowIndex, $arRowsBlocked)) {
                $arRows[$rowIndex] = array("events" => array());
                $arRowsBlocked[$rowIndex] = 0;
            }
        } while ($arRowsBlocked[$rowIndex] >= $eventDetails["timeStart"]);
        $arBacktrace = array();
        if (array_key_exists("backtrace", $eventDetails["details"])) {
            $arBacktraceRaw = unserialize($eventDetails["details"]["backtrace"]);
            foreach ($arBacktraceRaw as $btIndex => $btEntry) {
                $tpl_backtrace = new Template("tpl/de/system-admin-loadtime.row.backtrace.htm");
                $tpl_backtrace->addvars($btEntry);
                $arBacktrace[] = $tpl_backtrace->process(true);
            }
        }
        $eventDetails["backtrace"] = implode("\n", $arBacktrace);
        // Add event to target row
        $eventDetails["durationMs"] = $eventDetails["duration"] * 1000;
        $eventDetails["posLeft"] = ($eventDetails["timeStart"] - $requestStart) * 100 / $requestDuration;
        $eventDetails["width"] = $eventDetails["duration"] * 100 / $requestDuration;
        
        $tplEvent = new Template("tpl/".$s_lang."/system-admin-loadtime.row.event-entry.htm");
        $tplEvent->addvars($eventDetails);
        $tplEvent->addvars(array_flatten($eventDetails["details"], true, "_", "details_"));
        $arRows[$rowIndex]["events"][] = $tplEvent;
        $arRowsBlocked[$rowIndex] = $eventDetails["timeEnd"];
    }
    $tplRequest = new Template("tpl/".$s_lang."/system-admin-loadtime.row.request.htm");
    $tplRequest->addvars($requestEvent);
    $tplRequest->addlist("rows", $arRows, "tpl/".$s_lang."/system-admin-loadtime.row.event-row.htm");
    $arRequests[] = $tplRequest;
}
$tpl_content->addvar("requests", $arRequests);