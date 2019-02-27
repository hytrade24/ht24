<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.calendar_event.php';
$calendarManagement = CalendarEventManagement::getInstance($db);

$param = array();
// Kategorie
if (isset($_POST['FK_KAT']) && $_POST['FK_KAT'] != "") {
    $param['CATEGORY'] = $_POST['FK_KAT'];
}

if (isset($_POST['CITY']) && $_POST['CITY'] != "") {
    $param['CITY'] = $_POST['CITY'];
}

if (isset($_POST['ZIP']) && $_POST['ZIP'] != "") {
    $param['ZIP'] = $_POST['ZIP'];
}

if (isset($_POST['FK_COUNTRY']) && $_POST['FK_COUNTRY'] != "") {
    $param['FK_COUNTRY'] = $_POST['FK_COUNTRY'];
}

if (isset($_POST['STAMP_END_GT']) && preg_match("/([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{2,4})/", $_POST['STAMP_END_GT'], $matches)) {
    $param['STAMP_END_GT'] = $matches[3]."-".$matches[2]."-".$matches[1];
}
if (isset($_POST['STAMP_START_GT']) && preg_match("/([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{2,4})/", $_POST['STAMP_START_GT'], $matches)) {
    $param['STAMP_START_GT'] = $matches[3]."-".$matches[2]."-".$matches[1];
}
if (isset($_POST['STAMP_START_LT']) && preg_match("/([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{2,4})/", $_POST['STAMP_START_LT'], $matches)) {
    $param['STAMP_START_LT'] = $matches[3]."-".$matches[2]."-".$matches[1];
}

if (isset($_POST['SEARCHCALENDAREVENT']) && $_POST['SEARCHCALENDAREVENT'] != "") {
    $param['SEARCHCALENDAREVENT'] = $_POST['SEARCHCALENDAREVENT'];
}

// Umkreissuche
if (!empty($_POST['LONGITUDE']) && !empty($_POST['LATITUDE'])) {
    $param['LONGITUDE'] = $_POST['LONGITUDE'];
    $param['LATITUDE'] = $_POST['LATITUDE'];
    $param['LU_UMKREIS'] = $_POST['LU_UMKREIS'];
}
$param["PRIVACY"] = 1;

$return_json = $calendarManagement->getSearchHash($param);

header('Content-type: application/json');
die(json_encode($return_json));