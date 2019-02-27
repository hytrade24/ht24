<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.job.php';
$jobManagement = JobManagement::getInstance($db);

$param = array();
// Kategorie
if (isset($_POST['FK_KAT']) && $_POST['FK_KAT'] != "") {
    $param['CATEGORY'] = $_POST['FK_KAT'];
}


if (isset($_POST['SEARCH_JOB']) && $_POST['SEARCH_JOB'] != "") {
    $param['SEARCH_JOB'] = $_POST['SEARCH_JOB'];
}
if (isset($_POST['LONGITUDE']) && isset($_POST['LATITUDE'])) {
    $param['LONGITUDE'] = $_POST['LONGITUDE'];
    $param['LATITUDE'] = $_POST['LATITUDE'];
    $param['LU_UMKREIS'] = (int)$_POST['LU_UMKREIS'];
}

$param['PUBLISHED'] = true;


$return = $jobManagement->countByParam($param);
if ($return > 0) {
    $lifetime = time() + (60 * 60 * 24);
    $paramSer = serialize($param);
	$hash = substr(md5("job ".$paramSer), 0, 15);
    //$hash = md5(microtime());
    //$hash = substr($hash, 0, 15);

    $ar = array('QUERY' => $hash, 'LIFETIME' => date("Y-m-d H:i:s", $lifetime), 'S_STRING' => $paramSer, 'S_WHERE' => "");
    $id_known = $db->fetch_atom("SELECT `ID_SEARCHSTRING` FROM `searchstring` WHERE QUERY='".mysql_real_escape_string($hash)."'");
    if ($id_known > 0) $ar["ID_SEARCHSTRING"] = $id_known;
    $id = $db->update("searchstring", $ar);

    $return_json["COUNT"] = $return;
    $return_json["HASH"] = $hash;
} else {
    $return_json["COUNT"] = 0;
}

if($_SESSION['USER_IS_ADMIN']) {
    $return_json['QUE'] = $query;
}
header('Content-type: application/json');
die(json_encode($return_json));