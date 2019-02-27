<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.ad_request.php';
$adRequestManagement = AdRequestManagement::getInstance($db);

// Kategorie
if (isset($_POST['FK_KAT']) && $_POST['FK_KAT'] != "") {
    $param['CATEGORY'] = $_POST['FK_KAT'];
}


if (isset($_POST['SEARCH_AD_REQUEST']) && $_POST['SEARCH_AD_REQUEST'] != "") {
    $param['SEARCH_AD_REQUEST'] = $_POST['SEARCH_AD_REQUEST'];
}
if (isset($_POST['LONGITUDE']) && isset($_POST['LATITUDE'])) {
    $param['LONGITUDE'] = $_POST['LONGITUDE'];
    $param['LATITUDE'] = $_POST['LATITUDE'];
    $param['LU_UMKREIS'] = (int)$_POST['LU_UMKREIS'];
}
$param['STATUS'] = 1;


$adRequests = $adRequestManagement->fetchAllByParam($param);

$return = count($adRequests);
if ($return > 0) {
    $lifetime = time() + (60 * 60 * 24);
    $hash = md5(microtime());
    $hash = substr($hash, 0, 15);

    $ar = array('QUERY' => $hash, 'LIFETIME' => date("Y-m-d H:i:s", $lifetime), 'S_STRING' => serialize($param), 'S_WHERE' => "");
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