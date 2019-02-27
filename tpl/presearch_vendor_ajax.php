<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.vendor.php';
$vendorManagement = VendorManagement::getInstance($db);

$param = array();
// Kategorie
if (isset($_POST['FK_KAT']) && $_POST['FK_KAT'] != "") {
    $param['CATEGORY'] = $_POST['FK_KAT'];
    unset($_POST['FK_KAT']);
}

if (isset($_POST['CITY']) && $_POST['CITY'] != "") {
    $param['ORT'] = $_POST['CITY'];
	unset($_POST['CITY']);
}

if (isset($_POST['ZIP']) && $_POST['ZIP'] != "") {
    $param['PLZ'] = $_POST['ZIP'];
	unset($_POST['ZIP']);
}

if (isset($_POST['FK_COUNTRY']) && $_POST['FK_COUNTRY'] != "") {
    $param['FK_COUNTRY'] = $_POST['FK_COUNTRY'];
	unset($_POST['FK_COUNTRY']);
}

if (isset($_POST['land']) && $_POST['land'] != "") {
	$param['FK_COUNTRY'] = $_POST['land'];
	unset($_POST['FK_COUNTRY']);
}


if (isset($_POST['SEARCHVENDOR']) && $_POST['SEARCHVENDOR'] != "") {
    $param['SEARCHVENDOR'] = $_POST['SEARCHVENDOR'];
	unset($_POST['SEARCHVENDOR']);
}

// Umkreissuche
if (!empty($_POST['LONGITUDE']) && !empty($_POST['LATITUDE'])) {
    $param['LONGITUDE'] = $_POST['LONGITUDE'];
    $param['LATITUDE'] = $_POST['LATITUDE'];
    $param['LU_UMKREIS'] = $_POST['LU_UMKREIS'];
	unset($_POST['LONGITUDE']);
	unset($_POST['LATITUDE']);
	unset($_POST['LU_UMKREIS']);
} else if(!isset($param['ORT']) && isset($_POST['GOOGLE_MAPS_PLACE']) && $_POST['GOOGLE_MAPS_PLACE'] != "") {
    // Google - Fallback
    $param['ORT'] = $_POST['GOOGLE_MAPS_PLACE'];
    unset($_POST['GOOGLE_MAPS_PLACE']);
}
$param = array_merge($param,$_POST);

$return = $vendorManagement->countByParam($param);
if ($return > 0) {
    $lifetime = time() + (60 * 60 * 24);
    $paramSer = serialize($param);
	$hash = substr(md5("vendor ".$paramSer), 0, 15);
    //$hash = md5(microtime());
    //$hash = substr($hash, 0, 15);

    $ar = array('QUERY' => $hash, 'LIFETIME' => date("Y-m-d H:i:s", $lifetime), 'S_STRING' => $paramSer, 'S_WHERE' => "");
    $id_known = $db->fetch_atom("SELECT `ID_SEARCHSTRING` FROM `searchstring` WHERE QUERY='".mysql_real_escape_string($hash)."'");
    if ($id_known > 0) $ar["ID_SEARCHSTRING"] = $id_known;
    $id = $db->update("searchstring", $ar);

    $return_json["COUNT"] = $return;
    $return_json["HASH"] = $hash;
    $return_json["SEARCHURL"] = "/".$s_lang."/anbieter/anbieter,".$param['CATEGORY'].",".$hash.".htm";
} else {
    $return_json["COUNT"] = 0;
}

if(isset($_REQUEST['SEARCH_PROXY'])) {
    if($return_json['HASH'] === null) {
        die(forward($tpl_content->tpl_uri_action('vendor,'.$param['CATEGORY'].',NO_SEARCH_RESULTS')));
    } else {
        die(forward($tpl_content->tpl_uri_action('vendor,'.$param['CATEGORY'].','.$return_json['HASH'])));
    }
}

if($_SESSION['USER_IS_ADMIN']) {
    $return_json['QUE'] = $query;
}
header('Content-type: application/json');
die(json_encode($return_json));