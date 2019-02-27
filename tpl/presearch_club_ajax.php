<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.club.php';
$clubManagement = ClubManagement::getInstance($db);

$param = array();
// Kategorie
if (isset($_POST['FK_KAT']) && $_POST['FK_KAT'] != "") {
    $param['CATEGORY'] = $_POST['FK_KAT'];
}

if (isset($_POST['CITY']) && $_POST['CITY'] != "") {
    $param['ORT'] = $_POST['CITY'];
}

if (isset($_POST['ZIP']) && $_POST['ZIP'] != "") {
    $param['PLZ'] = $_POST['ZIP'];
}

if (isset($_POST['SEARCH_FK_COUNTRY']) && $_POST['SEARCH_FK_COUNTRY'] != "") {
    $param['FK_COUNTRY'] = $_POST['SEARCH_FK_COUNTRY'];
}
if (isset($_POST['FK_COUNTRY']) && $_POST['FK_COUNTRY'] != "") {
    $param['FK_COUNTRY'] = $_POST['FK_COUNTRY'];
}


if (isset($_POST['SEARCHCLUB']) && $_POST['SEARCHCLUB'] != "") {
    $param['SEARCHCLUB'] = $_POST['SEARCHCLUB'];
}

// Umkreissuche
if (!empty($_POST['LONGITUDE']) && !empty($_POST['LATITUDE'])) {
    $param['LONGITUDE'] = $_POST['LONGITUDE'];
    $param['LATITUDE'] = $_POST['LATITUDE'];
    $param['LU_UMKREIS'] = $_POST['LU_UMKREIS'];
}


$return = $clubManagement->countClubsByParam($param);
if ($return > 0) {
    $lifetime = time() + (60 * 60 * 24);
    $paramSer = serialize($param);
	$hash = substr(md5("club ".$paramSer), 0, 15);
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